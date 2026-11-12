<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Peppol;

use InvalidArgumentException;

/**
 * A Peppol document type identifier in the busdox-docid-qns scheme:
 * "<root namespace>::<local name>##<customization>::<version>". Compared case-sensitively.
 */
final readonly class DocumentTypeId
{
    public const SCHEME = 'busdox-docid-qns';

    public function __construct(
        /** Document type identifier without scheme. */
        public string $value,
    ) {}

    /**
     * The identifier with scheme, such as "busdox-docid-qns::urn:oasis:…::Invoice##…::2.1".
     */
    public function toUri(): string
    {
        return self::SCHEME.'::'.$this->value;
    }

    /**
     * The parts the SBDH DocumentIdentification carries: the root namespace (Standard), the root
     * local name (Type) and the syntax version (TypeVersion).
     *
     * @return array{standard: string, type: string, typeVersion: string}
     *
     * @throws InvalidArgumentException when the identifier is not of the busdox-docid-qns form
     */
    public function sbdhParts(): array
    {
        if (preg_match('/\A(?<standard>\S+?)::(?<type>[^:#\s]+)##\S+::(?<version>[^:\s]+)\z/', $this->value, $match) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'The document type identifier "%s" is not of the form "<namespace>::<local name>##<customization>::<version>".',
                $this->value,
            ));
        }

        return ['standard' => $match['standard'], 'type' => $match['type'], 'typeVersion' => $match['version']];
    }
}
