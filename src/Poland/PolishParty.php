<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\Model\PostalAddress;
use InvalidArgumentException;

/**
 * FA(3) party data that the EN 16931 party does not carry, for the seller (Podmiot1), the buyer
 * (Podmiot2) or a third party (Podmiot3).
 */
final readonly class PolishParty
{
    public const MAX_CONTACTS = 3;

    public const MAX_BUYER_ID_LENGTH = 32;

    /**
     * @param  list<ContactDetails>  $contacts  Contact entries (DaneKontaktowe), at most three.
     *
     * @throws InvalidArgumentException when there are more than three contacts or the buyer
     *                                  identifier is longer than 32 characters
     */
    public function __construct(
        /** EORI number (NrEORI). */
        public ?string $eori = null,
        public array $contacts = [],
        /** Customer number (NrKlienta). */
        public ?string $customerNumber = null,
        /** Buyer identifier for a business unit (IDNabywcy), at most 32 characters. */
        public ?string $buyerId = null,
        /** VAT prefix of the party (PrefiksPodatnika / PrefiksNabywcy). */
        public ?CountryCode $vatPrefix = null,
        /** Taxpayer status (StatusInfoPodatnika). */
        public ?TaxpayerStatus $status = null,
        /** Correspondence address (AdresKoresp). */
        public ?PostalAddress $correspondenceAddress = null,
        /** Global Location Number (GLN). */
        public ?string $gln = null,
    ) {
        Limit::count($contacts, 'DaneKontaktowe', self::MAX_CONTACTS);
        if ($buyerId !== null && mb_strlen($buyerId) > self::MAX_BUYER_ID_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'The buyer identifier (IDNabywcy) has %d characters; FA(3) allows at most %d.',
                mb_strlen($buyerId),
                self::MAX_BUYER_ID_LENGTH,
            ));
        }
    }
}
