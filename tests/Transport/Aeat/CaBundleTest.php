<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Aeat;

use IntrAgile\EInvoicing\Tests\Support\AeatCertificates;
use IntrAgile\EInvoicing\Transport\Aeat\CaBundle;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CaBundleTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function test_fnmt_root_is_found_by_subject(): void
    {
        $other = AeatCertificates::selfSigned('Some Other Root CA');
        $fnmt = AeatCertificates::selfSigned(CaBundle::FNMT_ROOT_CN);

        $this->files[] = $with = AeatCertificates::file($other."\n".$fnmt, '.pem');
        $this->files[] = $without = AeatCertificates::file($other, '.pem');

        self::assertTrue(CaBundle::containsFnmtRoot($with));
        self::assertFalse(CaBundle::containsFnmtRoot($without));
    }

    public function test_a_lookalike_common_name_does_not_count(): void
    {
        $this->files[] = $bundle = AeatCertificates::file(AeatCertificates::selfSigned(CaBundle::FNMT_ROOT_CN.' 2'), '.pem');

        self::assertFalse(CaBundle::containsFnmtRoot($bundle));
    }

    public function test_unreadable_bundle_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CaBundle::containsFnmtRoot(sys_get_temp_dir().'/no-such-bundle-'.bin2hex(random_bytes(4)).'.pem');
    }

    public function test_default_path_is_readable_or_null(): void
    {
        $path = CaBundle::defaultPath();
        $configured = openssl_get_cert_locations()['default_cert_file'] ?? '';

        if ($configured !== '' && is_file($configured) && is_readable($configured)) {
            self::assertSame($configured, $path);
        } else {
            self::assertNull($path);
        }
    }
}
