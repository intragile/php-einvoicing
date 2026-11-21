<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Session;

use IntrAgile\EInvoicing\Transport\Ksef\Session\BatchPackage;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class BatchPackageTest extends TestCase
{
    public function test_package_splits_at_part_size(): void
    {
        $invoices = [];
        for ($i = 1; $i <= 5; $i++) {
            // Random content does not compress, so the archive spans several 1,000-byte parts.
            $invoices[sprintf('invoice-%d.xml', $i)] = '<Faktura>'.bin2hex(random_bytes(1_000)).'</Faktura>';
        }

        $package = BatchPackage::build($invoices, 1_000);

        self::assertGreaterThanOrEqual(3, count($package->parts));
        self::assertSame(range(1, count($package->parts)), array_keys($package->parts));
        foreach ($package->parts as $part) {
            self::assertLessThanOrEqual(1_000, strlen($part));
            self::assertNotSame('', $part);
        }
        self::assertSame($package->zip, implode('', $package->parts));
        self::assertSame(strlen($package->zip), $package->fileSize);
        self::assertSame(base64_encode(hash('sha256', $package->zip, true)), $package->fileHash);

        self::assertSame($invoices, self::unzip($package->zip));
    }

    public function test_small_package_is_one_part(): void
    {
        $package = BatchPackage::build(['a.xml' => '<Faktura/>'], 100_000_000);

        self::assertSame([1 => $package->zip], $package->parts);
    }

    /**
     * @param  array<array-key, string>  $invoices
     */
    #[DataProvider('refusedNames')]
    public function test_names_must_be_unique_and_safe(array $invoices): void
    {
        $this->expectException(InvalidArgumentException::class);

        BatchPackage::build($invoices, 1_000);
    }

    /**
     * @return iterable<string, array{array<array-key, string>}>
     */
    public static function refusedNames(): iterable
    {
        yield 'parent directory' => [['../a.xml' => '<Faktura/>']];
        yield 'directory' => [['dir/a.xml' => '<Faktura/>']];
        yield 'backslash' => [['dir\\a.xml' => '<Faktura/>']];
        yield 'not xml' => [['a.txt' => '<Faktura/>']];
        yield 'empty base name' => [['.xml' => '<Faktura/>']];
        yield 'space' => [['a b.xml' => '<Faktura/>']];
        yield 'integer key' => [['<Faktura/>']];
        yield 'too long' => [[str_repeat('a', 125).'.xml' => '<Faktura/>']];
        yield 'duplicate ignoring case' => [['a.xml' => '<Faktura/>', 'A.XML' => '<Faktura/>']];
    }

    public function test_longest_safe_name_is_accepted(): void
    {
        $name = str_repeat('a', 124).'.xml';

        self::assertSame([$name => '<Faktura/>'], self::unzip(BatchPackage::build([$name => '<Faktura/>'], 1_000)->zip));
    }

    public function test_empty_list_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BatchPackage::build([], 1_000);
    }

    public function test_more_than_10000_invoices_are_refused(): void
    {
        $invoices = [];
        for ($i = 0; $i <= 10_000; $i++) {
            $invoices[sprintf('f%05d.xml', $i)] = '';
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('10000');

        BatchPackage::build($invoices, 1_000);
    }

    public function test_invoice_over_1_mb_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('big.xml');

        BatchPackage::build(['ok.xml' => '<Faktura/>', 'big.xml' => str_repeat('a', 1_000_001)], 100_000_000);
    }

    public function test_invoice_of_exactly_1_mb_is_accepted(): void
    {
        $package = BatchPackage::build(['max.xml' => str_repeat('a', 1_000_000)], 100_000_000);

        self::assertSame(1_000_000, strlen(self::unzip($package->zip)['max.xml']));
    }

    public function test_part_size_must_be_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BatchPackage::build(['a.xml' => '<Faktura/>'], 0);
    }

    public function test_more_than_50_parts_are_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('50');

        BatchPackage::build(['a.xml' => bin2hex(random_bytes(2_000))], 10);
    }

    public function test_missing_zip_extension_message(): void
    {
        BatchPackage::requireZipExtension(true);

        try {
            BatchPackage::requireZipExtension(false);
            self::fail('A missing zip extension must be refused.');
        } catch (LogicException $exception) {
            self::assertStringContainsString('ext-zip', $exception->getMessage());
            self::assertStringContainsString('batch', $exception->getMessage());
        }
    }

    /**
     * @return array<string, string>
     */
    private static function unzip(string $zip): array
    {
        $path = tempnam(sys_get_temp_dir(), 'ksef-test-');
        self::assertIsString($path);
        try {
            file_put_contents($path, $zip);
            $archive = new ZipArchive;
            self::assertTrue($archive->open($path, ZipArchive::RDONLY));
            $files = [];
            for ($i = 0; $i < $archive->numFiles; $i++) {
                $name = $archive->getNameIndex($i);
                self::assertIsString($name);
                $contents = $archive->getFromIndex($i);
                self::assertIsString($contents);
                $files[$name] = $contents;
            }
            $archive->close();

            return $files;
        } finally {
            unlink($path);
        }
    }
}
