<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\Peppol;

use IntrAgile\EInvoicing\Validation\Peppol\Downloader;
use IntrAgile\EInvoicing\Validation\Peppol\PeppolSources;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DownloaderTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function nonHttpsUrls(): array
    {
        return [
            'http' => ['http://raw.githubusercontent.com/OpenPEPPOL/peppol-bis-invoice-3/3.0.22/rules/sch/CEN-EN16931-UBL.sch'],
            'file' => ['file:///etc/hosts'],
            'plain path' => ['/etc/hosts'],
            'upper-case scheme' => ['HTTP://example.com/'],
        ];
    }

    #[DataProvider('nonHttpsUrls')]
    public function test_refuses_anything_but_https(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('https');

        (new Downloader)->get($url);
    }

    #[Group('network')]
    public function test_downloads_the_pinned_rule_files(): void
    {
        $downloader = new Downloader;

        foreach (PeppolSources::all() as $source) {
            self::assertSame($source['sha256'], hash('sha256', $downloader->get(PeppolSources::BASE_URL.$source['file'])), $source['file']);
        }
    }

    #[Group('network')]
    public function test_aborts_above_the_size_limit(): void
    {
        $url = PeppolSources::BASE_URL.PeppolSources::all()['cen-ubl']['file'];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($url);

        (new Downloader(maxBytes: 1000))->get($url);
    }
}
