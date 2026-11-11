<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Ubl;

use DOMElement;
use IntrAgile\EInvoicing\Model\Identifier;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Syntax\WriterGuard;

/**
 * Writes UBL parties (BG-4, BG-7, BG-10, BG-11) and postal addresses; the inverse of PartyMapper.
 *
 * @internal
 */
final class PartyWriter
{
    /**
     * Placeholder tax scheme for a seller tax registration (BT-32) that carries none: UBL makes
     * cac:TaxScheme mandatory in cac:PartyTaxScheme. "TAX" is the Peppol BIS convention.
     */
    private const DEFAULT_TAX_REGISTRATION_SCHEME = 'TAX';

    public function __construct(private readonly UblOutput $out) {}

    /**
     * Writes cac:Party (seller, buyer) or the party element itself (payee, tax representative)
     * into $party, in the element order of the UBL 2.1 PartyType.
     *
     * @param  PartyMapper::SELLER|PartyMapper::BUYER|PartyMapper::PAYEE|PartyMapper::TAX_REPRESENTATIVE  $role
     * @param  ?string  $creditorIdentifier  the bank assigned creditor identifier (BT-90) to add as a SEPA identifier
     */
    public function party(DOMElement $party, Party $model, string $role, ?string $creditorIdentifier = null): void
    {
        $out = $this->out;
        $isTrader = $role === PartyMapper::SELLER || $role === PartyMapper::BUYER;
        if ($role === PartyMapper::BUYER || $role === PartyMapper::PAYEE) {
            WriterGuard::singlePartyIdentifier($model, $role);
        }

        if ($isTrader && $model->electronicAddress !== null) {
            $out->text($party, 'cbc:EndpointID', $model->electronicAddress->value, ['schemeID' => $model->electronicAddress->scheme->value]);
        }

        if ($role !== PartyMapper::TAX_REPRESENTATIVE) {
            foreach ($model->identifiers as $identifier) {
                $out->identifier($out->add($party, 'cac:PartyIdentification'), 'cbc:ID', $identifier);
            }
        }
        if ($creditorIdentifier !== null) {
            $out->identifier($out->add($party, 'cac:PartyIdentification'), 'cbc:ID', new Identifier(value: $creditorIdentifier, scheme: 'SEPA'));
        }

        $partyName = $isTrader ? $model->tradingName : $model->name;
        if ($partyName !== null) {
            $out->text($out->add($party, 'cac:PartyName'), 'cbc:Name', $partyName);
        }

        if ($role !== PartyMapper::PAYEE) {
            $this->address($party, 'cac:PostalAddress', $model->address);
        }

        if ($role !== PartyMapper::PAYEE && $model->vatIdentifier !== null) {
            $taxScheme = $out->add($party, 'cac:PartyTaxScheme');
            $out->text($taxScheme, 'cbc:CompanyID', $model->vatIdentifier);
            $out->taxScheme($taxScheme, 'VAT');
        }
        if ($role === PartyMapper::SELLER && $model->taxRegistration !== null) {
            $taxScheme = $out->add($party, 'cac:PartyTaxScheme');
            $out->text($taxScheme, 'cbc:CompanyID', $model->taxRegistration->identifier);
            $out->taxScheme($taxScheme, $model->taxRegistration->taxScheme ?? self::DEFAULT_TAX_REGISTRATION_SCHEME);
        }

        if ($isTrader) {
            $legalEntity = $out->add($party, 'cac:PartyLegalEntity');
            $out->text($legalEntity, 'cbc:RegistrationName', $model->name);
            $out->identifier($legalEntity, 'cbc:CompanyID', $model->legalRegistration);
            if ($role === PartyMapper::SELLER) {
                $out->text($legalEntity, 'cbc:CompanyLegalForm', $model->additionalLegalInformation);
            }
        } elseif ($role === PartyMapper::PAYEE && $model->legalRegistration !== null) {
            $out->identifier($out->add($party, 'cac:PartyLegalEntity'), 'cbc:CompanyID', $model->legalRegistration);
        }

        $contact = $model->contact;
        if ($isTrader && $contact !== null && ($contact->name ?? $contact->phone ?? $contact->email) !== null) {
            $element = $out->add($party, 'cac:Contact');
            $out->text($element, 'cbc:Name', $contact->name);
            $out->text($element, 'cbc:Telephone', $contact->phone);
            $out->text($element, 'cbc:ElectronicMail', $contact->email);
        }
    }

    /**
     * Writes a UBL AddressType element (cac:PostalAddress or cac:Address).
     */
    public function address(DOMElement $parent, string $name, ?PostalAddress $address): void
    {
        if ($address === null) {
            return;
        }
        $out = $this->out;
        $element = $out->add($parent, $name);
        $out->text($element, 'cbc:StreetName', $address->line1);
        $out->text($element, 'cbc:AdditionalStreetName', $address->line2);
        $out->text($element, 'cbc:CityName', $address->city);
        $out->text($element, 'cbc:PostalZone', $address->postCode);
        $out->text($element, 'cbc:CountrySubentity', $address->subdivision);
        if ($address->line3 !== null) {
            $out->text($out->add($element, 'cac:AddressLine'), 'cbc:Line', $address->line3);
        }
        $out->text($out->add($element, 'cac:Country'), 'cbc:IdentificationCode', $address->country->value);
    }
}
