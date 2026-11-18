<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Fa3;

use DOMElement;
use IntrAgile\EInvoicing\Model\Contact;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Poland\ContactDetails;
use IntrAgile\EInvoicing\Poland\PolishInvoice;
use IntrAgile\EInvoicing\Poland\PolishParty;
use IntrAgile\EInvoicing\Poland\ThirdParty;

/**
 * Writes the FA(3) parties: the seller (Podmiot1), the buyer (Podmiot2), the third parties
 * (Podmiot3), and the seller and buyers as shown on a corrected invoice (Podmiot1K,
 * Podmiot2K).
 *
 * Addresses: AdresL1 joins the address lines with ", ", AdresL2 is "post code city" and is left
 * out when empty. Contacts (DaneKontaktowe) are the Polish contact entries, else the e-mail
 * address and phone number of the EN 16931 contact.
 *
 * Buyer and third-party identification: a PL VAT identifier is written as NIP, another EU VAT
 * identifier as KodUE and NrVatUE, any other VAT identifier, else the legal registration
 * identifier, as NrID with the country of the address (KodKraju); without either, BrakID.
 *
 * @internal
 */
final class Fa3PartyWriter
{
    public static function seller(Fa3Output $output, Invoice $invoice, PolishInvoice $polish): void
    {
        $seller = $invoice->seller;
        $extras = $polish->seller;
        $element = $output->add($output->root, 'Podmiot1');

        $output->text($element, 'PrefiksPodatnika', $extras?->vatPrefix?->value);
        $output->text($element, 'NrEORI', $extras?->eori);
        $identification = $output->add($element, 'DaneIdentyfikacyjne');
        $output->text($identification, 'NIP', self::nip($seller->vatIdentifier));
        $output->text($identification, 'Nazwa', $seller->name);
        self::address($output, $element, 'Adres', $seller->address, $extras?->gln);
        self::address($output, $element, 'AdresKoresp', $extras?->correspondenceAddress);
        self::contacts($output, $element, $seller->contact, $extras);
        $output->text($element, 'StatusInfoPodatnika', $extras?->status === null ? null : (string) $extras->status->value);
    }

    public static function buyer(Fa3Output $output, Invoice $invoice, PolishInvoice $polish): void
    {
        $buyer = $invoice->buyer;
        $extras = $polish->buyer;
        $element = $output->add($output->root, 'Podmiot2');

        $output->text($element, 'NrEORI', $extras?->eori);
        self::identification($output, $element, $buyer, null);
        self::address($output, $element, 'Adres', $buyer->address, $extras?->gln);
        self::address($output, $element, 'AdresKoresp', $extras?->correspondenceAddress);
        self::contacts($output, $element, $buyer->contact, $extras);
        $output->text($element, 'NrKlienta', $extras?->customerNumber);
        $output->text($element, 'IDNabywcy', $extras?->buyerId);
        $output->yesNo($element, 'JST', $polish->buyerIsJstUnit);
        $output->yesNo($element, 'GV', $polish->buyerIsGvMember);
    }

    public static function thirdParty(Fa3Output $output, ThirdParty $thirdParty): void
    {
        $party = $thirdParty->party;
        $extras = $thirdParty->extras;
        $element = $output->add($output->root, 'Podmiot3');

        $output->text($element, 'IDNabywcy', $extras?->buyerId);
        $output->text($element, 'NrEORI', $extras?->eori);
        self::identification($output, $element, $party, $thirdParty->internalId);
        self::address($output, $element, 'Adres', $party->address, $extras?->gln);
        self::address($output, $element, 'AdresKoresp', $extras?->correspondenceAddress);
        self::contacts($output, $element, $party->contact, $extras);
        if ($thirdParty->role !== null) {
            $output->text($element, 'Rola', (string) $thirdParty->role->value);
        } else {
            $output->marker($element, 'RolaInna');
            $output->text($element, 'OpisRoli', $thirdParty->otherRole);
        }
        $output->decimal($element, 'Udzial', $thirdParty->share);
        $output->text($element, 'NrKlienta', $extras?->customerNumber);
    }

