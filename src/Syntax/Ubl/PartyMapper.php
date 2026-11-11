<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Ubl;

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
 * Maps UBL parties (BG-4, BG-7, BG-10, BG-11) and postal addresses (BG-5, BG-8, BG-12, BG-15).
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
        self::SELLER => ['cac:PartyLegalEntity/cbc:RegistrationName', 'BT-27'],
        self::BUYER => ['cac:PartyLegalEntity/cbc:RegistrationName', 'BT-44'],
        self::PAYEE => ['cac:PartyName/cbc:Name', 'BT-59'],
        self::TAX_REPRESENTATIVE => ['cac:PartyName/cbc:Name', 'BT-62'],
    ];

    public function __construct(private readonly UblDocument $document) {}

    /**
     * @param  self::SELLER|self::BUYER|self::PAYEE|self::TAX_REPRESENTATIVE  $role
     */
    public function party(DOMElement $party, string $role): Party
    {
        $d = $this->document;
        [$nameExpression, $nameTerm] = self::NAME_TERMS[$role];
        $hasLegalEntity = $role !== self::TAX_REPRESENTATIVE;
        $isTrader = $role === self::SELLER || $role === self::BUYER;

        return new Party(
            name: $d->requiredText($nameExpression, $party, $nameTerm),
            tradingName: $isTrader ? $d->text('cac:PartyName/cbc:Name', $party) : null,
            identifiers: $role === self::TAX_REPRESENTATIVE ? [] : $this->identifiers($party, $role),
            legalRegistration: $hasLegalEntity ? $this->identifier($d->one('cac:PartyLegalEntity/cbc:CompanyID', $party)) : null,
            vatIdentifier: $role === self::PAYEE ? null : $this->vatIdentifier($party),
            taxRegistration: $role === self::SELLER ? $this->taxRegistration($party) : null,
            additionalLegalInformation: $role === self::SELLER ? $d->text('cac:PartyLegalEntity/cbc:CompanyLegalForm', $party) : null,
            electronicAddress: $isTrader ? $this->electronicAddress($d->one('cbc:EndpointID', $party)) : null,
            address: $role === self::PAYEE ? null : $this->address($d->one('cac:PostalAddress', $party)),
            contact: $isTrader ? $this->contact($d->one('cac:Contact', $party)) : null,
        );
    }

    public function address(?DOMElement $address): ?PostalAddress
    {
        if ($address === null) {
            return null;
        }
        $d = $this->document;

        $country = $d->code(CountryCode::class, $d->one('cac:Country/cbc:IdentificationCode', $address));
        if ($country === null) {
            $d->reject($address, 'dropped: no valid country code');

            return null;
        }

        return new PostalAddress(
            country: $country,
            line1: $d->text('cbc:StreetName', $address),
            line2: $d->text('cbc:AdditionalStreetName', $address),
            line3: $d->text('cac:AddressLine/cbc:Line', $address),
            city: $d->text('cbc:CityName', $address),
            postCode: $d->text('cbc:PostalZone', $address),
            subdivision: $d->text('cbc:CountrySubentity', $address),
        );
    }

    public function identifier(?DOMElement $element): ?Identifier
    {
        if ($element === null || $element->textContent === '') {
            return null;
        }
        $this->document->use($element);

        return new Identifier(value: $element->textContent, scheme: $this->document->attribute($element, 'schemeID'));
    }

    /**
     * Whether a party identifier is a SEPA creditor identifier, which is BT-90 and not BT-29/BT-60.
     */
    public static function isSepa(UblDocument $document, DOMElement $id): bool
    {
        return strtoupper((string) $document->attribute($id, 'schemeID')) === 'SEPA';
    }

    /**
     * BT-29 (0..n), BT-46 and BT-60 (0..1 each). SEPA identifiers of seller and payee are left
     * for the payment mapper.
     *
     * @return list<Identifier>
     */
    private function identifiers(DOMElement $party, string $role): array
    {
        $identifiers = [];
        foreach ($this->document->all('cac:PartyIdentification/cbc:ID', $party) as $id) {
            if ($role !== self::BUYER && self::isSepa($this->document, $id)) {
                continue;
            }
            if ($role !== self::SELLER && $identifiers !== []) {
                break;
            }
            $identifier = $this->identifier($id);
            if ($identifier !== null) {
                $identifiers[] = $identifier;
            }
        }

        return $identifiers;
    }

    private function vatIdentifier(DOMElement $party): ?string
    {
        foreach ($this->document->all('cac:PartyTaxScheme', $party) as $taxScheme) {
            $scheme = $this->document->one('cac:TaxScheme/cbc:ID', $taxScheme);
            if ($scheme !== null && strtoupper(trim($scheme->textContent)) === 'VAT') {
                $this->document->use($scheme);

                return $this->document->text('cbc:CompanyID', $taxScheme);
            }
        }

        return null;
    }

    private function taxRegistration(DOMElement $party): ?TaxRegistration
    {
        foreach ($this->document->all('cac:PartyTaxScheme', $party) as $taxScheme) {
            $scheme = $this->document->one('cac:TaxScheme/cbc:ID', $taxScheme);
            if ($scheme !== null && strtoupper(trim($scheme->textContent)) === 'VAT') {
                continue;
            }
            $identifier = $this->document->text('cbc:CompanyID', $taxScheme);
            if ($identifier === null) {
                continue;
            }

            return new TaxRegistration(identifier: $identifier, taxScheme: $this->document->token('cac:TaxScheme/cbc:ID', $taxScheme));
        }

        return null;
    }

    private function electronicAddress(?DOMElement $endpoint): ?ElectronicAddress
    {
        if ($endpoint === null || $endpoint->textContent === '') {
            return null;
        }
        if ($this->document->attribute($endpoint, 'schemeID') === null) {
            $this->document->reject($endpoint, 'missing scheme identifier', 'schemeID');

            return null;
        }
        $scheme = $this->document->code(ElectronicAddressScheme::class, $endpoint, 'schemeID');

        return $scheme === null ? null : new ElectronicAddress(value: $endpoint->textContent, scheme: $scheme);
    }

    private function contact(?DOMElement $contact): ?Contact
    {
        if ($contact === null) {
            return null;
        }
        $d = $this->document;
        $name = $d->text('cbc:Name', $contact);
        $phone = $d->text('cbc:Telephone', $contact);
        $email = $d->text('cbc:ElectronicMail', $contact);

        return $name === null && $phone === null && $email === null
            ? null
            : new Contact(name: $name, phone: $phone, email: $email);
    }
}
