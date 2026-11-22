<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Certificates;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A certificate issued by KSeF, without its private key.
 *
 * The serial number is the 16-digit upper-case hexadecimal form KSeF uses in its API and in the
 * issuer's link (KOD II); a lower-case serial is converted.
 */
final readonly class KsefCertificate
{
    /** A KSeF certificate serial number: 16 hexadecimal digits. */
    public const SERIAL_PATTERN = '/\A[0-9A-F]{16}\z/';

    public string $serialNumber;

    /**
     * @param  string  $certificatePem  the X.509 certificate, PEM-encoded
     * @param  ?string  $name  the name given at enrolment
     * @param  ?string  $status  Active, Blocked, Revoked or Expired, as KSeF last reported it; null
     *                           when the certificate was read without its status
     *
     * @throws InvalidArgumentException when the serial number is not 16 hexadecimal digits
     */
    public function __construct(
        string $serialNumber,
        public KsefCertificateType $type,
        public string $certificatePem,
        public DateTimeImmutable $validFrom,
        public DateTimeImmutable $validTo,
        public ?string $name = null,
        public ?string $status = null,
    ) {
        $this->serialNumber = self::serial($serialNumber);
    }

    /**
     * The serial number in upper case.
     *
     * @throws InvalidArgumentException when it is not 16 hexadecimal digits
     */
    public static function serial(string $serialNumber): string
    {
        $upper = strtoupper($serialNumber);
        if (preg_match(self::SERIAL_PATTERN, $upper) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'A KSeF certificate serial number has 16 hexadecimal digits; the given value has %d characters or other characters.',
                strlen($serialNumber),
            ));
        }

        return $upper;
    }
}
