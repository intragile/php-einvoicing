<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Session;

use InvalidArgumentException;
use LogicException;
use RuntimeException;
use ZipArchive;

/**
 * The ZIP archive of the invoices sent in one KSeF batch session, split into the parts that are
 * encrypted and uploaded one by one.
 *
 * The archive is split binarily: each part is at most the given number of bytes, and the parts
 * concatenated in ordinal order are the archive. The whole archive and its parts are kept in
 * memory.
 *
 * Building a package needs the PHP zip extension (ext-zip), which the library only suggests.
 */
final readonly class BatchPackage
{
    /** Invoices KSeF accepts in one session. */
    public const MAX_INVOICES = 10_000;

    /** The largest invoice file KSeF accepts without attachments, in bytes. */
    public const MAX_INVOICE_BYTES = 1_000_000;

    /** Parts KSeF accepts in one batch session. */
    public const MAX_PARTS = 50;

    /** The largest archive KSeF accepts, in bytes. */
    public const MAX_ARCHIVE_BYTES = 5_000_000_000;

    /** File names that are safe as archive entries. */
    private const FILE_NAME = '/\A[A-Za-z0-9._-]{1,128}\.xml\z/';

    /** The longest invoiceFileName KSeF reports. */
    private const MAX_FILE_NAME_LENGTH = 128;

    /**
     * @param  string  $zip  the archive
     * @param  array<int, string>  $parts  the archive split into parts, keyed by ordinal number from 1
     * @param  int  $fileSize  the size of the archive in bytes
     * @param  string  $fileHash  the Base64 SHA-256 digest of the archive
     */
    private function __construct(
        public string $zip,
        public array $parts,
        public int $fileSize,
        public string $fileHash,
    ) {}

    /**
     * Archives the invoices and splits the archive into parts of at most $partBytes bytes.
     *
     * The invoices are given by file name, which becomes the entry name in the archive and the
     * invoiceFileName KSeF reports the status under. A name is letters, digits, ".", "_" and "-"
     * followed by ".xml", at most 128 characters in all; names must be unique ignoring case. The files
     * are archived as given; they are not validated here.
     *
     * @param  array<array-key, string>  $invoices  the invoice XML by file name
     * @param  int  $partBytes  the largest part in bytes, before encryption
     *
     * @throws InvalidArgumentException when the list is empty or holds more than 10,000 invoices,
     *                                  a name is unsafe or repeated, an invoice is larger than
     *                                  1 MB, the part size is not positive, or the archive is
     *                                  larger than KSeF accepts (5 GB, at most 50 parts)
     * @throws LogicException when the zip extension is not loaded
     * @throws RuntimeException when the archive cannot be written
     */
    public static function build(array $invoices, int $partBytes): self
    {
        if ($partBytes < 1) {
            throw new InvalidArgumentException(sprintf('The batch part size must be a positive number of bytes, %d given.', $partBytes));
        }
        self::validate($invoices);
        self::requireZipExtension();

        $zip = self::archive($invoices);
        $size = strlen($zip);
        if ($size > self::MAX_ARCHIVE_BYTES) {
            throw new InvalidArgumentException(sprintf('The batch archive is %d bytes; KSeF accepts at most %d. Send the invoices in several batches.', $size, self::MAX_ARCHIVE_BYTES));
        }
        $count = intdiv($size + $partBytes - 1, $partBytes);
        if ($count > self::MAX_PARTS) {
            throw new InvalidArgumentException(sprintf(
                'The batch archive of %d bytes needs %d parts of at most %d bytes; KSeF accepts at most %d parts. Use a larger part size or several batches.',
                $size,
                $count,
                $partBytes,
                self::MAX_PARTS,
            ));
        }

        $parts = [];
        foreach (str_split($zip, $partBytes) as $index => $part) {
            $parts[$index + 1] = $part;
        }

        return new self($zip, $parts, $size, base64_encode(hash('sha256', $zip, true)));
    }

    /**
     * Fails unless the zip extension is loaded.
     *
     * @internal
     *
     * @param  bool|null  $loaded  whether the extension is loaded; detected when null
     *
     * @throws LogicException when the extension is not loaded
     */
    public static function requireZipExtension(?bool $loaded = null): void
    {
        if (! ($loaded ?? extension_loaded('zip'))) {
            throw new LogicException('KSeF batch sessions need the PHP zip extension (ext-zip), which is not loaded. Install or enable it to send invoices in batches.');
        }
    }

    /**
     * @param  array<array-key, string>  $invoices
     *
     * @throws InvalidArgumentException
     */
    private static function validate(array $invoices): void
    {
        if ($invoices === []) {
            throw new InvalidArgumentException('A KSeF batch needs at least one invoice.');
        }
        if (count($invoices) > self::MAX_INVOICES) {
            throw new InvalidArgumentException(sprintf('A KSeF batch holds at most %d invoices, %d given.', self::MAX_INVOICES, count($invoices)));
        }

        $seen = [];
        foreach ($invoices as $name => $xml) {
            if (! is_string($name) || preg_match(self::FILE_NAME, $name) !== 1 || strlen($name) > self::MAX_FILE_NAME_LENGTH) {
                throw new InvalidArgumentException(sprintf(
                    'The batch invoice file name "%s" is not allowed: use letters, digits, ".", "_" or "-" followed by ".xml", at most 128 characters in all.',
                    addcslashes((string) $name, "\0..\37\"\\"),
                ));
            }
            $folded = strtolower($name);
            if (isset($seen[$folded])) {
                throw new InvalidArgumentException(sprintf('The batch invoice file names "%s" and "%s" are the same ignoring case.', $seen[$folded], $name));
            }
            $seen[$folded] = $name;
            if (strlen($xml) > self::MAX_INVOICE_BYTES) {
                throw new InvalidArgumentException(sprintf('The batch invoice %s is %d bytes; KSeF accepts at most %d.', $name, strlen($xml), self::MAX_INVOICE_BYTES));
            }
        }
    }

    /**
     * @param  array<array-key, string>  $invoices
     *
     * @throws RuntimeException
     */
    private static function archive(array $invoices): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ksef-batch-');
        if ($path === false) {
            throw new RuntimeException('The KSeF batch archive could not be created: no temporary file is available.');
        }
        try {
            $archive = new ZipArchive;
            if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('The KSeF batch archive could not be created.');
            }
            foreach ($invoices as $name => $xml) {
                if (! $archive->addFromString((string) $name, $xml)) {
                    $archive->close();

                    throw new RuntimeException(sprintf('The invoice %s could not be added to the KSeF batch archive.', $name));
                }
            }
            if (! $archive->close()) {
                throw new RuntimeException('The KSeF batch archive could not be written.');
            }
            $zip = file_get_contents($path);
            if ($zip === false || $zip === '') {
                throw new RuntimeException('The KSeF batch archive could not be read back.');
            }

            return $zip;
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
