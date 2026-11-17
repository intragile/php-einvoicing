<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France;

use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\Model\Identifier;

/**
 * Seller of an invoice line (EXTENDED-CTC-FR), for multi-vendor invoices.
 */
final readonly class LineSeller
{
    public function __construct(
        /** Line seller name (EXT-FR-FE-164). */
        public ?string $name = null,
        /** Line seller global identifier (EXT-FR-FE-166) with its ISO 6523 scheme. */
        public ?Identifier $globalId = null,
        /** Line seller legal registration identifier (EXT-FR-FE-167), a SIREN with scheme 0002. */
        public ?Identifier $legalRegistration = null,
        /** Line seller VAT identifier (EXT-FR-FE-168). */
        public ?string $vatIdentifier = null,
        /** Line seller country code (EXT-FR-FE-177). */
        public ?CountryCode $country = null,
    ) {}
}
