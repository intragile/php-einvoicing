<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Cii;

use DOMElement;
use IntrAgile\EInvoicing\Model\Identifier;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Syntax\WriterGuard;

/**
 * Writes CII trade parties (BG-4, BG-7, BG-10, BG-11) and postal addresses; the inverse of
 * PartyMapper.
 *
 * @internal
 */
final class PartyWriter
{
    /** schemeID of the VAT identifier (BT-31, BT-48, BT-63). */
    private const VAT_SCHEME = 'VA';

    /** schemeID of the seller tax registration identifier (BT-32). */
    private const TAX_REGISTRATION_SCHEME = 'FC';

    public function __construct(private readonly CiiOutput $out) {}

    /**
     * Writes the party into $party in the element order of the D16B and D22B TradePartyType.
     *
     * @param  PartyMapper::SELLER|PartyMapper::BUYER|PartyMapper::PAYEE|PartyMapper::TAX_REPRESENTATIVE  $role
     *
     * @throws \InvalidArgumentException when a buyer or payee has more than one identifier
     */
    public function party(DOMElement $party, Party $model, string $role): void
    {
        $out = $this->out;
        $isTrader = $role === PartyMapper::SELLER || $role === PartyMapper::BUYER;
        if ($role === PartyMapper::BUYER || $role === PartyMapper::PAYEE) {
            WriterGuard::singlePartyIdentifier($model, $role);
        }

        if ($role !== PartyMapper::TAX_REPRESENTATIVE) {
            $this->identifiers($party, $model->identifiers);
        }
        $out->text($party, 'ram:Name', $model->name);
        if ($role === PartyMapper::SELLER) {
            $out->text($party, 'ram:Description', $model->additionalLegalInformation);
        }

        $tradingName = $isTrader ? $model->tradingName : null;
        $legalRegistration = $role === PartyMapper::TAX_REPRESENTATIVE ? null : $model->legalRegistration;
        if ($legalRegistration !== null || $tradingName !== null) {
            $organization = $out->add($party, 'ram:SpecifiedLegalOrganization');
            $out->identifier($organization, 'ram:ID', $legalRegistration);
            $out->text($organization, 'ram:TradingBusinessName', $tradingName);
        }

        $contact = $model->contact;
        if ($isTrader && $contact !== null && ($contact->name ?? $contact->phone ?? $contact->email) !== null) {
            $element = $out->add($party, 'ram:DefinedTradeContact');
            $out->text($element, 'ram:PersonName', $contact->name);
            $out->wrapped($element, 'ram:TelephoneUniversalCommunication', 'ram:CompleteNumber', $contact->phone);
            $out->wrapped($element, 'ram:EmailURIUniversalCommunication', 'ram:URIID', $contact->email);
        }

        if ($role !== PartyMapper::PAYEE) {
            $this->address($party, $model->address);
        }

        if ($isTrader && $model->electronicAddress !== null) {
            $out->text($out->add($party, 'ram:URIUniversalCommunication'), 'ram:URIID', $model->electronicAddress->value, [
                'schemeID' => $model->electronicAddress->scheme->value,
            ]);
        }

        if ($role !== PartyMapper::PAYEE && $model->vatIdentifier !== null) {
            $out->text($out->add($party, 'ram:SpecifiedTaxRegistration'), 'ram:ID', $model->vatIdentifier, ['schemeID' => self::VAT_SCHEME]);
        }
        if ($role === PartyMapper::SELLER && $model->taxRegistration !== null) {
            $out->text($out->add($party, 'ram:SpecifiedTaxRegistration'), 'ram:ID', $model->taxRegistration->identifier, [
                'schemeID' => self::TAX_REGISTRATION_SCHEME,
            ]);
        }
    }

    /**
     * Writes ram:PostalTradeAddress in the element order of the D16B and D22B TradeAddressType.
     */
    public function address(DOMElement $parent, ?PostalAddress $address): void
    {
        if ($address === null) {
            return;
        }
        $out = $this->out;
        $element = $out->add($parent, 'ram:PostalTradeAddress');
        $out->text($element, 'ram:PostcodeCode', $address->postCode);
        $out->text($element, 'ram:LineOne', $address->line1);
        $out->text($element, 'ram:LineTwo', $address->line2);
        $out->text($element, 'ram:LineThree', $address->line3);
        $out->text($element, 'ram:CityName', $address->city);
        $out->text($element, 'ram:CountryID', $address->country->value);
        $out->text($element, 'ram:CountrySubDivisionName', $address->subdivision);
    }

    /**
     * Identifiers without a scheme as ram:ID, those with one as ram:GlobalID. The schema places
     * every ram:ID before the first ram:GlobalID, so the order within each kind is kept but the
     * two kinds are not interleaved.
     *
     * @param  list<Identifier>  $identifiers
     */
    public function identifiers(DOMElement $party, array $identifiers): void
    {
        foreach ($identifiers as $identifier) {
            if ($identifier->scheme === null) {
                $this->out->text($party, 'ram:ID', $identifier->value);
            }
        }
        foreach ($identifiers as $identifier) {
            if ($identifier->scheme !== null) {
                $this->out->identifier($party, 'ram:GlobalID', $identifier);
            }
        }
    }
}
