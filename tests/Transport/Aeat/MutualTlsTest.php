<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Aeat;

use IntrAgile\EInvoicing\Tests\Support\AeatCertificates;
use IntrAgile\EInvoicing\Tests\Support\StreamHttpClient;
use IntrAgile\EInvoicing\Transport\Aeat\ClientCertificate;
use Nyholm\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

/**
 * Proves on the loopback interface that the materialized certificate is presented in a mutual TLS
 * handshake. A child PHP process plays the server; nothing outside 127.0.0.1 is contacted.
 */
final class MutualTlsTest extends TestCase
{
    private const PASSWORD = 's3cret-pass-123';

    /** Seconds the server child lives at most, whatever happens to the test. */
    private const SERVER_LIFETIME = 30;

    /** Seconds the test waits for the child to report its port. */
    private const STARTUP_TIMEOUT = 10.0;

    private const SERVER = <<<'PHP'
        [$certificate, $key, $ca, $lifetime] = array_slice($argv, 1);
        $context = stream_context_create(['ssl' => [
            'local_cert' => $certificate,
            'local_pk' => $key,
            'cafile' => $ca,
            'verify_peer' => true,
            'verify_peer_name' => false,
            'allow_self_signed' => false,
            'capture_peer_cert' => true,
        ]]);
        $server = stream_socket_server('tls://127.0.0.1:0', $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
        if ($server === false) {
            fwrite(STDERR, "listen failed: {$errstr}\n");
            exit(1);
        }
        $name = stream_socket_get_name($server, false);
        fwrite(STDOUT, 'PORT '.substr((string) $name, strrpos((string) $name, ':') + 1)."\n");
        $deadline = time() + (int) $lifetime;
        while (time() < $deadline) {
            $connection = @stream_socket_accept($server, 1.0);
            if ($connection === false) {
                continue;
            }
            stream_set_timeout($connection, 5);
            $request = '';
            while (! str_contains($request, "\r\n\r\n") && ! feof($connection)) {
                $chunk = fread($connection, 8192);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $request .= $chunk;
            }
            $peer = stream_context_get_params($connection)['options']['ssl']['peer_certificate'] ?? null;
            $parsed = $peer === null ? false : openssl_x509_parse($peer);
            $commonName = is_array($parsed) ? (string) ($parsed['subject']['CN'] ?? '') : '';
            if ($commonName === '') {
                fclose($connection);

                continue;
            }
            fwrite($connection, "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\nContent-Length: ".strlen($commonName)."\r\nConnection: close\r\n\r\n".$commonName);
            fclose($connection);
        }
        PHP;

    /** @var resource|null */
    private $server = null;

    /** @var array<int, resource> */
    private array $pipes = [];

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/aeat-mtls-test-'.bin2hex(random_bytes(6));
        mkdir($this->directory, 0700);
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server, 9);
            foreach ($this->pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close($this->server);
        }
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function test_client_certificate_is_presented(): void
    {
        $caFile = $this->write('ca.pem', AeatCertificates::caPem());
        $server = AeatCertificates::server();
        $port = $this->startServer($this->write('server.crt', $server['certificate']), $this->write('server.key', $server['privateKey']), $caFile);

        $issued = AeatCertificates::client(['commonName' => 'MUTUAL TLS CLIENT', 'organizationIdentifier' => 'VATES-B64829179']);
        $certificate = ClientCertificate::fromPkcs12(AeatCertificates::pkcs12($issued['certificate'], $issued['privateKey'], self::PASSWORD), self::PASSWORD);
        $materialized = $certificate->materialize($this->directory);

        $url = "https://127.0.0.1:{$port}/";
        $response = (new StreamHttpClient(10.0, $materialized->streamContextOptions($caFile)))->sendRequest(new Request('GET', $url));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('MUTUAL TLS CLIENT', (string) $response->getBody());

        try {
            (new StreamHttpClient(10.0, ['cafile' => $caFile]))->sendRequest(new Request('GET', $url));
            self::fail('The server answered a client without a certificate.');
        } catch (ClientExceptionInterface $exception) {
            self::assertStringNotContainsString(self::PASSWORD, $exception->getMessage());
        }
    }

    private function write(string $name, string $contents): string
    {
        $path = $this->directory.'/'.$name;
        file_put_contents($path, $contents);

        return $path;
    }

    private function startServer(string $certificate, string $key, string $caFile): int
    {
        $command = [PHP_BINARY, '-d', 'display_errors=stderr', '-r', self::SERVER, '--', $certificate, $key, $caFile, (string) self::SERVER_LIFETIME];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException('Cannot start the TLS test server.');
        }
        $this->server = $process;
        $this->pipes = $pipes;
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);

        $line = '';
        $deadline = hrtime(true) / 1e9 + self::STARTUP_TIMEOUT;
        while (! str_contains($line, "\n")) {
            $remaining = $deadline - hrtime(true) / 1e9;
            if ($remaining <= 0 || ! proc_get_status($process)['running']) {
                stream_set_blocking($pipes[2], false);
                self::fail('The TLS test server did not start: '.stream_get_contents($pipes[2]));
            }
            $read = [$pipes[1]];
            $write = $except = null;
            if (@stream_select($read, $write, $except, 0, (int) min(200_000, $remaining * 1e6)) > 0) {
                $line .= (string) fread($pipes[1], 64);
            }
        }
        if (preg_match('/\APORT (\d+)\n/', $line, $match) !== 1) {
            self::fail('The TLS test server reported no port.');
        }

        return (int) $match[1];
    }
}
