<?php

// Generated from KoSIT XRechnung Schematron 2.6.0 (Apache-2.0) — do not edit.
// Source: XRechnung-CII-validation.sch (SHA-256 53bb5c0664fc603bbaaddeed26eb59525a05d3359d1a63f148876b2f2eedde1d).
// Regenerate with: composer compile-rules

declare(strict_types=1);

return array (
  'format' => 3,
  'id' => 'xrechnung-cii',
  'version' => '2.6.0',
  'source' => 'XRechnung-CII-validation.sch',
  'sha256' => '53bb5c0664fc603bbaaddeed26eb59525a05d3359d1a63f148876b2f2eedde1d',
  'namespaces' => 
  array (
    'rsm' => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
    'ccts' => 'urn:un:unece:uncefact:documentation:standard:CoreComponentsTechnicalSpecification:2',
    'udt' => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
    'qdt' => 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100',
    'ram' => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
    'xr' => 'xrechnung',
    'u' => 'utils',
  ),
  'lets' => 
  array (
    0 => 
    array (
      'name' => 'profile',
      'value' => '        if (normalize-space(/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:BusinessProcessSpecifiedDocumentContextParameter/ram:ID) = \'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0\')        then \'01\'        else if (normalize-space(/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:BusinessProcessSpecifiedDocumentContextParameter/ram:ID) = \'urn:peppol:bis:billing_with_response\')        then \'02\'        else \'Unknown\'      ',
      'ast' => 
      array (
        0 => 'if',
        1 => 
        array (
          0 => 'gcmp',
          1 => '=',
          2 => 
          array (
            0 => 'call',
            1 => 'fn:normalize-space',
            2 => 
            array (
              0 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'root',
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                          2 => 'CrossIndustryInvoice',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                        2 => 'ExchangedDocumentContext',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'BusinessProcessSpecifiedDocumentContextParameter',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'ID',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
            ),
          ),
          3 => 
          array (
            0 => 'literal',
            1 => 'xs:string',
            2 => 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0',
          ),
        ),
        2 => 
        array (
          0 => 'literal',
          1 => 'xs:string',
          2 => '01',
        ),
        3 => 
        array (
          0 => 'if',
          1 => 
          array (
            0 => 'gcmp',
            1 => '=',
            2 => 
            array (
              0 => 'call',
              1 => 'fn:normalize-space',
              2 => 
              array (
                0 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'root',
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                            2 => 'CrossIndustryInvoice',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                          2 => 'ExchangedDocumentContext',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'BusinessProcessSpecifiedDocumentContextParameter',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'ID',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
              ),
            ),
            3 => 
            array (
              0 => 'literal',
              1 => 'xs:string',
              2 => 'urn:peppol:bis:billing_with_response',
            ),
          ),
          2 => 
          array (
            0 => 'literal',
            1 => 'xs:string',
            2 => '02',
          ),
          3 => 
          array (
            0 => 'literal',
            1 => 'xs:string',
            2 => 'Unknown',
          ),
        ),
      ),
    ),
    1 => 
    array (
      'name' => 'supplierCountry',
      'value' => '             if (/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction[1]/ram:ApplicableHeaderTradeAgreement[1]/ram:SellerTradeParty[1]/ram:SpecifiedTaxRegistration[ram:ID/@schemeID = \'VAT\']/substring(ram:ID, 1, 2)) then                 upper-case(normalize-space(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction[1]/ram:ApplicableHeaderTradeAgreement[1]/ram:SellerTradeParty[1]/ram:SpecifiedTaxRegistration[ram:ID/@schemeID = \'VAT\']/substring(ram:ID, 1, 2)))             else                 if (/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTaxRepresentativeTradeParty/ram:SpecifiedTaxRegistration[ram:ID/@schemeID = \'VAT\']/substring(ram:ID, 1, 2)) then                     upper-case(normalize-space(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTaxRepresentativeTradeParty/ram:SpecifiedTaxRegistration[ram:ID/@schemeID = \'VAT\']/substring(ram:ID, 1, 2)))                 else                     if (/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:PostalTradeAddress/ram:CountryID) then                         upper-case(normalize-space(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:PostalTradeAddress/ram:CountryID))                     else                         \'XX\'',
      'ast' => 
      array (
        0 => 'if',
        1 => 
        array (
          0 => 'path',
          1 => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'root',
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                        2 => 'CrossIndustryInvoice',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                      2 => 'SupplyChainTradeTransaction',
                    ),
                    3 => 
                    array (
                      0 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:integer',
                        2 => '1',
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'ApplicableHeaderTradeAgreement',
                  ),
                  3 => 
                  array (
                    0 => 
                    array (
                      0 => 'literal',
                      1 => 'xs:integer',
                      2 => '1',
                    ),
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'SellerTradeParty',
                ),
                3 => 
                array (
                  0 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:integer',
                    2 => '1',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                2 => 'SpecifiedTaxRegistration',
              ),
              3 => 
              array (
                0 => 
                array (
                  0 => 'gcmp',
                  1 => '=',
                  2 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'ID',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'attribute',
                      2 => 
                      array (
                        0 => 'name',
                        1 => '',
                        2 => 'schemeID',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:string',
                    2 => 'VAT',
                  ),
                ),
              ),
            ),
          ),
          2 => 
          array (
            0 => 'call',
            1 => 'fn:substring',
            2 => 
            array (
              0 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'ID',
                ),
                3 => 
                array (
                ),
              ),
              1 => 
              array (
                0 => 'literal',
                1 => 'xs:integer',
                2 => '1',
              ),
              2 => 
              array (
                0 => 'literal',
                1 => 'xs:integer',
                2 => '2',
              ),
            ),
          ),
        ),
        2 => 
        array (
          0 => 'call',
          1 => 'fn:upper-case',
          2 => 
          array (
            0 => 
            array (
              0 => 'call',
              1 => 'fn:normalize-space',
              2 => 
              array (
                0 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'root',
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                                2 => 'CrossIndustryInvoice',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                              2 => 'SupplyChainTradeTransaction',
                            ),
                            3 => 
                            array (
                              0 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:integer',
                                2 => '1',
                              ),
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'ApplicableHeaderTradeAgreement',
                          ),
                          3 => 
                          array (
                            0 => 
                            array (
                              0 => 'literal',
                              1 => 'xs:integer',
                              2 => '1',
                            ),
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'SellerTradeParty',
                        ),
                        3 => 
                        array (
                          0 => 
                          array (
                            0 => 'literal',
                            1 => 'xs:integer',
                            2 => '1',
                          ),
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'SpecifiedTaxRegistration',
                      ),
                      3 => 
                      array (
                        0 => 
                        array (
                          0 => 'gcmp',
                          1 => '=',
                          2 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'ID',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'attribute',
                              2 => 
                              array (
                                0 => 'name',
                                1 => '',
                                2 => 'schemeID',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          3 => 
                          array (
                            0 => 'literal',
                            1 => 'xs:string',
                            2 => 'VAT',
                          ),
                        ),
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'call',
                    1 => 'fn:substring',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'ID',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                      1 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:integer',
                        2 => '1',
                      ),
                      2 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:integer',
                        2 => '2',
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
        3 => 
        array (
          0 => 'if',
          1 => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'root',
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                          2 => 'CrossIndustryInvoice',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                        2 => 'SupplyChainTradeTransaction',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'ApplicableHeaderTradeAgreement',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'SellerTaxRepresentativeTradeParty',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'SpecifiedTaxRegistration',
                ),
                3 => 
                array (
                  0 => 
                  array (
                    0 => 'gcmp',
                    1 => '=',
                    2 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'ID',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'attribute',
                        2 => 
                        array (
                          0 => 'name',
                          1 => '',
                          2 => 'schemeID',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    3 => 
                    array (
                      0 => 'literal',
                      1 => 'xs:string',
                      2 => 'VAT',
                    ),
                  ),
                ),
              ),
            ),
            2 => 
            array (
              0 => 'call',
              1 => 'fn:substring',
              2 => 
              array (
                0 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'ID',
                  ),
                  3 => 
                  array (
                  ),
                ),
                1 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '1',
                ),
                2 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '2',
                ),
              ),
            ),
          ),
          2 => 
          array (
            0 => 'call',
            1 => 'fn:upper-case',
            2 => 
            array (
              0 => 
              array (
                0 => 'call',
                1 => 'fn:normalize-space',
                2 => 
                array (
                  0 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'path',
                              1 => 
                              array (
                                0 => 'root',
                              ),
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                                  2 => 'CrossIndustryInvoice',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                                2 => 'SupplyChainTradeTransaction',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'ApplicableHeaderTradeAgreement',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'SellerTaxRepresentativeTradeParty',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'SpecifiedTaxRegistration',
                        ),
                        3 => 
                        array (
                          0 => 
                          array (
                            0 => 'gcmp',
                            1 => '=',
                            2 => 
                            array (
                              0 => 'path',
                              1 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                  2 => 'ID',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'attribute',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => '',
                                  2 => 'schemeID',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                            3 => 
                            array (
                              0 => 'literal',
                              1 => 'xs:string',
                              2 => 'VAT',
                            ),
                          ),
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'call',
                      1 => 'fn:substring',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'ID',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        1 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:integer',
                          2 => '1',
                        ),
                        2 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:integer',
                          2 => '2',
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
          3 => 
          array (
            0 => 'if',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'root',
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                            2 => 'CrossIndustryInvoice',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                          2 => 'SupplyChainTradeTransaction',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'ApplicableHeaderTradeAgreement',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'SellerTradeParty',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'PostalTradeAddress',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'CountryID',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'call',
              1 => 'fn:upper-case',
              2 => 
              array (
                0 => 
                array (
                  0 => 'call',
                  1 => 'fn:normalize-space',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'path',
                              1 => 
                              array (
                                0 => 'path',
                                1 => 
                                array (
                                  0 => 'root',
                                ),
                                2 => 
                                array (
                                  0 => 'step',
                                  1 => 'child',
                                  2 => 
                                  array (
                                    0 => 'name',
                                    1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                                    2 => 'CrossIndustryInvoice',
                                  ),
                                  3 => 
                                  array (
                                  ),
                                ),
                              ),
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                                  2 => 'SupplyChainTradeTransaction',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'ApplicableHeaderTradeAgreement',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'SellerTradeParty',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'PostalTradeAddress',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'CountryID',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
            3 => 
            array (
              0 => 'literal',
              1 => 'xs:string',
              2 => 'XX',
            ),
          ),
        ),
      ),
    ),
    2 => 
    array (
      'name' => 'documentCurrencyCode',
      'value' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:InvoiceCurrencyCode',
      'ast' => 
      array (
        0 => 'path',
        1 => 
        array (
          0 => 'path',
          1 => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'root',
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                  2 => 'CrossIndustryInvoice',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                2 => 'SupplyChainTradeTransaction',
              ),
              3 => 
              array (
              ),
            ),
          ),
          2 => 
          array (
            0 => 'step',
            1 => 'child',
            2 => 
            array (
              0 => 'name',
              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
              2 => 'ApplicableHeaderTradeSettlement',
            ),
            3 => 
            array (
            ),
          ),
        ),
        2 => 
        array (
          0 => 'step',
          1 => 'child',
          2 => 
          array (
            0 => 'name',
            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
            2 => 'InvoiceCurrencyCode',
          ),
          3 => 
          array (
          ),
        ),
      ),
    ),
    3 => 
    array (
      'name' => 'slackValue',
      'value' => 'if($documentCurrencyCode = \'HUF\') then 0.5 else 0.02',
      'ast' => 
      array (
        0 => 'if',
        1 => 
        array (
          0 => 'gcmp',
          1 => '=',
          2 => 
          array (
            0 => 'var',
            1 => 'documentCurrencyCode',
          ),
          3 => 
          array (
            0 => 'literal',
            1 => 'xs:string',
            2 => 'HUF',
          ),
        ),
        2 => 
        array (
          0 => 'literal',
          1 => 'xs:decimal',
          2 => '0.5',
        ),
        3 => 
        array (
          0 => 'literal',
          1 => 'xs:decimal',
          2 => '0.02',
        ),
      ),
    ),
    4 => 
    array (
      'name' => 'taxCurrencyCode',
      'value' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:TaxCurrencyCode',
      'ast' => 
      array (
        0 => 'path',
        1 => 
        array (
          0 => 'path',
          1 => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'root',
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                  2 => 'CrossIndustryInvoice',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                2 => 'SupplyChainTradeTransaction',
              ),
              3 => 
              array (
              ),
            ),
          ),
          2 => 
          array (
            0 => 'step',
            1 => 'child',
            2 => 
            array (
              0 => 'name',
              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
              2 => 'ApplicableHeaderTradeSettlement',
            ),
            3 => 
            array (
            ),
          ),
        ),
        2 => 
        array (
          0 => 'step',
          1 => 'child',
          2 => 
          array (
            0 => 'name',
            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
            2 => 'TaxCurrencyCode',
          ),
          3 => 
          array (
          ),
        ),
      ),
    ),
    5 => 
    array (
      'name' => 'XR-MAJOR-MINOR-VERSION',
      'value' => '\'3.0\'',
      'ast' => 
      array (
        0 => 'literal',
        1 => 'xs:string',
        2 => '3.0',
      ),
    ),
    6 => 
    array (
      'name' => 'CVD-MAJOR-MINOR-VERSION',
      'value' => '\'0.9\'',
      'ast' => 
      array (
        0 => 'literal',
        1 => 'xs:string',
        2 => '0.9',
      ),
    ),
    7 => 
    array (
      'name' => 'XR-CIUS-ID',
      'value' => 'concat(\'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_\', $XR-MAJOR-MINOR-VERSION )',
      'ast' => 
      array (
        0 => 'call',
        1 => 'fn:concat',
        2 => 
        array (
          0 => 
          array (
            0 => 'literal',
            1 => 'xs:string',
            2 => 'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_',
          ),
          1 => 
          array (
            0 => 'var',
            1 => 'XR-MAJOR-MINOR-VERSION',
          ),
        ),
      ),
    ),
    8 => 
    array (
      'name' => 'XR-EXTENSION-ID',
      'value' => 'concat($XR-CIUS-ID, \'#conformant#urn:xeinkauf.de:kosit:extension:xrechnung_\' ,$XR-MAJOR-MINOR-VERSION )',
      'ast' => 
      array (
        0 => 'call',
        1 => 'fn:concat',
        2 => 
        array (
          0 => 
          array (
            0 => 'var',
            1 => 'XR-CIUS-ID',
          ),
          1 => 
          array (
            0 => 'literal',
            1 => 'xs:string',
            2 => '#conformant#urn:xeinkauf.de:kosit:extension:xrechnung_',
          ),
          2 => 
          array (
            0 => 'var',
            1 => 'XR-MAJOR-MINOR-VERSION',
          ),
        ),
      ),
    ),
    9 => 
    array (
      'name' => 'XR-CVD-ID',
      'value' => 'concat($XR-CIUS-ID, \'#compliant#urn:xeinkauf.de:kosit:xrechnung:cvd_\' , $CVD-MAJOR-MINOR-VERSION )',
      'ast' => 
      array (
        0 => 'call',
        1 => 'fn:concat',
        2 => 
        array (
          0 => 
          array (
            0 => 'var',
            1 => 'XR-CIUS-ID',
          ),
          1 => 
          array (
            0 => 'literal',
            1 => 'xs:string',
            2 => '#compliant#urn:xeinkauf.de:kosit:xrechnung:cvd_',
          ),
          2 => 
          array (
            0 => 'var',
            1 => 'CVD-MAJOR-MINOR-VERSION',
          ),
        ),
      ),
    ),
    10 => 
    array (
      'name' => 'XR-SKONTO-REGEX',
      'value' => '\'(^|\\r?\\n)#(SKONTO)#TAGE=([0-9]+#PROZENT=[0-9]+\\.[0-9]{2})(#BASISBETRAG=-?[0-9]+\\.[0-9]{2})?#$\'',
      'ast' => 
      array (
        0 => 'literal',
        1 => 'xs:string',
        2 => '(^|\\r?\\n)#(SKONTO)#TAGE=([0-9]+#PROZENT=[0-9]+\\.[0-9]{2})(#BASISBETRAG=-?[0-9]+\\.[0-9]{2})?#$',
      ),
    ),
    11 => 
    array (
      'name' => 'XR-EMAIL-REGEX',
      'value' => '\'^[^@\\s]+@([^@.\\s]+\\.)+[^@.\\s]+$\'',
      'ast' => 
      array (
        0 => 'literal',
        1 => 'xs:string',
        2 => '^[^@\\s]+@([^@.\\s]+\\.)+[^@.\\s]+$',
      ),
    ),
    12 => 
    array (
      'name' => 'XR-TELEPHONE-REGEX',
      'value' => '\'.*([0-9].*){3,}.*\'',
      'ast' => 
      array (
        0 => 'literal',
        1 => 'xs:string',
        2 => '.*([0-9].*){3,}.*',
      ),
    ),
    13 => 
    array (
      'name' => 'XR-URL-REGEX',
      'value' => '\'^([a-zA-Z])([a-zA-Z0-9+.-])+:.*\'',
      'ast' => 
      array (
        0 => 'literal',
        1 => 'xs:string',
        2 => '^([a-zA-Z])([a-zA-Z0-9+.-])+:.*',
      ),
    ),
    14 => 
    array (
      'name' => 'XR-IBAN-REGEX',
      'value' => '\'^[A-Z]{2}[0-9]{2}[a-zA-Z0-9]{0,30}$\'',
      'ast' => 
      array (
        0 => 'literal',
        1 => 'xs:string',
        2 => '^[A-Z]{2}[0-9]{2}[a-zA-Z0-9]{0,30}$',
      ),
    ),
    15 => 
    array (
      'name' => 'DIGA-CODES',
      'value' => '\' XR01 XR02 XR03 \'',
      'ast' => 
      array (
        0 => 'literal',
        1 => 'xs:string',
        2 => ' XR01 XR02 XR03 ',
      ),
    ),
    16 => 
    array (
      'name' => 'ISO-6523-ICD-CODES',
      'value' => '\' 0002 0003 0004 0005 0006 0007 0008 0009 0010 0011 0012 0013 0014 0015 0016 0017 0018 0019 0020 0021 0022 0023 0024 0025 0026 0027 0028 0029 0030 0031 0032 0033 0034 0035 0036 0037 0038 0039 0040 0041 0042 0043 0044 0045 0046 0047 0048 0049 0050 0051 0052 0053 0054 0055 0056 0057 0058 0059 0060 0061 0062 0063 0064 0065 0066 0067 0068 0069 0070 0071 0072 0073 0074 0075 0076 0077 0078 0079 0080 0081 0082 0083 0084 0085 0086 0087 0088 0089 0090 0091 0093 0094 0095 0096 0097 0098 0099 0100 0101 0102 0104 0105 0106 0107 0108 0109 0110 0111 0112 0113 0114 0115 0116 0117 0118 0119 0120 0121 0122 0123 0124 0125 0126 0127 0128 0129 0130 0131 0132 0133 0134 0135 0136 0137 0138 0139 0140 0141 0142 0143 0144 0145 0146 0147 0148 0149 0150 0151 0152 0153 0154 0155 0156 0157 0158 0159 0160 0161 0162 0163 0164 0165 0166 0167 0168 0169 0170 0171 0172 0173 0174 0175 0176 0177 0178 0179 0180 0183 0184 0185 0186 0187 0188 0189 0190 0191 0192 0193 0194 0195 0196 0197 0198 0199 0200 0201 0202 0203 0204 0205 0206 0207 0208 0209 0210 0211 0212 0213 0214 0215 0216 0217 0218 0219 0220 0221 0222 0223 0224 0225 0226 0227 0228 0229 0230 0231 0232 0233 0234 0235 0236 0237 0238 0239 0240 0241 0242 0243 0244 0245 0246 0247 0248 \'',
      'ast' => 
      array (
        0 => 'literal',
        1 => 'xs:string',
        2 => ' 0002 0003 0004 0005 0006 0007 0008 0009 0010 0011 0012 0013 0014 0015 0016 0017 0018 0019 0020 0021 0022 0023 0024 0025 0026 0027 0028 0029 0030 0031 0032 0033 0034 0035 0036 0037 0038 0039 0040 0041 0042 0043 0044 0045 0046 0047 0048 0049 0050 0051 0052 0053 0054 0055 0056 0057 0058 0059 0060 0061 0062 0063 0064 0065 0066 0067 0068 0069 0070 0071 0072 0073 0074 0075 0076 0077 0078 0079 0080 0081 0082 0083 0084 0085 0086 0087 0088 0089 0090 0091 0093 0094 0095 0096 0097 0098 0099 0100 0101 0102 0104 0105 0106 0107 0108 0109 0110 0111 0112 0113 0114 0115 0116 0117 0118 0119 0120 0121 0122 0123 0124 0125 0126 0127 0128 0129 0130 0131 0132 0133 0134 0135 0136 0137 0138 0139 0140 0141 0142 0143 0144 0145 0146 0147 0148 0149 0150 0151 0152 0153 0154 0155 0156 0157 0158 0159 0160 0161 0162 0163 0164 0165 0166 0167 0168 0169 0170 0171 0172 0173 0174 0175 0176 0177 0178 0179 0180 0183 0184 0185 0186 0187 0188 0189 0190 0191 0192 0193 0194 0195 0196 0197 0198 0199 0200 0201 0202 0203 0204 0205 0206 0207 0208 0209 0210 0211 0212 0213 0214 0215 0216 0217 0218 0219 0220 0221 0222 0223 0224 0225 0226 0227 0228 0229 0230 0231 0232 0233 0234 0235 0236 0237 0238 0239 0240 0241 0242 0243 0244 0245 0246 0247 0248 ',
      ),
    ),
    17 => 
    array (
      'name' => 'ISO-6523-ICD-EXT-CODES',
      'value' => 'concat($DIGA-CODES, $ISO-6523-ICD-CODES)',
      'ast' => 
      array (
        0 => 'call',
        1 => 'fn:concat',
        2 => 
        array (
          0 => 
          array (
            0 => 'var',
            1 => 'DIGA-CODES',
          ),
          1 => 
          array (
            0 => 'var',
            1 => 'ISO-6523-ICD-CODES',
          ),
        ),
      ),
    ),
    18 => 
    array (
      'name' => 'CEF-EAS-CODES',
      'value' => '\' 0002 0007 0009 0037 0060 0088 0096 0097 0106 0130 0135 0142 0147 0151 0154 0158 0170 0177 0183 0184 0188 0190 0191 0192 0193 0194 0195 0196 0198 0199 0200 0201 0202 0203 0204 0205 0208 0209 0210 0211 0212 0213 0215 0216 0217 0218 0219 0220 0221 0225 0230 0235 0240 0244 0242 0245 0246 0248 9910 9913 9914 9915 9918 9919 9920 9922 9923 9924 9925 9926 9927 9928 9929 9930 9931 9932 9933 9934 9935 9936 9937 9938 9939 9940 9941 9942 9943 9944 9945 9946 9947 9948 9949 9950 9951 9952 9953 9957 9959 AN AQ AS AU EM \'',
      'ast' => 
      array (
        0 => 'literal',
        1 => 'xs:string',
        2 => ' 0002 0007 0009 0037 0060 0088 0096 0097 0106 0130 0135 0142 0147 0151 0154 0158 0170 0177 0183 0184 0188 0190 0191 0192 0193 0194 0195 0196 0198 0199 0200 0201 0202 0203 0204 0205 0208 0209 0210 0211 0212 0213 0215 0216 0217 0218 0219 0220 0221 0225 0230 0235 0240 0244 0242 0245 0246 0248 9910 9913 9914 9915 9918 9919 9920 9922 9923 9924 9925 9926 9927 9928 9929 9930 9931 9932 9933 9934 9935 9936 9937 9938 9939 9940 9941 9942 9943 9944 9945 9946 9947 9948 9949 9950 9951 9952 9953 9957 9959 AN AQ AS AU EM ',
      ),
    ),
    19 => 
    array (
      'name' => 'CEF-EAS-EXT-CODES',
      'value' => 'concat($DIGA-CODES, $CEF-EAS-CODES)',
      'ast' => 
      array (
        0 => 'call',
        1 => 'fn:concat',
        2 => 
        array (
          0 => 
          array (
            0 => 'var',
            1 => 'DIGA-CODES',
          ),
          1 => 
          array (
            0 => 'var',
            1 => 'CEF-EAS-CODES',
          ),
        ),
      ),
    ),
    20 => 
    array (
      'name' => 'CVD-CODE',
      'value' => '\' CVD \'',
      'ast' => 
      array (
        0 => 'literal',
        1 => 'xs:string',
        2 => ' CVD ',
      ),
    ),
    21 => 
    array (
      'name' => 'UNTDID-7143-CODES',
      'value' => '\' AA AB AC AD AE AF AG AH AI AJ AK AL AM AN AO AP AQ AR AS AT AU AV AW AX AY AZ BA BB BC BD BE BF BG BH BI BJ BK BL BM BN BO BP BQ BR BS BT BU BV BW BX BY BZ CC CG CL CR CV DR DW EC EF EMD EN FS GB GN GMN GS HS IB IN IS IT IZ MA MF MN MP NB ON PD PL PO PPI PV QS RC RN RU RY SA SG SK SN SRS SRT SRU SRV SRW SRX SRY SRZ SS SSA SSB SSC SSD SSE SSF SSG SSH SSI SSJ SSK SSL SSM SSN SSO SSP SSQ SSR SSS SST SSU SSV SSW SSX SSY SSZ ST STA STB STC STD STE STF STG STH STI STJ STK STL STM STN STO STP STQ STR STS STT STU STV STW STX STY STZ SUA SUB SUC SUD SUE SUF SUG SUH SUI SUJ SUK SUL SUM TG TSN TSO TSP TSQ TSR TSS TST TSU UA UP VN VP VS VX ZZZ \'',
      'ast' => 
      array (
        0 => 'literal',
        1 => 'xs:string',
        2 => ' AA AB AC AD AE AF AG AH AI AJ AK AL AM AN AO AP AQ AR AS AT AU AV AW AX AY AZ BA BB BC BD BE BF BG BH BI BJ BK BL BM BN BO BP BQ BR BS BT BU BV BW BX BY BZ CC CG CL CR CV DR DW EC EF EMD EN FS GB GN GMN GS HS IB IN IS IT IZ MA MF MN MP NB ON PD PL PO PPI PV QS RC RN RU RY SA SG SK SN SRS SRT SRU SRV SRW SRX SRY SRZ SS SSA SSB SSC SSD SSE SSF SSG SSH SSI SSJ SSK SSL SSM SSN SSO SSP SSQ SSR SSS SST SSU SSV SSW SSX SSY SSZ ST STA STB STC STD STE STF STG STH STI STJ STK STL STM STN STO STP STQ STR STS STT STU STV STW STX STY STZ SUA SUB SUC SUD SUE SUF SUG SUH SUI SUJ SUK SUL SUM TG TSN TSO TSP TSQ TSR TSS TST TSU UA UP VN VP VS VX ZZZ ',
      ),
    ),
    22 => 
    array (
      'name' => 'UNTDID-7143-CVD-CODES',
      'value' => 'concat($CVD-CODE, $UNTDID-7143-CODES)',
      'ast' => 
      array (
        0 => 'call',
        1 => 'fn:concat',
        2 => 
        array (
          0 => 
          array (
            0 => 'var',
            1 => 'CVD-CODE',
          ),
          1 => 
          array (
            0 => 'var',
            1 => 'UNTDID-7143-CODES',
          ),
        ),
      ),
    ),
    23 => 
    array (
      'name' => 'CVD-VEHICLE-CATEGORY',
      'value' => '(\'M1\', \'M2\', \'M3\', \'N1\', \'N2\', \'N3\')',
      'ast' => 
      array (
        0 => 'sequence',
        1 => 
        array (
          0 => 
          array (
            0 => 'literal',
            1 => 'xs:string',
            2 => 'M1',
          ),
          1 => 
          array (
            0 => 'literal',
            1 => 'xs:string',
            2 => 'M2',
          ),
          2 => 
          array (
            0 => 'literal',
            1 => 'xs:string',
            2 => 'M3',
          ),
          3 => 
          array (
            0 => 'literal',
            1 => 'xs:string',
            2 => 'N1',
          ),
          4 => 
          array (
            0 => 'literal',
            1 => 'xs:string',
            2 => 'N2',
          ),
          5 => 
          array (
            0 => 'literal',
            1 => 'xs:string',
            2 => 'N3',
          ),
        ),
      ),
    ),
    24 => 
    array (
      'name' => 'CVA-CODES',
      'value' => '(\'clean\', \'zero-emission\', \'other\')',
      'ast' => 
      array (
        0 => 'sequence',
        1 => 
        array (
          0 => 
          array (
            0 => 'literal',
            1 => 'xs:string',
            2 => 'clean',
          ),
          1 => 
          array (
            0 => 'literal',
            1 => 'xs:string',
            2 => 'zero-emission',
          ),
          2 => 
          array (
            0 => 'literal',
            1 => 'xs:string',
            2 => 'other',
          ),
        ),
      ),
    ),
    25 => 
    array (
      'name' => 'isExtension',
      'value' => 'exists(/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID[text() = concat( \'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_\', $XR-MAJOR-MINOR-VERSION ,\'#conformant#urn:xeinkauf.de:kosit:extension:xrechnung_\', $XR-MAJOR-MINOR-VERSION) ] )',
      'ast' => 
      array (
        0 => 'call',
        1 => 'fn:exists',
        2 => 
        array (
          0 => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'root',
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                      2 => 'CrossIndustryInvoice',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                    2 => 'ExchangedDocumentContext',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'GuidelineSpecifiedDocumentContextParameter',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                2 => 'ID',
              ),
              3 => 
              array (
                0 => 
                array (
                  0 => 'gcmp',
                  1 => '=',
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'text',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'call',
                    1 => 'fn:concat',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:string',
                        2 => 'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_',
                      ),
                      1 => 
                      array (
                        0 => 'var',
                        1 => 'XR-MAJOR-MINOR-VERSION',
                      ),
                      2 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:string',
                        2 => '#conformant#urn:xeinkauf.de:kosit:extension:xrechnung_',
                      ),
                      3 => 
                      array (
                        0 => 'var',
                        1 => 'XR-MAJOR-MINOR-VERSION',
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    ),
    26 => 
    array (
      'name' => 'isCVD',
      'value' => 'rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID/text() = $XR-CVD-ID',
      'ast' => 
      array (
        0 => 'gcmp',
        1 => '=',
        2 => 
        array (
          0 => 'path',
          1 => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                    2 => 'CrossIndustryInvoice',
                  ),
                  3 => 
                  array (
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                    2 => 'ExchangedDocumentContext',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'GuidelineSpecifiedDocumentContextParameter',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                2 => 'ID',
              ),
              3 => 
              array (
              ),
            ),
          ),
          2 => 
          array (
            0 => 'step',
            1 => 'child',
            2 => 
            array (
              0 => 'text',
            ),
            3 => 
            array (
            ),
          ),
        ),
        3 => 
        array (
          0 => 'var',
          1 => 'XR-CVD-ID',
        ),
      ),
    ),
  ),
  'functions' => 
  array (
    '{utils}decimalOrZero#1' => 
    array (
      'name' => 'u:decimalOrZero',
      'as' => 'xs:decimal',
      'params' => 
      array (
        0 => 
        array (
          'name' => 'element',
          'as' => NULL,
        ),
      ),
      'body' => 
      array (
        0 => 
        array (
          0 => 'sequence',
          1 => 
          array (
            0 => 'if',
            1 => 
            array (
              0 => 'call',
              1 => 'fn:boolean',
              2 => 
              array (
                0 => 
                array (
                  0 => 'var',
                  1 => 'element',
                ),
              ),
            ),
            2 => 
            array (
              0 => 'call',
              1 => 'xs:decimal',
              2 => 
              array (
                0 => 
                array (
                  0 => 'var',
                  1 => 'element',
                ),
              ),
            ),
            3 => 
            array (
              0 => 'literal',
              1 => 'xs:integer',
              2 => '0',
            ),
          ),
        ),
      ),
    ),
    '{xrechnung}checkIBAN#1' => 
    array (
      'name' => 'xr:checkIBAN',
      'as' => 'xs:boolean',
      'params' => 
      array (
        0 => 
        array (
          'name' => 'iban',
          'as' => 'xs:string',
        ),
      ),
      'body' => 
      array (
        0 => 
        array (
          0 => 'variable',
          1 => 'normalizedIban',
          2 => NULL,
          3 => 
          array (
            0 => 'call',
            1 => 'fn:normalize-space',
            2 => 
            array (
              0 => 
              array (
                0 => 'call',
                1 => 'fn:replace',
                2 => 
                array (
                  0 => 
                  array (
                    0 => 'var',
                    1 => 'iban',
                  ),
                  1 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:string',
                    2 => '([\\s])',
                  ),
                  2 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:string',
                    2 => '',
                  ),
                ),
              ),
            ),
          ),
        ),
        1 => 
        array (
          0 => 'sequence',
          1 => 
          array (
            0 => 'and',
            1 => 
            array (
              0 => 'call',
              1 => 'fn:matches',
              2 => 
              array (
                0 => 
                array (
                  0 => 'var',
                  1 => 'normalizedIban',
                ),
                1 => 
                array (
                  0 => 'var',
                  1 => 'XR-IBAN-REGEX',
                ),
              ),
            ),
            2 => 
            array (
              0 => 'gcmp',
              1 => '=',
              2 => 
              array (
                0 => 'arith',
                1 => 'mod',
                2 => 
                array (
                  0 => 'call',
                  1 => 'xs:integer',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'call',
                      1 => 'fn:string-join',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'for',
                          1 => 
                          array (
                            0 => 
                            array (
                              0 => 'cp',
                              1 => 
                              array (
                                0 => 'call',
                                1 => 'fn:string-to-codepoints',
                                2 => 
                                array (
                                  0 => 
                                  array (
                                    0 => 'call',
                                    1 => 'fn:concat',
                                    2 => 
                                    array (
                                      0 => 
                                      array (
                                        0 => 'call',
                                        1 => 'fn:substring',
                                        2 => 
                                        array (
                                          0 => 
                                          array (
                                            0 => 'var',
                                            1 => 'normalizedIban',
                                          ),
                                          1 => 
                                          array (
                                            0 => 'literal',
                                            1 => 'xs:integer',
                                            2 => '5',
                                          ),
                                        ),
                                      ),
                                      1 => 
                                      array (
                                        0 => 'call',
                                        1 => 'fn:upper-case',
                                        2 => 
                                        array (
                                          0 => 
                                          array (
                                            0 => 'call',
                                            1 => 'fn:substring',
                                            2 => 
                                            array (
                                              0 => 
                                              array (
                                                0 => 'var',
                                                1 => 'normalizedIban',
                                              ),
                                              1 => 
                                              array (
                                                0 => 'literal',
                                                1 => 'xs:integer',
                                                2 => '1',
                                              ),
                                              2 => 
                                              array (
                                                0 => 'literal',
                                                1 => 'xs:integer',
                                                2 => '2',
                                              ),
                                            ),
                                          ),
                                        ),
                                      ),
                                      2 => 
                                      array (
                                        0 => 'call',
                                        1 => 'fn:substring',
                                        2 => 
                                        array (
                                          0 => 
                                          array (
                                            0 => 'var',
                                            1 => 'normalizedIban',
                                          ),
                                          1 => 
                                          array (
                                            0 => 'literal',
                                            1 => 'xs:integer',
                                            2 => '3',
                                          ),
                                          2 => 
                                          array (
                                            0 => 'literal',
                                            1 => 'xs:integer',
                                            2 => '2',
                                          ),
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'if',
                            1 => 
                            array (
                              0 => 'gcmp',
                              1 => '>',
                              2 => 
                              array (
                                0 => 'var',
                                1 => 'cp',
                              ),
                              3 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:integer',
                                2 => '64',
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'call',
                              1 => 'fn:string',
                              2 => 
                              array (
                                0 => 
                                array (
                                  0 => 'arith',
                                  1 => '-',
                                  2 => 
                                  array (
                                    0 => 'var',
                                    1 => 'cp',
                                  ),
                                  3 => 
                                  array (
                                    0 => 'literal',
                                    1 => 'xs:integer',
                                    2 => '55',
                                  ),
                                ),
                              ),
                            ),
                            3 => 
                            array (
                              0 => 'call',
                              1 => 'fn:string',
                              2 => 
                              array (
                                0 => 
                                array (
                                  0 => 'arith',
                                  1 => '-',
                                  2 => 
                                  array (
                                    0 => 'var',
                                    1 => 'cp',
                                  ),
                                  3 => 
                                  array (
                                    0 => 'literal',
                                    1 => 'xs:integer',
                                    2 => '48',
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ),
                        1 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => '',
                        ),
                      ),
                    ),
                  ),
                ),
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '97',
                ),
              ),
              3 => 
              array (
                0 => 'literal',
                1 => 'xs:integer',
                2 => '1',
              ),
            ),
          ),
        ),
      ),
    ),
    '{utils}gln#1' => 
    array (
      'name' => 'u:gln',
      'as' => 'xs:boolean',
      'params' => 
      array (
        0 => 
        array (
          'name' => 'val',
          'as' => NULL,
        ),
      ),
      'body' => 
      array (
        0 => 
        array (
          0 => 'variable',
          1 => 'length',
          2 => NULL,
          3 => 
          array (
            0 => 'arith',
            1 => '-',
            2 => 
            array (
              0 => 'call',
              1 => 'fn:string-length',
              2 => 
              array (
                0 => 
                array (
                  0 => 'var',
                  1 => 'val',
                ),
              ),
            ),
            3 => 
            array (
              0 => 'literal',
              1 => 'xs:integer',
              2 => '1',
            ),
          ),
        ),
        1 => 
        array (
          0 => 'variable',
          1 => 'digits',
          2 => NULL,
          3 => 
          array (
            0 => 'call',
            1 => 'fn:reverse',
            2 => 
            array (
              0 => 
              array (
                0 => 'for',
                1 => 
                array (
                  0 => 
                  array (
                    0 => 'i',
                    1 => 
                    array (
                      0 => 'call',
                      1 => 'fn:string-to-codepoints',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'call',
                          1 => 'fn:substring',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'var',
                              1 => 'val',
                            ),
                            1 => 
                            array (
                              0 => 'literal',
                              1 => 'xs:integer',
                              2 => '0',
                            ),
                            2 => 
                            array (
                              0 => 'arith',
                              1 => '+',
                              2 => 
                              array (
                                0 => 'var',
                                1 => 'length',
                              ),
                              3 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:integer',
                                2 => '1',
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'arith',
                  1 => '-',
                  2 => 
                  array (
                    0 => 'var',
                    1 => 'i',
                  ),
                  3 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:integer',
                    2 => '48',
                  ),
                ),
              ),
            ),
          ),
        ),
        2 => 
        array (
          0 => 'variable',
          1 => 'weightedSum',
          2 => NULL,
          3 => 
          array (
            0 => 'call',
            1 => 'fn:sum',
            2 => 
            array (
              0 => 
              array (
                0 => 'for',
                1 => 
                array (
                  0 => 
                  array (
                    0 => 'i',
                    1 => 
                    array (
                      0 => 'range',
                      1 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:integer',
                        2 => '0',
                      ),
                      2 => 
                      array (
                        0 => 'arith',
                        1 => '-',
                        2 => 
                        array (
                          0 => 'var',
                          1 => 'length',
                        ),
                        3 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:integer',
                          2 => '1',
                        ),
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'arith',
                  1 => '*',
                  2 => 
                  array (
                    0 => 'filter',
                    1 => 
                    array (
                      0 => 'var',
                      1 => 'digits',
                    ),
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'arith',
                        1 => '+',
                        2 => 
                        array (
                          0 => 'var',
                          1 => 'i',
                        ),
                        3 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:integer',
                          2 => '1',
                        ),
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'arith',
                    1 => '+',
                    2 => 
                    array (
                      0 => 'literal',
                      1 => 'xs:integer',
                      2 => '1',
                    ),
                    3 => 
                    array (
                      0 => 'arith',
                      1 => '*',
                      2 => 
                      array (
                        0 => 'arith',
                        1 => 'mod',
                        2 => 
                        array (
                          0 => 'arith',
                          1 => '+',
                          2 => 
                          array (
                            0 => 'var',
                            1 => 'i',
                          ),
                          3 => 
                          array (
                            0 => 'literal',
                            1 => 'xs:integer',
                            2 => '1',
                          ),
                        ),
                        3 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:integer',
                          2 => '2',
                        ),
                      ),
                      3 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:integer',
                        2 => '2',
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
        3 => 
        array (
          0 => 'sequence',
          1 => 
          array (
            0 => 'gcmp',
            1 => '=',
            2 => 
            array (
              0 => 'arith',
              1 => 'mod',
              2 => 
              array (
                0 => 'arith',
                1 => '-',
                2 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '10',
                ),
                3 => 
                array (
                  0 => 'arith',
                  1 => 'mod',
                  2 => 
                  array (
                    0 => 'var',
                    1 => 'weightedSum',
                  ),
                  3 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:integer',
                    2 => '10',
                  ),
                ),
              ),
              3 => 
              array (
                0 => 'literal',
                1 => 'xs:integer',
                2 => '10',
              ),
            ),
            3 => 
            array (
              0 => 'call',
              1 => 'fn:number',
              2 => 
              array (
                0 => 
                array (
                  0 => 'call',
                  1 => 'fn:substring',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'var',
                      1 => 'val',
                    ),
                    1 => 
                    array (
                      0 => 'arith',
                      1 => '+',
                      2 => 
                      array (
                        0 => 'var',
                        1 => 'length',
                      ),
                      3 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:integer',
                        2 => '1',
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'literal',
                      1 => 'xs:integer',
                      2 => '1',
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    ),
    '{utils}slack#3' => 
    array (
      'name' => 'u:slack',
      'as' => 'xs:boolean',
      'params' => 
      array (
        0 => 
        array (
          'name' => 'exp',
          'as' => 'xs:decimal',
        ),
        1 => 
        array (
          'name' => 'val',
          'as' => 'xs:decimal',
        ),
        2 => 
        array (
          'name' => 'slack',
          'as' => 'xs:decimal',
        ),
      ),
      'body' => 
      array (
        0 => 
        array (
          0 => 'sequence',
          1 => 
          array (
            0 => 'and',
            1 => 
            array (
              0 => 'gcmp',
              1 => '>=',
              2 => 
              array (
                0 => 'call',
                1 => 'xs:decimal',
                2 => 
                array (
                  0 => 
                  array (
                    0 => 'arith',
                    1 => '+',
                    2 => 
                    array (
                      0 => 'var',
                      1 => 'exp',
                    ),
                    3 => 
                    array (
                      0 => 'var',
                      1 => 'slack',
                    ),
                  ),
                ),
              ),
              3 => 
              array (
                0 => 'var',
                1 => 'val',
              ),
            ),
            2 => 
            array (
              0 => 'gcmp',
              1 => '<=',
              2 => 
              array (
                0 => 'call',
                1 => 'xs:decimal',
                2 => 
                array (
                  0 => 
                  array (
                    0 => 'arith',
                    1 => '-',
                    2 => 
                    array (
                      0 => 'var',
                      1 => 'exp',
                    ),
                    3 => 
                    array (
                      0 => 'var',
                      1 => 'slack',
                    ),
                  ),
                ),
              ),
              3 => 
              array (
                0 => 'var',
                1 => 'val',
              ),
            ),
          ),
        ),
      ),
    ),
    '{utils}mod11#1' => 
    array (
      'name' => 'u:mod11',
      'as' => 'xs:boolean',
      'params' => 
      array (
        0 => 
        array (
          'name' => 'val',
          'as' => NULL,
        ),
      ),
      'body' => 
      array (
        0 => 
        array (
          0 => 'variable',
          1 => 'length',
          2 => NULL,
          3 => 
          array (
            0 => 'arith',
            1 => '-',
            2 => 
            array (
              0 => 'call',
              1 => 'fn:string-length',
              2 => 
              array (
                0 => 
                array (
                  0 => 'var',
                  1 => 'val',
                ),
              ),
            ),
            3 => 
            array (
              0 => 'literal',
              1 => 'xs:integer',
              2 => '1',
            ),
          ),
        ),
        1 => 
        array (
          0 => 'variable',
          1 => 'digits',
          2 => NULL,
          3 => 
          array (
            0 => 'call',
            1 => 'fn:reverse',
            2 => 
            array (
              0 => 
              array (
                0 => 'for',
                1 => 
                array (
                  0 => 
                  array (
                    0 => 'i',
                    1 => 
                    array (
                      0 => 'call',
                      1 => 'fn:string-to-codepoints',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'call',
                          1 => 'fn:substring',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'var',
                              1 => 'val',
                            ),
                            1 => 
                            array (
                              0 => 'literal',
                              1 => 'xs:integer',
                              2 => '0',
                            ),
                            2 => 
                            array (
                              0 => 'arith',
                              1 => '+',
                              2 => 
                              array (
                                0 => 'var',
                                1 => 'length',
                              ),
                              3 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:integer',
                                2 => '1',
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'arith',
                  1 => '-',
                  2 => 
                  array (
                    0 => 'var',
                    1 => 'i',
                  ),
                  3 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:integer',
                    2 => '48',
                  ),
                ),
              ),
            ),
          ),
        ),
        2 => 
        array (
          0 => 'variable',
          1 => 'weightedSum',
          2 => NULL,
          3 => 
          array (
            0 => 'call',
            1 => 'fn:sum',
            2 => 
            array (
              0 => 
              array (
                0 => 'for',
                1 => 
                array (
                  0 => 
                  array (
                    0 => 'i',
                    1 => 
                    array (
                      0 => 'range',
                      1 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:integer',
                        2 => '0',
                      ),
                      2 => 
                      array (
                        0 => 'arith',
                        1 => '-',
                        2 => 
                        array (
                          0 => 'var',
                          1 => 'length',
                        ),
                        3 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:integer',
                          2 => '1',
                        ),
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'arith',
                  1 => '*',
                  2 => 
                  array (
                    0 => 'filter',
                    1 => 
                    array (
                      0 => 'var',
                      1 => 'digits',
                    ),
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'arith',
                        1 => '+',
                        2 => 
                        array (
                          0 => 'var',
                          1 => 'i',
                        ),
                        3 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:integer',
                          2 => '1',
                        ),
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'arith',
                    1 => '+',
                    2 => 
                    array (
                      0 => 'arith',
                      1 => 'mod',
                      2 => 
                      array (
                        0 => 'var',
                        1 => 'i',
                      ),
                      3 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:integer',
                        2 => '6',
                      ),
                    ),
                    3 => 
                    array (
                      0 => 'literal',
                      1 => 'xs:integer',
                      2 => '2',
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
        3 => 
        array (
          0 => 'sequence',
          1 => 
          array (
            0 => 'and',
            1 => 
            array (
              0 => 'gcmp',
              1 => '>',
              2 => 
              array (
                0 => 'call',
                1 => 'fn:number',
                2 => 
                array (
                  0 => 
                  array (
                    0 => 'var',
                    1 => 'val',
                  ),
                ),
              ),
              3 => 
              array (
                0 => 'literal',
                1 => 'xs:integer',
                2 => '0',
              ),
            ),
            2 => 
            array (
              0 => 'gcmp',
              1 => '=',
              2 => 
              array (
                0 => 'arith',
                1 => 'mod',
                2 => 
                array (
                  0 => 'arith',
                  1 => '-',
                  2 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:integer',
                    2 => '11',
                  ),
                  3 => 
                  array (
                    0 => 'arith',
                    1 => 'mod',
                    2 => 
                    array (
                      0 => 'var',
                      1 => 'weightedSum',
                    ),
                    3 => 
                    array (
                      0 => 'literal',
                      1 => 'xs:integer',
                      2 => '11',
                    ),
                  ),
                ),
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '11',
                ),
              ),
              3 => 
              array (
                0 => 'call',
                1 => 'fn:number',
                2 => 
                array (
                  0 => 
                  array (
                    0 => 'call',
                    1 => 'fn:substring',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'var',
                        1 => 'val',
                      ),
                      1 => 
                      array (
                        0 => 'arith',
                        1 => '+',
                        2 => 
                        array (
                          0 => 'var',
                          1 => 'length',
                        ),
                        3 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:integer',
                          2 => '1',
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:integer',
                        2 => '1',
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    ),
    '{utils}mod97-0208#1' => 
    array (
      'name' => 'u:mod97-0208',
      'as' => 'xs:boolean',
      'params' => 
      array (
        0 => 
        array (
          'name' => 'val',
          'as' => NULL,
        ),
      ),
      'body' => 
      array (
        0 => 
        array (
          0 => 'variable',
          1 => 'checkdigits',
          2 => NULL,
          3 => 
          array (
            0 => 'call',
            1 => 'fn:substring',
            2 => 
            array (
              0 => 
              array (
                0 => 'var',
                1 => 'val',
              ),
              1 => 
              array (
                0 => 'literal',
                1 => 'xs:integer',
                2 => '9',
              ),
              2 => 
              array (
                0 => 'literal',
                1 => 'xs:integer',
                2 => '2',
              ),
            ),
          ),
        ),
        1 => 
        array (
          0 => 'variable',
          1 => 'calculated_digits',
          2 => NULL,
          3 => 
          array (
            0 => 'call',
            1 => 'xs:string',
            2 => 
            array (
              0 => 
              array (
                0 => 'arith',
                1 => '-',
                2 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '97',
                ),
                3 => 
                array (
                  0 => 'arith',
                  1 => 'mod',
                  2 => 
                  array (
                    0 => 'call',
                    1 => 'xs:integer',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'call',
                        1 => 'fn:substring',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'var',
                            1 => 'val',
                          ),
                          1 => 
                          array (
                            0 => 'literal',
                            1 => 'xs:integer',
                            2 => '1',
                          ),
                          2 => 
                          array (
                            0 => 'literal',
                            1 => 'xs:integer',
                            2 => '8',
                          ),
                        ),
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:integer',
                    2 => '97',
                  ),
                ),
              ),
            ),
          ),
        ),
        2 => 
        array (
          0 => 'sequence',
          1 => 
          array (
            0 => 'gcmp',
            1 => '=',
            2 => 
            array (
              0 => 'call',
              1 => 'fn:number',
              2 => 
              array (
                0 => 
                array (
                  0 => 'var',
                  1 => 'checkdigits',
                ),
              ),
            ),
            3 => 
            array (
              0 => 'call',
              1 => 'fn:number',
              2 => 
              array (
                0 => 
                array (
                  0 => 'var',
                  1 => 'calculated_digits',
                ),
              ),
            ),
          ),
        ),
      ),
    ),
    '{utils}checkCodiceIPA#1' => 
    array (
      'name' => 'u:checkCodiceIPA',
      'as' => 'xs:boolean',
      'params' => 
      array (
        0 => 
        array (
          'name' => 'arg',
          'as' => 'xs:string?',
        ),
      ),
      'body' => 
      array (
        0 => 
        array (
          0 => 'variable-text',
          1 => 'allowed-characters',
          2 => NULL,
          3 => 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',
        ),
        1 => 
        array (
          0 => 'sequence',
          1 => 
          array (
            0 => 'if',
            1 => 
            array (
              0 => 'and',
              1 => 
              array (
                0 => 'gcmp',
                1 => '=',
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:string-length',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'call',
                      1 => 'fn:translate',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'var',
                          1 => 'arg',
                        ),
                        1 => 
                        array (
                          0 => 'var',
                          1 => 'allowed-characters',
                        ),
                        2 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => '',
                        ),
                      ),
                    ),
                  ),
                ),
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '0',
                ),
              ),
              2 => 
              array (
                0 => 'gcmp',
                1 => '=',
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:string-length',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'var',
                      1 => 'arg',
                    ),
                  ),
                ),
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '6',
                ),
              ),
            ),
            2 => 
            array (
              0 => 'call',
              1 => 'fn:true',
              2 => 
              array (
              ),
            ),
            3 => 
            array (
              0 => 'call',
              1 => 'fn:false',
              2 => 
              array (
              ),
            ),
          ),
        ),
      ),
    ),
    '{utils}checkCF#1' => 
    array (
      'name' => 'u:checkCF',
      'as' => 'xs:boolean',
      'params' => 
      array (
        0 => 
        array (
          'name' => 'arg',
          'as' => 'xs:string?',
        ),
      ),
      'body' => 
      array (
        0 => 
        array (
          0 => 'sequence',
          1 => 
          array (
            0 => 'if',
            1 => 
            array (
              0 => 'or',
              1 => 
              array (
                0 => 'gcmp',
                1 => '=',
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:string-length',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'var',
                      1 => 'arg',
                    ),
                  ),
                ),
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '16',
                ),
              ),
              2 => 
              array (
                0 => 'gcmp',
                1 => '=',
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:string-length',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'var',
                      1 => 'arg',
                    ),
                  ),
                ),
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '11',
                ),
              ),
            ),
            2 => 
            array (
              0 => 'if',
              1 => 
              array (
                0 => 'gcmp',
                1 => '=',
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:string-length',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'var',
                      1 => 'arg',
                    ),
                  ),
                ),
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '16',
                ),
              ),
              2 => 
              array (
                0 => 'if',
                1 => 
                array (
                  0 => 'ucall',
                  1 => 'utils',
                  2 => 'checkCF16',
                  3 => 
                  array (
                    0 => 
                    array (
                      0 => 'var',
                      1 => 'arg',
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:true',
                  2 => 
                  array (
                  ),
                ),
                3 => 
                array (
                  0 => 'call',
                  1 => 'fn:false',
                  2 => 
                  array (
                  ),
                ),
              ),
              3 => 
              array (
                0 => 'if',
                1 => 
                array (
                  0 => 'castable',
                  1 => 'xs:integer',
                  2 => false,
                  3 => 
                  array (
                    0 => 'var',
                    1 => 'arg',
                  ),
                ),
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:true',
                  2 => 
                  array (
                  ),
                ),
                3 => 
                array (
                  0 => 'call',
                  1 => 'fn:false',
                  2 => 
                  array (
                  ),
                ),
              ),
            ),
            3 => 
            array (
              0 => 'call',
              1 => 'fn:false',
              2 => 
              array (
              ),
            ),
          ),
        ),
      ),
    ),
    '{utils}checkCF16#1' => 
    array (
      'name' => 'u:checkCF16',
      'as' => 'xs:boolean',
      'params' => 
      array (
        0 => 
        array (
          'name' => 'arg',
          'as' => 'xs:string?',
        ),
      ),
      'body' => 
      array (
        0 => 
        array (
          0 => 'variable-text',
          1 => 'allowed-characters',
          2 => NULL,
          3 => 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz',
        ),
        1 => 
        array (
          0 => 'sequence',
          1 => 
          array (
            0 => 'if',
            1 => 
            array (
              0 => 'and',
              1 => 
              array (
                0 => 'and',
                1 => 
                array (
                  0 => 'and',
                  1 => 
                  array (
                    0 => 'and',
                    1 => 
                    array (
                      0 => 'and',
                      1 => 
                      array (
                        0 => 'and',
                        1 => 
                        array (
                          0 => 'gcmp',
                          1 => '=',
                          2 => 
                          array (
                            0 => 'call',
                            1 => 'fn:string-length',
                            2 => 
                            array (
                              0 => 
                              array (
                                0 => 'call',
                                1 => 'fn:translate',
                                2 => 
                                array (
                                  0 => 
                                  array (
                                    0 => 'call',
                                    1 => 'fn:substring',
                                    2 => 
                                    array (
                                      0 => 
                                      array (
                                        0 => 'var',
                                        1 => 'arg',
                                      ),
                                      1 => 
                                      array (
                                        0 => 'literal',
                                        1 => 'xs:integer',
                                        2 => '1',
                                      ),
                                      2 => 
                                      array (
                                        0 => 'literal',
                                        1 => 'xs:integer',
                                        2 => '6',
                                      ),
                                    ),
                                  ),
                                  1 => 
                                  array (
                                    0 => 'var',
                                    1 => 'allowed-characters',
                                  ),
                                  2 => 
                                  array (
                                    0 => 'literal',
                                    1 => 'xs:string',
                                    2 => '',
                                  ),
                                ),
                              ),
                            ),
                          ),
                          3 => 
                          array (
                            0 => 'literal',
                            1 => 'xs:integer',
                            2 => '0',
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'castable',
                          1 => 'xs:integer',
                          2 => false,
                          3 => 
                          array (
                            0 => 'call',
                            1 => 'fn:substring',
                            2 => 
                            array (
                              0 => 
                              array (
                                0 => 'var',
                                1 => 'arg',
                              ),
                              1 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:integer',
                                2 => '7',
                              ),
                              2 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:integer',
                                2 => '2',
                              ),
                            ),
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'gcmp',
                        1 => '=',
                        2 => 
                        array (
                          0 => 'call',
                          1 => 'fn:string-length',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'call',
                              1 => 'fn:translate',
                              2 => 
                              array (
                                0 => 
                                array (
                                  0 => 'call',
                                  1 => 'fn:substring',
                                  2 => 
                                  array (
                                    0 => 
                                    array (
                                      0 => 'var',
                                      1 => 'arg',
                                    ),
                                    1 => 
                                    array (
                                      0 => 'literal',
                                      1 => 'xs:integer',
                                      2 => '9',
                                    ),
                                    2 => 
                                    array (
                                      0 => 'literal',
                                      1 => 'xs:integer',
                                      2 => '1',
                                    ),
                                  ),
                                ),
                                1 => 
                                array (
                                  0 => 'var',
                                  1 => 'allowed-characters',
                                ),
                                2 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:string',
                                  2 => '',
                                ),
                              ),
                            ),
                          ),
                        ),
                        3 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:integer',
                          2 => '0',
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'castable',
                      1 => 'xs:integer',
                      2 => false,
                      3 => 
                      array (
                        0 => 'call',
                        1 => 'fn:substring',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'var',
                            1 => 'arg',
                          ),
                          1 => 
                          array (
                            0 => 'literal',
                            1 => 'xs:integer',
                            2 => '10',
                          ),
                          2 => 
                          array (
                            0 => 'literal',
                            1 => 'xs:integer',
                            2 => '2',
                          ),
                        ),
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'castable',
                    1 => 'xs:string',
                    2 => false,
                    3 => 
                    array (
                      0 => 'call',
                      1 => 'fn:substring',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'var',
                          1 => 'arg',
                        ),
                        1 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:integer',
                          2 => '12',
                        ),
                        2 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:integer',
                          2 => '3',
                        ),
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'castable',
                  1 => 'xs:integer',
                  2 => false,
                  3 => 
                  array (
                    0 => 'call',
                    1 => 'fn:substring',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'var',
                        1 => 'arg',
                      ),
                      1 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:integer',
                        2 => '15',
                      ),
                      2 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:integer',
                        2 => '1',
                      ),
                    ),
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'gcmp',
                1 => '=',
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:string-length',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'call',
                      1 => 'fn:translate',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'call',
                          1 => 'fn:substring',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'var',
                              1 => 'arg',
                            ),
                            1 => 
                            array (
                              0 => 'literal',
                              1 => 'xs:integer',
                              2 => '16',
                            ),
                            2 => 
                            array (
                              0 => 'literal',
                              1 => 'xs:integer',
                              2 => '1',
                            ),
                          ),
                        ),
                        1 => 
                        array (
                          0 => 'var',
                          1 => 'allowed-characters',
                        ),
                        2 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => '',
                        ),
                      ),
                    ),
                  ),
                ),
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '0',
                ),
              ),
            ),
            2 => 
            array (
              0 => 'call',
              1 => 'fn:true',
              2 => 
              array (
              ),
            ),
            3 => 
            array (
              0 => 'call',
              1 => 'fn:false',
              2 => 
              array (
              ),
            ),
          ),
        ),
      ),
    ),
    '{utils}checkPIVAseIT#1' => 
    array (
      'name' => 'u:checkPIVAseIT',
      'as' => 'xs:boolean',
      'params' => 
      array (
        0 => 
        array (
          'name' => 'arg',
          'as' => 'xs:string',
        ),
      ),
      'body' => 
      array (
        0 => 
        array (
          0 => 'variable',
          1 => 'paese',
          2 => NULL,
          3 => 
          array (
            0 => 'call',
            1 => 'fn:substring',
            2 => 
            array (
              0 => 
              array (
                0 => 'var',
                1 => 'arg',
              ),
              1 => 
              array (
                0 => 'literal',
                1 => 'xs:integer',
                2 => '1',
              ),
              2 => 
              array (
                0 => 'literal',
                1 => 'xs:integer',
                2 => '2',
              ),
            ),
          ),
        ),
        1 => 
        array (
          0 => 'variable',
          1 => 'codice',
          2 => NULL,
          3 => 
          array (
            0 => 'call',
            1 => 'fn:substring',
            2 => 
            array (
              0 => 
              array (
                0 => 'var',
                1 => 'arg',
              ),
              1 => 
              array (
                0 => 'literal',
                1 => 'xs:integer',
                2 => '3',
              ),
            ),
          ),
        ),
        2 => 
        array (
          0 => 'sequence',
          1 => 
          array (
            0 => 'if',
            1 => 
            array (
              0 => 'or',
              1 => 
              array (
                0 => 'gcmp',
                1 => '=',
                2 => 
                array (
                  0 => 'var',
                  1 => 'paese',
                ),
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:string',
                  2 => 'IT',
                ),
              ),
              2 => 
              array (
                0 => 'gcmp',
                1 => '=',
                2 => 
                array (
                  0 => 'var',
                  1 => 'paese',
                ),
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:string',
                  2 => 'it',
                ),
              ),
            ),
            2 => 
            array (
              0 => 'if',
              1 => 
              array (
                0 => 'and',
                1 => 
                array (
                  0 => 'gcmp',
                  1 => '=',
                  2 => 
                  array (
                    0 => 'call',
                    1 => 'fn:string-length',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'var',
                        1 => 'codice',
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:integer',
                    2 => '11',
                  ),
                ),
                2 => 
                array (
                  0 => 'if',
                  1 => 
                  array (
                    0 => 'gcmp',
                    1 => '!=',
                    2 => 
                    array (
                      0 => 'ucall',
                      1 => 'utils',
                      2 => 'checkPIVA',
                      3 => 
                      array (
                        0 => 
                        array (
                          0 => 'var',
                          1 => 'codice',
                        ),
                      ),
                    ),
                    3 => 
                    array (
                      0 => 'literal',
                      1 => 'xs:integer',
                      2 => '0',
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'call',
                    1 => 'fn:false',
                    2 => 
                    array (
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'call',
                    1 => 'fn:true',
                    2 => 
                    array (
                    ),
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'call',
                1 => 'fn:true',
                2 => 
                array (
                ),
              ),
              3 => 
              array (
                0 => 'call',
                1 => 'fn:false',
                2 => 
                array (
                ),
              ),
            ),
            3 => 
            array (
              0 => 'call',
              1 => 'fn:true',
              2 => 
              array (
              ),
            ),
          ),
        ),
      ),
    ),
    '{utils}checkPIVA#1' => 
    array (
      'name' => 'u:checkPIVA',
      'as' => 'xs:integer',
      'params' => 
      array (
        0 => 
        array (
          'name' => 'arg',
          'as' => 'xs:string?',
        ),
      ),
      'body' => 
      array (
        0 => 
        array (
          0 => 'sequence',
          1 => 
          array (
            0 => 'if',
            1 => 
            array (
              0 => 'call',
              1 => 'fn:not',
              2 => 
              array (
                0 => 
                array (
                  0 => 'castable',
                  1 => 'xs:integer',
                  2 => false,
                  3 => 
                  array (
                    0 => 'var',
                    1 => 'arg',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              0 => 'literal',
              1 => 'xs:integer',
              2 => '1',
            ),
            3 => 
            array (
              0 => 'arith',
              1 => 'mod',
              2 => 
              array (
                0 => 'ucall',
                1 => 'utils',
                2 => 'addPIVA',
                3 => 
                array (
                  0 => 
                  array (
                    0 => 'var',
                    1 => 'arg',
                  ),
                  1 => 
                  array (
                    0 => 'call',
                    1 => 'xs:integer',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:integer',
                        2 => '0',
                      ),
                    ),
                  ),
                ),
              ),
              3 => 
              array (
                0 => 'literal',
                1 => 'xs:integer',
                2 => '10',
              ),
            ),
          ),
        ),
      ),
    ),
    '{utils}addPIVA#2' => 
    array (
      'name' => 'u:addPIVA',
      'as' => 'xs:integer',
      'params' => 
      array (
        0 => 
        array (
          'name' => 'arg',
          'as' => 'xs:string',
        ),
        1 => 
        array (
          'name' => 'pari',
          'as' => 'xs:integer',
        ),
      ),
      'body' => 
      array (
        0 => 
        array (
          0 => 'variable',
          1 => 'tappo',
          2 => NULL,
          3 => 
          array (
            0 => 'if',
            1 => 
            array (
              0 => 'call',
              1 => 'fn:not',
              2 => 
              array (
                0 => 
                array (
                  0 => 'castable',
                  1 => 'xs:integer',
                  2 => false,
                  3 => 
                  array (
                    0 => 'var',
                    1 => 'arg',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              0 => 'literal',
              1 => 'xs:integer',
              2 => '0',
            ),
            3 => 
            array (
              0 => 'literal',
              1 => 'xs:integer',
              2 => '1',
            ),
          ),
        ),
        1 => 
        array (
          0 => 'variable',
          1 => 'mapper',
          2 => NULL,
          3 => 
          array (
            0 => 'if',
            1 => 
            array (
              0 => 'gcmp',
              1 => '=',
              2 => 
              array (
                0 => 'var',
                1 => 'tappo',
              ),
              3 => 
              array (
                0 => 'literal',
                1 => 'xs:integer',
                2 => '0',
              ),
            ),
            2 => 
            array (
              0 => 'literal',
              1 => 'xs:integer',
              2 => '0',
            ),
            3 => 
            array (
              0 => 'if',
              1 => 
              array (
                0 => 'gcmp',
                1 => '=',
                2 => 
                array (
                  0 => 'var',
                  1 => 'pari',
                ),
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '1',
                ),
              ),
              2 => 
              array (
                0 => 'call',
                1 => 'xs:integer',
                2 => 
                array (
                  0 => 
                  array (
                    0 => 'call',
                    1 => 'fn:substring',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:string',
                        2 => '0246813579',
                      ),
                      1 => 
                      array (
                        0 => 'arith',
                        1 => '+',
                        2 => 
                        array (
                          0 => 'call',
                          1 => 'xs:integer',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'call',
                              1 => 'fn:substring',
                              2 => 
                              array (
                                0 => 
                                array (
                                  0 => 'var',
                                  1 => 'arg',
                                ),
                                1 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:integer',
                                  2 => '1',
                                ),
                                2 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:integer',
                                  2 => '1',
                                ),
                              ),
                            ),
                          ),
                        ),
                        3 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:integer',
                          2 => '1',
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:integer',
                        2 => '1',
                      ),
                    ),
                  ),
                ),
              ),
              3 => 
              array (
                0 => 'call',
                1 => 'xs:integer',
                2 => 
                array (
                  0 => 
                  array (
                    0 => 'call',
                    1 => 'fn:substring',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'var',
                        1 => 'arg',
                      ),
                      1 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:integer',
                        2 => '1',
                      ),
                      2 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:integer',
                        2 => '1',
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
        2 => 
        array (
          0 => 'sequence',
          1 => 
          array (
            0 => 'if',
            1 => 
            array (
              0 => 'gcmp',
              1 => '=',
              2 => 
              array (
                0 => 'var',
                1 => 'tappo',
              ),
              3 => 
              array (
                0 => 'literal',
                1 => 'xs:integer',
                2 => '0',
              ),
            ),
            2 => 
            array (
              0 => 'var',
              1 => 'mapper',
            ),
            3 => 
            array (
              0 => 'arith',
              1 => '+',
              2 => 
              array (
                0 => 'call',
                1 => 'xs:integer',
                2 => 
                array (
                  0 => 
                  array (
                    0 => 'var',
                    1 => 'mapper',
                  ),
                ),
              ),
              3 => 
              array (
                0 => 'ucall',
                1 => 'utils',
                2 => 'addPIVA',
                3 => 
                array (
                  0 => 
                  array (
                    0 => 'call',
                    1 => 'fn:substring',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'call',
                        1 => 'xs:string',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'var',
                            1 => 'arg',
                          ),
                        ),
                      ),
                      1 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:integer',
                        2 => '2',
                      ),
                    ),
                  ),
                  1 => 
                  array (
                    0 => 'if',
                    1 => 
                    array (
                      0 => 'gcmp',
                      1 => '=',
                      2 => 
                      array (
                        0 => 'var',
                        1 => 'pari',
                      ),
                      3 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:integer',
                        2 => '0',
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'literal',
                      1 => 'xs:integer',
                      2 => '1',
                    ),
                    3 => 
                    array (
                      0 => 'literal',
                      1 => 'xs:integer',
                      2 => '0',
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    ),
    '{utils}abn#1' => 
    array (
      'name' => 'u:abn',
      'as' => 'xs:boolean',
      'params' => 
      array (
        0 => 
        array (
          'name' => 'val',
          'as' => NULL,
        ),
      ),
      'body' => 
      array (
        0 => 
        array (
          0 => 'sequence',
          1 => 
          array (
            0 => 'gcmp',
            1 => '=',
            2 => 
            array (
              0 => 'arith',
              1 => 'mod',
              2 => 
              array (
                0 => 'arith',
                1 => '+',
                2 => 
                array (
                  0 => 'arith',
                  1 => '+',
                  2 => 
                  array (
                    0 => 'arith',
                    1 => '+',
                    2 => 
                    array (
                      0 => 'arith',
                      1 => '+',
                      2 => 
                      array (
                        0 => 'arith',
                        1 => '+',
                        2 => 
                        array (
                          0 => 'arith',
                          1 => '+',
                          2 => 
                          array (
                            0 => 'arith',
                            1 => '+',
                            2 => 
                            array (
                              0 => 'arith',
                              1 => '+',
                              2 => 
                              array (
                                0 => 'arith',
                                1 => '+',
                                2 => 
                                array (
                                  0 => 'arith',
                                  1 => '+',
                                  2 => 
                                  array (
                                    0 => 'arith',
                                    1 => '*',
                                    2 => 
                                    array (
                                      0 => 'arith',
                                      1 => '-',
                                      2 => 
                                      array (
                                        0 => 'call',
                                        1 => 'fn:string-to-codepoints',
                                        2 => 
                                        array (
                                          0 => 
                                          array (
                                            0 => 'call',
                                            1 => 'fn:substring',
                                            2 => 
                                            array (
                                              0 => 
                                              array (
                                                0 => 'var',
                                                1 => 'val',
                                              ),
                                              1 => 
                                              array (
                                                0 => 'literal',
                                                1 => 'xs:integer',
                                                2 => '1',
                                              ),
                                              2 => 
                                              array (
                                                0 => 'literal',
                                                1 => 'xs:integer',
                                                2 => '1',
                                              ),
                                            ),
                                          ),
                                        ),
                                      ),
                                      3 => 
                                      array (
                                        0 => 'literal',
                                        1 => 'xs:integer',
                                        2 => '49',
                                      ),
                                    ),
                                    3 => 
                                    array (
                                      0 => 'literal',
                                      1 => 'xs:integer',
                                      2 => '10',
                                    ),
                                  ),
                                  3 => 
                                  array (
                                    0 => 'arith',
                                    1 => '*',
                                    2 => 
                                    array (
                                      0 => 'arith',
                                      1 => '-',
                                      2 => 
                                      array (
                                        0 => 'call',
                                        1 => 'fn:string-to-codepoints',
                                        2 => 
                                        array (
                                          0 => 
                                          array (
                                            0 => 'call',
                                            1 => 'fn:substring',
                                            2 => 
                                            array (
                                              0 => 
                                              array (
                                                0 => 'var',
                                                1 => 'val',
                                              ),
                                              1 => 
                                              array (
                                                0 => 'literal',
                                                1 => 'xs:integer',
                                                2 => '2',
                                              ),
                                              2 => 
                                              array (
                                                0 => 'literal',
                                                1 => 'xs:integer',
                                                2 => '1',
                                              ),
                                            ),
                                          ),
                                        ),
                                      ),
                                      3 => 
                                      array (
                                        0 => 'literal',
                                        1 => 'xs:integer',
                                        2 => '48',
                                      ),
                                    ),
                                    3 => 
                                    array (
                                      0 => 'literal',
                                      1 => 'xs:integer',
                                      2 => '1',
                                    ),
                                  ),
                                ),
                                3 => 
                                array (
                                  0 => 'arith',
                                  1 => '*',
                                  2 => 
                                  array (
                                    0 => 'arith',
                                    1 => '-',
                                    2 => 
                                    array (
                                      0 => 'call',
                                      1 => 'fn:string-to-codepoints',
                                      2 => 
                                      array (
                                        0 => 
                                        array (
                                          0 => 'call',
                                          1 => 'fn:substring',
                                          2 => 
                                          array (
                                            0 => 
                                            array (
                                              0 => 'var',
                                              1 => 'val',
                                            ),
                                            1 => 
                                            array (
                                              0 => 'literal',
                                              1 => 'xs:integer',
                                              2 => '3',
                                            ),
                                            2 => 
                                            array (
                                              0 => 'literal',
                                              1 => 'xs:integer',
                                              2 => '1',
                                            ),
                                          ),
                                        ),
                                      ),
                                    ),
                                    3 => 
                                    array (
                                      0 => 'literal',
                                      1 => 'xs:integer',
                                      2 => '48',
                                    ),
                                  ),
                                  3 => 
                                  array (
                                    0 => 'literal',
                                    1 => 'xs:integer',
                                    2 => '3',
                                  ),
                                ),
                              ),
                              3 => 
                              array (
                                0 => 'arith',
                                1 => '*',
                                2 => 
                                array (
                                  0 => 'arith',
                                  1 => '-',
                                  2 => 
                                  array (
                                    0 => 'call',
                                    1 => 'fn:string-to-codepoints',
                                    2 => 
                                    array (
                                      0 => 
                                      array (
                                        0 => 'call',
                                        1 => 'fn:substring',
                                        2 => 
                                        array (
                                          0 => 
                                          array (
                                            0 => 'var',
                                            1 => 'val',
                                          ),
                                          1 => 
                                          array (
                                            0 => 'literal',
                                            1 => 'xs:integer',
                                            2 => '4',
                                          ),
                                          2 => 
                                          array (
                                            0 => 'literal',
                                            1 => 'xs:integer',
                                            2 => '1',
                                          ),
                                        ),
                                      ),
                                    ),
                                  ),
                                  3 => 
                                  array (
                                    0 => 'literal',
                                    1 => 'xs:integer',
                                    2 => '48',
                                  ),
                                ),
                                3 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:integer',
                                  2 => '5',
                                ),
                              ),
                            ),
                            3 => 
                            array (
                              0 => 'arith',
                              1 => '*',
                              2 => 
                              array (
                                0 => 'arith',
                                1 => '-',
                                2 => 
                                array (
                                  0 => 'call',
                                  1 => 'fn:string-to-codepoints',
                                  2 => 
                                  array (
                                    0 => 
                                    array (
                                      0 => 'call',
                                      1 => 'fn:substring',
                                      2 => 
                                      array (
                                        0 => 
                                        array (
                                          0 => 'var',
                                          1 => 'val',
                                        ),
                                        1 => 
                                        array (
                                          0 => 'literal',
                                          1 => 'xs:integer',
                                          2 => '5',
                                        ),
                                        2 => 
                                        array (
                                          0 => 'literal',
                                          1 => 'xs:integer',
                                          2 => '1',
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                                3 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:integer',
                                  2 => '48',
                                ),
                              ),
                              3 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:integer',
                                2 => '7',
                              ),
                            ),
                          ),
                          3 => 
                          array (
                            0 => 'arith',
                            1 => '*',
                            2 => 
                            array (
                              0 => 'arith',
                              1 => '-',
                              2 => 
                              array (
                                0 => 'call',
                                1 => 'fn:string-to-codepoints',
                                2 => 
                                array (
                                  0 => 
                                  array (
                                    0 => 'call',
                                    1 => 'fn:substring',
                                    2 => 
                                    array (
                                      0 => 
                                      array (
                                        0 => 'var',
                                        1 => 'val',
                                      ),
                                      1 => 
                                      array (
                                        0 => 'literal',
                                        1 => 'xs:integer',
                                        2 => '6',
                                      ),
                                      2 => 
                                      array (
                                        0 => 'literal',
                                        1 => 'xs:integer',
                                        2 => '1',
                                      ),
                                    ),
                                  ),
                                ),
                              ),
                              3 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:integer',
                                2 => '48',
                              ),
                            ),
                            3 => 
                            array (
                              0 => 'literal',
                              1 => 'xs:integer',
                              2 => '9',
                            ),
                          ),
                        ),
                        3 => 
                        array (
                          0 => 'arith',
                          1 => '*',
                          2 => 
                          array (
                            0 => 'arith',
                            1 => '-',
                            2 => 
                            array (
                              0 => 'call',
                              1 => 'fn:string-to-codepoints',
                              2 => 
                              array (
                                0 => 
                                array (
                                  0 => 'call',
                                  1 => 'fn:substring',
                                  2 => 
                                  array (
                                    0 => 
                                    array (
                                      0 => 'var',
                                      1 => 'val',
                                    ),
                                    1 => 
                                    array (
                                      0 => 'literal',
                                      1 => 'xs:integer',
                                      2 => '7',
                                    ),
                                    2 => 
                                    array (
                                      0 => 'literal',
                                      1 => 'xs:integer',
                                      2 => '1',
                                    ),
                                  ),
                                ),
                              ),
                            ),
                            3 => 
                            array (
                              0 => 'literal',
                              1 => 'xs:integer',
                              2 => '48',
                            ),
                          ),
                          3 => 
                          array (
                            0 => 'literal',
                            1 => 'xs:integer',
                            2 => '11',
                          ),
                        ),
                      ),
                      3 => 
                      array (
                        0 => 'arith',
                        1 => '*',
                        2 => 
                        array (
                          0 => 'arith',
                          1 => '-',
                          2 => 
                          array (
                            0 => 'call',
                            1 => 'fn:string-to-codepoints',
                            2 => 
                            array (
                              0 => 
                              array (
                                0 => 'call',
                                1 => 'fn:substring',
                                2 => 
                                array (
                                  0 => 
                                  array (
                                    0 => 'var',
                                    1 => 'val',
                                  ),
                                  1 => 
                                  array (
                                    0 => 'literal',
                                    1 => 'xs:integer',
                                    2 => '8',
                                  ),
                                  2 => 
                                  array (
                                    0 => 'literal',
                                    1 => 'xs:integer',
                                    2 => '1',
                                  ),
                                ),
                              ),
                            ),
                          ),
                          3 => 
                          array (
                            0 => 'literal',
                            1 => 'xs:integer',
                            2 => '48',
                          ),
                        ),
                        3 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:integer',
                          2 => '13',
                        ),
                      ),
                    ),
                    3 => 
                    array (
                      0 => 'arith',
                      1 => '*',
                      2 => 
                      array (
                        0 => 'arith',
                        1 => '-',
                        2 => 
                        array (
                          0 => 'call',
                          1 => 'fn:string-to-codepoints',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'call',
                              1 => 'fn:substring',
                              2 => 
                              array (
                                0 => 
                                array (
                                  0 => 'var',
                                  1 => 'val',
                                ),
                                1 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:integer',
                                  2 => '9',
                                ),
                                2 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:integer',
                                  2 => '1',
                                ),
                              ),
                            ),
                          ),
                        ),
                        3 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:integer',
                          2 => '48',
                        ),
                      ),
                      3 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:integer',
                        2 => '15',
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'arith',
                    1 => '*',
                    2 => 
                    array (
                      0 => 'arith',
                      1 => '-',
                      2 => 
                      array (
                        0 => 'call',
                        1 => 'fn:string-to-codepoints',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'call',
                            1 => 'fn:substring',
                            2 => 
                            array (
                              0 => 
                              array (
                                0 => 'var',
                                1 => 'val',
                              ),
                              1 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:integer',
                                2 => '10',
                              ),
                              2 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:integer',
                                2 => '1',
                              ),
                            ),
                          ),
                        ),
                      ),
                      3 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:integer',
                        2 => '48',
                      ),
                    ),
                    3 => 
                    array (
                      0 => 'literal',
                      1 => 'xs:integer',
                      2 => '17',
                    ),
                  ),
                ),
                3 => 
                array (
                  0 => 'arith',
                  1 => '*',
                  2 => 
                  array (
                    0 => 'arith',
                    1 => '-',
                    2 => 
                    array (
                      0 => 'call',
                      1 => 'fn:string-to-codepoints',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'call',
                          1 => 'fn:substring',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'var',
                              1 => 'val',
                            ),
                            1 => 
                            array (
                              0 => 'literal',
                              1 => 'xs:integer',
                              2 => '11',
                            ),
                            2 => 
                            array (
                              0 => 'literal',
                              1 => 'xs:integer',
                              2 => '1',
                            ),
                          ),
                        ),
                      ),
                    ),
                    3 => 
                    array (
                      0 => 'literal',
                      1 => 'xs:integer',
                      2 => '48',
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:integer',
                    2 => '19',
                  ),
                ),
              ),
              3 => 
              array (
                0 => 'literal',
                1 => 'xs:integer',
                2 => '89',
              ),
            ),
            3 => 
            array (
              0 => 'literal',
              1 => 'xs:integer',
              2 => '0',
            ),
          ),
        ),
      ),
    ),
    '{utils}checkSEOrgnr#1' => 
    array (
      'name' => 'u:checkSEOrgnr',
      'as' => 'xs:boolean',
      'params' => 
      array (
        0 => 
        array (
          'name' => 'number',
          'as' => 'xs:string',
        ),
      ),
      'body' => 
      array (
        0 => 
        array (
          0 => 'choose',
          1 => 
          array (
            0 => 
            array (
              0 => 
              array (
                0 => 'call',
                1 => 'fn:not',
                2 => 
                array (
                  0 => 
                  array (
                    0 => 'call',
                    1 => 'fn:matches',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'var',
                        1 => 'number',
                      ),
                      1 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:string',
                        2 => '^\\d+$',
                      ),
                    ),
                  ),
                ),
              ),
              1 => 
              array (
                0 => 
                array (
                  0 => 'sequence',
                  1 => 
                  array (
                    0 => 'call',
                    1 => 'fn:false',
                    2 => 
                    array (
                    ),
                  ),
                ),
              ),
            ),
          ),
          2 => 
          array (
            0 => 
            array (
              0 => 'variable',
              1 => 'mainPart',
              2 => NULL,
              3 => 
              array (
                0 => 'call',
                1 => 'fn:substring',
                2 => 
                array (
                  0 => 
                  array (
                    0 => 'var',
                    1 => 'number',
                  ),
                  1 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:integer',
                    2 => '1',
                  ),
                  2 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:integer',
                    2 => '9',
                  ),
                ),
              ),
            ),
            1 => 
            array (
              0 => 'variable',
              1 => 'checkDigit',
              2 => NULL,
              3 => 
              array (
                0 => 'call',
                1 => 'fn:substring',
                2 => 
                array (
                  0 => 
                  array (
                    0 => 'var',
                    1 => 'number',
                  ),
                  1 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:integer',
                    2 => '10',
                  ),
                  2 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:integer',
                    2 => '1',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              0 => 'variable',
              1 => 'sum',
              2 => 'xs:integer',
              3 => 
              array (
                0 => 'call',
                1 => 'xs:integer',
                2 => 
                array (
                  0 => 
                  array (
                    0 => 'call',
                    1 => 'fn:sum',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'for',
                        1 => 
                        array (
                          0 => 
                          array (
                            0 => 'pos',
                            1 => 
                            array (
                              0 => 'range',
                              1 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:integer',
                                2 => '1',
                              ),
                              2 => 
                              array (
                                0 => 'call',
                                1 => 'fn:string-length',
                                2 => 
                                array (
                                  0 => 
                                  array (
                                    0 => 'var',
                                    1 => 'mainPart',
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'if',
                          1 => 
                          array (
                            0 => 'gcmp',
                            1 => '=',
                            2 => 
                            array (
                              0 => 'arith',
                              1 => 'mod',
                              2 => 
                              array (
                                0 => 'var',
                                1 => 'pos',
                              ),
                              3 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:integer',
                                2 => '2',
                              ),
                            ),
                            3 => 
                            array (
                              0 => 'literal',
                              1 => 'xs:integer',
                              2 => '1',
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'arith',
                            1 => '+',
                            2 => 
                            array (
                              0 => 'arith',
                              1 => 'mod',
                              2 => 
                              array (
                                0 => 'arith',
                                1 => '*',
                                2 => 
                                array (
                                  0 => 'call',
                                  1 => 'fn:number',
                                  2 => 
                                  array (
                                    0 => 
                                    array (
                                      0 => 'call',
                                      1 => 'fn:substring',
                                      2 => 
                                      array (
                                        0 => 
                                        array (
                                          0 => 'var',
                                          1 => 'mainPart',
                                        ),
                                        1 => 
                                        array (
                                          0 => 'arith',
                                          1 => '+',
                                          2 => 
                                          array (
                                            0 => 'arith',
                                            1 => '-',
                                            2 => 
                                            array (
                                              0 => 'call',
                                              1 => 'fn:string-length',
                                              2 => 
                                              array (
                                                0 => 
                                                array (
                                                  0 => 'var',
                                                  1 => 'mainPart',
                                                ),
                                              ),
                                            ),
                                            3 => 
                                            array (
                                              0 => 'var',
                                              1 => 'pos',
                                            ),
                                          ),
                                          3 => 
                                          array (
                                            0 => 'literal',
                                            1 => 'xs:integer',
                                            2 => '1',
                                          ),
                                        ),
                                        2 => 
                                        array (
                                          0 => 'literal',
                                          1 => 'xs:integer',
                                          2 => '1',
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                                3 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:integer',
                                  2 => '2',
                                ),
                              ),
                              3 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:integer',
                                2 => '10',
                              ),
                            ),
                            3 => 
                            array (
                              0 => 'arith',
                              1 => 'idiv',
                              2 => 
                              array (
                                0 => 'arith',
                                1 => '*',
                                2 => 
                                array (
                                  0 => 'call',
                                  1 => 'fn:number',
                                  2 => 
                                  array (
                                    0 => 
                                    array (
                                      0 => 'call',
                                      1 => 'fn:substring',
                                      2 => 
                                      array (
                                        0 => 
                                        array (
                                          0 => 'var',
                                          1 => 'mainPart',
                                        ),
                                        1 => 
                                        array (
                                          0 => 'arith',
                                          1 => '+',
                                          2 => 
                                          array (
                                            0 => 'arith',
                                            1 => '-',
                                            2 => 
                                            array (
                                              0 => 'call',
                                              1 => 'fn:string-length',
                                              2 => 
                                              array (
                                                0 => 
                                                array (
                                                  0 => 'var',
                                                  1 => 'mainPart',
                                                ),
                                              ),
                                            ),
                                            3 => 
                                            array (
                                              0 => 'var',
                                              1 => 'pos',
                                            ),
                                          ),
                                          3 => 
                                          array (
                                            0 => 'literal',
                                            1 => 'xs:integer',
                                            2 => '1',
                                          ),
                                        ),
                                        2 => 
                                        array (
                                          0 => 'literal',
                                          1 => 'xs:integer',
                                          2 => '1',
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                                3 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:integer',
                                  2 => '2',
                                ),
                              ),
                              3 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:integer',
                                2 => '10',
                              ),
                            ),
                          ),
                          3 => 
                          array (
                            0 => 'call',
                            1 => 'fn:number',
                            2 => 
                            array (
                              0 => 
                              array (
                                0 => 'call',
                                1 => 'fn:substring',
                                2 => 
                                array (
                                  0 => 
                                  array (
                                    0 => 'var',
                                    1 => 'mainPart',
                                  ),
                                  1 => 
                                  array (
                                    0 => 'arith',
                                    1 => '+',
                                    2 => 
                                    array (
                                      0 => 'arith',
                                      1 => '-',
                                      2 => 
                                      array (
                                        0 => 'call',
                                        1 => 'fn:string-length',
                                        2 => 
                                        array (
                                          0 => 
                                          array (
                                            0 => 'var',
                                            1 => 'mainPart',
                                          ),
                                        ),
                                      ),
                                      3 => 
                                      array (
                                        0 => 'var',
                                        1 => 'pos',
                                      ),
                                    ),
                                    3 => 
                                    array (
                                      0 => 'literal',
                                      1 => 'xs:integer',
                                      2 => '1',
                                    ),
                                  ),
                                  2 => 
                                  array (
                                    0 => 'literal',
                                    1 => 'xs:integer',
                                    2 => '1',
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
            3 => 
            array (
              0 => 'variable',
              1 => 'calculatedCheckDigit',
              2 => NULL,
              3 => 
              array (
                0 => 'arith',
                1 => 'mod',
                2 => 
                array (
                  0 => 'arith',
                  1 => '-',
                  2 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:integer',
                    2 => '10',
                  ),
                  3 => 
                  array (
                    0 => 'arith',
                    1 => 'mod',
                    2 => 
                    array (
                      0 => 'var',
                      1 => 'sum',
                    ),
                    3 => 
                    array (
                      0 => 'literal',
                      1 => 'xs:integer',
                      2 => '10',
                    ),
                  ),
                ),
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '10',
                ),
              ),
            ),
            4 => 
            array (
              0 => 'sequence',
              1 => 
              array (
                0 => 'gcmp',
                1 => '=',
                2 => 
                array (
                  0 => 'var',
                  1 => 'calculatedCheckDigit',
                ),
                3 => 
                array (
                  0 => 'call',
                  1 => 'fn:number',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'var',
                      1 => 'checkDigit',
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    ),
  ),
  'patterns' => 
  array (
    0 => 
    array (
      'id' => 'variable-pattern',
      'lets' => 
      array (
      ),
      'rules' => 
      array (
      ),
    ),
    1 => 
    array (
      'id' => 'peppol-cii-pattern-1',
      'lets' => 
      array (
      ),
      'rules' => 
      array (
        0 => 
        array (
          'context' => 'rsm:ExchangedDocumentContext',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'root',
            ),
            2 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'step',
                1 => 'descendant-or-self',
                2 => 
                array (
                  0 => 'node',
                ),
                3 => 
                array (
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                  2 => 'ExchangedDocumentContext',
                ),
                3 => 
                array (
                ),
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'PEPPOL-EN16931-R001',
              'flag' => 'fatal',
              'test' => 'ram:BusinessProcessSpecifiedDocumentContextParameter/ram:ID',
              'ast' => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'BusinessProcessSpecifiedDocumentContextParameter',
                  ),
                  3 => 
                  array (
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'ID',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              'message' => '[PEPPOL-EN16931-R001]-Business process MUST be provided.',
            ),
          ),
        ),
        1 => 
        array (
          'context' => 'ram:ApplicableHeaderTradeSettlement',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'root',
            ),
            2 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'step',
                1 => 'descendant-or-self',
                2 => 
                array (
                  0 => 'node',
                ),
                3 => 
                array (
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'ApplicableHeaderTradeSettlement',
                ),
                3 => 
                array (
                ),
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'PEPPOL-EN16931-R005',
              'flag' => 'fatal',
              'test' => 'not(ram:TaxCurrencyCode) or normalize-space(ram:TaxCurrencyCode/text()) != normalize-space(ram:InvoiceCurrencyCode/text())',
              'ast' => 
              array (
                0 => 'or',
                1 => 
                array (
                  0 => 'call',
                  1 => 'fn:not',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'TaxCurrencyCode',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'gcmp',
                  1 => '!=',
                  2 => 
                  array (
                    0 => 'call',
                    1 => 'fn:normalize-space',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'TaxCurrencyCode',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'text',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'call',
                    1 => 'fn:normalize-space',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'InvoiceCurrencyCode',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'text',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[PEPPOL-EN16931-R005]-VAT accounting currency code MUST be different from invoice currency code when provided.',
            ),
            1 => 
            array (
              'id' => 'PEPPOL-EN16931-R053',
              'flag' => 'fatal',
              'test' => 'count(ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID = $documentCurrencyCode]) <=1',
              'ast' => 
              array (
                0 => 'gcmp',
                1 => '<=',
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:count',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'SpecifiedTradeSettlementHeaderMonetarySummation',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'TaxTotalAmount',
                        ),
                        3 => 
                        array (
                          0 => 
                          array (
                            0 => 'gcmp',
                            1 => '=',
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'attribute',
                              2 => 
                              array (
                                0 => 'name',
                                1 => '',
                                2 => 'currencyID',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                            3 => 
                            array (
                              0 => 'var',
                              1 => 'documentCurrencyCode',
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '1',
                ),
              ),
              'message' => '[PEPPOL-EN16931-R053]-No more than one tax total amount must be provided where currency id equals document currency code.',
            ),
            2 => 
            array (
              'id' => 'PEPPOL-EN16931-R054',
              'flag' => 'fatal',
              'test' => '                     count(ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID != $documentCurrencyCode]) = (if (ram:TaxCurrencyCode) then                         1                     else                         0)',
              'ast' => 
              array (
                0 => 'gcmp',
                1 => '=',
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:count',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'SpecifiedTradeSettlementHeaderMonetarySummation',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'TaxTotalAmount',
                        ),
                        3 => 
                        array (
                          0 => 
                          array (
                            0 => 'gcmp',
                            1 => '!=',
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'attribute',
                              2 => 
                              array (
                                0 => 'name',
                                1 => '',
                                2 => 'currencyID',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                            3 => 
                            array (
                              0 => 'var',
                              1 => 'documentCurrencyCode',
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
                3 => 
                array (
                  0 => 'if',
                  1 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'TaxCurrencyCode',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:integer',
                    2 => '1',
                  ),
                  3 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:integer',
                    2 => '0',
                  ),
                ),
              ),
              'message' => '[PEPPOL-EN16931-R054]-Only one tax total amount must be provided where currency id equals tax currency code, if tax currency code (BT-6) is provided.',
            ),
            3 => 
            array (
              'id' => 'PEPPOL-EN16931-R055',
              'flag' => 'fatal',
              'test' => 'not(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:TaxCurrencyCode and ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID = $documentCurrencyCode]) or (ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID = $taxCurrencyCode] < 0 and ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID = $documentCurrencyCode] < 0) or (ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID = $taxCurrencyCode] >= 0 and ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID = $documentCurrencyCode] >= 0)',
              'ast' => 
              array (
                0 => 'or',
                1 => 
                array (
                  0 => 'or',
                  1 => 
                  array (
                    0 => 'call',
                    1 => 'fn:not',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'and',
                        1 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'path',
                              1 => 
                              array (
                                0 => 'path',
                                1 => 
                                array (
                                  0 => 'root',
                                ),
                                2 => 
                                array (
                                  0 => 'step',
                                  1 => 'child',
                                  2 => 
                                  array (
                                    0 => 'name',
                                    1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                                    2 => 'CrossIndustryInvoice',
                                  ),
                                  3 => 
                                  array (
                                  ),
                                ),
                              ),
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                                  2 => 'SupplyChainTradeTransaction',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'ApplicableHeaderTradeSettlement',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'TaxCurrencyCode',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'SpecifiedTradeSettlementHeaderMonetarySummation',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'TaxTotalAmount',
                            ),
                            3 => 
                            array (
                              0 => 
                              array (
                                0 => 'gcmp',
                                1 => '=',
                                2 => 
                                array (
                                  0 => 'step',
                                  1 => 'attribute',
                                  2 => 
                                  array (
                                    0 => 'name',
                                    1 => '',
                                    2 => 'currencyID',
                                  ),
                                  3 => 
                                  array (
                                  ),
                                ),
                                3 => 
                                array (
                                  0 => 'var',
                                  1 => 'documentCurrencyCode',
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'and',
                    1 => 
                    array (
                      0 => 'gcmp',
                      1 => '<',
                      2 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'SpecifiedTradeSettlementHeaderMonetarySummation',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'TaxTotalAmount',
                          ),
                          3 => 
                          array (
                            0 => 
                            array (
                              0 => 'gcmp',
                              1 => '=',
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'attribute',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => '',
                                  2 => 'currencyID',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                              3 => 
                              array (
                                0 => 'var',
                                1 => 'taxCurrencyCode',
                              ),
                            ),
                          ),
                        ),
                      ),
                      3 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:integer',
                        2 => '0',
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'gcmp',
                      1 => '<',
                      2 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'SpecifiedTradeSettlementHeaderMonetarySummation',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'TaxTotalAmount',
                          ),
                          3 => 
                          array (
                            0 => 
                            array (
                              0 => 'gcmp',
                              1 => '=',
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'attribute',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => '',
                                  2 => 'currencyID',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                              3 => 
                              array (
                                0 => 'var',
                                1 => 'documentCurrencyCode',
                              ),
                            ),
                          ),
                        ),
                      ),
                      3 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:integer',
                        2 => '0',
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'and',
                  1 => 
                  array (
                    0 => 'gcmp',
                    1 => '>=',
                    2 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'SpecifiedTradeSettlementHeaderMonetarySummation',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'TaxTotalAmount',
                        ),
                        3 => 
                        array (
                          0 => 
                          array (
                            0 => 'gcmp',
                            1 => '=',
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'attribute',
                              2 => 
                              array (
                                0 => 'name',
                                1 => '',
                                2 => 'currencyID',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                            3 => 
                            array (
                              0 => 'var',
                              1 => 'taxCurrencyCode',
                            ),
                          ),
                        ),
                      ),
                    ),
                    3 => 
                    array (
                      0 => 'literal',
                      1 => 'xs:integer',
                      2 => '0',
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'gcmp',
                    1 => '>=',
                    2 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'SpecifiedTradeSettlementHeaderMonetarySummation',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'TaxTotalAmount',
                        ),
                        3 => 
                        array (
                          0 => 
                          array (
                            0 => 'gcmp',
                            1 => '=',
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'attribute',
                              2 => 
                              array (
                                0 => 'name',
                                1 => '',
                                2 => 'currencyID',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                            3 => 
                            array (
                              0 => 'var',
                              1 => 'documentCurrencyCode',
                            ),
                          ),
                        ),
                      ),
                    ),
                    3 => 
                    array (
                      0 => 'literal',
                      1 => 'xs:integer',
                      2 => '0',
                    ),
                  ),
                ),
              ),
              'message' => '[PEPPOL-EN16931-R055]-Invoice total VAT amount and Invoice total VAT amount in accounting currency MUST have the same operational sign',
            ),
          ),
        ),
        2 => 
        array (
          'context' => 'ram:BuyerTradeParty',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'root',
            ),
            2 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'step',
                1 => 'descendant-or-self',
                2 => 
                array (
                  0 => 'node',
                ),
                3 => 
                array (
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'BuyerTradeParty',
                ),
                3 => 
                array (
                ),
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'PEPPOL-EN16931-R010',
              'flag' => 'fatal',
              'test' => 'ram:URIUniversalCommunication/ram:URIID',
              'ast' => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'URIUniversalCommunication',
                  ),
                  3 => 
                  array (
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'URIID',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              'message' => '[PEPPOL-EN16931-R010]-Buyer electronic address MUST be provided',
            ),
          ),
        ),
        3 => 
        array (
          'context' => 'ram:SellerTradeParty',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'root',
            ),
            2 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'step',
                1 => 'descendant-or-self',
                2 => 
                array (
                  0 => 'node',
                ),
                3 => 
                array (
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'SellerTradeParty',
                ),
                3 => 
                array (
                ),
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'PEPPOL-EN16931-R020',
              'flag' => 'fatal',
              'test' => 'ram:URIUniversalCommunication/ram:URIID',
              'ast' => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'URIUniversalCommunication',
                  ),
                  3 => 
                  array (
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'URIID',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              'message' => '[PEPPOL-EN16931-R020]-Seller electronic address MUST be provided',
            ),
          ),
        ),
        4 => 
        array (
          'context' => 'ram:SpecifiedTradeAllowanceCharge[ram:CalculationPercent and not(ram:BasisAmount)]',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'root',
            ),
            2 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'step',
                1 => 'descendant-or-self',
                2 => 
                array (
                  0 => 'node',
                ),
                3 => 
                array (
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'SpecifiedTradeAllowanceCharge',
                ),
                3 => 
                array (
                  0 => 
                  array (
                    0 => 'and',
                    1 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'CalculationPercent',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'call',
                      1 => 'fn:not',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'BasisAmount',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'PEPPOL-EN16931-R041',
              'flag' => 'fatal',
              'test' => 'false()',
              'ast' => 
              array (
                0 => 'call',
                1 => 'fn:false',
                2 => 
                array (
                ),
              ),
              'message' => '[PEPPOL-EN16931-R041]-Allowance/charge base amount MUST be provided when allowance/charge percentage is provided.',
            ),
          ),
        ),
        5 => 
        array (
          'context' => 'ram:SpecifiedTradeAllowanceCharge[not(ram:CalculationPercent) and ram:BasisAmount]',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'root',
            ),
            2 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'step',
                1 => 'descendant-or-self',
                2 => 
                array (
                  0 => 'node',
                ),
                3 => 
                array (
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'SpecifiedTradeAllowanceCharge',
                ),
                3 => 
                array (
                  0 => 
                  array (
                    0 => 'and',
                    1 => 
                    array (
                      0 => 'call',
                      1 => 'fn:not',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'CalculationPercent',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'BasisAmount',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'PEPPOL-EN16931-R042',
              'flag' => 'fatal',
              'test' => 'false()',
              'ast' => 
              array (
                0 => 'call',
                1 => 'fn:false',
                2 => 
                array (
                ),
              ),
              'message' => '[PEPPOL-EN16931-R042]-Allowance/charge percentage MUST be provided when allowance/charge base amount is provided.',
            ),
          ),
        ),
        6 => 
        array (
          'context' => 'ram:SpecifiedTradeAllowanceCharge',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'root',
            ),
            2 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'step',
                1 => 'descendant-or-self',
                2 => 
                array (
                  0 => 'node',
                ),
                3 => 
                array (
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'SpecifiedTradeAllowanceCharge',
                ),
                3 => 
                array (
                ),
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'PEPPOL-EN16931-R040',
              'flag' => 'fatal',
              'test' => 'not(ram:CalculationPercent and ram:BasisAmount) or u:slack(if (ram:ActualAmount) then ram:ActualAmount else 0, (xs:decimal(ram:BasisAmount) * xs:decimal(ram:CalculationPercent)) div 100, $slackValue)',
              'ast' => 
              array (
                0 => 'or',
                1 => 
                array (
                  0 => 'call',
                  1 => 'fn:not',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'and',
                      1 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'CalculationPercent',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'BasisAmount',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'ucall',
                  1 => 'utils',
                  2 => 'slack',
                  3 => 
                  array (
                    0 => 
                    array (
                      0 => 'if',
                      1 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'ActualAmount',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'ActualAmount',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                      3 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:integer',
                        2 => '0',
                      ),
                    ),
                    1 => 
                    array (
                      0 => 'arith',
                      1 => 'div',
                      2 => 
                      array (
                        0 => 'arith',
                        1 => '*',
                        2 => 
                        array (
                          0 => 'call',
                          1 => 'xs:decimal',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'BasisAmount',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                        ),
                        3 => 
                        array (
                          0 => 'call',
                          1 => 'xs:decimal',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'CalculationPercent',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                        ),
                      ),
                      3 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:integer',
                        2 => '100',
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'var',
                      1 => 'slackValue',
                    ),
                  ),
                ),
              ),
              'message' => '[PEPPOL-EN16931-R040]-Allowance/charge amount must equal base amount * percentage/100 if base amount and percentage exists',
            ),
            1 => 
            array (
              'id' => 'PEPPOL-EN16931-R043-1',
              'flag' => 'fatal',
              'test' => 'normalize-space(ram:ChargeIndicator/udt:Indicator/text()) = \'true\' or normalize-space(ram:ChargeIndicator/udt:Indicator/text()) = \'false\'',
              'ast' => 
              array (
                0 => 'or',
                1 => 
                array (
                  0 => 'gcmp',
                  1 => '=',
                  2 => 
                  array (
                    0 => 'call',
                    1 => 'fn:normalize-space',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'ChargeIndicator',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
                              2 => 'Indicator',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'text',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:string',
                    2 => 'true',
                  ),
                ),
                2 => 
                array (
                  0 => 'gcmp',
                  1 => '=',
                  2 => 
                  array (
                    0 => 'call',
                    1 => 'fn:normalize-space',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'ChargeIndicator',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
                              2 => 'Indicator',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'text',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:string',
                    2 => 'false',
                  ),
                ),
              ),
              'message' => '[PEPPOL-EN16931-R043]-Allowance/charge ChargeIndicator value MUST equal \'true\' or \'false\'',
            ),
          ),
        ),
        7 => 
        array (
          'context' => 'ram:AppliedTradeAllowanceCharge',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'root',
            ),
            2 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'step',
                1 => 'descendant-or-self',
                2 => 
                array (
                  0 => 'node',
                ),
                3 => 
                array (
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'AppliedTradeAllowanceCharge',
                ),
                3 => 
                array (
                ),
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'PEPPOL-EN16931-R043-2',
              'flag' => 'fatal',
              'test' => 'normalize-space(ram:ChargeIndicator/udt:Indicator/text()) = \'true\' or normalize-space(ram:ChargeIndicator/udt:Indicator/text()) = \'false\'',
              'ast' => 
              array (
                0 => 'or',
                1 => 
                array (
                  0 => 'gcmp',
                  1 => '=',
                  2 => 
                  array (
                    0 => 'call',
                    1 => 'fn:normalize-space',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'ChargeIndicator',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
                              2 => 'Indicator',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'text',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:string',
                    2 => 'true',
                  ),
                ),
                2 => 
                array (
                  0 => 'gcmp',
                  1 => '=',
                  2 => 
                  array (
                    0 => 'call',
                    1 => 'fn:normalize-space',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'ChargeIndicator',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
                              2 => 'Indicator',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'text',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:string',
                    2 => 'false',
                  ),
                ),
              ),
              'message' => '[PEPPOL-EN16931-R043]-Allowance/charge ChargeIndicator value MUST equal \'true\' or \'false\'',
            ),
          ),
        ),
        8 => 
        array (
          'context' => '                 ram:SpecifiedTradeSettlementPaymentMeans[some $code in tokenize(\'49 59\', \'\\s\')                     satisfies normalize-space(ram:TypeCode) = $code]',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'root',
            ),
            2 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'step',
                1 => 'descendant-or-self',
                2 => 
                array (
                  0 => 'node',
                ),
                3 => 
                array (
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'SpecifiedTradeSettlementPaymentMeans',
                ),
                3 => 
                array (
                  0 => 
                  array (
                    0 => 'quant',
                    1 => 'some',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'code',
                        1 => 
                        array (
                          0 => 'call',
                          1 => 'fn:tokenize',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'literal',
                              1 => 'xs:string',
                              2 => '49 59',
                            ),
                            1 => 
                            array (
                              0 => 'literal',
                              1 => 'xs:string',
                              2 => '\\s',
                            ),
                          ),
                        ),
                      ),
                    ),
                    3 => 
                    array (
                      0 => 'gcmp',
                      1 => '=',
                      2 => 
                      array (
                        0 => 'call',
                        1 => 'fn:normalize-space',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'TypeCode',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                      ),
                      3 => 
                      array (
                        0 => 'var',
                        1 => 'code',
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'PEPPOL-EN16931-R061',
              'flag' => 'fatal',
              'test' => '../ram:SpecifiedTradePaymentTerms/ram:DirectDebitMandateID',
              'ast' => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'step',
                    1 => 'parent',
                    2 => 
                    array (
                      0 => 'node',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'SpecifiedTradePaymentTerms',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'DirectDebitMandateID',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              'message' => '[PEPPOL-EN16931-R061]-Mandate reference MUST be provided for direct debit.',
            ),
          ),
        ),
        9 => 
        array (
          'context' => 'rsm:SupplyChainTradeTransaction[ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod/ram:StartDateTime]/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:BillingSpecifiedPeriod/ram:StartDateTime',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'root',
                    ),
                    2 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'step',
                        1 => 'descendant-or-self',
                        2 => 
                        array (
                          0 => 'node',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                          2 => 'SupplyChainTradeTransaction',
                        ),
                        3 => 
                        array (
                          0 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'path',
                              1 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                  2 => 'ApplicableHeaderTradeSettlement',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                  2 => 'BillingSpecifiedPeriod',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'StartDateTime',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'IncludedSupplyChainTradeLineItem',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'SpecifiedLineTradeSettlement',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'BillingSpecifiedPeriod',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                2 => 'StartDateTime',
              ),
              3 => 
              array (
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'PEPPOL-EN16931-R110',
              'flag' => 'fatal',
              'test' => 'udt:DateTimeString >= ../../../../ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod/ram:StartDateTime/udt:DateTimeString',
              'ast' => 
              array (
                0 => 'gcmp',
                1 => '>=',
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
                    2 => 'DateTimeString',
                  ),
                  3 => 
                  array (
                  ),
                ),
                3 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'path',
                              1 => 
                              array (
                                0 => 'step',
                                1 => 'parent',
                                2 => 
                                array (
                                  0 => 'node',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'parent',
                                2 => 
                                array (
                                  0 => 'node',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'parent',
                              2 => 
                              array (
                                0 => 'node',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'parent',
                            2 => 
                            array (
                              0 => 'node',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'ApplicableHeaderTradeSettlement',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'BillingSpecifiedPeriod',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'StartDateTime',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
                      2 => 'DateTimeString',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
              ),
              'message' => '[PEPPOL-EN16931-R110]-Start date of line period MUST be within invoice period.',
            ),
          ),
        ),
        10 => 
        array (
          'context' => 'rsm:SupplyChainTradeTransaction[ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod/ram:EndDateTime]/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:BillingSpecifiedPeriod/ram:EndDateTime',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'root',
                    ),
                    2 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'step',
                        1 => 'descendant-or-self',
                        2 => 
                        array (
                          0 => 'node',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                          2 => 'SupplyChainTradeTransaction',
                        ),
                        3 => 
                        array (
                          0 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'path',
                              1 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                  2 => 'ApplicableHeaderTradeSettlement',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                  2 => 'BillingSpecifiedPeriod',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'EndDateTime',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'IncludedSupplyChainTradeLineItem',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'SpecifiedLineTradeSettlement',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'BillingSpecifiedPeriod',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                2 => 'EndDateTime',
              ),
              3 => 
              array (
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'PEPPOL-EN16931-R111',
              'flag' => 'fatal',
              'test' => 'udt:DateTimeString <= ../../../../ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod/ram:EndDateTime/udt:DateTimeString',
              'ast' => 
              array (
                0 => 'gcmp',
                1 => '<=',
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
                    2 => 'DateTimeString',
                  ),
                  3 => 
                  array (
                  ),
                ),
                3 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'path',
                              1 => 
                              array (
                                0 => 'step',
                                1 => 'parent',
                                2 => 
                                array (
                                  0 => 'node',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'parent',
                                2 => 
                                array (
                                  0 => 'node',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'parent',
                              2 => 
                              array (
                                0 => 'node',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'parent',
                            2 => 
                            array (
                              0 => 'node',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'ApplicableHeaderTradeSettlement',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'BillingSpecifiedPeriod',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'EndDateTime',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
                      2 => 'DateTimeString',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
              ),
              'message' => '[PEPPOL-EN16931-R111]-End date of line period MUST be within invoice period.',
            ),
          ),
        ),
        11 => 
        array (
          'context' => 'ram:IncludedSupplyChainTradeLineItem',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'root',
            ),
            2 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'step',
                1 => 'descendant-or-self',
                2 => 
                array (
                  0 => 'node',
                ),
                3 => 
                array (
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'IncludedSupplyChainTradeLineItem',
                ),
                3 => 
                array (
                ),
              ),
            ),
          ),
          'lets' => 
          array (
            0 => 
            array (
              'name' => 'lineExtensionAmount',
              'value' => '                     if (ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount) then                         xs:decimal(ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount)                     else                         0',
              'ast' => 
              array (
                0 => 'if',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'SpecifiedLineTradeSettlement',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'SpecifiedTradeSettlementLineMonetarySummation',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'LineTotalAmount',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'call',
                  1 => 'xs:decimal',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'SpecifiedLineTradeSettlement',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'SpecifiedTradeSettlementLineMonetarySummation',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'LineTotalAmount',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                  ),
                ),
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '0',
                ),
              ),
            ),
            1 => 
            array (
              'name' => 'quantity',
              'value' => '                     if (ram:SpecifiedLineTradeDelivery/ram:BilledQuantity) then                         xs:decimal(ram:SpecifiedLineTradeDelivery/ram:BilledQuantity)                     else                         1',
              'ast' => 
              array (
                0 => 'if',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'SpecifiedLineTradeDelivery',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'BilledQuantity',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'call',
                  1 => 'xs:decimal',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'SpecifiedLineTradeDelivery',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'BilledQuantity',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                  ),
                ),
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '1',
                ),
              ),
            ),
            2 => 
            array (
              'name' => 'priceAmount',
              'value' => '                     if (ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:ChargeAmount) then                         xs:decimal(ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:ChargeAmount)                     else                         0',
              'ast' => 
              array (
                0 => 'if',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'SpecifiedLineTradeAgreement',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'NetPriceProductTradePrice',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'ChargeAmount',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'call',
                  1 => 'xs:decimal',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'SpecifiedLineTradeAgreement',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'NetPriceProductTradePrice',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'ChargeAmount',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                  ),
                ),
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '0',
                ),
              ),
            ),
            3 => 
            array (
              'name' => 'baseQuantity',
              'value' => 'if (ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:BasisQuantity and xs:decimal(ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:BasisQuantity) != 0) then xs:decimal(ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:BasisQuantity) else if (ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice/ram:BasisQuantity and xs:decimal(ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice/ram:BasisQuantity) != 0) then xs:decimal(ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice/ram:BasisQuantity) else 1',
              'ast' => 
              array (
                0 => 'if',
                1 => 
                array (
                  0 => 'and',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'SpecifiedLineTradeAgreement',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'NetPriceProductTradePrice',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'BasisQuantity',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'gcmp',
                    1 => '!=',
                    2 => 
                    array (
                      0 => 'call',
                      1 => 'xs:decimal',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'SpecifiedLineTradeAgreement',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'NetPriceProductTradePrice',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'BasisQuantity',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                      ),
                    ),
                    3 => 
                    array (
                      0 => 'literal',
                      1 => 'xs:integer',
                      2 => '0',
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'call',
                  1 => 'xs:decimal',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'SpecifiedLineTradeAgreement',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'NetPriceProductTradePrice',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'BasisQuantity',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                  ),
                ),
                3 => 
                array (
                  0 => 'if',
                  1 => 
                  array (
                    0 => 'and',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'SpecifiedLineTradeAgreement',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'GrossPriceProductTradePrice',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'BasisQuantity',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'gcmp',
                      1 => '!=',
                      2 => 
                      array (
                        0 => 'call',
                        1 => 'xs:decimal',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'path',
                              1 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                  2 => 'SpecifiedLineTradeAgreement',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                  2 => 'GrossPriceProductTradePrice',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'BasisQuantity',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                        ),
                      ),
                      3 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:integer',
                        2 => '0',
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'call',
                    1 => 'xs:decimal',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'SpecifiedLineTradeAgreement',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'GrossPriceProductTradePrice',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'BasisQuantity',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:integer',
                    2 => '1',
                  ),
                ),
              ),
            ),
            4 => 
            array (
              'name' => 'allowancesTotal',
              'value' => '                     if (ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeAllowanceCharge[normalize-space(ram:ChargeIndicator/udt:Indicator) = \'false\']) then                         round(sum(ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeAllowanceCharge[normalize-space(ram:ChargeIndicator/udt:Indicator) = \'false\']/ram:ActualAmount/xs:decimal(.)) * 10 * 10) div 100                     else                         0',
              'ast' => 
              array (
                0 => 'if',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'SpecifiedLineTradeSettlement',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'SpecifiedTradeAllowanceCharge',
                    ),
                    3 => 
                    array (
                      0 => 
                      array (
                        0 => 'gcmp',
                        1 => '=',
                        2 => 
                        array (
                          0 => 'call',
                          1 => 'fn:normalize-space',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'path',
                              1 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                  2 => 'ChargeIndicator',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
                                  2 => 'Indicator',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                          ),
                        ),
                        3 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => 'false',
                        ),
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'arith',
                  1 => 'div',
                  2 => 
                  array (
                    0 => 'call',
                    1 => 'fn:round',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'arith',
                        1 => '*',
                        2 => 
                        array (
                          0 => 'arith',
                          1 => '*',
                          2 => 
                          array (
                            0 => 'call',
                            1 => 'fn:sum',
                            2 => 
                            array (
                              0 => 
                              array (
                                0 => 'path',
                                1 => 
                                array (
                                  0 => 'path',
                                  1 => 
                                  array (
                                    0 => 'path',
                                    1 => 
                                    array (
                                      0 => 'step',
                                      1 => 'child',
                                      2 => 
                                      array (
                                        0 => 'name',
                                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                        2 => 'SpecifiedLineTradeSettlement',
                                      ),
                                      3 => 
                                      array (
                                      ),
                                    ),
                                    2 => 
                                    array (
                                      0 => 'step',
                                      1 => 'child',
                                      2 => 
                                      array (
                                        0 => 'name',
                                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                        2 => 'SpecifiedTradeAllowanceCharge',
                                      ),
                                      3 => 
                                      array (
                                        0 => 
                                        array (
                                          0 => 'gcmp',
                                          1 => '=',
                                          2 => 
                                          array (
                                            0 => 'call',
                                            1 => 'fn:normalize-space',
                                            2 => 
                                            array (
                                              0 => 
                                              array (
                                                0 => 'path',
                                                1 => 
                                                array (
                                                  0 => 'step',
                                                  1 => 'child',
                                                  2 => 
                                                  array (
                                                    0 => 'name',
                                                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                                    2 => 'ChargeIndicator',
                                                  ),
                                                  3 => 
                                                  array (
                                                  ),
                                                ),
                                                2 => 
                                                array (
                                                  0 => 'step',
                                                  1 => 'child',
                                                  2 => 
                                                  array (
                                                    0 => 'name',
                                                    1 => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
                                                    2 => 'Indicator',
                                                  ),
                                                  3 => 
                                                  array (
                                                  ),
                                                ),
                                              ),
                                            ),
                                          ),
                                          3 => 
                                          array (
                                            0 => 'literal',
                                            1 => 'xs:string',
                                            2 => 'false',
                                          ),
                                        ),
                                      ),
                                    ),
                                  ),
                                  2 => 
                                  array (
                                    0 => 'step',
                                    1 => 'child',
                                    2 => 
                                    array (
                                      0 => 'name',
                                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                      2 => 'ActualAmount',
                                    ),
                                    3 => 
                                    array (
                                    ),
                                  ),
                                ),
                                2 => 
                                array (
                                  0 => 'call',
                                  1 => 'xs:decimal',
                                  2 => 
                                  array (
                                    0 => 
                                    array (
                                      0 => 'context',
                                    ),
                                  ),
                                ),
                              ),
                            ),
                          ),
                          3 => 
                          array (
                            0 => 'literal',
                            1 => 'xs:integer',
                            2 => '10',
                          ),
                        ),
                        3 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:integer',
                          2 => '10',
                        ),
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:integer',
                    2 => '100',
                  ),
                ),
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '0',
                ),
              ),
            ),
            5 => 
            array (
              'name' => 'chargesTotal',
              'value' => '                     if (ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeAllowanceCharge[normalize-space(ram:ChargeIndicator/udt:Indicator) = \'true\']) then                         round(sum(ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeAllowanceCharge[normalize-space(ram:ChargeIndicator/udt:Indicator) = \'true\']/ram:ActualAmount/xs:decimal(.)) * 10 * 10) div 100                     else                         0',
              'ast' => 
              array (
                0 => 'if',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'SpecifiedLineTradeSettlement',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'SpecifiedTradeAllowanceCharge',
                    ),
                    3 => 
                    array (
                      0 => 
                      array (
                        0 => 'gcmp',
                        1 => '=',
                        2 => 
                        array (
                          0 => 'call',
                          1 => 'fn:normalize-space',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'path',
                              1 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                  2 => 'ChargeIndicator',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
                                  2 => 'Indicator',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                          ),
                        ),
                        3 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => 'true',
                        ),
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'arith',
                  1 => 'div',
                  2 => 
                  array (
                    0 => 'call',
                    1 => 'fn:round',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'arith',
                        1 => '*',
                        2 => 
                        array (
                          0 => 'arith',
                          1 => '*',
                          2 => 
                          array (
                            0 => 'call',
                            1 => 'fn:sum',
                            2 => 
                            array (
                              0 => 
                              array (
                                0 => 'path',
                                1 => 
                                array (
                                  0 => 'path',
                                  1 => 
                                  array (
                                    0 => 'path',
                                    1 => 
                                    array (
                                      0 => 'step',
                                      1 => 'child',
                                      2 => 
                                      array (
                                        0 => 'name',
                                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                        2 => 'SpecifiedLineTradeSettlement',
                                      ),
                                      3 => 
                                      array (
                                      ),
                                    ),
                                    2 => 
                                    array (
                                      0 => 'step',
                                      1 => 'child',
                                      2 => 
                                      array (
                                        0 => 'name',
                                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                        2 => 'SpecifiedTradeAllowanceCharge',
                                      ),
                                      3 => 
                                      array (
                                        0 => 
                                        array (
                                          0 => 'gcmp',
                                          1 => '=',
                                          2 => 
                                          array (
                                            0 => 'call',
                                            1 => 'fn:normalize-space',
                                            2 => 
                                            array (
                                              0 => 
                                              array (
                                                0 => 'path',
                                                1 => 
                                                array (
                                                  0 => 'step',
                                                  1 => 'child',
                                                  2 => 
                                                  array (
                                                    0 => 'name',
                                                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                                    2 => 'ChargeIndicator',
                                                  ),
                                                  3 => 
                                                  array (
                                                  ),
                                                ),
                                                2 => 
                                                array (
                                                  0 => 'step',
                                                  1 => 'child',
                                                  2 => 
                                                  array (
                                                    0 => 'name',
                                                    1 => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
                                                    2 => 'Indicator',
                                                  ),
                                                  3 => 
                                                  array (
                                                  ),
                                                ),
                                              ),
                                            ),
                                          ),
                                          3 => 
                                          array (
                                            0 => 'literal',
                                            1 => 'xs:string',
                                            2 => 'true',
                                          ),
                                        ),
                                      ),
                                    ),
                                  ),
                                  2 => 
                                  array (
                                    0 => 'step',
                                    1 => 'child',
                                    2 => 
                                    array (
                                      0 => 'name',
                                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                      2 => 'ActualAmount',
                                    ),
                                    3 => 
                                    array (
                                    ),
                                  ),
                                ),
                                2 => 
                                array (
                                  0 => 'call',
                                  1 => 'xs:decimal',
                                  2 => 
                                  array (
                                    0 => 
                                    array (
                                      0 => 'context',
                                    ),
                                  ),
                                ),
                              ),
                            ),
                          ),
                          3 => 
                          array (
                            0 => 'literal',
                            1 => 'xs:integer',
                            2 => '10',
                          ),
                        ),
                        3 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:integer',
                          2 => '10',
                        ),
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:integer',
                    2 => '100',
                  ),
                ),
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '0',
                ),
              ),
            ),
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'PEPPOL-EN16931-R120',
              'flag' => 'warning',
              'test' => 'u:slack($lineExtensionAmount, ($quantity * ($priceAmount div $baseQuantity)) + $chargesTotal - $allowancesTotal, $slackValue)',
              'ast' => 
              array (
                0 => 'ucall',
                1 => 'utils',
                2 => 'slack',
                3 => 
                array (
                  0 => 
                  array (
                    0 => 'var',
                    1 => 'lineExtensionAmount',
                  ),
                  1 => 
                  array (
                    0 => 'arith',
                    1 => '-',
                    2 => 
                    array (
                      0 => 'arith',
                      1 => '+',
                      2 => 
                      array (
                        0 => 'arith',
                        1 => '*',
                        2 => 
                        array (
                          0 => 'var',
                          1 => 'quantity',
                        ),
                        3 => 
                        array (
                          0 => 'arith',
                          1 => 'div',
                          2 => 
                          array (
                            0 => 'var',
                            1 => 'priceAmount',
                          ),
                          3 => 
                          array (
                            0 => 'var',
                            1 => 'baseQuantity',
                          ),
                        ),
                      ),
                      3 => 
                      array (
                        0 => 'var',
                        1 => 'chargesTotal',
                      ),
                    ),
                    3 => 
                    array (
                      0 => 'var',
                      1 => 'allowancesTotal',
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'var',
                    1 => 'slackValue',
                  ),
                ),
              ),
              'message' => '[PEPPOL-EN16931-R120]-Invoice line net amount MUST equal (Invoiced quantity * (Item net price/item price base quantity) + Sum of invoice line charge amount - sum of invoice line allowance amount',
            ),
            1 => 
            array (
              'id' => 'PEPPOL-EN16931-R101',
              'flag' => 'fatal',
              'test' => '(not(ram:SpecifiedLineTradeSettlement/ram:AdditionalReferencedDocument) or (ram:SpecifiedLineTradeSettlement/ram:AdditionalReferencedDocument/ram:TypeCode=\'130\'))',
              'ast' => 
              array (
                0 => 'or',
                1 => 
                array (
                  0 => 'call',
                  1 => 'fn:not',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'SpecifiedLineTradeSettlement',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'AdditionalReferencedDocument',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'gcmp',
                  1 => '=',
                  2 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'SpecifiedLineTradeSettlement',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'AdditionalReferencedDocument',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'TypeCode',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:string',
                    2 => '130',
                  ),
                ),
              ),
              'message' => '[PEPPOL-EN16931-R101]-Element Additional referenced document can only be used for Invoice line object.',
            ),
          ),
        ),
        12 => 
        array (
          'context' => 'ram:NetPriceProductTradePrice | ram:GrossPriceProductTradePrice',
          'match' => 
          array (
            0 => 'union',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'root',
              ),
              2 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'step',
                  1 => 'descendant-or-self',
                  2 => 
                  array (
                    0 => 'node',
                  ),
                  3 => 
                  array (
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'NetPriceProductTradePrice',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
            ),
            2 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'root',
              ),
              2 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'step',
                  1 => 'descendant-or-self',
                  2 => 
                  array (
                    0 => 'node',
                  ),
                  3 => 
                  array (
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'GrossPriceProductTradePrice',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'PEPPOL-EN16931-R121',
              'flag' => 'fatal',
              'test' => 'not(ram:BasisQuantity) or xs:decimal(ram:BasisQuantity) > 0',
              'ast' => 
              array (
                0 => 'or',
                1 => 
                array (
                  0 => 'call',
                  1 => 'fn:not',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'BasisQuantity',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'gcmp',
                  1 => '>',
                  2 => 
                  array (
                    0 => 'call',
                    1 => 'xs:decimal',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'BasisQuantity',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:integer',
                    2 => '0',
                  ),
                ),
              ),
              'message' => '[PEPPOL-EN16931-R121]-Base quantity MUST be a positive number above zero.',
            ),
          ),
        ),
        13 => 
        array (
          'context' => 'ram:NetPriceProductTradePrice/ram:BasisQuantity[@unitCode] | ram:GrossPriceProductTradePrice/ram:BasisQuantity[@unitCode]',
          'match' => 
          array (
            0 => 'union',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'root',
                ),
                2 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'step',
                    1 => 'descendant-or-self',
                    2 => 
                    array (
                      0 => 'node',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'NetPriceProductTradePrice',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'BasisQuantity',
                ),
                3 => 
                array (
                  0 => 
                  array (
                    0 => 'step',
                    1 => 'attribute',
                    2 => 
                    array (
                      0 => 'name',
                      1 => '',
                      2 => 'unitCode',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
              ),
            ),
            2 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'root',
                ),
                2 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'step',
                    1 => 'descendant-or-self',
                    2 => 
                    array (
                      0 => 'node',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'GrossPriceProductTradePrice',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'BasisQuantity',
                ),
                3 => 
                array (
                  0 => 
                  array (
                    0 => 'step',
                    1 => 'attribute',
                    2 => 
                    array (
                      0 => 'name',
                      1 => '',
                      2 => 'unitCode',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'PEPPOL-EN16931-R130',
              'flag' => 'fatal',
              'test' => '@unitCode = ../../../ram:SpecifiedLineTradeDelivery/ram:BilledQuantity/@unitCode',
              'ast' => 
              array (
                0 => 'gcmp',
                1 => '=',
                2 => 
                array (
                  0 => 'step',
                  1 => 'attribute',
                  2 => 
                  array (
                    0 => 'name',
                    1 => '',
                    2 => 'unitCode',
                  ),
                  3 => 
                  array (
                  ),
                ),
                3 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'step',
                            1 => 'parent',
                            2 => 
                            array (
                              0 => 'node',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'parent',
                            2 => 
                            array (
                              0 => 'node',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'parent',
                          2 => 
                          array (
                            0 => 'node',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'SpecifiedLineTradeDelivery',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'BilledQuantity',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'attribute',
                    2 => 
                    array (
                      0 => 'name',
                      1 => '',
                      2 => 'unitCode',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
              ),
              'message' => '[PEPPOL-EN16931-R130]-Unit code of price base quantity MUST be same as invoiced quantity.',
            ),
          ),
        ),
      ),
    ),
    2 => 
    array (
      'id' => 'peppol-cii-pattern-0-a',
      'lets' => 
      array (
      ),
      'rules' => 
      array (
        0 => 
        array (
          'context' => '//*[not(name() = \'ram:ApplicableHeaderTradeDelivery\') and not(*) and not(normalize-space())]',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'root',
            ),
            2 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'step',
                1 => 'descendant-or-self',
                2 => 
                array (
                  0 => 'node',
                ),
                3 => 
                array (
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'any-name',
                ),
                3 => 
                array (
                  0 => 
                  array (
                    0 => 'and',
                    1 => 
                    array (
                      0 => 'and',
                      1 => 
                      array (
                        0 => 'call',
                        1 => 'fn:not',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'gcmp',
                            1 => '=',
                            2 => 
                            array (
                              0 => 'call',
                              1 => 'fn:name',
                              2 => 
                              array (
                              ),
                            ),
                            3 => 
                            array (
                              0 => 'literal',
                              1 => 'xs:string',
                              2 => 'ram:ApplicableHeaderTradeDelivery',
                            ),
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'call',
                        1 => 'fn:not',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'any-name',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'call',
                      1 => 'fn:not',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'call',
                          1 => 'fn:normalize-space',
                          2 => 
                          array (
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'PEPPOL-EN16931-R008',
              'flag' => 'fatal',
              'test' => 'false()',
              'ast' => 
              array (
                0 => 'call',
                1 => 'fn:false',
                2 => 
                array (
                ),
              ),
              'message' => '[PEPPOL-EN16931-R008]-Document MUST not contain empty elements.',
            ),
          ),
        ),
      ),
    ),
    3 => 
    array (
      'id' => 'peppol-cii-pattern-0-b',
      'lets' => 
      array (
      ),
      'rules' => 
      array (
        0 => 
        array (
          'context' => 'rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'root',
                  ),
                  2 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'step',
                      1 => 'descendant-or-self',
                      2 => 
                      array (
                        0 => 'node',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                        2 => 'SupplyChainTradeTransaction',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'IncludedSupplyChainTradeLineItem',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'SpecifiedLineTradeAgreement',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                2 => 'GrossPriceProductTradePrice',
              ),
              3 => 
              array (
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'PEPPOL-EN16931-R044',
              'flag' => 'fatal',
              'test' => 'not(ram:AppliedTradeAllowanceCharge/ram:ActualAmount) or ram:AppliedTradeAllowanceCharge/ram:ChargeIndicator/udt:Indicator = \'false\'',
              'ast' => 
              array (
                0 => 'or',
                1 => 
                array (
                  0 => 'call',
                  1 => 'fn:not',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'AppliedTradeAllowanceCharge',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'ActualAmount',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'gcmp',
                  1 => '=',
                  2 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'AppliedTradeAllowanceCharge',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'ChargeIndicator',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
                        2 => 'Indicator',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:string',
                    2 => 'false',
                  ),
                ),
              ),
              'message' => '[PEPPOL-EN16931-R044]-Charge on price level is NOT allowed. Only value \'false\' allowed.',
            ),
            1 => 
            array (
              'id' => 'PEPPOL-EN16931-R046',
              'flag' => 'fatal',
              'test' => 'not(ram:ChargeAmount) or xs:decimal(../ram:NetPriceProductTradePrice/ram:ChargeAmount) = xs:decimal(ram:ChargeAmount) - u:decimalOrZero(ram:AppliedTradeAllowanceCharge/ram:ActualAmount[1])',
              'ast' => 
              array (
                0 => 'or',
                1 => 
                array (
                  0 => 'call',
                  1 => 'fn:not',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'ChargeAmount',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'gcmp',
                  1 => '=',
                  2 => 
                  array (
                    0 => 'call',
                    1 => 'xs:decimal',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'step',
                            1 => 'parent',
                            2 => 
                            array (
                              0 => 'node',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'NetPriceProductTradePrice',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'ChargeAmount',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'arith',
                    1 => '-',
                    2 => 
                    array (
                      0 => 'call',
                      1 => 'xs:decimal',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'ChargeAmount',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                    ),
                    3 => 
                    array (
                      0 => 'ucall',
                      1 => 'utils',
                      2 => 'decimalOrZero',
                      3 => 
                      array (
                        0 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'AppliedTradeAllowanceCharge',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'ActualAmount',
                            ),
                            3 => 
                            array (
                              0 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:integer',
                                2 => '1',
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[PEPPOL-EN16931-R046]-Item net price MUST equal (Gross price - Allowance amount) when gross price is provided.',
            ),
          ),
        ),
      ),
    ),
    4 => 
    array (
      'id' => 'cii-pattern',
      'lets' => 
      array (
      ),
      'rules' => 
      array (
        0 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'root',
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                2 => 'CrossIndustryInvoice',
              ),
              3 => 
              array (
              ),
            ),
          ),
          'lets' => 
          array (
            0 => 
            array (
              'name' => 'BT-89-path',
              'value' => 'rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:DirectDebitMandateID',
              'ast' => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                        2 => 'SupplyChainTradeTransaction',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'ApplicableHeaderTradeSettlement',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'SpecifiedTradePaymentTerms',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'DirectDebitMandateID',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
            ),
            1 => 
            array (
              'name' => 'BT-90-path',
              'value' => 'rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:CreditorReferenceID',
              'ast' => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                      2 => 'SupplyChainTradeTransaction',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'ApplicableHeaderTradeSettlement',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'CreditorReferenceID',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'name' => 'BT-91-path',
              'value' => 'rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementPaymentMeans/ram:PayerPartyDebtorFinancialAccount/ram:IBANID',
              'ast' => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                          2 => 'SupplyChainTradeTransaction',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'ApplicableHeaderTradeSettlement',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'SpecifiedTradeSettlementPaymentMeans',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'PayerPartyDebtorFinancialAccount',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'IBANID',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
            ),
            3 => 
            array (
              'name' => 'BG-19-not-existing',
              'value' => 'not(exists(($BT-89-path, $BT-90-path, $BT-91-path)))',
              'ast' => 
              array (
                0 => 'call',
                1 => 'fn:not',
                2 => 
                array (
                  0 => 
                  array (
                    0 => 'call',
                    1 => 'fn:exists',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'sequence',
                        1 => 
                        array (
                          0 => 
                          array (
                            0 => 'var',
                            1 => 'BT-89-path',
                          ),
                          1 => 
                          array (
                            0 => 'var',
                            1 => 'BT-90-path',
                          ),
                          2 => 
                          array (
                            0 => 'var',
                            1 => 'BT-91-path',
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-DE-30',
              'flag' => 'fatal',
              'test' => '(($BT-89-path or $BT-91-path) and $BT-90-path) or $BG-19-not-existing',
              'ast' => 
              array (
                0 => 'or',
                1 => 
                array (
                  0 => 'and',
                  1 => 
                  array (
                    0 => 'or',
                    1 => 
                    array (
                      0 => 'var',
                      1 => 'BT-89-path',
                    ),
                    2 => 
                    array (
                      0 => 'var',
                      1 => 'BT-91-path',
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'var',
                    1 => 'BT-90-path',
                  ),
                ),
                2 => 
                array (
                  0 => 'var',
                  1 => 'BG-19-not-existing',
                ),
              ),
              'message' => '[BR-DE-30] Wenn "DIRECT DEBIT" BG-19 vorhanden ist, dann muss "Bank assigned creditor identifier" BT-90 übermittelt werden.',
            ),
            1 => 
            array (
              'id' => 'BR-DE-31',
              'flag' => 'fatal',
              'test' => '(($BT-89-path or $BT-90-path) and $BT-91-path) or $BG-19-not-existing',
              'ast' => 
              array (
                0 => 'or',
                1 => 
                array (
                  0 => 'and',
                  1 => 
                  array (
                    0 => 'or',
                    1 => 
                    array (
                      0 => 'var',
                      1 => 'BT-89-path',
                    ),
                    2 => 
                    array (
                      0 => 'var',
                      1 => 'BT-90-path',
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'var',
                    1 => 'BT-91-path',
                  ),
                ),
                2 => 
                array (
                  0 => 'var',
                  1 => 'BG-19-not-existing',
                ),
              ),
              'message' => '[BR-DE-31] Wenn "DIRECT DEBIT" BG-19 vorhanden ist, dann muss "Debited account identifier" BT-91 übermittelt werden.',
            ),
            2 => 
            array (
              'id' => 'BR-DE-1',
              'flag' => 'fatal',
              'test' => 'rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementPaymentMeans',
              'ast' => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                      2 => 'SupplyChainTradeTransaction',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'ApplicableHeaderTradeSettlement',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'SpecifiedTradeSettlementPaymentMeans',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              'message' => '[BR-DE-1] Eine Rechnung (INVOICE) muss Angaben zu "PAYMENT INSTRUCTIONS" (BG-16) enthalten.',
            ),
            3 => 
            array (
              'id' => 'BR-DE-15',
              'flag' => 'fatal',
              'test' => 'rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerReference[boolean(normalize-space(.))]',
              'ast' => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                      2 => 'SupplyChainTradeTransaction',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'ApplicableHeaderTradeAgreement',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'BuyerReference',
                  ),
                  3 => 
                  array (
                    0 => 
                    array (
                      0 => 'call',
                      1 => 'fn:boolean',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'call',
                          1 => 'fn:normalize-space',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'context',
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-15] Das Element "Buyer reference" (BT-10) muss übermittelt werden.',
            ),
            4 => 
            array (
              'id' => 'BR-DE-16',
              'flag' => 'fatal',
              'test' => 'not((rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:TypeCode = \'VAT\' and                          rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:CategoryCode = (\'S\', \'Z\', \'E\', \'AE\', \'K\', \'G\', \'L\', \'M\')) or                         (rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeAllowanceCharge/ram:CategoryTradeTax = \'VAT\' and                          rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeAllowanceCharge/ram:CategoryTradeTax/ram:CategoryCode = (\'S\', \'Z\', \'E\', \'AE\', \'K\', \'G\', \'L\', \'M\')) or                         (rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:TypeCode = \'VAT\' and                          rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:CategoryCode = (\'S\', \'Z\', \'E\', \'AE\', \'K\', \'G\', \'L\', \'M\'))) or                     ((rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:SpecifiedTaxRegistration/ram:ID[normalize-space(@schemeID)=\'VA\' or                                                                                                                                                   normalize-space(@schemeID)=\'FC\'][boolean(normalize-space(.))], rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTaxRepresentativeTradeParty))',
              'ast' => 
              array (
                0 => 'or',
                1 => 
                array (
                  0 => 'call',
                  1 => 'fn:not',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'or',
                      1 => 
                      array (
                        0 => 'or',
                        1 => 
                        array (
                          0 => 'and',
                          1 => 
                          array (
                            0 => 'gcmp',
                            1 => '=',
                            2 => 
                            array (
                              0 => 'path',
                              1 => 
                              array (
                                0 => 'path',
                                1 => 
                                array (
                                  0 => 'path',
                                  1 => 
                                  array (
                                    0 => 'path',
                                    1 => 
                                    array (
                                      0 => 'step',
                                      1 => 'child',
                                      2 => 
                                      array (
                                        0 => 'name',
                                        1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                                        2 => 'SupplyChainTradeTransaction',
                                      ),
                                      3 => 
                                      array (
                                      ),
                                    ),
                                    2 => 
                                    array (
                                      0 => 'step',
                                      1 => 'child',
                                      2 => 
                                      array (
                                        0 => 'name',
                                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                        2 => 'IncludedSupplyChainTradeLineItem',
                                      ),
                                      3 => 
                                      array (
                                      ),
                                    ),
                                  ),
                                  2 => 
                                  array (
                                    0 => 'step',
                                    1 => 'child',
                                    2 => 
                                    array (
                                      0 => 'name',
                                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                      2 => 'SpecifiedLineTradeSettlement',
                                    ),
                                    3 => 
                                    array (
                                    ),
                                  ),
                                ),
                                2 => 
                                array (
                                  0 => 'step',
                                  1 => 'child',
                                  2 => 
                                  array (
                                    0 => 'name',
                                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                    2 => 'ApplicableTradeTax',
                                  ),
                                  3 => 
                                  array (
                                  ),
                                ),
                              ),
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                  2 => 'TypeCode',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                            3 => 
                            array (
                              0 => 'literal',
                              1 => 'xs:string',
                              2 => 'VAT',
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'gcmp',
                            1 => '=',
                            2 => 
                            array (
                              0 => 'path',
                              1 => 
                              array (
                                0 => 'path',
                                1 => 
                                array (
                                  0 => 'path',
                                  1 => 
                                  array (
                                    0 => 'path',
                                    1 => 
                                    array (
                                      0 => 'step',
                                      1 => 'child',
                                      2 => 
                                      array (
                                        0 => 'name',
                                        1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                                        2 => 'SupplyChainTradeTransaction',
                                      ),
                                      3 => 
                                      array (
                                      ),
                                    ),
                                    2 => 
                                    array (
                                      0 => 'step',
                                      1 => 'child',
                                      2 => 
                                      array (
                                        0 => 'name',
                                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                        2 => 'IncludedSupplyChainTradeLineItem',
                                      ),
                                      3 => 
                                      array (
                                      ),
                                    ),
                                  ),
                                  2 => 
                                  array (
                                    0 => 'step',
                                    1 => 'child',
                                    2 => 
                                    array (
                                      0 => 'name',
                                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                      2 => 'SpecifiedLineTradeSettlement',
                                    ),
                                    3 => 
                                    array (
                                    ),
                                  ),
                                ),
                                2 => 
                                array (
                                  0 => 'step',
                                  1 => 'child',
                                  2 => 
                                  array (
                                    0 => 'name',
                                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                    2 => 'ApplicableTradeTax',
                                  ),
                                  3 => 
                                  array (
                                  ),
                                ),
                              ),
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                  2 => 'CategoryCode',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                            3 => 
                            array (
                              0 => 'sequence',
                              1 => 
                              array (
                                0 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:string',
                                  2 => 'S',
                                ),
                                1 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:string',
                                  2 => 'Z',
                                ),
                                2 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:string',
                                  2 => 'E',
                                ),
                                3 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:string',
                                  2 => 'AE',
                                ),
                                4 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:string',
                                  2 => 'K',
                                ),
                                5 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:string',
                                  2 => 'G',
                                ),
                                6 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:string',
                                  2 => 'L',
                                ),
                                7 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:string',
                                  2 => 'M',
                                ),
                              ),
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'and',
                          1 => 
                          array (
                            0 => 'gcmp',
                            1 => '=',
                            2 => 
                            array (
                              0 => 'path',
                              1 => 
                              array (
                                0 => 'path',
                                1 => 
                                array (
                                  0 => 'path',
                                  1 => 
                                  array (
                                    0 => 'step',
                                    1 => 'child',
                                    2 => 
                                    array (
                                      0 => 'name',
                                      1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                                      2 => 'SupplyChainTradeTransaction',
                                    ),
                                    3 => 
                                    array (
                                    ),
                                  ),
                                  2 => 
                                  array (
                                    0 => 'step',
                                    1 => 'child',
                                    2 => 
                                    array (
                                      0 => 'name',
                                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                      2 => 'ApplicableHeaderTradeSettlement',
                                    ),
                                    3 => 
                                    array (
                                    ),
                                  ),
                                ),
                                2 => 
                                array (
                                  0 => 'step',
                                  1 => 'child',
                                  2 => 
                                  array (
                                    0 => 'name',
                                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                    2 => 'SpecifiedTradeAllowanceCharge',
                                  ),
                                  3 => 
                                  array (
                                  ),
                                ),
                              ),
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                  2 => 'CategoryTradeTax',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                            3 => 
                            array (
                              0 => 'literal',
                              1 => 'xs:string',
                              2 => 'VAT',
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'gcmp',
                            1 => '=',
                            2 => 
                            array (
                              0 => 'path',
                              1 => 
                              array (
                                0 => 'path',
                                1 => 
                                array (
                                  0 => 'path',
                                  1 => 
                                  array (
                                    0 => 'path',
                                    1 => 
                                    array (
                                      0 => 'step',
                                      1 => 'child',
                                      2 => 
                                      array (
                                        0 => 'name',
                                        1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                                        2 => 'SupplyChainTradeTransaction',
                                      ),
                                      3 => 
                                      array (
                                      ),
                                    ),
                                    2 => 
                                    array (
                                      0 => 'step',
                                      1 => 'child',
                                      2 => 
                                      array (
                                        0 => 'name',
                                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                        2 => 'ApplicableHeaderTradeSettlement',
                                      ),
                                      3 => 
                                      array (
                                      ),
                                    ),
                                  ),
                                  2 => 
                                  array (
                                    0 => 'step',
                                    1 => 'child',
                                    2 => 
                                    array (
                                      0 => 'name',
                                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                      2 => 'SpecifiedTradeAllowanceCharge',
                                    ),
                                    3 => 
                                    array (
                                    ),
                                  ),
                                ),
                                2 => 
                                array (
                                  0 => 'step',
                                  1 => 'child',
                                  2 => 
                                  array (
                                    0 => 'name',
                                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                    2 => 'CategoryTradeTax',
                                  ),
                                  3 => 
                                  array (
                                  ),
                                ),
                              ),
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                  2 => 'CategoryCode',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                            3 => 
                            array (
                              0 => 'sequence',
                              1 => 
                              array (
                                0 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:string',
                                  2 => 'S',
                                ),
                                1 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:string',
                                  2 => 'Z',
                                ),
                                2 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:string',
                                  2 => 'E',
                                ),
                                3 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:string',
                                  2 => 'AE',
                                ),
                                4 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:string',
                                  2 => 'K',
                                ),
                                5 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:string',
                                  2 => 'G',
                                ),
                                6 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:string',
                                  2 => 'L',
                                ),
                                7 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:string',
                                  2 => 'M',
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'and',
                        1 => 
                        array (
                          0 => 'gcmp',
                          1 => '=',
                          2 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'path',
                              1 => 
                              array (
                                0 => 'path',
                                1 => 
                                array (
                                  0 => 'path',
                                  1 => 
                                  array (
                                    0 => 'step',
                                    1 => 'child',
                                    2 => 
                                    array (
                                      0 => 'name',
                                      1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                                      2 => 'SupplyChainTradeTransaction',
                                    ),
                                    3 => 
                                    array (
                                    ),
                                  ),
                                  2 => 
                                  array (
                                    0 => 'step',
                                    1 => 'child',
                                    2 => 
                                    array (
                                      0 => 'name',
                                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                      2 => 'IncludedSupplyChainTradeLineItem',
                                    ),
                                    3 => 
                                    array (
                                    ),
                                  ),
                                ),
                                2 => 
                                array (
                                  0 => 'step',
                                  1 => 'child',
                                  2 => 
                                  array (
                                    0 => 'name',
                                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                    2 => 'SpecifiedLineTradeSettlement',
                                  ),
                                  3 => 
                                  array (
                                  ),
                                ),
                              ),
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                  2 => 'ApplicableTradeTax',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'TypeCode',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          3 => 
                          array (
                            0 => 'literal',
                            1 => 'xs:string',
                            2 => 'VAT',
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'gcmp',
                          1 => '=',
                          2 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'path',
                              1 => 
                              array (
                                0 => 'path',
                                1 => 
                                array (
                                  0 => 'path',
                                  1 => 
                                  array (
                                    0 => 'step',
                                    1 => 'child',
                                    2 => 
                                    array (
                                      0 => 'name',
                                      1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                                      2 => 'SupplyChainTradeTransaction',
                                    ),
                                    3 => 
                                    array (
                                    ),
                                  ),
                                  2 => 
                                  array (
                                    0 => 'step',
                                    1 => 'child',
                                    2 => 
                                    array (
                                      0 => 'name',
                                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                      2 => 'IncludedSupplyChainTradeLineItem',
                                    ),
                                    3 => 
                                    array (
                                    ),
                                  ),
                                ),
                                2 => 
                                array (
                                  0 => 'step',
                                  1 => 'child',
                                  2 => 
                                  array (
                                    0 => 'name',
                                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                    2 => 'SpecifiedLineTradeSettlement',
                                  ),
                                  3 => 
                                  array (
                                  ),
                                ),
                              ),
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                  2 => 'ApplicableTradeTax',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'CategoryCode',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          3 => 
                          array (
                            0 => 'sequence',
                            1 => 
                            array (
                              0 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:string',
                                2 => 'S',
                              ),
                              1 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:string',
                                2 => 'Z',
                              ),
                              2 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:string',
                                2 => 'E',
                              ),
                              3 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:string',
                                2 => 'AE',
                              ),
                              4 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:string',
                                2 => 'K',
                              ),
                              5 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:string',
                                2 => 'G',
                              ),
                              6 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:string',
                                2 => 'L',
                              ),
                              7 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:string',
                                2 => 'M',
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'sequence',
                  1 => 
                  array (
                    0 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                                2 => 'SupplyChainTradeTransaction',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'ApplicableHeaderTradeAgreement',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'SellerTradeParty',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'SpecifiedTaxRegistration',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'ID',
                        ),
                        3 => 
                        array (
                          0 => 
                          array (
                            0 => 'or',
                            1 => 
                            array (
                              0 => 'gcmp',
                              1 => '=',
                              2 => 
                              array (
                                0 => 'call',
                                1 => 'fn:normalize-space',
                                2 => 
                                array (
                                  0 => 
                                  array (
                                    0 => 'step',
                                    1 => 'attribute',
                                    2 => 
                                    array (
                                      0 => 'name',
                                      1 => '',
                                      2 => 'schemeID',
                                    ),
                                    3 => 
                                    array (
                                    ),
                                  ),
                                ),
                              ),
                              3 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:string',
                                2 => 'VA',
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'gcmp',
                              1 => '=',
                              2 => 
                              array (
                                0 => 'call',
                                1 => 'fn:normalize-space',
                                2 => 
                                array (
                                  0 => 
                                  array (
                                    0 => 'step',
                                    1 => 'attribute',
                                    2 => 
                                    array (
                                      0 => 'name',
                                      1 => '',
                                      2 => 'schemeID',
                                    ),
                                    3 => 
                                    array (
                                    ),
                                  ),
                                ),
                              ),
                              3 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:string',
                                2 => 'FC',
                              ),
                            ),
                          ),
                          1 => 
                          array (
                            0 => 'call',
                            1 => 'fn:boolean',
                            2 => 
                            array (
                              0 => 
                              array (
                                0 => 'call',
                                1 => 'fn:normalize-space',
                                2 => 
                                array (
                                  0 => 
                                  array (
                                    0 => 'context',
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                            2 => 'SupplyChainTradeTransaction',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'ApplicableHeaderTradeAgreement',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'SellerTaxRepresentativeTradeParty',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-16] Wenn in einer Rechnung die Steuercodes S, Z, E, AE, K, G, L oder M verwendet werden, muss mindestens eines der Elemente "Seller VAT identifier" (BT-31), "Seller tax registration identifier" (BT-32) oder "SELLER TAX REPRESENTATIVE PARTY" (BG-11) übermittelt werden.',
            ),
            5 => 
            array (
              'id' => 'BR-DE-17',
              'flag' => 'warning',
              'test' => 'normalize-space(rsm:ExchangedDocument/ram:TypeCode) = (\'326\', \'380\', \'384\', \'389\', \'381\', \'875\', \'876\', \'877\')',
              'ast' => 
              array (
                0 => 'gcmp',
                1 => '=',
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:normalize-space',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                          2 => 'ExchangedDocument',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'TypeCode',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                  ),
                ),
                3 => 
                array (
                  0 => 'sequence',
                  1 => 
                  array (
                    0 => 
                    array (
                      0 => 'literal',
                      1 => 'xs:string',
                      2 => '326',
                    ),
                    1 => 
                    array (
                      0 => 'literal',
                      1 => 'xs:string',
                      2 => '380',
                    ),
                    2 => 
                    array (
                      0 => 'literal',
                      1 => 'xs:string',
                      2 => '384',
                    ),
                    3 => 
                    array (
                      0 => 'literal',
                      1 => 'xs:string',
                      2 => '389',
                    ),
                    4 => 
                    array (
                      0 => 'literal',
                      1 => 'xs:string',
                      2 => '381',
                    ),
                    5 => 
                    array (
                      0 => 'literal',
                      1 => 'xs:string',
                      2 => '875',
                    ),
                    6 => 
                    array (
                      0 => 'literal',
                      1 => 'xs:string',
                      2 => '876',
                    ),
                    7 => 
                    array (
                      0 => 'literal',
                      1 => 'xs:string',
                      2 => '877',
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-17] Mit dem Element "Invoice type code" (BT-3) sollen ausschließlich folgende Codes aus der Codeliste UNTDID 1001 übermittelt werden: 326 (Partial invoice), 380 (Commercial invoice), 384 (Corrected invoice), 389 (Self-billed invoice) und 381 (Credit note),875 (Partial construction invoice), 876 (Partial final construction invoice), 877 (Final construction invoice).',
            ),
            6 => 
            array (
              'id' => 'BR-DE-18',
              'flag' => 'fatal',
              'test' => 'every $line                        in rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:Description[1]/tokenize(. , \'(\\r?\\n)\')[starts-with( normalize-space(.) , \'#\')]                        satisfies matches ( normalize-space ($line), $XR-SKONTO-REGEX ) and                     matches( rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:Description[1]/tokenize(. ,  \'#.+#\')[last()], \'^\\s*\\n\' )',
              'ast' => 
              array (
                0 => 'quant',
                1 => 'every',
                2 => 
                array (
                  0 => 
                  array (
                    0 => 'line',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                                2 => 'SupplyChainTradeTransaction',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'ApplicableHeaderTradeSettlement',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'SpecifiedTradePaymentTerms',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'Description',
                          ),
                          3 => 
                          array (
                            0 => 
                            array (
                              0 => 'literal',
                              1 => 'xs:integer',
                              2 => '1',
                            ),
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'filter',
                        1 => 
                        array (
                          0 => 'call',
                          1 => 'fn:tokenize',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'context',
                            ),
                            1 => 
                            array (
                              0 => 'literal',
                              1 => 'xs:string',
                              2 => '(\\r?\\n)',
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'call',
                            1 => 'fn:starts-with',
                            2 => 
                            array (
                              0 => 
                              array (
                                0 => 'call',
                                1 => 'fn:normalize-space',
                                2 => 
                                array (
                                  0 => 
                                  array (
                                    0 => 'context',
                                  ),
                                ),
                              ),
                              1 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:string',
                                2 => '#',
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
                3 => 
                array (
                  0 => 'and',
                  1 => 
                  array (
                    0 => 'call',
                    1 => 'fn:matches',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'call',
                        1 => 'fn:normalize-space',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'var',
                            1 => 'line',
                          ),
                        ),
                      ),
                      1 => 
                      array (
                        0 => 'var',
                        1 => 'XR-SKONTO-REGEX',
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'call',
                    1 => 'fn:matches',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'path',
                              1 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                                  2 => 'SupplyChainTradeTransaction',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                  2 => 'ApplicableHeaderTradeSettlement',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'SpecifiedTradePaymentTerms',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'Description',
                            ),
                            3 => 
                            array (
                              0 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:integer',
                                2 => '1',
                              ),
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'filter',
                          1 => 
                          array (
                            0 => 'call',
                            1 => 'fn:tokenize',
                            2 => 
                            array (
                              0 => 
                              array (
                                0 => 'context',
                              ),
                              1 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:string',
                                2 => '#.+#',
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'call',
                              1 => 'fn:last',
                              2 => 
                              array (
                              ),
                            ),
                          ),
                        ),
                      ),
                      1 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:string',
                        2 => '^\\s*\\n',
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-18] Skonto Zeilen in muessen diesem regulärem Ausdruck entsprechen: . Die Informationen zur Gewährung von Skonto müssen wie folgt im Element "Payment terms" (BT-20) übermittelt werden: Anzugeben ist im ersten Segment "SKONTO", im zweiten "TAGE=n", im dritten "PROZENT=n". Prozentzahlen sind ohne Vorzeichen sowie mit Punkt getrennt von zwei Nachkommastellen anzugeben. Liegt dem zu berechnenden Betrag nicht BT-115, "fälliger Betrag" zugrunde, sondern nur ein Teil des fälligen Betrags der Rechnung, ist der Grundwert zur Berechnung von Skonto als viertes Segment "BASISBETRAG=n" gemäß dem semantischen Datentypen Amount anzugeben. Jeder Eintrag beginnt mit einer #, die Segmente sind mit einer # getrennt und eine Zeile schließt mit einer # ab. Am Ende einer vollständigen Skontoangabe muss ein XML-konformer Zeilenumbruch folgen. Alle Angaben zur Gewährung von Skonto müssen in Großbuchstaben gemacht werden. Zusätzliches Whitespace (Leerzeichen, Tabulatoren oder Zeilenumbrüche) ist nicht zulässig. Andere Zeichen oder Texte als in den oberen Vorgaben genannt sind nicht zulässig.',
              'messageParts' => 
              array (
                0 => '[BR-DE-18] Skonto Zeilen in ',
                1 => 
                array (
                  'name' => true,
                ),
                2 => ' muessen diesem regulärem Ausdruck entsprechen: ',
                3 => 
                array (
                  'valueOf' => 
                  array (
                    0 => 'var',
                    1 => 'XR-SKONTO-REGEX',
                  ),
                ),
                4 => '. Die Informationen zur Gewährung von Skonto müssen wie folgt im Element "Payment terms" (BT-20) übermittelt werden: Anzugeben ist im ersten Segment "SKONTO", im zweiten "TAGE=n", im dritten "PROZENT=n". Prozentzahlen sind ohne Vorzeichen sowie mit Punkt getrennt von zwei Nachkommastellen anzugeben. Liegt dem zu berechnenden Betrag nicht BT-115, "fälliger Betrag" zugrunde, sondern nur ein Teil des fälligen Betrags der Rechnung, ist der Grundwert zur Berechnung von Skonto als viertes Segment "BASISBETRAG=n" gemäß dem semantischen Datentypen Amount anzugeben. Jeder Eintrag beginnt mit einer #, die Segmente sind mit einer # getrennt und eine Zeile schließt mit einer # ab. Am Ende einer vollständigen Skontoangabe muss ein XML-konformer Zeilenumbruch folgen. Alle Angaben zur Gewährung von Skonto müssen in Großbuchstaben gemacht werden. Zusätzliches Whitespace (Leerzeichen, Tabulatoren oder Zeilenumbrüche) ist nicht zulässig. Andere Zeichen oder Texte als in den oberen Vorgaben genannt sind nicht zulässig.',
              ),
            ),
            7 => 
            array (
              'id' => 'BR-DE-22',
              'flag' => 'fatal',
              'test' => 'count(//ram:AdditionalReferencedDocument) = count(//ram:AdditionalReferencedDocument[not(./ram:AttachmentBinaryObject/@filename = preceding-sibling::ram:AdditionalReferencedDocument/ram:AttachmentBinaryObject/@filename)])',
              'ast' => 
              array (
                0 => 'gcmp',
                1 => '=',
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:count',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'root',
                      ),
                      2 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'step',
                          1 => 'descendant-or-self',
                          2 => 
                          array (
                            0 => 'node',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'AdditionalReferencedDocument',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
                3 => 
                array (
                  0 => 'call',
                  1 => 'fn:count',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'root',
                      ),
                      2 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'step',
                          1 => 'descendant-or-self',
                          2 => 
                          array (
                            0 => 'node',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'AdditionalReferencedDocument',
                          ),
                          3 => 
                          array (
                            0 => 
                            array (
                              0 => 'call',
                              1 => 'fn:not',
                              2 => 
                              array (
                                0 => 
                                array (
                                  0 => 'gcmp',
                                  1 => '=',
                                  2 => 
                                  array (
                                    0 => 'path',
                                    1 => 
                                    array (
                                      0 => 'path',
                                      1 => 
                                      array (
                                        0 => 'context',
                                      ),
                                      2 => 
                                      array (
                                        0 => 'step',
                                        1 => 'child',
                                        2 => 
                                        array (
                                          0 => 'name',
                                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                          2 => 'AttachmentBinaryObject',
                                        ),
                                        3 => 
                                        array (
                                        ),
                                      ),
                                    ),
                                    2 => 
                                    array (
                                      0 => 'step',
                                      1 => 'attribute',
                                      2 => 
                                      array (
                                        0 => 'name',
                                        1 => '',
                                        2 => 'filename',
                                      ),
                                      3 => 
                                      array (
                                      ),
                                    ),
                                  ),
                                  3 => 
                                  array (
                                    0 => 'path',
                                    1 => 
                                    array (
                                      0 => 'path',
                                      1 => 
                                      array (
                                        0 => 'step',
                                        1 => 'preceding-sibling',
                                        2 => 
                                        array (
                                          0 => 'name',
                                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                          2 => 'AdditionalReferencedDocument',
                                        ),
                                        3 => 
                                        array (
                                        ),
                                      ),
                                      2 => 
                                      array (
                                        0 => 'step',
                                        1 => 'child',
                                        2 => 
                                        array (
                                          0 => 'name',
                                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                          2 => 'AttachmentBinaryObject',
                                        ),
                                        3 => 
                                        array (
                                        ),
                                      ),
                                    ),
                                    2 => 
                                    array (
                                      0 => 'step',
                                      1 => 'attribute',
                                      2 => 
                                      array (
                                        0 => 'name',
                                        1 => '',
                                        2 => 'filename',
                                      ),
                                      3 => 
                                      array (
                                      ),
                                    ),
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-22] Not all filename attributes of the embeddedDocumentBinaryObject elements are unique',
            ),
            8 => 
            array (
              'id' => 'BR-DE-26',
              'flag' => 'warning',
              'test' => 'not(normalize-space(rsm:ExchangedDocument/ram:TypeCode) = \'384\') or                     (rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:InvoiceReferencedDocument)',
              'ast' => 
              array (
                0 => 'or',
                1 => 
                array (
                  0 => 'call',
                  1 => 'fn:not',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'gcmp',
                      1 => '=',
                      2 => 
                      array (
                        0 => 'call',
                        1 => 'fn:normalize-space',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                                2 => 'ExchangedDocument',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'TypeCode',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                        ),
                      ),
                      3 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:string',
                        2 => '384',
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                        2 => 'SupplyChainTradeTransaction',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'ApplicableHeaderTradeSettlement',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'InvoiceReferencedDocument',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-26] Wenn im Element Invoice type code (BT-3) der Code 384 (Corrected invoice) übergeben wird, soll PRECEDING INVOICE REFERENCE BG-3 mind. einmal vorhanden sein.',
            ),
          ),
        ),
        1 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'root',
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                  2 => 'CrossIndustryInvoice',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                2 => 'ExchangedDocumentContext',
              ),
              3 => 
              array (
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-DE-21',
              'flag' => 'warning',
              'test' => 'ram:GuidelineSpecifiedDocumentContextParameter/ram:ID = $XR-CIUS-ID or                     ram:GuidelineSpecifiedDocumentContextParameter/ram:ID = $XR-EXTENSION-ID or                     ram:GuidelineSpecifiedDocumentContextParameter/ram:ID = $XR-CVD-ID',
              'ast' => 
              array (
                0 => 'or',
                1 => 
                array (
                  0 => 'or',
                  1 => 
                  array (
                    0 => 'gcmp',
                    1 => '=',
                    2 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'GuidelineSpecifiedDocumentContextParameter',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'ID',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    3 => 
                    array (
                      0 => 'var',
                      1 => 'XR-CIUS-ID',
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'gcmp',
                    1 => '=',
                    2 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'GuidelineSpecifiedDocumentContextParameter',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'ID',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    3 => 
                    array (
                      0 => 'var',
                      1 => 'XR-EXTENSION-ID',
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'gcmp',
                  1 => '=',
                  2 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'GuidelineSpecifiedDocumentContextParameter',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'ID',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'var',
                    1 => 'XR-CVD-ID',
                  ),
                ),
              ),
              'message' => '[BR-DE-21] Das Element "Specification identifier" (BT-24) soll syntaktisch der Kennung des Standards XRechnung entsprechen.',
            ),
          ),
        ),
        2 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'root',
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                      2 => 'CrossIndustryInvoice',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                    2 => 'SupplyChainTradeTransaction',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'ApplicableHeaderTradeAgreement',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                2 => 'SellerTradeParty',
              ),
              3 => 
              array (
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-DE-2',
              'flag' => 'fatal',
              'test' => 'ram:DefinedTradeContact',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'DefinedTradeContact',
                ),
                3 => 
                array (
                ),
              ),
              'message' => '[BR-DE-2] Die Gruppe "SELLER CONTACT" (BG-6) muss übermittelt werden.',
            ),
          ),
        ),
        3 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:PostalTradeAddress',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'root',
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                        2 => 'CrossIndustryInvoice',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                      2 => 'SupplyChainTradeTransaction',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'ApplicableHeaderTradeAgreement',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'SellerTradeParty',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                2 => 'PostalTradeAddress',
              ),
              3 => 
              array (
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-DE-3',
              'flag' => 'fatal',
              'test' => 'ram:CityName[boolean(normalize-space(.))]',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'CityName',
                ),
                3 => 
                array (
                  0 => 
                  array (
                    0 => 'call',
                    1 => 'fn:boolean',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'call',
                        1 => 'fn:normalize-space',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'context',
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-3] Das Element "Seller city" (BT-37) muss übermittelt werden.',
            ),
            1 => 
            array (
              'id' => 'BR-DE-4',
              'flag' => 'fatal',
              'test' => 'ram:PostcodeCode[boolean(normalize-space(.))]',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'PostcodeCode',
                ),
                3 => 
                array (
                  0 => 
                  array (
                    0 => 'call',
                    1 => 'fn:boolean',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'call',
                        1 => 'fn:normalize-space',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'context',
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-4] Das Element "Seller post code" (BT-38) muss übermittelt werden.',
            ),
          ),
        ),
        4 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:DefinedTradeContact',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'root',
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                        2 => 'CrossIndustryInvoice',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                      2 => 'SupplyChainTradeTransaction',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'ApplicableHeaderTradeAgreement',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'SellerTradeParty',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                2 => 'DefinedTradeContact',
              ),
              3 => 
              array (
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-DE-5',
              'flag' => 'fatal',
              'test' => '(ram:PersonName,ram:DepartmentName)[boolean(normalize-space(.))]',
              'ast' => 
              array (
                0 => 'filter',
                1 => 
                array (
                  0 => 'sequence',
                  1 => 
                  array (
                    0 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'PersonName',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                    1 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'DepartmentName',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 
                  array (
                    0 => 'call',
                    1 => 'fn:boolean',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'call',
                        1 => 'fn:normalize-space',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'context',
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-5] Das Element "Seller contact point" (BT-41) muss übermittelt werden.',
            ),
            1 => 
            array (
              'id' => 'BR-DE-6',
              'flag' => 'fatal',
              'test' => 'ram:TelephoneUniversalCommunication/ram:CompleteNumber[boolean(normalize-space(.))]',
              'ast' => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'TelephoneUniversalCommunication',
                  ),
                  3 => 
                  array (
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'CompleteNumber',
                  ),
                  3 => 
                  array (
                    0 => 
                    array (
                      0 => 'call',
                      1 => 'fn:boolean',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'call',
                          1 => 'fn:normalize-space',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'context',
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-6] Das Element "Seller contact telephone number" (BT-42) muss übermittelt werden.',
            ),
            2 => 
            array (
              'id' => 'BR-DE-7',
              'flag' => 'fatal',
              'test' => 'ram:EmailURIUniversalCommunication/ram:URIID[boolean(normalize-space(.))]',
              'ast' => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'EmailURIUniversalCommunication',
                  ),
                  3 => 
                  array (
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'URIID',
                  ),
                  3 => 
                  array (
                    0 => 
                    array (
                      0 => 'call',
                      1 => 'fn:boolean',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'call',
                          1 => 'fn:normalize-space',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'context',
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-7] Das Element "Seller contact email address" (BT-43) muss übermittelt werden.',
            ),
            3 => 
            array (
              'id' => 'BR-DE-27',
              'flag' => 'warning',
              'test' => 'matches(normalize-space(ram:TelephoneUniversalCommunication/ram:CompleteNumber), $XR-TELEPHONE-REGEX)',
              'ast' => 
              array (
                0 => 'call',
                1 => 'fn:matches',
                2 => 
                array (
                  0 => 
                  array (
                    0 => 'call',
                    1 => 'fn:normalize-space',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'TelephoneUniversalCommunication',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'CompleteNumber',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                    ),
                  ),
                  1 => 
                  array (
                    0 => 'var',
                    1 => 'XR-TELEPHONE-REGEX',
                  ),
                ),
              ),
              'message' => '[BR-DE-27] In BT-42 sollen mindestens drei Ziffern enthalten sein.',
            ),
            4 => 
            array (
              'id' => 'BR-DE-28',
              'flag' => 'warning',
              'test' => 'matches(normalize-space(ram:EmailURIUniversalCommunication/ram:URIID), $XR-EMAIL-REGEX)',
              'ast' => 
              array (
                0 => 'call',
                1 => 'fn:matches',
                2 => 
                array (
                  0 => 
                  array (
                    0 => 'call',
                    1 => 'fn:normalize-space',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'EmailURIUniversalCommunication',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'URIID',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                    ),
                  ),
                  1 => 
                  array (
                    0 => 'var',
                    1 => 'XR-EMAIL-REGEX',
                  ),
                ),
              ),
              'message' => '[BR-DE-28] In BT-43 soll genau ein @-Zeichen enthalten sein, welches nicht von einem Leerzeichen, einem Punkt, aber mindestens zwei Zeichen auf beiden Seiten flankiert werden soll. Ein Punkt sollte nicht am Anfang oder am Ende stehen.',
            ),
          ),
        ),
        5 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty/ram:PostalTradeAddress',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'root',
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                        2 => 'CrossIndustryInvoice',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                      2 => 'SupplyChainTradeTransaction',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'ApplicableHeaderTradeAgreement',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'BuyerTradeParty',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                2 => 'PostalTradeAddress',
              ),
              3 => 
              array (
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-DE-8',
              'flag' => 'fatal',
              'test' => 'ram:CityName[boolean(normalize-space(.))]',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'CityName',
                ),
                3 => 
                array (
                  0 => 
                  array (
                    0 => 'call',
                    1 => 'fn:boolean',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'call',
                        1 => 'fn:normalize-space',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'context',
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-8] Das Element "Buyer city" (BT-52) muss übermittelt werden.',
            ),
            1 => 
            array (
              'id' => 'BR-DE-9',
              'flag' => 'fatal',
              'test' => 'ram:PostcodeCode[boolean(normalize-space(.))]',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'PostcodeCode',
                ),
                3 => 
                array (
                  0 => 
                  array (
                    0 => 'call',
                    1 => 'fn:boolean',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'call',
                        1 => 'fn:normalize-space',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'context',
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-9] Das Element "Buyer post code" (BT-53) muss übermittelt werden.',
            ),
          ),
        ),
        6 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:AdditionalReferencedDocument[ram:TypeCode = \'916\']',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'root',
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                      2 => 'CrossIndustryInvoice',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                    2 => 'SupplyChainTradeTransaction',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'ApplicableHeaderTradeAgreement',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                2 => 'AdditionalReferencedDocument',
              ),
              3 => 
              array (
                0 => 
                array (
                  0 => 'gcmp',
                  1 => '=',
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'TypeCode',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:string',
                    2 => '916',
                  ),
                ),
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-TMP-2',
              'flag' => 'fatal',
              'test' => 'not(exists(ram:URIID)) or (matches(ram:URIID, $XR-URL-REGEX))',
              'ast' => 
              array (
                0 => 'or',
                1 => 
                array (
                  0 => 'call',
                  1 => 'fn:not',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'call',
                      1 => 'fn:exists',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'URIID',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:matches',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'URIID',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                    1 => 
                    array (
                      0 => 'var',
                      1 => 'XR-URL-REGEX',
                    ),
                  ),
                ),
              ),
              'message' => '[BR-TMP-2] BT-124 "External document location" muss eine absolute URL mit gültigem Schema enthalten.',
            ),
            1 => 
            array (
              'id' => 'BR-TMP-4',
              'flag' => 'warning',
              'test' => 'count(ram:Name) <= 1',
              'ast' => 
              array (
                0 => 'gcmp',
                1 => '<=',
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:count',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'Name',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                ),
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '1',
                ),
              ),
              'message' => '[BR-TMP-4] BT-123 "Supporting document description" (ram:Name) darf innerhalb von "Additional supporting documents" (BG-24) höchstens einmal vorkommen.',
            ),
            2 => 
            array (
              'id' => 'BR-TMP-5',
              'flag' => 'warning',
              'test' => 'count(ram:AttachmentBinaryObject) <= 1',
              'ast' => 
              array (
                0 => 'gcmp',
                1 => '<=',
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:count',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'AttachmentBinaryObject',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                ),
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '1',
                ),
              ),
              'message' => '[BR-TMP-5] BT-125 "Attached document" (ram:AttachmentBinaryObject) darf innerhalb von "Additional Supporting documents" (BG-24) höchstens einmal vorkommen.',
            ),
          ),
        ),
        7 => 
        array (
          'context' => 'udt:DateTimeString | qdt:DateTimeString | udt:DateString',
          'match' => 
          array (
            0 => 'union',
            1 => 
            array (
              0 => 'union',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'root',
                ),
                2 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'step',
                    1 => 'descendant-or-self',
                    2 => 
                    array (
                      0 => 'node',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
                      2 => 'DateTimeString',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'root',
                ),
                2 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'step',
                    1 => 'descendant-or-self',
                    2 => 
                    array (
                      0 => 'node',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100',
                      2 => 'DateTimeString',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
              ),
            ),
            2 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'root',
              ),
              2 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'step',
                  1 => 'descendant-or-self',
                  2 => 
                  array (
                    0 => 'node',
                  ),
                  3 => 
                  array (
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
                    2 => 'DateString',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-TMP-7',
              'flag' => 'warning',
              'test' => 'normalize-space(@format) = \'102\' and matches(normalize-space(text()), \'^\\d{8}$\')',
              'ast' => 
              array (
                0 => 'and',
                1 => 
                array (
                  0 => 'gcmp',
                  1 => '=',
                  2 => 
                  array (
                    0 => 'call',
                    1 => 'fn:normalize-space',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'step',
                        1 => 'attribute',
                        2 => 
                        array (
                          0 => 'name',
                          1 => '',
                          2 => 'format',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:string',
                    2 => '102',
                  ),
                ),
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:matches',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'call',
                      1 => 'fn:normalize-space',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'text',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                    ),
                    1 => 
                    array (
                      0 => 'literal',
                      1 => 'xs:string',
                      2 => '^\\d{8}$',
                    ),
                  ),
                ),
              ),
              'message' => '[BR-TMP-7] Datumsangaben in UNCEFACT/CII müssen das Attribut format="102" tragen und im Format JJJJMMTT (YYYYMMDD) übermittelt werden.',
            ),
          ),
        ),
        8 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery/ram:ShipToTradeParty/ram:PostalTradeAddress',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'root',
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                        2 => 'CrossIndustryInvoice',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                      2 => 'SupplyChainTradeTransaction',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'ApplicableHeaderTradeDelivery',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'ShipToTradeParty',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                2 => 'PostalTradeAddress',
              ),
              3 => 
              array (
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-DE-10',
              'flag' => 'fatal',
              'test' => 'ram:CityName[boolean(normalize-space(.))]',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'CityName',
                ),
                3 => 
                array (
                  0 => 
                  array (
                    0 => 'call',
                    1 => 'fn:boolean',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'call',
                        1 => 'fn:normalize-space',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'context',
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-10] Das Element "Deliver to city" (BT-77) muss übermittelt werden, wenn die Gruppe "DELIVER TO ADDRESS" (BG-15) übermittelt wird.',
            ),
            1 => 
            array (
              'id' => 'BR-DE-11',
              'flag' => 'fatal',
              'test' => 'ram:PostcodeCode[boolean(normalize-space(.))]',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'PostcodeCode',
                ),
                3 => 
                array (
                  0 => 
                  array (
                    0 => 'call',
                    1 => 'fn:boolean',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'call',
                        1 => 'fn:normalize-space',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'context',
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-11] Das Element "Deliver to post code" (BT-78) muss übermittelt werden, wenn die Gruppe "DELIVER TO ADDRESS" (BG-15) übermittelt wird.',
            ),
          ),
        ),
        9 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementPaymentMeans[normalize-space(ram:TypeCode) = (\'30\',\'58\')]',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'root',
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                      2 => 'CrossIndustryInvoice',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                    2 => 'SupplyChainTradeTransaction',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'ApplicableHeaderTradeSettlement',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                2 => 'SpecifiedTradeSettlementPaymentMeans',
              ),
              3 => 
              array (
                0 => 
                array (
                  0 => 'gcmp',
                  1 => '=',
                  2 => 
                  array (
                    0 => 'call',
                    1 => 'fn:normalize-space',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'TypeCode',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'sequence',
                    1 => 
                    array (
                      0 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:string',
                        2 => '30',
                      ),
                      1 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:string',
                        2 => '58',
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-DE-19',
              'flag' => 'warning',
              'test' => 'not(normalize-space(ram:TypeCode) = \'58\') or                     xr:checkIBAN(string(ram:PayeePartyCreditorFinancialAccount/ram:IBANID))',
              'ast' => 
              array (
                0 => 'or',
                1 => 
                array (
                  0 => 'call',
                  1 => 'fn:not',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'gcmp',
                      1 => '=',
                      2 => 
                      array (
                        0 => 'call',
                        1 => 'fn:normalize-space',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'TypeCode',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                      ),
                      3 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:string',
                        2 => '58',
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'ucall',
                  1 => 'xrechnung',
                  2 => 'checkIBAN',
                  3 => 
                  array (
                    0 => 
                    array (
                      0 => 'call',
                      1 => 'fn:string',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'PayeePartyCreditorFinancialAccount',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'IBANID',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-19] "Payment account identifier" (BT-84) soll eine korrekte IBAN enthalten, wenn in "Payment means type code" (BT-81) mit dem Code 58 SEPA als Zahlungsmittel gefordert wird.',
            ),
            1 => 
            array (
              'id' => 'BR-DE-23-a',
              'flag' => 'fatal',
              'test' => 'ram:PayeePartyCreditorFinancialAccount',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'PayeePartyCreditorFinancialAccount',
                ),
                3 => 
                array (
                ),
              ),
              'message' => '[BR-DE-23-a] Wenn BT-81 "Payment means type code" einen Schlüssel für Überweisungen enthält (30, 58), muss BG-17 "CREDIT TRANSFER" übermittelt werden.',
            ),
            2 => 
            array (
              'id' => 'BR-DE-23-b',
              'flag' => 'fatal',
              'test' => 'not(ram:ApplicableTradeSettlementFinancialCard) and                     not(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:DirectDebitMandateID or                         /rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:CreditorReferenceID or                         ram:PayerPartyDebtorFinancialAccount/ram:IBANID)',
              'ast' => 
              array (
                0 => 'and',
                1 => 
                array (
                  0 => 'call',
                  1 => 'fn:not',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'ApplicableTradeSettlementFinancialCard',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:not',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'or',
                      1 => 
                      array (
                        0 => 'or',
                        1 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'path',
                              1 => 
                              array (
                                0 => 'path',
                                1 => 
                                array (
                                  0 => 'path',
                                  1 => 
                                  array (
                                    0 => 'root',
                                  ),
                                  2 => 
                                  array (
                                    0 => 'step',
                                    1 => 'child',
                                    2 => 
                                    array (
                                      0 => 'name',
                                      1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                                      2 => 'CrossIndustryInvoice',
                                    ),
                                    3 => 
                                    array (
                                    ),
                                  ),
                                ),
                                2 => 
                                array (
                                  0 => 'step',
                                  1 => 'child',
                                  2 => 
                                  array (
                                    0 => 'name',
                                    1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                                    2 => 'SupplyChainTradeTransaction',
                                  ),
                                  3 => 
                                  array (
                                  ),
                                ),
                              ),
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                  2 => 'ApplicableHeaderTradeSettlement',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'SpecifiedTradePaymentTerms',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'DirectDebitMandateID',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'path',
                              1 => 
                              array (
                                0 => 'path',
                                1 => 
                                array (
                                  0 => 'root',
                                ),
                                2 => 
                                array (
                                  0 => 'step',
                                  1 => 'child',
                                  2 => 
                                  array (
                                    0 => 'name',
                                    1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                                    2 => 'CrossIndustryInvoice',
                                  ),
                                  3 => 
                                  array (
                                  ),
                                ),
                              ),
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                                  2 => 'SupplyChainTradeTransaction',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'ApplicableHeaderTradeSettlement',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'CreditorReferenceID',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'PayerPartyDebtorFinancialAccount',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'IBANID',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-23-b] Wenn BT-81 "Payment means type code" einen Schlüssel für Überweisungen enthält (30, 58), dürfen BG-18 und BG-19 nicht übermittelt werden.',
            ),
          ),
        ),
        10 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementPaymentMeans[normalize-space(ram:TypeCode) = (\'48\',\'54\',\'55\')]',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'root',
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                      2 => 'CrossIndustryInvoice',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                    2 => 'SupplyChainTradeTransaction',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'ApplicableHeaderTradeSettlement',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                2 => 'SpecifiedTradeSettlementPaymentMeans',
              ),
              3 => 
              array (
                0 => 
                array (
                  0 => 'gcmp',
                  1 => '=',
                  2 => 
                  array (
                    0 => 'call',
                    1 => 'fn:normalize-space',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'TypeCode',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'sequence',
                    1 => 
                    array (
                      0 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:string',
                        2 => '48',
                      ),
                      1 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:string',
                        2 => '54',
                      ),
                      2 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:string',
                        2 => '55',
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-DE-24-a',
              'flag' => 'fatal',
              'test' => 'ram:ApplicableTradeSettlementFinancialCard',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'ApplicableTradeSettlementFinancialCard',
                ),
                3 => 
                array (
                ),
              ),
              'message' => '[BR-DE-24-a] Wenn BT-81 "Payment means type code" einen Schlüssel für Kartenzahlungen enthält (48, 54, 55), muss genau BG-18 "PAYMENT CARD INFORMATION" übermittelt werden.',
            ),
            1 => 
            array (
              'id' => 'BR-DE-24-b',
              'flag' => 'fatal',
              'test' => 'not(ram:PayeePartyCreditorFinancialAccount) and                     not(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:DirectDebitMandateID or                         /rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:CreditorReferenceID or                         ram:PayerPartyDebtorFinancialAccount/ram:IBANID)',
              'ast' => 
              array (
                0 => 'and',
                1 => 
                array (
                  0 => 'call',
                  1 => 'fn:not',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'PayeePartyCreditorFinancialAccount',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:not',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'or',
                      1 => 
                      array (
                        0 => 'or',
                        1 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'path',
                              1 => 
                              array (
                                0 => 'path',
                                1 => 
                                array (
                                  0 => 'path',
                                  1 => 
                                  array (
                                    0 => 'root',
                                  ),
                                  2 => 
                                  array (
                                    0 => 'step',
                                    1 => 'child',
                                    2 => 
                                    array (
                                      0 => 'name',
                                      1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                                      2 => 'CrossIndustryInvoice',
                                    ),
                                    3 => 
                                    array (
                                    ),
                                  ),
                                ),
                                2 => 
                                array (
                                  0 => 'step',
                                  1 => 'child',
                                  2 => 
                                  array (
                                    0 => 'name',
                                    1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                                    2 => 'SupplyChainTradeTransaction',
                                  ),
                                  3 => 
                                  array (
                                  ),
                                ),
                              ),
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                  2 => 'ApplicableHeaderTradeSettlement',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'SpecifiedTradePaymentTerms',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'DirectDebitMandateID',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'path',
                              1 => 
                              array (
                                0 => 'path',
                                1 => 
                                array (
                                  0 => 'root',
                                ),
                                2 => 
                                array (
                                  0 => 'step',
                                  1 => 'child',
                                  2 => 
                                  array (
                                    0 => 'name',
                                    1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                                    2 => 'CrossIndustryInvoice',
                                  ),
                                  3 => 
                                  array (
                                  ),
                                ),
                              ),
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                                  2 => 'SupplyChainTradeTransaction',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'ApplicableHeaderTradeSettlement',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'CreditorReferenceID',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'PayerPartyDebtorFinancialAccount',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'IBANID',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-24-b] Wenn BT-81 "Payment means type code" einen Schlüssel für Kartenzahlungen enthält (48, 54, 55), dürfen BG-17 und BG-19 nicht übermittelt werden.',
            ),
          ),
        ),
        11 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementPaymentMeans[normalize-space(ram:TypeCode) = \'59\']',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'root',
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                      2 => 'CrossIndustryInvoice',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                    2 => 'SupplyChainTradeTransaction',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'ApplicableHeaderTradeSettlement',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                2 => 'SpecifiedTradeSettlementPaymentMeans',
              ),
              3 => 
              array (
                0 => 
                array (
                  0 => 'gcmp',
                  1 => '=',
                  2 => 
                  array (
                    0 => 'call',
                    1 => 'fn:normalize-space',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'TypeCode',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:string',
                    2 => '59',
                  ),
                ),
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-DE-20',
              'flag' => 'warning',
              'test' => 'not(normalize-space(ram:TypeCode) = \'59\') or                     xr:checkIBAN(string(ram:PayerPartyDebtorFinancialAccount/ram:IBANID))',
              'ast' => 
              array (
                0 => 'or',
                1 => 
                array (
                  0 => 'call',
                  1 => 'fn:not',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'gcmp',
                      1 => '=',
                      2 => 
                      array (
                        0 => 'call',
                        1 => 'fn:normalize-space',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'TypeCode',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                      ),
                      3 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:string',
                        2 => '59',
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'ucall',
                  1 => 'xrechnung',
                  2 => 'checkIBAN',
                  3 => 
                  array (
                    0 => 
                    array (
                      0 => 'call',
                      1 => 'fn:string',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'PayerPartyDebtorFinancialAccount',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'IBANID',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-20] "Debited account identifier" (BT-91) soll eine korrekte IBAN enthalten, wenn in "Payment means type code" (BT-81) mit dem Code 59 SEPA als Zahlungsmittel gefordert wird.',
            ),
            1 => 
            array (
              'id' => 'BR-DE-25-a',
              'flag' => 'fatal',
              'test' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:DirectDebitMandateID or                     /rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:CreditorReferenceID or                     ram:PayerPartyDebtorFinancialAccount/ram:IBANID',
              'ast' => 
              array (
                0 => 'or',
                1 => 
                array (
                  0 => 'or',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'root',
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                                2 => 'CrossIndustryInvoice',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                              2 => 'SupplyChainTradeTransaction',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'ApplicableHeaderTradeSettlement',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'SpecifiedTradePaymentTerms',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'DirectDebitMandateID',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'root',
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                              2 => 'CrossIndustryInvoice',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                            2 => 'SupplyChainTradeTransaction',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'ApplicableHeaderTradeSettlement',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'CreditorReferenceID',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'PayerPartyDebtorFinancialAccount',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'IBANID',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-25-a] Wenn BT-81 "Payment means type code" einen Schlüssel für Lastschriften enthält (59), muss genau BG-19 "DIRECT DEBIT" übermittelt werden.',
            ),
            2 => 
            array (
              'id' => 'BR-DE-25-b',
              'flag' => 'fatal',
              'test' => 'not(ram:PayeePartyCreditorFinancialAccount) and                     not(ram:PayeeSpecifiedCreditorFinancialInstitution) and                     not(ram:PayerSpecifiedDebtorFinancialInstitution) and                     not(ram:ApplicableTradeSettlementFinancialCard)',
              'ast' => 
              array (
                0 => 'and',
                1 => 
                array (
                  0 => 'and',
                  1 => 
                  array (
                    0 => 'and',
                    1 => 
                    array (
                      0 => 'call',
                      1 => 'fn:not',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'PayeePartyCreditorFinancialAccount',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'call',
                      1 => 'fn:not',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'PayeeSpecifiedCreditorFinancialInstitution',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'call',
                    1 => 'fn:not',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'PayerSpecifiedDebtorFinancialInstitution',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:not',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'ApplicableTradeSettlementFinancialCard',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-25-b] Wenn BT-81 "Payment means type code" einen Schlüssel für Lastschriften enthält (59), dürfen BG-17 und BG-18 nicht übermittelt werden.',
            ),
          ),
        ),
        12 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'root',
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                      2 => 'CrossIndustryInvoice',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                    2 => 'SupplyChainTradeTransaction',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'ApplicableHeaderTradeSettlement',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                2 => 'ApplicableTradeTax',
              ),
              3 => 
              array (
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-DE-14',
              'flag' => 'fatal',
              'test' => 'ram:RateApplicablePercent[boolean(normalize-space(.))]',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'RateApplicablePercent',
                ),
                3 => 
                array (
                  0 => 
                  array (
                    0 => 'call',
                    1 => 'fn:boolean',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'call',
                        1 => 'fn:normalize-space',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'context',
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-14] Das Element "VAT category rate" (BT-119) muss übermittelt werden.',
            ),
          ),
        ),
        13 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'root',
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                  2 => 'CrossIndustryInvoice',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                2 => 'SupplyChainTradeTransaction',
              ),
              3 => 
              array (
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-DE-TMP-32',
              'flag' => 'information',
              'test' => '               ram:ApplicableHeaderTradeDelivery/ram:ActualDeliverySupplyChainEvent/ram:OccurrenceDateTime               or ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod               or (every $line in ram:IncludedSupplyChainTradeLineItem               satisfies $line/ram:SpecifiedLineTradeSettlement/ram:BillingSpecifiedPeriod)',
              'ast' => 
              array (
                0 => 'or',
                1 => 
                array (
                  0 => 'or',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'ApplicableHeaderTradeDelivery',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'ActualDeliverySupplyChainEvent',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'OccurrenceDateTime',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'ApplicableHeaderTradeSettlement',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'BillingSpecifiedPeriod',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'quant',
                  1 => 'every',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'line',
                      1 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'IncludedSupplyChainTradeLineItem',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'var',
                        1 => 'line',
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'SpecifiedLineTradeSettlement',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'BillingSpecifiedPeriod',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-TMP-32] Eine Rechnung sollte zur Angabe des Liefer-/Leistungsdatums entweder BT-72 "Actual delivery date", BG-14 "Invoicing period" oder in jeder Rechnungsposition BG-26 "Invoice line period" enthalten.',
            ),
          ),
        ),
        14 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'root',
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                    2 => 'CrossIndustryInvoice',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                  2 => 'SupplyChainTradeTransaction',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                2 => 'IncludedSupplyChainTradeLineItem',
              ),
              3 => 
              array (
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-TMP-3',
              'flag' => 'fatal',
              'test' => 'not(ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice/ram:BasisQuantity                         and                         ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:BasisQuantity)                     or                     (ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice/ram:BasisQuantity =                      ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:BasisQuantity                      and                      (not(ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:BasisQuantity/@unitCode                      and                      ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice/ram:BasisQuantity/@unitCode)                       or                       ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice/ram:BasisQuantity/@unitCode =                       ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:BasisQuantity/@unitCode))',
              'ast' => 
              array (
                0 => 'or',
                1 => 
                array (
                  0 => 'call',
                  1 => 'fn:not',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'and',
                      1 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'SpecifiedLineTradeAgreement',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'GrossPriceProductTradePrice',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'BasisQuantity',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'SpecifiedLineTradeAgreement',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'NetPriceProductTradePrice',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'BasisQuantity',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'and',
                  1 => 
                  array (
                    0 => 'gcmp',
                    1 => '=',
                    2 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'SpecifiedLineTradeAgreement',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'GrossPriceProductTradePrice',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'BasisQuantity',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    3 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'SpecifiedLineTradeAgreement',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'NetPriceProductTradePrice',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'BasisQuantity',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'or',
                    1 => 
                    array (
                      0 => 'call',
                      1 => 'fn:not',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'and',
                          1 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'path',
                              1 => 
                              array (
                                0 => 'path',
                                1 => 
                                array (
                                  0 => 'step',
                                  1 => 'child',
                                  2 => 
                                  array (
                                    0 => 'name',
                                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                    2 => 'SpecifiedLineTradeAgreement',
                                  ),
                                  3 => 
                                  array (
                                  ),
                                ),
                                2 => 
                                array (
                                  0 => 'step',
                                  1 => 'child',
                                  2 => 
                                  array (
                                    0 => 'name',
                                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                    2 => 'NetPriceProductTradePrice',
                                  ),
                                  3 => 
                                  array (
                                  ),
                                ),
                              ),
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                  2 => 'BasisQuantity',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'attribute',
                              2 => 
                              array (
                                0 => 'name',
                                1 => '',
                                2 => 'unitCode',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'path',
                              1 => 
                              array (
                                0 => 'path',
                                1 => 
                                array (
                                  0 => 'step',
                                  1 => 'child',
                                  2 => 
                                  array (
                                    0 => 'name',
                                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                    2 => 'SpecifiedLineTradeAgreement',
                                  ),
                                  3 => 
                                  array (
                                  ),
                                ),
                                2 => 
                                array (
                                  0 => 'step',
                                  1 => 'child',
                                  2 => 
                                  array (
                                    0 => 'name',
                                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                    2 => 'GrossPriceProductTradePrice',
                                  ),
                                  3 => 
                                  array (
                                  ),
                                ),
                              ),
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                  2 => 'BasisQuantity',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'attribute',
                              2 => 
                              array (
                                0 => 'name',
                                1 => '',
                                2 => 'unitCode',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'gcmp',
                      1 => '=',
                      2 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'SpecifiedLineTradeAgreement',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'GrossPriceProductTradePrice',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'BasisQuantity',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'attribute',
                          2 => 
                          array (
                            0 => 'name',
                            1 => '',
                            2 => 'unitCode',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      3 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'SpecifiedLineTradeAgreement',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'NetPriceProductTradePrice',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'BasisQuantity',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'attribute',
                          2 => 
                          array (
                            0 => 'name',
                            1 => '',
                            2 => 'unitCode',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-TMP-3] Wenn BT-149 (Item price base quantity) sowohl in GrossPriceProductTradePrice als auch in NetPriceProductTradePrice vorhanden ist, müssen die Werte identisch sein. Wenn BT-150 (unit of measure code) auf dem NetPrice-Pfad vorhanden ist, muss es auch auf dem GrossPrice-Pfad vorhanden und identisch sein.',
            ),
          ),
        ),
      ),
    ),
    5 => 
    array (
      'id' => 'cii-extension-pattern',
      'lets' => 
      array (
      ),
      'rules' => 
      array (
        0 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:AssociatedDocumentLineDocument[$isExtension]',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'root',
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                      2 => 'CrossIndustryInvoice',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                    2 => 'SupplyChainTradeTransaction',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'IncludedSupplyChainTradeLineItem',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                2 => 'AssociatedDocumentLineDocument',
              ),
              3 => 
              array (
                0 => 
                array (
                  0 => 'var',
                  1 => 'isExtension',
                ),
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-DEX-15',
              'flag' => 'warning',
              'test' => 'not(exists(//ram:ParentLineID))',
              'ast' => 
              array (
                0 => 'call',
                1 => 'fn:not',
                2 => 
                array (
                  0 => 
                  array (
                    0 => 'call',
                    1 => 'fn:exists',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'root',
                        ),
                        2 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'step',
                            1 => 'descendant-or-self',
                            2 => 
                            array (
                              0 => 'node',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'ParentLineID',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DEX-15] This CII file might use the concept of Sub Invoice Lines. However XRechnung does not support this.',
            ),
          ),
        ),
        1 => 
        array (
          'context' => '//ram:GlobalID[@schemeID and $isExtension][not(ancestor::ram:SpecifiedTradeProduct) and not(ancestor::ram:ShipToTradeParty)]',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'root',
            ),
            2 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'step',
                1 => 'descendant-or-self',
                2 => 
                array (
                  0 => 'node',
                ),
                3 => 
                array (
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'GlobalID',
                ),
                3 => 
                array (
                  0 => 
                  array (
                    0 => 'and',
                    1 => 
                    array (
                      0 => 'step',
                      1 => 'attribute',
                      2 => 
                      array (
                        0 => 'name',
                        1 => '',
                        2 => 'schemeID',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'var',
                      1 => 'isExtension',
                    ),
                  ),
                  1 => 
                  array (
                    0 => 'and',
                    1 => 
                    array (
                      0 => 'call',
                      1 => 'fn:not',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'step',
                          1 => 'ancestor',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'SpecifiedTradeProduct',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'call',
                      1 => 'fn:not',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'step',
                          1 => 'ancestor',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'ShipToTradeParty',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-DEX-04',
              'flag' => 'fatal',
              'test' => '((not(contains(normalize-space(@schemeID), \' \')) and contains($ISO-6523-ICD-EXT-CODES, concat(\' \', normalize-space(@schemeID), \' \'))))',
              'ast' => 
              array (
                0 => 'and',
                1 => 
                array (
                  0 => 'call',
                  1 => 'fn:not',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'call',
                      1 => 'fn:contains',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'call',
                          1 => 'fn:normalize-space',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'step',
                              1 => 'attribute',
                              2 => 
                              array (
                                0 => 'name',
                                1 => '',
                                2 => 'schemeID',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                        ),
                        1 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => ' ',
                        ),
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:contains',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'var',
                      1 => 'ISO-6523-ICD-EXT-CODES',
                    ),
                    1 => 
                    array (
                      0 => 'call',
                      1 => 'fn:concat',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => ' ',
                        ),
                        1 => 
                        array (
                          0 => 'call',
                          1 => 'fn:normalize-space',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'step',
                              1 => 'attribute',
                              2 => 
                              array (
                                0 => 'name',
                                1 => '',
                                2 => 'schemeID',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => ' ',
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DEX-04] Any scheme identifier in MUST be coded using one of the ISO 6523 ICD list.',
              'messageParts' => 
              array (
                0 => '[BR-DEX-04] Any scheme identifier in ',
                1 => 
                array (
                  'name' => true,
                ),
                2 => ' MUST be coded using one of the ISO 6523 ICD list. ',
              ),
            ),
          ),
        ),
        2 => 
        array (
          'context' => 'ram:ID[@schemeID and $isExtension][not(ancestor::ram:SpecifiedTaxRegistration)]',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'root',
            ),
            2 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'step',
                1 => 'descendant-or-self',
                2 => 
                array (
                  0 => 'node',
                ),
                3 => 
                array (
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'ID',
                ),
                3 => 
                array (
                  0 => 
                  array (
                    0 => 'and',
                    1 => 
                    array (
                      0 => 'step',
                      1 => 'attribute',
                      2 => 
                      array (
                        0 => 'name',
                        1 => '',
                        2 => 'schemeID',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'var',
                      1 => 'isExtension',
                    ),
                  ),
                  1 => 
                  array (
                    0 => 'call',
                    1 => 'fn:not',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'step',
                        1 => 'ancestor',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'SpecifiedTaxRegistration',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-DEX-05',
              'flag' => 'fatal',
              'test' => '((not(contains(normalize-space(@schemeID), \' \')) and contains($ISO-6523-ICD-EXT-CODES, concat(\' \', normalize-space(@schemeID), \' \'))))',
              'ast' => 
              array (
                0 => 'and',
                1 => 
                array (
                  0 => 'call',
                  1 => 'fn:not',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'call',
                      1 => 'fn:contains',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'call',
                          1 => 'fn:normalize-space',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'step',
                              1 => 'attribute',
                              2 => 
                              array (
                                0 => 'name',
                                1 => '',
                                2 => 'schemeID',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                        ),
                        1 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => ' ',
                        ),
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:contains',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'var',
                      1 => 'ISO-6523-ICD-EXT-CODES',
                    ),
                    1 => 
                    array (
                      0 => 'call',
                      1 => 'fn:concat',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => ' ',
                        ),
                        1 => 
                        array (
                          0 => 'call',
                          1 => 'fn:normalize-space',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'step',
                              1 => 'attribute',
                              2 => 
                              array (
                                0 => 'name',
                                1 => '',
                                2 => 'schemeID',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => ' ',
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DEX-05] Any scheme identifier in MUST be coded using one of the ISO 6523 ICD list.',
              'messageParts' => 
              array (
                0 => '[BR-DEX-05] Any scheme identifier in ',
                1 => 
                array (
                  'name' => true,
                ),
                2 => ' MUST be coded using one of the ISO 6523 ICD list. ',
              ),
            ),
          ),
        ),
        3 => 
        array (
          'context' => 'ram:SpecifiedTradeProduct/ram:GlobalID[@schemeID and $isExtension]',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'root',
              ),
              2 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'step',
                  1 => 'descendant-or-self',
                  2 => 
                  array (
                    0 => 'node',
                  ),
                  3 => 
                  array (
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'SpecifiedTradeProduct',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                2 => 'GlobalID',
              ),
              3 => 
              array (
                0 => 
                array (
                  0 => 'and',
                  1 => 
                  array (
                    0 => 'step',
                    1 => 'attribute',
                    2 => 
                    array (
                      0 => 'name',
                      1 => '',
                      2 => 'schemeID',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'var',
                    1 => 'isExtension',
                  ),
                ),
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-DEX-06',
              'flag' => 'fatal',
              'test' => '((not(contains(normalize-space(@schemeID), \' \')) and contains($ISO-6523-ICD-EXT-CODES, concat(\' \', normalize-space(@schemeID), \' \'))))',
              'ast' => 
              array (
                0 => 'and',
                1 => 
                array (
                  0 => 'call',
                  1 => 'fn:not',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'call',
                      1 => 'fn:contains',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'call',
                          1 => 'fn:normalize-space',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'step',
                              1 => 'attribute',
                              2 => 
                              array (
                                0 => 'name',
                                1 => '',
                                2 => 'schemeID',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                        ),
                        1 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => ' ',
                        ),
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:contains',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'var',
                      1 => 'ISO-6523-ICD-EXT-CODES',
                    ),
                    1 => 
                    array (
                      0 => 'call',
                      1 => 'fn:concat',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => ' ',
                        ),
                        1 => 
                        array (
                          0 => 'call',
                          1 => 'fn:normalize-space',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'step',
                              1 => 'attribute',
                              2 => 
                              array (
                                0 => 'name',
                                1 => '',
                                2 => 'schemeID',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => ' ',
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DEX-06] Any scheme identifier in MUST be coded using one of the ISO 6523 ICD list.',
              'messageParts' => 
              array (
                0 => '[BR-DEX-06] Any scheme identifier in ',
                1 => 
                array (
                  'name' => true,
                ),
                2 => ' MUST be coded using one of the ISO 6523 ICD list. ',
              ),
            ),
          ),
        ),
        4 => 
        array (
          'context' => 'ram:URIUniversalCommunication/ram:URIID[@schemeID and $isExtension]',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'root',
              ),
              2 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'step',
                  1 => 'descendant-or-self',
                  2 => 
                  array (
                    0 => 'node',
                  ),
                  3 => 
                  array (
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'URIUniversalCommunication',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                2 => 'URIID',
              ),
              3 => 
              array (
                0 => 
                array (
                  0 => 'and',
                  1 => 
                  array (
                    0 => 'step',
                    1 => 'attribute',
                    2 => 
                    array (
                      0 => 'name',
                      1 => '',
                      2 => 'schemeID',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'var',
                    1 => 'isExtension',
                  ),
                ),
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-DEX-07',
              'flag' => 'fatal',
              'test' => '((not(contains(normalize-space(@schemeID), \' \')) and contains($CEF-EAS-EXT-CODES, concat(\' \', normalize-space(@schemeID), \' \'))))',
              'ast' => 
              array (
                0 => 'and',
                1 => 
                array (
                  0 => 'call',
                  1 => 'fn:not',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'call',
                      1 => 'fn:contains',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'call',
                          1 => 'fn:normalize-space',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'step',
                              1 => 'attribute',
                              2 => 
                              array (
                                0 => 'name',
                                1 => '',
                                2 => 'schemeID',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                        ),
                        1 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => ' ',
                        ),
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:contains',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'var',
                      1 => 'CEF-EAS-EXT-CODES',
                    ),
                    1 => 
                    array (
                      0 => 'call',
                      1 => 'fn:concat',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => ' ',
                        ),
                        1 => 
                        array (
                          0 => 'call',
                          1 => 'fn:normalize-space',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'step',
                              1 => 'attribute',
                              2 => 
                              array (
                                0 => 'name',
                                1 => '',
                                2 => 'schemeID',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => ' ',
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DEX-07] Any scheme identifier for an Endpoint Identifier in MUST belong to the CEF EAS code list.',
              'messageParts' => 
              array (
                0 => '[BR-DEX-07] Any scheme identifier for an Endpoint Identifier in ',
                1 => 
                array (
                  'name' => true,
                ),
                2 => ' MUST belong to the CEF EAS code list. ',
              ),
            ),
          ),
        ),
        5 => 
        array (
          'context' => 'ram:ApplicableHeaderTradeDelivery/ram:ShipToTradeParty/ram:GlobalID[@schemeID and $isExtension]',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'root',
                ),
                2 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'step',
                    1 => 'descendant-or-self',
                    2 => 
                    array (
                      0 => 'node',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'ApplicableHeaderTradeDelivery',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'ShipToTradeParty',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                2 => 'GlobalID',
              ),
              3 => 
              array (
                0 => 
                array (
                  0 => 'and',
                  1 => 
                  array (
                    0 => 'step',
                    1 => 'attribute',
                    2 => 
                    array (
                      0 => 'name',
                      1 => '',
                      2 => 'schemeID',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'var',
                    1 => 'isExtension',
                  ),
                ),
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-DEX-08',
              'flag' => 'fatal',
              'test' => '((not(contains(normalize-space(@schemeID), \' \')) and contains($ISO-6523-ICD-EXT-CODES, concat(\' \', normalize-space(@schemeID), \' \'))))',
              'ast' => 
              array (
                0 => 'and',
                1 => 
                array (
                  0 => 'call',
                  1 => 'fn:not',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'call',
                      1 => 'fn:contains',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'call',
                          1 => 'fn:normalize-space',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'step',
                              1 => 'attribute',
                              2 => 
                              array (
                                0 => 'name',
                                1 => '',
                                2 => 'schemeID',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                        ),
                        1 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => ' ',
                        ),
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:contains',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'var',
                      1 => 'ISO-6523-ICD-EXT-CODES',
                    ),
                    1 => 
                    array (
                      0 => 'call',
                      1 => 'fn:concat',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => ' ',
                        ),
                        1 => 
                        array (
                          0 => 'call',
                          1 => 'fn:normalize-space',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'step',
                              1 => 'attribute',
                              2 => 
                              array (
                                0 => 'name',
                                1 => '',
                                2 => 'schemeID',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => ' ',
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DEX-08] Any scheme identifier for a Delivery location identifier in MUST be coded using one of the ISO 6523 ICD list.',
              'messageParts' => 
              array (
                0 => '[BR-DEX-08] Any scheme identifier for a Delivery location identifier in ',
                1 => 
                array (
                  'name' => true,
                ),
                2 => ' MUST be coded using one of the ISO 6523 ICD list. ',
              ),
            ),
          ),
        ),
        6 => 
        array (
          'context' => 'ram:AttachmentBinaryObject[$isExtension]',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'root',
            ),
            2 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'step',
                1 => 'descendant-or-self',
                2 => 
                array (
                  0 => 'node',
                ),
                3 => 
                array (
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'AttachmentBinaryObject',
                ),
                3 => 
                array (
                  0 => 
                  array (
                    0 => 'var',
                    1 => 'isExtension',
                  ),
                ),
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-DEX-01',
              'flag' => 'fatal',
              'test' => '.[@mimeCode = \'application/pdf\' or               @mimeCode = \'image/png\' or               @mimeCode = \'image/jpeg\' or               @mimeCode = \'text/csv\' or               @mimeCode = \'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet\' or               @mimeCode = \'application/vnd.oasis.opendocument.spreadsheet\' or               @mimeCode = \'application/xml\']',
              'ast' => 
              array (
                0 => 'filter',
                1 => 
                array (
                  0 => 'context',
                ),
                2 => 
                array (
                  0 => 
                  array (
                    0 => 'or',
                    1 => 
                    array (
                      0 => 'or',
                      1 => 
                      array (
                        0 => 'or',
                        1 => 
                        array (
                          0 => 'or',
                          1 => 
                          array (
                            0 => 'or',
                            1 => 
                            array (
                              0 => 'or',
                              1 => 
                              array (
                                0 => 'gcmp',
                                1 => '=',
                                2 => 
                                array (
                                  0 => 'step',
                                  1 => 'attribute',
                                  2 => 
                                  array (
                                    0 => 'name',
                                    1 => '',
                                    2 => 'mimeCode',
                                  ),
                                  3 => 
                                  array (
                                  ),
                                ),
                                3 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:string',
                                  2 => 'application/pdf',
                                ),
                              ),
                              2 => 
                              array (
                                0 => 'gcmp',
                                1 => '=',
                                2 => 
                                array (
                                  0 => 'step',
                                  1 => 'attribute',
                                  2 => 
                                  array (
                                    0 => 'name',
                                    1 => '',
                                    2 => 'mimeCode',
                                  ),
                                  3 => 
                                  array (
                                  ),
                                ),
                                3 => 
                                array (
                                  0 => 'literal',
                                  1 => 'xs:string',
                                  2 => 'image/png',
                                ),
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'gcmp',
                              1 => '=',
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'attribute',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => '',
                                  2 => 'mimeCode',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                              3 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:string',
                                2 => 'image/jpeg',
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'gcmp',
                            1 => '=',
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'attribute',
                              2 => 
                              array (
                                0 => 'name',
                                1 => '',
                                2 => 'mimeCode',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                            3 => 
                            array (
                              0 => 'literal',
                              1 => 'xs:string',
                              2 => 'text/csv',
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'gcmp',
                          1 => '=',
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'attribute',
                            2 => 
                            array (
                              0 => 'name',
                              1 => '',
                              2 => 'mimeCode',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                          3 => 
                          array (
                            0 => 'literal',
                            1 => 'xs:string',
                            2 => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'gcmp',
                        1 => '=',
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'attribute',
                          2 => 
                          array (
                            0 => 'name',
                            1 => '',
                            2 => 'mimeCode',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        3 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => 'application/vnd.oasis.opendocument.spreadsheet',
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'gcmp',
                      1 => '=',
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'attribute',
                        2 => 
                        array (
                          0 => 'name',
                          1 => '',
                          2 => 'mimeCode',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                      3 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:string',
                        2 => 'application/xml',
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DEX-01] Das Element "Attached Document" (BT-125) benutzt einen nicht zulässigen MIME-Code: . Im Falle einer Extension darf zusätzlich zu der Liste der mime codes (definiert in Abschnitt 8.2, "Binary Object") der MIME-Code application/xml genutzt werden.',
              'messageParts' => 
              array (
                0 => '[BR-DEX-01] Das Element ',
                1 => 
                array (
                  'name' => true,
                ),
                2 => ' "Attached Document" (BT-125) benutzt einen nicht zulässigen MIME-Code: ',
                3 => 
                array (
                  'valueOf' => 
                  array (
                    0 => 'step',
                    1 => 'attribute',
                    2 => 
                    array (
                      0 => 'name',
                      1 => '',
                      2 => 'mimeCode',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                4 => '. Im Falle einer Extension darf zusätzlich zu der Liste der mime codes (definiert in Abschnitt 8.2, "Binary Object") der MIME-Code application/xml genutzt werden.',
              ),
            ),
          ),
        ),
      ),
    ),
    6 => 
    array (
      'id' => 'cii-cvd-pattern',
      'lets' => 
      array (
      ),
      'rules' => 
      array (
        0 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice[$isCVD]/rsm:SupplyChainTradeTransaction',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'root',
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                  2 => 'CrossIndustryInvoice',
                ),
                3 => 
                array (
                  0 => 
                  array (
                    0 => 'var',
                    1 => 'isCVD',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                2 => 'SupplyChainTradeTransaction',
              ),
              3 => 
              array (
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-DE-CVD-03',
              'flag' => 'fatal',
              'test' => 'ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedTradeProduct[ram:DesignatedProductClassification/ram:ClassCode/@listID = \'CVD\' and ram:ApplicableProductCharacteristic/ram:Description = \'cva\']',
              'ast' => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'IncludedSupplyChainTradeLineItem',
                  ),
                  3 => 
                  array (
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'SpecifiedTradeProduct',
                  ),
                  3 => 
                  array (
                    0 => 
                    array (
                      0 => 'and',
                      1 => 
                      array (
                        0 => 'gcmp',
                        1 => '=',
                        2 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'DesignatedProductClassification',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'ClassCode',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'attribute',
                            2 => 
                            array (
                              0 => 'name',
                              1 => '',
                              2 => 'listID',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        3 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => 'CVD',
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'gcmp',
                        1 => '=',
                        2 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'ApplicableProductCharacteristic',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'Description',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        3 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => 'cva',
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-CVD-03] In einer Rechnung muss mindestens eine INVOICE LINE (BG-25) enthalten sein, in der der Scheme identifier von "Item classification identifier" (BT-158) den Wert \'CVD\' und der "Item attribute name" (BT-160) den Wert \'cva\' enthält.',
              'messageParts' => 
              array (
                0 => '
                [BR-DE-CVD-03] In einer Rechnung muss mindestens eine ',
                1 => 
                array (
                  'name' => true,
                ),
                2 => ' INVOICE LINE (BG-25) enthalten sein, in der der Scheme identifier von ',
                3 => 
                array (
                  'name' => true,
                ),
                4 => ' "Item classification identifier" (BT-158) den Wert \'CVD\' und der ',
                5 => 
                array (
                  'name' => true,
                ),
                6 => ' "Item attribute name" (BT-160) den Wert \'cva\' enthält.
            ',
              ),
            ),
          ),
        ),
        1 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice[$isCVD]/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedTradeProduct',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'root',
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                      2 => 'CrossIndustryInvoice',
                    ),
                    3 => 
                    array (
                      0 => 
                      array (
                        0 => 'var',
                        1 => 'isCVD',
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                    2 => 'SupplyChainTradeTransaction',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'IncludedSupplyChainTradeLineItem',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                2 => 'SpecifiedTradeProduct',
              ),
              3 => 
              array (
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-DE-CVD-06-b',
              'flag' => 'fatal',
              'test' => 'not(ram:ApplicableProductCharacteristic/ram:Description = \'cva\') or count(ram:DesignatedProductClassification/ram:ClassCode[@listID = \'CVD\']) = 1',
              'ast' => 
              array (
                0 => 'or',
                1 => 
                array (
                  0 => 'call',
                  1 => 'fn:not',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'gcmp',
                      1 => '=',
                      2 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'ApplicableProductCharacteristic',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'Description',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      3 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:string',
                        2 => 'cva',
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'gcmp',
                  1 => '=',
                  2 => 
                  array (
                    0 => 'call',
                    1 => 'fn:count',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'DesignatedProductClassification',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'ClassCode',
                          ),
                          3 => 
                          array (
                            0 => 
                            array (
                              0 => 'gcmp',
                              1 => '=',
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'attribute',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => '',
                                  2 => 'listID',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                              3 => 
                              array (
                                0 => 'literal',
                                1 => 'xs:string',
                                2 => 'CVD',
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:integer',
                    2 => '1',
                  ),
                ),
              ),
              'message' => '[BR-DE-CVD-06-b] Wenn "Item attribute name" (BT-160) mit dem Wert \'cva\' angegeben ist, muss in derselben Rechnungszeile genau ein "Item classification identifier" (BT-158) mit dem Scheme identifier \'CVD\' vorhanden sein.',
              'messageParts' => 
              array (
                0 => '
                [BR-DE-CVD-06-b] Wenn ',
                1 => 
                array (
                  'name' => true,
                ),
                2 => ' "Item attribute name" (BT-160) mit dem Wert \'cva\' angegeben ist, muss in derselben Rechnungszeile genau ein ',
                3 => 
                array (
                  'name' => true,
                ),
                4 => ' "Item classification identifier" (BT-158) mit dem Scheme identifier \'CVD\' vorhanden sein.
            ',
              ),
            ),
            1 => 
            array (
              'id' => 'BR-DE-CVD-06-a',
              'flag' => 'fatal',
              'test' => 'not(ram:DesignatedProductClassification/ram:ClassCode/@listID = \'CVD\') or count(ram:ApplicableProductCharacteristic[ram:Description = \'cva\']) = 1',
              'ast' => 
              array (
                0 => 'or',
                1 => 
                array (
                  0 => 'call',
                  1 => 'fn:not',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'gcmp',
                      1 => '=',
                      2 => 
                      array (
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'path',
                          1 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'DesignatedProductClassification',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'ClassCode',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'attribute',
                          2 => 
                          array (
                            0 => 'name',
                            1 => '',
                            2 => 'listID',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      3 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:string',
                        2 => 'CVD',
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'gcmp',
                  1 => '=',
                  2 => 
                  array (
                    0 => 'call',
                    1 => 'fn:count',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                          2 => 'ApplicableProductCharacteristic',
                        ),
                        3 => 
                        array (
                          0 => 
                          array (
                            0 => 'gcmp',
                            1 => '=',
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                                2 => 'Description',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                            3 => 
                            array (
                              0 => 'literal',
                              1 => 'xs:string',
                              2 => 'cva',
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:integer',
                    2 => '1',
                  ),
                ),
              ),
              'message' => '[BR-DE-CVD-06-a] Wenn der Scheme identifier von "Item classification identifier" (BT-158) mit dem Wert \'CVD\' angegeben ist, muss in derselben Rechnungszeile genau ein "Item attribute name" (BT-160) mit dem Wert \'cva\' vorhanden sein.',
              'messageParts' => 
              array (
                0 => '
                [BR-DE-CVD-06-a] Wenn der Scheme identifier von ',
                1 => 
                array (
                  'name' => true,
                ),
                2 => ' "Item classification identifier" (BT-158) mit dem Wert \'CVD\' angegeben ist, muss in derselben Rechnungszeile genau ein ',
                3 => 
                array (
                  'name' => true,
                ),
                4 => ' "Item attribute name" (BT-160) mit dem Wert \'cva\' vorhanden sein.
            ',
              ),
            ),
          ),
        ),
        2 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice[$isCVD]/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedTradeProduct/ram:DesignatedProductClassification/ram:ClassCode',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'root',
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                          2 => 'CrossIndustryInvoice',
                        ),
                        3 => 
                        array (
                          0 => 
                          array (
                            0 => 'var',
                            1 => 'isCVD',
                          ),
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                        2 => 'SupplyChainTradeTransaction',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'IncludedSupplyChainTradeLineItem',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'SpecifiedTradeProduct',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'DesignatedProductClassification',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                2 => 'ClassCode',
              ),
              3 => 
              array (
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-TMP-CVD-01',
              'flag' => 'fatal',
              'test' => '((not(contains(normalize-space(@listID), \' \')) and contains($UNTDID-7143-CVD-CODES, concat(\' \', normalize-space(@listID), \' \'))))',
              'ast' => 
              array (
                0 => 'and',
                1 => 
                array (
                  0 => 'call',
                  1 => 'fn:not',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'call',
                      1 => 'fn:contains',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'call',
                          1 => 'fn:normalize-space',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'step',
                              1 => 'attribute',
                              2 => 
                              array (
                                0 => 'name',
                                1 => '',
                                2 => 'listID',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                        ),
                        1 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => ' ',
                        ),
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:contains',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'var',
                      1 => 'UNTDID-7143-CVD-CODES',
                    ),
                    1 => 
                    array (
                      0 => 'call',
                      1 => 'fn:concat',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => ' ',
                        ),
                        1 => 
                        array (
                          0 => 'call',
                          1 => 'fn:normalize-space',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'step',
                              1 => 'attribute',
                              2 => 
                              array (
                                0 => 'name',
                                1 => '',
                                2 => 'listID',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => ' ',
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-TMP-CVD-01] Das Bildungsschema für "Item classification identifier" (BT-158) ist aus der Codeliste UNTDID 7143 zu wählen.',
              'messageParts' => 
              array (
                0 => '
                [BR-TMP-CVD-01] Das Bildungsschema für ',
                1 => 
                array (
                  'name' => true,
                ),
                2 => ' "Item classification identifier" (BT-158) ist aus der Codeliste UNTDID 7143 zu wählen.
            ',
              ),
            ),
            1 => 
            array (
              'id' => 'BR-DE-CVD-04',
              'flag' => 'fatal',
              'test' => 'not(normalize-space(@listID) = \'CVD\') or normalize-space(.) = $CVD-VEHICLE-CATEGORY',
              'ast' => 
              array (
                0 => 'or',
                1 => 
                array (
                  0 => 'call',
                  1 => 'fn:not',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'gcmp',
                      1 => '=',
                      2 => 
                      array (
                        0 => 'call',
                        1 => 'fn:normalize-space',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'step',
                            1 => 'attribute',
                            2 => 
                            array (
                              0 => 'name',
                              1 => '',
                              2 => 'listID',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                      ),
                      3 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:string',
                        2 => 'CVD',
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'gcmp',
                  1 => '=',
                  2 => 
                  array (
                    0 => 'call',
                    1 => 'fn:normalize-space',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'context',
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'var',
                    1 => 'CVD-VEHICLE-CATEGORY',
                  ),
                ),
              ),
              'message' => '[BR-DE-CVD-04] Ein "Item classification identifier" (BT-158) mit dem Scheme identifier \'CVD\' muss einen Wert aus der Liste der zulässigen Fahrzeugkategorien enthalten.',
              'messageParts' => 
              array (
                0 => '
                [BR-DE-CVD-04] Ein ',
                1 => 
                array (
                  'name' => true,
                ),
                2 => ' "Item classification identifier" (BT-158) mit dem Scheme identifier \'CVD\' muss einen Wert aus der Liste der zulässigen Fahrzeugkategorien enthalten.
            ',
              ),
            ),
          ),
        ),
        3 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice[$isCVD]/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedTradeProduct/ram:ApplicableProductCharacteristic[ram:Description = \'cva\']',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'root',
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                        2 => 'CrossIndustryInvoice',
                      ),
                      3 => 
                      array (
                        0 => 
                        array (
                          0 => 'var',
                          1 => 'isCVD',
                        ),
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                      2 => 'SupplyChainTradeTransaction',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'IncludedSupplyChainTradeLineItem',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'SpecifiedTradeProduct',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                2 => 'ApplicableProductCharacteristic',
              ),
              3 => 
              array (
                0 => 
                array (
                  0 => 'gcmp',
                  1 => '=',
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                      2 => 'Description',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:string',
                    2 => 'cva',
                  ),
                ),
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-DE-CVD-05',
              'flag' => 'fatal',
              'test' => 'normalize-space(ram:Value) = $CVA-CODES',
              'ast' => 
              array (
                0 => 'gcmp',
                1 => '=',
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:normalize-space',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'Value',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                ),
                3 => 
                array (
                  0 => 'var',
                  1 => 'CVA-CODES',
                ),
              ),
              'message' => '[BR-DE-CVD-05] Wenn innerhalb von ITEM ATTRIBUTES (BG-32) der "Item attribute name" (BT-160) den Wert \'cva\' hat, muss der "Item attribute value" (BT-161) einen der zulässigen Werte enthalten.',
              'messageParts' => 
              array (
                0 => '
                [BR-DE-CVD-05] Wenn innerhalb von ',
                1 => 
                array (
                  'name' => true,
                ),
                2 => ' ITEM ATTRIBUTES (BG-32) der ',
                3 => 
                array (
                  'name' => true,
                ),
                4 => ' "Item attribute name" (BT-160) den Wert \'cva\' hat, muss der ',
                5 => 
                array (
                  'name' => true,
                ),
                6 => ' "Item attribute value" (BT-161) einen der zulässigen Werte enthalten.
            ',
              ),
            ),
          ),
        ),
        4 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice[$isCVD]/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement',
          'match' => 
          array (
            0 => 'path',
            1 => 
            array (
              0 => 'path',
              1 => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'root',
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                    2 => 'CrossIndustryInvoice',
                  ),
                  3 => 
                  array (
                    0 => 
                    array (
                      0 => 'var',
                      1 => 'isCVD',
                    ),
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
                  2 => 'SupplyChainTradeTransaction',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                2 => 'ApplicableHeaderTradeAgreement',
              ),
              3 => 
              array (
              ),
            ),
          ),
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-DE-CVD-01',
              'flag' => 'fatal',
              'test' => 'ram:ContractReferencedDocument/ram:IssuerAssignedID[boolean(normalize-space(.))]',
              'ast' => 
              array (
                0 => 'path',
                1 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'ContractReferencedDocument',
                  ),
                  3 => 
                  array (
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                    2 => 'IssuerAssignedID',
                  ),
                  3 => 
                  array (
                    0 => 
                    array (
                      0 => 'call',
                      1 => 'fn:boolean',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'call',
                          1 => 'fn:normalize-space',
                          2 => 
                          array (
                            0 => 
                            array (
                              0 => 'context',
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-CVD-01] Das Element "Contract reference" (BT-12) muss übermittelt werden.',
              'messageParts' => 
              array (
                0 => '
                [BR-DE-CVD-01] Das Element ',
                1 => 
                array (
                  'name' => true,
                ),
                2 => ' "Contract reference" (BT-12) muss übermittelt werden.
            ',
              ),
            ),
            1 => 
            array (
              'id' => 'BR-DE-CVD-02',
              'flag' => 'fatal',
              'test' => 'ram:AdditionalReferencedDocument[normalize-space(ram:TypeCode) = \'50\' and normalize-space(ram:IssuerAssignedID)]',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'AdditionalReferencedDocument',
                ),
                3 => 
                array (
                  0 => 
                  array (
                    0 => 'and',
                    1 => 
                    array (
                      0 => 'gcmp',
                      1 => '=',
                      2 => 
                      array (
                        0 => 'call',
                        1 => 'fn:normalize-space',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                              2 => 'TypeCode',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                      ),
                      3 => 
                      array (
                        0 => 'literal',
                        1 => 'xs:string',
                        2 => '50',
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'call',
                      1 => 'fn:normalize-space',
                      2 => 
                      array (
                        0 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                            2 => 'IssuerAssignedID',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-CVD-02] Das Element "Tender or lot reference" (BT-17) muss übermittelt werden.',
              'messageParts' => 
              array (
                0 => '
                [BR-DE-CVD-02] Das Element ',
                1 => 
                array (
                  'name' => true,
                ),
                2 => ' "Tender or lot reference" (BT-17) muss übermittelt werden.
            ',
              ),
            ),
          ),
        ),
      ),
    ),
  ),
);
