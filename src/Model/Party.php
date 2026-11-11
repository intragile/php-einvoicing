<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

/**
 * A party of the invoice: seller (BG-4), buyer (BG-7), payee (BG-10) or seller tax
 * representative (BG-11).
 *
 * Term ids are listed in the order seller / buyer / payee / tax representative; a term absent
 * from a role in EN 16931 has no id for that role and must stay empty there.
 */
final readonly class Party
{
    public function __construct(
        /** Party name (BT-27 / BT-44 / BT-59 / BT-62). */
        public string $name,
        /** Trading name (BT-28 / BT-45). */
        public ?string $tradingName = null,
        /** @var list<Identifier> Party identifiers (BT-29 0..n / BT-46 0..1 / BT-60 0..1) with schemes (BT-29-1 / BT-46-1 / BT-60-1). */
        public array $identifiers = [],
        /** Legal registration identifier (BT-30 / BT-47 / BT-61) with scheme (BT-30-1 / BT-47-1 / BT-61-1). */
        public ?Identifier $legalRegistration = null,
        /** VAT identifier (BT-31 / BT-48 / BT-63), with country prefix. */
        public ?string $vatIdentifier = null,
        /** Seller tax registration identifier (BT-32). */
        public ?TaxRegistration $taxRegistration = null,
        /** Seller additional legal information (BT-33). */
        public ?string $additionalLegalInformation = null,
        /** Electronic address (BT-34 / BT-49) with scheme (BT-34-1 / BT-49-1). */
        public ?ElectronicAddress $electronicAddress = null,
        /** Postal address (BG-5 / BG-8 / BG-12). */
        public ?PostalAddress $address = null,
        /** Contact (BG-6 / BG-9). */
        public ?Contact $contact = null,
    ) {}
}
