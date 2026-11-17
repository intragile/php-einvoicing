<?php

declare(strict_types=1);

/*
 * Element paths of the EXTENDED-CTC-FR line terms (EXT-FR-FE) in CII D22B and UBL 2.1.
 *
 * Taken from the term annotations of FNFE's commented EXTENDED-CTC-FR examples and, for terms the
 * examples do not use, from the EXTENDED-CTC-FR format specification. UBL paths are given for an
 * invoice; a credit note uses cac:CreditNoteLine instead of cac:InvoiceLine. A null path means the
 * syntax has no element for the term. Paths and identifiers only; no example content.
 */

$cii = '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem';
$ubl = '/Invoice/cac:InvoiceLine';

return [
    // Preceding invoice reference on the line (EXT-FR-FE-BG-06).
    'EXT-FR-FE-BG-06' => [
        'cii' => $cii.'/ram:SpecifiedLineTradeSettlement/ram:InvoiceReferencedDocument',
        'ubl' => $ubl.'/cac:BillingReference',
    ],
    'EXT-FR-FE-136' => [
        'cii' => $cii.'/ram:SpecifiedLineTradeSettlement/ram:InvoiceReferencedDocument/ram:IssuerAssignedID',
        'ubl' => $ubl.'/cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID',
    ],
    'EXT-FR-FE-138' => [
        'cii' => $cii.'/ram:SpecifiedLineTradeSettlement/ram:InvoiceReferencedDocument/ram:FormattedIssueDateTime/qdt:DateTimeString',
        'ubl' => $ubl.'/cac:BillingReference/cac:InvoiceDocumentReference/cbc:IssueDate',
    ],

    // Line deliver-to location (EXT-FR-FE-BG-10) and actual delivery (EXT-FR-FE-BG-11).
    'EXT-FR-FE-BG-10' => [
        'cii' => $cii.'/ram:SpecifiedLineTradeDelivery/ram:ShipToTradeParty',
        'ubl' => $ubl.'/cac:Delivery',
    ],
    'EXT-FR-FE-149' => [
        'cii' => $cii.'/ram:SpecifiedLineTradeDelivery/ram:ShipToTradeParty/ram:Name',
        'ubl' => $ubl.'/cac:Delivery/cac:DeliveryLocation/cbc:Name',
    ],
    'EXT-FR-FE-150' => [
        'cii' => $cii.'/ram:SpecifiedLineTradeDelivery/ram:ShipToTradeParty/ram:PostalTradeAddress',
        'ubl' => $ubl.'/cac:Delivery/cac:DeliveryLocation/cac:Address',
    ],
    'EXT-FR-FE-151' => [
        'cii' => $cii.'/ram:SpecifiedLineTradeDelivery/ram:ShipToTradeParty/ram:PostalTradeAddress/ram:LineOne',
        'ubl' => $ubl.'/cac:Delivery/cac:DeliveryLocation/cac:Address/cbc:StreetName',
    ],
    'EXT-FR-FE-152' => [
        'cii' => $cii.'/ram:SpecifiedLineTradeDelivery/ram:ShipToTradeParty/ram:PostalTradeAddress/ram:LineTwo',
        'ubl' => $ubl.'/cac:Delivery/cac:DeliveryLocation/cac:Address/cbc:AdditionalStreetName',
    ],
    'EXT-FR-FE-153' => [
        'cii' => $cii.'/ram:SpecifiedLineTradeDelivery/ram:ShipToTradeParty/ram:PostalTradeAddress/ram:LineThree',
        'ubl' => $ubl.'/cac:Delivery/cac:DeliveryLocation/cac:Address/cac:AddressLine/cbc:Line',
    ],
    'EXT-FR-FE-154' => [
        'cii' => $cii.'/ram:SpecifiedLineTradeDelivery/ram:ShipToTradeParty/ram:PostalTradeAddress/ram:CityName',
        'ubl' => $ubl.'/cac:Delivery/cac:DeliveryLocation/cac:Address/cbc:CityName',
    ],
    'EXT-FR-FE-155' => [
        'cii' => $cii.'/ram:SpecifiedLineTradeDelivery/ram:ShipToTradeParty/ram:PostalTradeAddress/ram:PostcodeCode',
        'ubl' => $ubl.'/cac:Delivery/cac:DeliveryLocation/cac:Address/cbc:PostalZone',
    ],
    'EXT-FR-FE-156' => [
        'cii' => $cii.'/ram:SpecifiedLineTradeDelivery/ram:ShipToTradeParty/ram:PostalTradeAddress/ram:CountrySubDivisionName',
        'ubl' => $ubl.'/cac:Delivery/cac:DeliveryLocation/cac:Address/cbc:CountrySubentity',
    ],
    'EXT-FR-FE-157' => [
        'cii' => $cii.'/ram:SpecifiedLineTradeDelivery/ram:ShipToTradeParty/ram:PostalTradeAddress/ram:CountryID',
        'ubl' => $ubl.'/cac:Delivery/cac:DeliveryLocation/cac:Address/cac:Country/cbc:IdentificationCode',
    ],
    'EXT-FR-FE-BG-11' => [
        'cii' => $cii.'/ram:SpecifiedLineTradeDelivery/ram:ActualDeliverySupplyChainEvent',
        'ubl' => $ubl.'/cac:Delivery',
    ],
    'EXT-FR-FE-158' => [
        'cii' => $cii.'/ram:SpecifiedLineTradeDelivery/ram:ActualDeliverySupplyChainEvent/ram:OccurrenceDateTime/udt:DateTimeString',
        'ubl' => $ubl.'/cac:Delivery/cbc:ActualDeliveryDate',
    ],

    // Line structure.
    'EXT-FR-FE-162' => [
        'cii' => $cii.'/ram:AssociatedDocumentLineDocument/ram:ParentLineID',
        'ubl' => $ubl.'/cac:BillingReference/cac:BillingReferenceLine/cbc:ID',
    ],
    'EXT-FR-FE-163' => [
        'cii' => $cii.'/ram:AssociatedDocumentLineDocument/ram:LineStatusReasonCode',
        'ubl' => $ubl.'/cac:BillingReference/cac:InvoiceDocumentReference/cbc:DocumentStatusCode',
    ],

    // Line seller.
    'EXT-FR-FE-164' => [
        'cii' => $cii.'/ram:SpecifiedLineTradeAgreement/ram:ItemSellerTradeParty/ram:Name',
        'ubl' => $ubl.'/cac:Item/cac:ManufacturerParty/cac:PartyLegalEntity/cbc:RegistrationName',
    ],
    'EXT-FR-FE-166' => [
        'cii' => $cii.'/ram:SpecifiedLineTradeAgreement/ram:ItemSellerTradeParty/ram:GlobalID',
        'ubl' => $ubl.'/cac:Item/cac:ManufacturerParty/cac:PartyIdentification/cbc:ID',
    ],
    'EXT-FR-FE-167' => [
        'cii' => $cii.'/ram:SpecifiedLineTradeAgreement/ram:ItemSellerTradeParty/ram:SpecifiedLegalOrganization/ram:ID',
        'ubl' => $ubl.'/cac:Item/cac:ManufacturerParty/cac:PartyLegalEntity/cbc:CompanyID',
    ],
    'EXT-FR-FE-168' => [
        'cii' => $cii.'/ram:SpecifiedLineTradeAgreement/ram:ItemSellerTradeParty/ram:SpecifiedTaxRegistration/ram:ID',
        'ubl' => $ubl.'/cac:Item/cac:ManufacturerParty/cac:PartyTaxScheme/cbc:CompanyID',
    ],
    // Tax scheme of the line seller VAT identifier; CII carries it as the schemeID attribute.
    'EXT-FR-FE-168-1' => [
        'cii' => null,
        'ubl' => $ubl.'/cac:Item/cac:ManufacturerParty/cac:PartyTaxScheme/cac:TaxScheme/cbc:ID',
    ],
    'EXT-FR-FE-177' => [
        'cii' => $cii.'/ram:SpecifiedLineTradeAgreement/ram:ItemSellerTradeParty/ram:PostalTradeAddress/ram:CountryID',
        'ubl' => $ubl.'/cac:Item/cac:ManufacturerParty/cac:PostalAddress/cac:Country/cbc:IdentificationCode',
    ],

    // Line VAT and amounts.
    'EXT-FR-FE-178' => [
        'cii' => $cii.'/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:ExemptionReason',
        'ubl' => $ubl.'/cac:Item/cac:ClassifiedTaxCategory/cbc:TaxExemptionReason',
    ],
    'EXT-FR-FE-180' => [
        'cii' => $cii.'/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:DueDateTypeCode',
        'ubl' => $ubl.'/cac:InvoicePeriod/cbc:DescriptionCode',
    ],
    'EXT-FR-FE-181' => [
        'cii' => $cii.'/ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:TaxTotalAmount',
        'ubl' => $ubl.'/cac:TaxTotal/cbc:TaxAmount',
    ],
    // Line note subject code; UBL carries it inside the note text as "#CODE#text".
    'EXT-FR-FE-183' => [
        'cii' => $cii.'/ram:AssociatedDocumentLineDocument/ram:IncludedNote/ram:SubjectCode',
        'ubl' => $ubl.'/cbc:Note',
    ],
    // Line amount with VAT; neither the specification nor the examples give a UBL element, so
    // UblWriter refuses a line that carries one.
    'EXT-FR-FE-184' => [
        'cii' => $cii.'/ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:GrandTotalAmount',
        'ubl' => null,
    ],
];