    /**
     * The seller as shown on the corrected invoice (Podmiot1K): NIP, name and address.
     */
    public static function sellerBefore(Fa3Output $output, DOMElement $fa, Party $seller): void
    {
        $element = $output->add($fa, 'Podmiot1K');
        $identification = $output->add($element, 'DaneIdentyfikacyjne');
        $output->text($identification, 'NIP', self::nip($seller->vatIdentifier));
        $output->text($identification, 'Nazwa', $seller->name);
        self::address($output, $element, 'Adres', $seller->address);
    }

    /**
     * A buyer as shown on the corrected invoice (Podmiot2K): identification and address.
     */
    public static function buyerBefore(Fa3Output $output, DOMElement $fa, Party $buyer): void
    {
        $element = $output->add($fa, 'Podmiot2K');
        self::identification($output, $element, $buyer, null);
        self::address($output, $element, 'Adres', $buyer->address);
    }

    /**
     * The NIP of a "PL" VAT identifier, or null for any other identifier.
     */
    public static function nip(?string $vatIdentifier): ?string
    {
        return $vatIdentifier !== null && str_starts_with($vatIdentifier, 'PL') ? substr($vatIdentifier, 2) : null;
    }

    /**
     * The FA(3) prefix of an EU VAT identifier other than a Polish one, or null.
     */
    public static function euPrefix(?string $vatIdentifier): ?string
    {
        if ($vatIdentifier === null) {
            return null;
        }
        $prefix = substr($vatIdentifier, 0, 2);

        return $prefix !== 'PL' && in_array($prefix, Fa3Format::EU_VAT_PREFIXES, true) ? $prefix : null;
    }

    /**
     * Address lines 1 to 3 joined as AdresL1, or null when the address has none.
     */
    public static function addressLine1(PostalAddress $address): ?string
    {
        $lines = array_filter(
            [$address->line1, $address->line2, $address->line3],
            static fn (?string $line): bool => $line !== null && trim($line) !== '',
        );

        return $lines === [] ? null : implode(', ', $lines);
    }

    private static function identification(Fa3Output $output, DOMElement $parent, Party $party, ?string $internalId): void
    {
        $element = $output->add($parent, 'DaneIdentyfikacyjne');
        $vat = $party->vatIdentifier;
        $euPrefix = self::euPrefix($vat);

        if ($internalId !== null) {
            $output->text($element, 'IDWew', $internalId);
        } elseif (self::nip($vat) !== null) {
            $output->text($element, 'NIP', self::nip($vat));
        } elseif ($vat !== null && $euPrefix !== null) {
            $output->text($element, 'KodUE', $euPrefix);
            $output->text($element, 'NrVatUE', substr($vat, 2));
        } elseif (($vat ?? $party->legalRegistration?->value) !== null) {
            $output->text($element, 'KodKraju', $party->address?->country->value);
            $output->text($element, 'NrID', $vat ?? $party->legalRegistration?->value);
        } else {
            $output->marker($element, 'BrakID');
        }
        $output->text($element, 'Nazwa', $party->name === '' ? null : $party->name);
    }

    private static function address(Fa3Output $output, DOMElement $parent, string $name, ?PostalAddress $address, ?string $gln = null): void
    {
        if ($address === null) {
            return;
        }
        $element = $output->add($parent, $name);
        $output->text($element, 'KodKraju', $address->country->value);
        $output->text($element, 'AdresL1', self::addressLine1($address));
        $line2 = trim(($address->postCode ?? '').' '.($address->city ?? ''));
        $output->text($element, 'AdresL2', $line2 === '' ? null : $line2);
        $output->text($element, 'GLN', $gln);
    }

    private static function contacts(Fa3Output $output, DOMElement $parent, ?Contact $contact, ?PolishParty $extras): void
    {
        $entries = $extras->contacts ?? [];
        if ($entries === [] && ($contact?->email !== null || $contact?->phone !== null)) {
            $entries = [new ContactDetails(email: $contact->email, phone: $contact->phone)];
        }
        foreach ($entries as $entry) {
            $element = $output->add($parent, 'DaneKontaktowe');
            $output->text($element, 'Email', $entry->email);
            $output->text($element, 'Telefon', $entry->phone);
        }
    }

    private function __construct() {}
}
