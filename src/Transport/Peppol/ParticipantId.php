<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Peppol;

use IntrAgile\EInvoicing\Model\ElectronicAddress;
use InvalidArgumentException;

/**
 * A Peppol participant identifier in the iso6523-actorid-upis scheme: an ISO 6523 ICD and the
 * identifier value issued under it, such as "0208:0123456789".
 *
 * The value keeps its case as written, because the SBDH and access point payloads must repeat it
 * exactly; equals() compares it case-insensitively, as the Peppol policy for identifiers requires.
 */
final readonly class ParticipantId
{
    public const SCHEME = 'iso6523-actorid-upis';

    public function __construct(
        /** Four-digit ISO 6523 international code designator, such as "0208". */
        public string $icd,
        /** Identifier value under the ICD, case preserved. */
        public string $value,
    ) {
        if (preg_match('/\A\d{4}\z/', $icd) !== 1) {
            throw new InvalidArgumentException(sprintf('The participant identifier scheme "%s" is not a four-digit ISO 6523 ICD.', $icd));
        }
        if ($value === '' || preg_match('/[\s\p{Cc}]/u', $value) !== 0) {
            throw new InvalidArgumentException(sprintf('The participant identifier value "%s" is empty or contains whitespace or control characters.', addcslashes($value, "\0..\37\177")));
        }
    }

    /**
     * Parses "0208:0123456789" or "iso6523-actorid-upis::0208:0123456789" (scheme prefix in any
     * case), ignoring surrounding whitespace.
     *
     * @throws InvalidArgumentException when the identifier is malformed
     */
    public static function parse(string $id): self
    {
        $id = trim($id);
        $prefix = self::SCHEME.'::';
        if (strncasecmp($id, $prefix, strlen($prefix)) === 0) {
            $id = substr($id, strlen($prefix));
        }

        $parts = explode(':', $id, 2);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException(sprintf('The participant identifier "%s" is not of the form "<icd>:<value>".', addcslashes($id, "\0..\37\177")));
        }

        return new self($parts[0], $parts[1]);
    }

    /**
     * The participant identifier of an electronic address (BT-34 / BT-49), its scheme (BT-34-1 /
     * BT-49-1) being the ICD.
     *
     * @throws InvalidArgumentException when the address is not a valid participant identifier
     */
    public static function fromElectronicAddress(ElectronicAddress $address): self
    {
        return new self($address->scheme->value, $address->value);
    }

    /**
     * Whether both identify the same participant: the ICD compared exactly, the value regardless
     * of case.
     */
    public function equals(self $other): bool
    {
        return $this->icd === $other->icd && mb_strtolower($this->value) === mb_strtolower($other->value);
    }

    /**
     * The identifier without scheme, such as "0208:0123456789".
     */
    public function toString(): string
    {
        return $this->icd.':'.$this->value;
    }

    /**
     * The identifier with scheme, such as "iso6523-actorid-upis::0208:0123456789".
     */
    public function toUri(): string
    {
        return self::SCHEME.'::'.$this->toString();
    }
}
