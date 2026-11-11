<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Cii;

use DOMElement;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\ElectronicAddressScheme;
use IntrAgile\EInvoicing\Model\Contact;
use IntrAgile\EInvoicing\Model\ElectronicAddress;
use IntrAgile\EInvoicing\Model\Identifier;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Model\TaxRegistration;

/**
 * Maps CII trade parties (BG-4, BG-7, BG-10, BG-11) and postal addresses (BG-5, BG-8, BG-12,
 * BG-15).
 *
 * @internal
 */
final class PartyMapper
{
    public const SELLER = 'seller';

    public const BUYER = 'buyer';

    public const PAYEE = 'payee';

    public const TAX_REPRESENTATIVE = 'taxRepresentative';

    private const NAME_TERMS = [
        self::SELLER => 'BT-27',
        self::BUYER => 'BT-44',
        self::PAYEE => 'BT-59',
        self::TAX_REPRESENTATIVE => 'BT-62',
    ];

    /** schemeID of the VAT identifier (BT-31, BT-48, BT-63). */
    private const VAT_SCHEME = 'VA';

    /** schemeID of the seller tax registration identifier (BT-32). */
    private const TAX_REGISTRATION_SCHEME = 'FC';

    public function __construct(private readonly CiiDocument $document) {}

    /**
     * @param  self::SELLER|self::BUYER|self::PAYEE|self::TAX_REPRESENTATIVE  $role
     */
    public function party(DOMElement $party, string $role): Party
    {
        $d = $this->document;
        $isTrader = $role === self::SELLER || $role === self::BUYER;

        return new Party(
            name: $d->requiredText('ram:Name', $party, self::NAME_TERMS[$role]),
            tradingName: $isTrader ? $d->text('ram:SpecifiedLegalOrganization/ram:TradingBusinessName', $party) : null,
            identifiers: $role === self::TAX_REPRESENTATIVE ? [] : $this->identifiers($party, $role === self::SELLER),
            legalRegistration: $role === self::TAX_REPRESENTATIVE ? null : $d->identifier($d->one('ram:SpecifiedLegalOrganization/ram:ID', $party)),
            vatIdentifier: $role === self::PAYEE ? null : $this->taxRegistrationId($party, self::VAT_SCHEME),
            taxRegistration: $role === self::SELLER ? $this->taxRegistration($party) : null,
            additionalLegalInformation: $role === self::SELLER ? $d->text('ram:Description', $party) : null,
            electronicAddress: $isTrader ? $this->electronicAddress($d->one('ram:URIUniversalCommunication/ram:URIID', $party)) : null,
            address: $role === self::PAYEE ? null : $this->address($d->one('ram:PostalTradeAddress', $party)),
            contact: $isTrader ? $this->contact($d->one('ram:DefinedTradeContact', $party)) : null,
        );
    }

    public function address(?DOMElement $address): ?PostalAddress
    {
        if ($address === null) {
            return null;
        }
        $d = $this->document;

        $country = $d->code(CountryCode::class, $d->one('ram:CountryID', $address));
        if ($country === null) {
            $d->reject($address, 'dropped: no valid country code');

            return null;
        }

        return new PostalAddress(
            country: $country,
            line1: $d->text('ram:LineOne', $address),
            line2: $d->text('ram:LineTwo', $address),
            line3: $d->text('ram:LineThree', $address),
            city: $d->text('ram:CityName', $address),
            postCode: $d->text('ram:PostcodeCode', $address),
            subdivision: $d->text('ram:CountrySubDivisionName', $address),
        );
    }

    /**
     * Party identifiers in document order: ram:ID without a scheme and ram:GlobalID with one. The
     * seller has 0..n (BT-29); buyer, payee and deliver-to location have 0..1 (BT-46, BT-60,
     * BT-71), so further identifiers are left unmapped.
     *
     * @return list<Identifier>
     */
    public function identifiers(DOMElement $party, bool $many): array
    {
        $identifiers = [];
        foreach ($this->document->all('ram:ID | ram:GlobalID', $party) as $id) {
            if (! $many && $identifiers !== []) {
                break;
            }
            $identifier = $this->document->identifier($id);
            if ($identifier !== null) {
                $identifiers[] = $identifier;
            }
        }

        return $identifiers;
    }

    private function taxRegistrationId(DOMElement $party, string $scheme): ?string
    {
        foreach ($this->document->all('ram:SpecifiedTaxRegistration/ram:ID', $party) as $id) {
            if (strtoupper((string) $this->document->attribute($id, 'schemeID')) === $scheme && $id->textContent !== '') {
                $this->document->use($id);

                return $id->textContent;
            }
        }

        return null;
    }

    private function taxRegistration(DOMElement $party): ?TaxRegistration
    {
        $identifier = $this->taxRegistrationId($party, self::TAX_REGISTRATION_SCHEME);

        return $identifier === null ? null : new TaxRegistration(identifier: $identifier, taxScheme: self::TAX_REGISTRATION_SCHEME);
    }

    private function electronicAddress(?DOMElement $uri): ?ElectronicAddress
    {
        if ($uri === null) {
            return null;
        }
        if ($uri->textContent === '') {
            $this->document->use($uri);

            return null;
        }
        if ($this->document->attribute($uri, 'schemeID') === null) {
            $this->document->reject($uri, 'missing scheme identifier', 'schemeID');

            return null;
        }
        $scheme = $this->document->code(ElectronicAddressScheme::class, $uri, 'schemeID');

        return $scheme === null ? null : new ElectronicAddress(value: $uri->textContent, scheme: $scheme);
    }

    /**
     * BG-6 / BG-9. The contact point (BT-41 / BT-56) is the person name or, failing that, the
     * department name; the CEN rules forbid both.
     */
    private function contact(?DOMElement $contact): ?Contact
    {
        if ($contact === null) {
            return null;
        }
        $d = $this->document;
        if ($contact->firstElementChild === null && trim($contact->textContent) === '') {
            $d->use($contact);

            return null;
        }
        $name = $d->text('ram:PersonName', $contact) ?? $d->text('ram:DepartmentName', $contact);
        $phone = $d->text('ram:TelephoneUniversalCommunication/ram:CompleteNumber', $contact);
        $email = $d->text('ram:EmailURIUniversalCommunication/ram:URIID', $contact);

        return $name === null && $phone === null && $email === null
            ? null
            : new Contact(name: $name, phone: $phone, email: $email);
    }
}
