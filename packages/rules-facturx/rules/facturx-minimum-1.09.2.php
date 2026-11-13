<?php

// Generated from Factur-X 1.09.2 technical artefacts (Apache-2.0) — do not edit.
// Source: FACTUR-X_MINIMUM.sch (SHA-256 c570a628498e0b633a3dd66dd745653a8a2bc66fc6ee346b1971b9dc2d5f6778).
// Regenerate with: composer compile-rules

declare(strict_types=1);

return array (
  'format' => 4,
  'id' => 'facturx-minimum',
  'version' => '1.09.2',
  'source' => 'FACTUR-X_MINIMUM.sch',
  'sha256' => 'c570a628498e0b633a3dd66dd745653a8a2bc66fc6ee346b1971b9dc2d5f6778',
  'documentSha256' => 
  array (
    'FACTUR-X_MINIMUM_codedb.xml' => '5c575cf37533a12a758f08b6b9691841c1691f2af214f21e040a07ec892e1f20',
  ),
  'namespaces' => 
  array (
    'rsm' => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
    'qdt' => 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100',
    'ram' => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
    'udt' => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
  ),
  'lets' => 
  array (
  ),
  'functions' => 
  array (
  ),
  'patterns' => 
  array (
    0 => 
    array (
      'id' => '',
      'lets' => 
      array (
      ),
      'rules' => 
      array (
        0 => 
        array (
          'context' => '//ram:SellerTradeParty',
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
              'id' => 'BR-CO-26',
              'flag' => 'fatal',
              'test' => '(ram:ID) or (ram:GlobalID) or (ram:SpecifiedLegalOrganization/ram:ID) or (ram:SpecifiedTaxRegistration/ram:ID[@schemeID=\'VA\'])',
              'ast' => 
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
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'GlobalID',
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
                        2 => 'SpecifiedLegalOrganization',
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
                      2 => 'SpecifiedTaxRegistration',
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
                            2 => 'schemeID',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        3 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => 'VA',
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-CO-26]-In order for the buyer to automatically identify a supplier, the Seller identifier (BT-29), the Seller legal registration identifier (BT-30) and/or the Seller VAT identifier (BT-31) shall be present.',
            ),
          ),
        ),
      ),
    ),
    1 => 
    array (
      'id' => '',
      'lets' => 
      array (
      ),
      'rules' => 
      array (
        0 => 
        array (
          'context' => '//ram:SpecifiedTaxRegistration/ram:ID[@schemeID=\'VA\']',
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
                    2 => 'SpecifiedTaxRegistration',
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
                  3 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:string',
                    2 => 'VA',
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
              'id' => 'BR-CO-09',
              'flag' => 'fatal',
              'test' => 'contains(\' 1A AD AE AF AG AI AL AM AN AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BL BJ BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH EL ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS XI YE YT ZA ZM ZW \', concat(\' \', substring(.,1,2), \' \'))',
              'ast' => 
              array (
                0 => 'call',
                1 => 'fn:contains',
                2 => 
                array (
                  0 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:string',
                    2 => ' 1A AD AE AF AG AI AL AM AN AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BL BJ BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH EL ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS XI YE YT ZA ZM ZW ',
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
                        1 => 'fn:substring',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'context',
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
              'message' => '[BR-CO-09]-The Seller VAT identifier (BT-31), the Seller tax representative VAT identifier (BT-63) and the Buyer VAT identifier (BT-48) shall have a prefix in accordance with ISO code ISO 3166-1 alpha-2 by which the country of issue may be identified. Nevertheless, Greece may use the prefix ‘EL’.',
            ),
          ),
        ),
      ),
    ),
    2 => 
    array (
      'id' => '',
      'lets' => 
      array (
      ),
      'rules' => 
      array (
        0 => 
        array (
          'context' => '//ram:SpecifiedTradeSettlementHeaderMonetarySummation',
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
                  2 => 'SpecifiedTradeSettlementHeaderMonetarySummation',
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
              'id' => 'BR-13',
              'flag' => 'fatal',
              'test' => '(ram:TaxBasisTotalAmount)',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'TaxBasisTotalAmount',
                ),
                3 => 
                array (
                ),
              ),
              'message' => '[BR-13]-An Invoice shall have the Invoice total amount without VAT (BT-109).',
            ),
            1 => 
            array (
              'id' => 'BR-14',
              'flag' => 'fatal',
              'test' => '(ram:GrandTotalAmount)',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'GrandTotalAmount',
                ),
                3 => 
                array (
                ),
              ),
              'message' => '[BR-14]-An Invoice shall have the Invoice total amount with VAT (BT-112).',
            ),
            2 => 
            array (
              'id' => 'BR-15',
              'flag' => 'fatal',
              'test' => '(ram:DuePayableAmount)',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                  2 => 'DuePayableAmount',
                ),
                3 => 
                array (
                ),
              ),
              'message' => '[BR-15]-An Invoice shall have the Amount due for payment (BT-115).',
            ),
            3 => 
            array (
              'id' => 'BR-DEC-12',
              'flag' => 'fatal',
              'test' => 'string-length(substring-after(ram:TaxBasisTotalAmount,\'.\'))<=2',
              'ast' => 
              array (
                0 => 'gcmp',
                1 => '<=',
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:string-length',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'call',
                      1 => 'fn:substring-after',
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
                            2 => 'TaxBasisTotalAmount',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        1 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => '.',
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
              'message' => '[BR-DEC-12]-The allowed maximum number of decimals for the Invoice total amount without VAT (BT-109) is 2.',
            ),
            4 => 
            array (
              'id' => 'BR-DEC-13',
              'flag' => 'fatal',
              'test' => 'not(ram:TaxTotalAmount) or ram:TaxTotalAmount[(@currencyID =/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:InvoiceCurrencyCode and . = round(. * 100) div 100) or not (@currencyID =/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:InvoiceCurrencyCode)]',
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
                        2 => 'TaxTotalAmount',
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
                    2 => 'TaxTotalAmount',
                  ),
                  3 => 
                  array (
                    0 => 
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
                        2 => 
                        array (
                          0 => 'gcmp',
                          1 => '=',
                          2 => 
                          array (
                            0 => 'context',
                          ),
                          3 => 
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
                                    0 => 'context',
                                  ),
                                  3 => 
                                  array (
                                    0 => 'literal',
                                    1 => 'xs:integer',
                                    2 => '100',
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
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DEC-13]-The allowed maximum number of decimals for the Invoice total VAT amount (BT-110) is 2.',
            ),
            5 => 
            array (
              'id' => 'BR-DEC-14',
              'flag' => 'fatal',
              'test' => 'string-length(substring-after(ram:GrandTotalAmount,\'.\'))<=2',
              'ast' => 
              array (
                0 => 'gcmp',
                1 => '<=',
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:string-length',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'call',
                      1 => 'fn:substring-after',
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
                            2 => 'GrandTotalAmount',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        1 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => '.',
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
              'message' => '[BR-DEC-14]-The allowed maximum number of decimals for the Invoice total amount with VAT (BT-112) is 2.',
            ),
            6 => 
            array (
              'id' => 'BR-DEC-18',
              'flag' => 'fatal',
              'test' => 'string-length(substring-after(ram:DuePayableAmount,\'.\'))<=2',
              'ast' => 
              array (
                0 => 'gcmp',
                1 => '<=',
                2 => 
                array (
                  0 => 'call',
                  1 => 'fn:string-length',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'call',
                      1 => 'fn:substring-after',
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
                            2 => 'DuePayableAmount',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        1 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => '.',
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
              'message' => '[BR-DEC-18]-The allowed maximum number of decimals for the Amount due for payment (BT-115) is 2.',
            ),
          ),
        ),
      ),
    ),
    3 => 
    array (
      'id' => '',
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
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-01',
              'flag' => 'fatal',
              'test' => '(rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID != \'\')',
              'ast' => 
              array (
                0 => 'gcmp',
                1 => '!=',
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
                        2 => 'ExchangedDocumentContext',
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
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:string',
                  2 => '',
                ),
              ),
              'message' => '[BR-01]-An Invoice shall have a Specification identifier (BT-24).',
            ),
            1 => 
            array (
              'id' => 'BR-02',
              'flag' => 'fatal',
              'test' => '(rsm:ExchangedDocument/ram:ID !=\'\')',
              'ast' => 
              array (
                0 => 'gcmp',
                1 => '!=',
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
                      2 => 'ID',
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
                  2 => '',
                ),
              ),
              'message' => '[BR-02]-An Invoice shall have an Invoice number (BT-1).',
            ),
            2 => 
            array (
              'id' => 'BR-03',
              'flag' => 'fatal',
              'test' => '(rsm:ExchangedDocument/ram:IssueDateTime/udt:DateTimeString[@format=\'102\']!=\'\')',
              'ast' => 
              array (
                0 => 'gcmp',
                1 => '!=',
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
                        2 => 'IssueDateTime',
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
                            2 => 'format',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        3 => 
                        array (
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => '102',
                        ),
                      ),
                    ),
                  ),
                ),
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:string',
                  2 => '',
                ),
              ),
              'message' => '[BR-03]-An Invoice shall have an Invoice issue date (BT-2).',
            ),
            3 => 
            array (
              'id' => 'BR-04',
              'flag' => 'fatal',
              'test' => '(rsm:ExchangedDocument/ram:TypeCode!=\'\')',
              'ast' => 
              array (
                0 => 'gcmp',
                1 => '!=',
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
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:string',
                  2 => '',
                ),
              ),
              'message' => '[BR-04]-An Invoice shall have an Invoice type code (BT-3).',
            ),
            4 => 
            array (
              'id' => 'BR-05',
              'flag' => 'fatal',
              'test' => '(rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:InvoiceCurrencyCode!=\'\')',
              'ast' => 
              array (
                0 => 'gcmp',
                1 => '!=',
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
                      2 => 'InvoiceCurrencyCode',
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
                  2 => '',
                ),
              ),
              'message' => '[BR-05]-An Invoice shall have an Invoice currency code (BT-5).',
            ),
            5 => 
            array (
              'id' => 'BR-06',
              'flag' => 'fatal',
              'test' => '(rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:Name!=\'\')',
              'ast' => 
              array (
                0 => 'gcmp',
                1 => '!=',
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
                      2 => 'Name',
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
                  2 => '',
                ),
              ),
              'message' => '[BR-06]-An Invoice shall contain the Seller name (BT-27).',
            ),
            6 => 
            array (
              'id' => 'BR-07',
              'flag' => 'fatal',
              'test' => '(rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty/ram:Name!=\'\')',
              'ast' => 
              array (
                0 => 'gcmp',
                1 => '!=',
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
                      2 => 'Name',
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
                  2 => '',
                ),
              ),
              'message' => '[BR-07]-An Invoice shall contain the Buyer name (BT-44).',
            ),
            7 => 
            array (
              'id' => 'BR-08',
              'flag' => 'fatal',
              'test' => '//ram:SellerTradeParty/ram:PostalTradeAddress',
              'ast' => 
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
                        2 => 'SellerTradeParty',
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
                    2 => 'PostalTradeAddress',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              'message' => '[BR-08]-An Invoice shall contain the Seller postal address (BG-5).',
            ),
            8 => 
            array (
              'id' => 'BR-09',
              'flag' => 'fatal',
              'test' => '//ram:SellerTradeParty/ram:PostalTradeAddress/ram:CountryID!=\'\'',
              'ast' => 
              array (
                0 => 'gcmp',
                1 => '!=',
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
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:string',
                  2 => '',
                ),
              ),
              'message' => '[BR-09]-The Seller postal address (BG-5) shall contain a Seller country code (BT-40).',
            ),
          ),
        ),
      ),
    ),
    4 => 
    array (
      'id' => '',
      'lets' => 
      array (
      ),
      'rules' => 
      array (
        0 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument',
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
                2 => 'ExchangedDocument',
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
              'id' => 'FX-SCH-A-000019',
              'flag' => 'fatal',
              'test' => 'count(ram:ID)=1',
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
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '1',
                ),
              ),
              'message' => 'Element \'ram:ID\' must occur exactly 1 times.',
            ),
            1 => 
            array (
              'id' => 'FX-SCH-A-000020',
              'flag' => 'fatal',
              'test' => 'count(ram:TypeCode)=1',
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
                  1 => 'xs:integer',
                  2 => '1',
                ),
              ),
              'message' => 'Element \'ram:TypeCode\' must occur exactly 1 times.',
            ),
          ),
        ),
        1 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:ID',
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
                  2 => 'ExchangedDocument',
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
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'FX-REPORT-af71fbab52',
              'flag' => 'fatal',
              'test' => '@schemeID',
              'ast' => 
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
              'message' => 'Attribute @schemeID\' marked as not used in the given context.',
              'report' => true,
            ),
          ),
        ),
        2 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:IssueDateTime/udt:DateTimeString',
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
                    2 => 'ExchangedDocument',
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
                  2 => 'IssueDateTime',
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
          'lets' => 
          array (
            0 => 
            array (
              'name' => 'codeValue3',
              'value' => '@format',
              'ast' => 
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
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'FX-SCH-A-000021',
              'flag' => 'fatal',
              'test' => '@format',
              'ast' => 
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
              'message' => 'Attribute \'@format\' is required in this context.',
            ),
            1 => 
            array (
              'id' => 'FX-SCH-A-000022',
              'flag' => 'fatal',
              'test' => 'string-length($codeValue3)=0 or document(\'FACTUR-X_MINIMUM_codedb.xml\')/codedb/cl[@id=3]/enumeration[@value=$codeValue3]',
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
                    1 => 'fn:string-length',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'var',
                        1 => 'codeValue3',
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
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'call',
                        1 => 'fn:document',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'literal',
                            1 => 'xs:string',
                            2 => 'FACTUR-X_MINIMUM_codedb.xml',
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
                          1 => '',
                          2 => 'codedb',
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
                        1 => '',
                        2 => 'cl',
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
                              2 => 'id',
                            ),
                            3 => 
                            array (
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
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => '',
                      2 => 'enumeration',
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
                            2 => 'value',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        3 => 
                        array (
                          0 => 'var',
                          1 => 'codeValue3',
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => 'Value of \'@format\' is not allowed.',
            ),
          ),
        ),
        3 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:TypeCode',
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
                  2 => 'ExchangedDocument',
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
          'lets' => 
          array (
            0 => 
            array (
              'name' => 'codeValue2',
              'value' => '.',
              'ast' => 
              array (
                0 => 'context',
              ),
            ),
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'FX-SCH-A-000023',
              'flag' => 'fatal',
              'test' => 'string-length($codeValue2)=0 or document(\'FACTUR-X_MINIMUM_codedb.xml\')/codedb/cl[@id=2]/enumeration[@value=$codeValue2]',
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
                    1 => 'fn:string-length',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'var',
                        1 => 'codeValue2',
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
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'call',
                        1 => 'fn:document',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'literal',
                            1 => 'xs:string',
                            2 => 'FACTUR-X_MINIMUM_codedb.xml',
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
                          1 => '',
                          2 => 'codedb',
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
                        1 => '',
                        2 => 'cl',
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
                              2 => 'id',
                            ),
                            3 => 
                            array (
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
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => '',
                      2 => 'enumeration',
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
                            2 => 'value',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        3 => 
                        array (
                          0 => 'var',
                          1 => 'codeValue2',
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => 'Value of \'ram:TypeCode\' is not allowed.',
            ),
          ),
        ),
        4 => 
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
              'id' => 'FX-SCH-A-000024',
              'flag' => 'fatal',
              'test' => 'count(ram:BusinessProcessSpecifiedDocumentContextParameter)<=1',
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
                        2 => 'BusinessProcessSpecifiedDocumentContextParameter',
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
              'message' => 'Element \'ram:BusinessProcessSpecifiedDocumentContextParameter\' may occur at maximum 1 times.',
            ),
            1 => 
            array (
              'id' => 'FX-SCH-A-000025',
              'flag' => 'fatal',
              'test' => 'count(ram:GuidelineSpecifiedDocumentContextParameter)=1',
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
                ),
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '1',
                ),
              ),
              'message' => 'Element \'ram:GuidelineSpecifiedDocumentContextParameter\' must occur exactly 1 times.',
            ),
          ),
        ),
        5 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:BusinessProcessSpecifiedDocumentContextParameter',
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
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'FX-SCH-A-000406',
              'flag' => 'fatal',
              'test' => 'count(ram:ID)=1',
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
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '1',
                ),
              ),
              'message' => 'Element \'ram:ID\' must occur exactly 1 times.',
            ),
          ),
        ),
        6 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:BusinessProcessSpecifiedDocumentContextParameter/ram:ID',
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
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'FX-REPORT-910f5066b8',
              'flag' => 'fatal',
              'test' => '@schemeID',
              'ast' => 
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
              'message' => 'Attribute @schemeID\' marked as not used in the given context.',
              'report' => true,
            ),
          ),
        ),
        7 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter',
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
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'FX-SCH-A-000407',
              'flag' => 'fatal',
              'test' => 'count(ram:ID)=1',
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
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '1',
                ),
              ),
              'message' => 'Element \'ram:ID\' must occur exactly 1 times.',
            ),
          ),
        ),
        8 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID',
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
          'lets' => 
          array (
            0 => 
            array (
              'name' => 'codeValue1',
              'value' => '.',
              'ast' => 
              array (
                0 => 'context',
              ),
            ),
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'FX-SCH-A-000026',
              'flag' => 'fatal',
              'test' => 'string-length($codeValue1)=0 or document(\'FACTUR-X_MINIMUM_codedb.xml\')/codedb/cl[@id=1]/enumeration[@value=$codeValue1]',
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
                    1 => 'fn:string-length',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'var',
                        1 => 'codeValue1',
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
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'call',
                        1 => 'fn:document',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'literal',
                            1 => 'xs:string',
                            2 => 'FACTUR-X_MINIMUM_codedb.xml',
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
                          1 => '',
                          2 => 'codedb',
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
                        1 => '',
                        2 => 'cl',
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
                              2 => 'id',
                            ),
                            3 => 
                            array (
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
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => '',
                      2 => 'enumeration',
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
                            2 => 'value',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        3 => 
                        array (
                          0 => 'var',
                          1 => 'codeValue1',
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => 'Value of \'ram:ID\' is not allowed.',
            ),
            1 => 
            array (
              'id' => 'FX-REPORT-460d55a24c',
              'flag' => 'fatal',
              'test' => '@schemeID',
              'ast' => 
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
              'message' => 'Attribute @schemeID\' marked as not used in the given context.',
              'report' => true,
            ),
          ),
        ),
        9 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement',
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
              'id' => 'FX-SCH-A-000027',
              'flag' => 'fatal',
              'test' => 'count(ram:SellerTradeParty)=1',
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
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '1',
                ),
              ),
              'message' => 'Element \'ram:SellerTradeParty\' must occur exactly 1 times.',
            ),
            1 => 
            array (
              'id' => 'FX-SCH-A-000028',
              'flag' => 'fatal',
              'test' => 'count(ram:BuyerTradeParty)=1',
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
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '1',
                ),
              ),
              'message' => 'Element \'ram:BuyerTradeParty\' must occur exactly 1 times.',
            ),
          ),
        ),
        10 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerOrderReferencedDocument',
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
                2 => 'BuyerOrderReferencedDocument',
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
              'id' => 'FX-SCH-A-000029',
              'flag' => 'fatal',
              'test' => 'count(ram:IssuerAssignedID)=1',
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
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '1',
                ),
              ),
              'message' => 'Element \'ram:IssuerAssignedID\' must occur exactly 1 times.',
            ),
          ),
        ),
        11 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerOrderReferencedDocument/ram:IssuerAssignedID',
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
                  2 => 'BuyerOrderReferencedDocument',
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
                2 => 'IssuerAssignedID',
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
              'id' => 'FX-REPORT-e5c4d66145',
              'flag' => 'fatal',
              'test' => '@schemeID',
              'ast' => 
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
              'message' => 'Attribute @schemeID\' marked as not used in the given context.',
              'report' => true,
            ),
          ),
        ),
        12 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty',
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
                2 => 'BuyerTradeParty',
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
              'id' => 'FX-SCH-A-000030',
              'flag' => 'fatal',
              'test' => 'count(ram:Name)=1',
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
              'message' => 'Element \'ram:Name\' must occur exactly 1 times.',
            ),
          ),
        ),
        13 => 
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
              'id' => 'FX-REPORT-79b5075d35',
              'flag' => 'fatal',
              'test' => 'true()',
              'ast' => 
              array (
                0 => 'call',
                1 => 'fn:true',
                2 => 
                array (
                ),
              ),
              'message' => 'Element \'ram:PostalTradeAddress\' is marked as not used in the given context.',
              'report' => true,
            ),
          ),
        ),
        14 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty/ram:SpecifiedLegalOrganization',
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
                2 => 'SpecifiedLegalOrganization',
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
              'id' => 'FX-SCH-A-000408',
              'flag' => 'fatal',
              'test' => 'count(ram:ID)=1',
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
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '1',
                ),
              ),
              'message' => 'Element \'ram:ID\' must occur exactly 1 times.',
            ),
          ),
        ),
        15 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty/ram:SpecifiedLegalOrganization/ram:ID',
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
                  2 => 'SpecifiedLegalOrganization',
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
          'lets' => 
          array (
            0 => 
            array (
              'name' => 'codeValue4',
              'value' => '@schemeID',
              'ast' => 
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
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'FX-SCH-A-000031',
              'flag' => 'fatal',
              'test' => 'string-length($codeValue4)=0 or document(\'FACTUR-X_MINIMUM_codedb.xml\')/codedb/cl[@id=4]/enumeration[@value=$codeValue4]',
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
                    1 => 'fn:string-length',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'var',
                        1 => 'codeValue4',
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
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'call',
                        1 => 'fn:document',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'literal',
                            1 => 'xs:string',
                            2 => 'FACTUR-X_MINIMUM_codedb.xml',
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
                          1 => '',
                          2 => 'codedb',
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
                        1 => '',
                        2 => 'cl',
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
                              2 => 'id',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                          3 => 
                          array (
                            0 => 'literal',
                            1 => 'xs:integer',
                            2 => '4',
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
                      1 => '',
                      2 => 'enumeration',
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
                            2 => 'value',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        3 => 
                        array (
                          0 => 'var',
                          1 => 'codeValue4',
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => 'Value of \'@schemeID\' is not allowed.',
            ),
          ),
        ),
        16 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty/ram:SpecifiedTaxRegistration',
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
                2 => 'SpecifiedTaxRegistration',
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
              'id' => 'FX-REPORT-afa6ba2879',
              'flag' => 'fatal',
              'test' => 'true()',
              'ast' => 
              array (
                0 => 'call',
                1 => 'fn:true',
                2 => 
                array (
                ),
              ),
              'message' => 'Element \'ram:SpecifiedTaxRegistration\' is marked as not used in the given context.',
              'report' => true,
            ),
          ),
        ),
        17 => 
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
              'id' => 'FX-SCH-A-000409',
              'flag' => 'fatal',
              'test' => 'count(ram:Name)=1',
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
              'message' => 'Element \'ram:Name\' must occur exactly 1 times.',
            ),
            1 => 
            array (
              'id' => 'FX-SCH-A-000032',
              'flag' => 'fatal',
              'test' => 'count(ram:PostalTradeAddress)=1',
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
                ),
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '1',
                ),
              ),
              'message' => 'Element \'ram:PostalTradeAddress\' must occur exactly 1 times.',
            ),
            2 => 
            array (
              'id' => 'FX-SCH-A-000033',
              'flag' => 'fatal',
              'test' => 'count(ram:SpecifiedTaxRegistration[ram:ID/@schemeID="VA"])<=1',
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
                            2 => 'VA',
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
              'message' => 'Element variant \'ram:SpecifiedTaxRegistration[ram:ID/@schemeID="VA"]\' may occur at maximum 1 times.',
            ),
            3 => 
            array (
              'id' => 'FX-SCH-A-000034',
              'flag' => 'fatal',
              'test' => 'count(ram:SpecifiedTaxRegistration[ram:ID/@schemeID="FC"])<=1',
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
                            2 => 'FC',
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
              'message' => 'Element variant \'ram:SpecifiedTaxRegistration[ram:ID/@schemeID="FC"]\' may occur at maximum 1 times.',
            ),
          ),
        ),
        18 => 
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
              'id' => 'FX-SCH-A-000035',
              'flag' => 'fatal',
              'test' => 'count(ram:CountryID)=1',
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
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '1',
                ),
              ),
              'message' => 'Element \'ram:CountryID\' must occur exactly 1 times.',
            ),
          ),
        ),
        19 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:PostalTradeAddress/ram:CountryID',
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
          'lets' => 
          array (
            0 => 
            array (
              'name' => 'codeValue5',
              'value' => '.',
              'ast' => 
              array (
                0 => 'context',
              ),
            ),
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'FX-SCH-A-000036',
              'flag' => 'fatal',
              'test' => 'string-length($codeValue5)=0 or document(\'FACTUR-X_MINIMUM_codedb.xml\')/codedb/cl[@id=5]/enumeration[@value=$codeValue5]',
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
                    1 => 'fn:string-length',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'var',
                        1 => 'codeValue5',
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
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'call',
                        1 => 'fn:document',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'literal',
                            1 => 'xs:string',
                            2 => 'FACTUR-X_MINIMUM_codedb.xml',
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
                          1 => '',
                          2 => 'codedb',
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
                        1 => '',
                        2 => 'cl',
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
                              2 => 'id',
                            ),
                            3 => 
                            array (
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
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => '',
                      2 => 'enumeration',
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
                            2 => 'value',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        3 => 
                        array (
                          0 => 'var',
                          1 => 'codeValue5',
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => 'Value of \'ram:CountryID\' is not allowed.',
            ),
          ),
        ),
        20 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:SpecifiedLegalOrganization/ram:ID',
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
                  2 => 'SpecifiedLegalOrganization',
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
          'lets' => 
          array (
            0 => 
            array (
              'name' => 'codeValue4',
              'value' => '@schemeID',
              'ast' => 
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
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'FX-SCH-A-000410',
              'flag' => 'fatal',
              'test' => 'string-length($codeValue4)=0 or document(\'FACTUR-X_MINIMUM_codedb.xml\')/codedb/cl[@id=4]/enumeration[@value=$codeValue4]',
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
                    1 => 'fn:string-length',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'var',
                        1 => 'codeValue4',
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
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'call',
                        1 => 'fn:document',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'literal',
                            1 => 'xs:string',
                            2 => 'FACTUR-X_MINIMUM_codedb.xml',
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
                          1 => '',
                          2 => 'codedb',
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
                        1 => '',
                        2 => 'cl',
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
                              2 => 'id',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                          3 => 
                          array (
                            0 => 'literal',
                            1 => 'xs:integer',
                            2 => '4',
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
                      1 => '',
                      2 => 'enumeration',
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
                            2 => 'value',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        3 => 
                        array (
                          0 => 'var',
                          1 => 'codeValue4',
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => 'Value of \'@schemeID\' is not allowed.',
            ),
          ),
        ),
        21 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:SpecifiedTaxRegistration[ not(ram:ID/@schemeID="VA") and  not(ram:ID/@schemeID="FC")]',
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
                2 => 'SpecifiedTaxRegistration',
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
                          2 => 'VA',
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
                          2 => 'FC',
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
              'id' => 'FX-REPORT-cd28952a79',
              'flag' => 'fatal',
              'test' => 'true()',
              'ast' => 
              array (
                0 => 'call',
                1 => 'fn:true',
                2 => 
                array (
                ),
              ),
              'message' => 'Element variant \'ram:SpecifiedTaxRegistration[ not(ram:ID/@schemeID="VA") and not(ram:ID/@schemeID="FC")]\' is marked as not used in the given context.',
              'report' => true,
            ),
          ),
        ),
        22 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:SpecifiedTaxRegistration[ram:ID/@schemeID="FC"]',
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
                    2 => 'FC',
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
              'id' => 'FX-SCH-A-000411',
              'flag' => 'fatal',
              'test' => 'count(ram:ID)=1',
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
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '1',
                ),
              ),
              'message' => 'Element \'ram:ID\' must occur exactly 1 times.',
            ),
          ),
        ),
        23 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:SpecifiedTaxRegistration[ram:ID/@schemeID="FC"]/ram:ID',
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
                      2 => 'FC',
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
                2 => 'ID',
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
              'id' => 'FX-SCH-A-000037',
              'flag' => 'fatal',
              'test' => '@schemeID',
              'ast' => 
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
              'message' => 'Attribute \'@schemeID\' is required in this context.',
            ),
          ),
        ),
        24 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:SpecifiedTaxRegistration[ram:ID/@schemeID="VA"]',
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
                    2 => 'VA',
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
              'id' => 'FX-SCH-A-000412',
              'flag' => 'fatal',
              'test' => 'count(ram:ID)=1',
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
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '1',
                ),
              ),
              'message' => 'Element \'ram:ID\' must occur exactly 1 times.',
            ),
          ),
        ),
        25 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:SpecifiedTaxRegistration[ram:ID/@schemeID="VA"]/ram:ID',
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
                      2 => 'VA',
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
                2 => 'ID',
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
              'id' => 'FX-SCH-A-000413',
              'flag' => 'fatal',
              'test' => '@schemeID',
              'ast' => 
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
              'message' => 'Attribute \'@schemeID\' is required in this context.',
            ),
          ),
        ),
        26 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement',
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
                2 => 'ApplicableHeaderTradeSettlement',
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
              'id' => 'FX-SCH-A-000038',
              'flag' => 'fatal',
              'test' => 'count(ram:InvoiceCurrencyCode)=1',
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
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '1',
                ),
              ),
              'message' => 'Element \'ram:InvoiceCurrencyCode\' must occur exactly 1 times.',
            ),
            1 => 
            array (
              'id' => 'FX-SCH-A-000039',
              'flag' => 'fatal',
              'test' => 'count(ram:SpecifiedTradeSettlementHeaderMonetarySummation)=1',
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
                  ),
                ),
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '1',
                ),
              ),
              'message' => 'Element \'ram:SpecifiedTradeSettlementHeaderMonetarySummation\' must occur exactly 1 times.',
            ),
          ),
        ),
        27 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:InvoiceCurrencyCode',
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
                2 => 'InvoiceCurrencyCode',
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
              'name' => 'codeValue6',
              'value' => '.',
              'ast' => 
              array (
                0 => 'context',
              ),
            ),
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'FX-SCH-A-000040',
              'flag' => 'fatal',
              'test' => 'string-length($codeValue6)=0 or document(\'FACTUR-X_MINIMUM_codedb.xml\')/codedb/cl[@id=6]/enumeration[@value=$codeValue6]',
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
                    1 => 'fn:string-length',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'var',
                        1 => 'codeValue6',
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
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'call',
                        1 => 'fn:document',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'literal',
                            1 => 'xs:string',
                            2 => 'FACTUR-X_MINIMUM_codedb.xml',
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
                          1 => '',
                          2 => 'codedb',
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
                        1 => '',
                        2 => 'cl',
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
                              2 => 'id',
                            ),
                            3 => 
                            array (
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
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => '',
                      2 => 'enumeration',
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
                            2 => 'value',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        3 => 
                        array (
                          0 => 'var',
                          1 => 'codeValue6',
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => 'Value of \'ram:InvoiceCurrencyCode\' is not allowed.',
            ),
          ),
        ),
        28 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation',
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
                2 => 'SpecifiedTradeSettlementHeaderMonetarySummation',
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
              'id' => 'FX-SCH-A-000041',
              'flag' => 'fatal',
              'test' => 'count(ram:TaxBasisTotalAmount)=1',
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
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'TaxBasisTotalAmount',
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
              'message' => 'Element \'ram:TaxBasisTotalAmount\' must occur exactly 1 times.',
            ),
            1 => 
            array (
              'id' => 'FX-SCH-A-000042',
              'flag' => 'fatal',
              'test' => 'count(ram:TaxTotalAmount[@currencyID=../../ram:InvoiceCurrencyCode])<=1',
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
              'message' => 'Element variant \'ram:TaxTotalAmount[@currencyID=../../ram:InvoiceCurrencyCode]\' may occur at maximum 1 times.',
            ),
            2 => 
            array (
              'id' => 'FX-SCH-A-000192',
              'flag' => 'fatal',
              'test' => 'count(ram:TaxTotalAmount[@currencyID=../../ram:TaxCurrencyCode])<=1',
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
              'message' => 'Element variant \'ram:TaxTotalAmount[@currencyID=../../ram:TaxCurrencyCode]\' may occur at maximum 1 times.',
            ),
            3 => 
            array (
              'id' => 'FX-SCH-A-000043',
              'flag' => 'fatal',
              'test' => 'count(ram:GrandTotalAmount)=1',
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
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'GrandTotalAmount',
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
              'message' => 'Element \'ram:GrandTotalAmount\' must occur exactly 1 times.',
            ),
            4 => 
            array (
              'id' => 'FX-SCH-A-000044',
              'flag' => 'fatal',
              'test' => 'count(ram:DuePayableAmount)=1',
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
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
                        2 => 'DuePayableAmount',
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
              'message' => 'Element \'ram:DuePayableAmount\' must occur exactly 1 times.',
            ),
          ),
        ),
        29 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:DuePayableAmount',
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
                  2 => 'SpecifiedTradeSettlementHeaderMonetarySummation',
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
                2 => 'DuePayableAmount',
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
              'id' => 'FX-REPORT-a19bb88981',
              'flag' => 'fatal',
              'test' => '@currencyID',
              'ast' => 
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
              'message' => 'Attribute @currencyID\' marked as not used in the given context.',
              'report' => true,
            ),
          ),
        ),
        30 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:GrandTotalAmount',
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
                  2 => 'SpecifiedTradeSettlementHeaderMonetarySummation',
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
                2 => 'GrandTotalAmount',
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
              'id' => 'FX-REPORT-1c21ba0ee9',
              'flag' => 'fatal',
              'test' => '@currencyID',
              'ast' => 
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
              'message' => 'Attribute @currencyID\' marked as not used in the given context.',
              'report' => true,
            ),
          ),
        ),
        31 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxBasisTotalAmount',
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
                  2 => 'SpecifiedTradeSettlementHeaderMonetarySummation',
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
                2 => 'TaxBasisTotalAmount',
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
              'id' => 'FX-REPORT-73fc98f8f9',
              'flag' => 'fatal',
              'test' => '@currencyID',
              'ast' => 
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
              'message' => 'Attribute @currencyID\' marked as not used in the given context.',
              'report' => true,
            ),
          ),
        ),
        32 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[ not(@currencyID=../../ram:InvoiceCurrencyCode) and  not(@currencyID=../../ram:TaxCurrencyCode)]',
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
                  2 => 'SpecifiedTradeSettlementHeaderMonetarySummation',
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
                2 => 'TaxTotalAmount',
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
              'id' => 'FX-REPORT-d2df2fb177',
              'flag' => 'fatal',
              'test' => 'true()',
              'ast' => 
              array (
                0 => 'call',
                1 => 'fn:true',
                2 => 
                array (
                ),
              ),
              'message' => 'Element variant \'ram:TaxTotalAmount[ not(@currencyID=../../ram:InvoiceCurrencyCode) and not(@currencyID=../../ram:TaxCurrencyCode)]\' is marked as not used in the given context.',
              'report' => true,
            ),
          ),
        ),
        33 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID=../../ram:InvoiceCurrencyCode]',
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
                  2 => 'SpecifiedTradeSettlementHeaderMonetarySummation',
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
              ),
            ),
          ),
          'lets' => 
          array (
            0 => 
            array (
              'name' => 'codeValue7',
              'value' => '@currencyID',
              'ast' => 
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
            ),
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'FX-SCH-A-000045',
              'flag' => 'fatal',
              'test' => 'string-length($codeValue7)=0 or document(\'FACTUR-X_MINIMUM_codedb.xml\')/codedb/cl[@id=7]/enumeration[@value=$codeValue7]',
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
                    1 => 'fn:string-length',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'var',
                        1 => 'codeValue7',
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
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'call',
                        1 => 'fn:document',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'literal',
                            1 => 'xs:string',
                            2 => 'FACTUR-X_MINIMUM_codedb.xml',
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
                          1 => '',
                          2 => 'codedb',
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
                        1 => '',
                        2 => 'cl',
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
                              2 => 'id',
                            ),
                            3 => 
                            array (
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
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => '',
                      2 => 'enumeration',
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
                            2 => 'value',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        3 => 
                        array (
                          0 => 'var',
                          1 => 'codeValue7',
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => 'Value of \'@currencyID\' is not allowed.',
            ),
          ),
        ),
        34 => 
        array (
          'context' => '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID=../../ram:TaxCurrencyCode]',
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
                  2 => 'SpecifiedTradeSettlementHeaderMonetarySummation',
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
              ),
            ),
          ),
          'lets' => 
          array (
            0 => 
            array (
              'name' => 'codeValue8',
              'value' => '@currencyID',
              'ast' => 
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
            ),
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'FX-SCH-A-000414',
              'flag' => 'fatal',
              'test' => 'string-length($codeValue8)=0 or document(\'FACTUR-X_MINIMUM_codedb.xml\')/codedb/cl[@id=8]/enumeration[@value=$codeValue8]',
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
                    1 => 'fn:string-length',
                    2 => 
                    array (
                      0 => 
                      array (
                        0 => 'var',
                        1 => 'codeValue8',
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
                  0 => 'path',
                  1 => 
                  array (
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'call',
                        1 => 'fn:document',
                        2 => 
                        array (
                          0 => 
                          array (
                            0 => 'literal',
                            1 => 'xs:string',
                            2 => 'FACTUR-X_MINIMUM_codedb.xml',
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
                          1 => '',
                          2 => 'codedb',
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
                        1 => '',
                        2 => 'cl',
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
                              2 => 'id',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                          3 => 
                          array (
                            0 => 'literal',
                            1 => 'xs:integer',
                            2 => '8',
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
                      1 => '',
                      2 => 'enumeration',
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
                            2 => 'value',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                        3 => 
                        array (
                          0 => 'var',
                          1 => 'codeValue8',
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => 'Value of \'@currencyID\' is not allowed.',
            ),
          ),
        ),
      ),
    ),
  ),
  'documents' => 
  array (
    'FACTUR-X_MINIMUM_codedb.xml' => '<?xml version="1.0" encoding="UTF-8"?>
<!-- Factur-X release metadata
     Version: 1.09.2
     Release date: 2026-08-04
     Effective date: 2026-09-01
-->
<codedb>
  <cl id="1">
    <enumeration value="urn:factur-x.eu:1p0:minimum"/>
    <enumeration value="urn:zugferd.de:2p0:minimum"/>
  </cl>
  <cl id="2">
    <enumeration value="71"/>
    <enumeration value="80"/>
    <enumeration value="81"/>
    <enumeration value="82"/>
    <enumeration value="83"/>
    <enumeration value="84"/>
    <enumeration value="102"/>
    <enumeration value="130"/>
    <enumeration value="202"/>
    <enumeration value="203"/>
    <enumeration value="204"/>
    <enumeration value="211"/>
    <enumeration value="218"/>
    <enumeration value="219"/>
    <enumeration value="261"/>
    <enumeration value="262"/>
    <enumeration value="295"/>
    <enumeration value="296"/>
    <enumeration value="308"/>
    <enumeration value="325"/>
    <enumeration value="326"/>
    <enumeration value="331"/>
    <enumeration value="380"/>
    <enumeration value="381"/>
    <enumeration value="382"/>
    <enumeration value="383"/>
    <enumeration value="384"/>
    <enumeration value="385"/>
    <enumeration value="386"/>
    <enumeration value="387"/>
    <enumeration value="388"/>
    <enumeration value="389"/>
    <enumeration value="390"/>
    <enumeration value="393"/>
    <enumeration value="394"/>
    <enumeration value="395"/>
    <enumeration value="396"/>
    <enumeration value="420"/>
    <enumeration value="456"/>
    <enumeration value="457"/>
    <enumeration value="458"/>
    <enumeration value="471"/>
    <enumeration value="472"/>
    <enumeration value="473"/>
    <enumeration value="500"/>
    <enumeration value="501"/>
    <enumeration value="502"/>
    <enumeration value="503"/>
    <enumeration value="527"/>
    <enumeration value="532"/>
    <enumeration value="553"/>
    <enumeration value="575"/>
    <enumeration value="623"/>
    <enumeration value="633"/>
    <enumeration value="751"/>
    <enumeration value="780"/>
    <enumeration value="817"/>
    <enumeration value="870"/>
    <enumeration value="875"/>
    <enumeration value="876"/>
    <enumeration value="877"/>
    <enumeration value="935"/>
  </cl>
  <cl id="3">
    <enumeration value="102"/>
  </cl>
  <cl id="4">
    <enumeration value="0002"/>
    <enumeration value="0003"/>
    <enumeration value="0004"/>
    <enumeration value="0005"/>
    <enumeration value="0006"/>
    <enumeration value="0007"/>
    <enumeration value="0008"/>
    <enumeration value="0009"/>
    <enumeration value="0010"/>
    <enumeration value="0011"/>
    <enumeration value="0012"/>
    <enumeration value="0013"/>
    <enumeration value="0014"/>
    <enumeration value="0015"/>
    <enumeration value="0016"/>
    <enumeration value="0017"/>
    <enumeration value="0018"/>
    <enumeration value="0019"/>
    <enumeration value="0020"/>
    <enumeration value="0021"/>
    <enumeration value="0022"/>
    <enumeration value="0023"/>
    <enumeration value="0024"/>
    <enumeration value="0025"/>
    <enumeration value="0026"/>
    <enumeration value="0027"/>
    <enumeration value="0028"/>
    <enumeration value="0029"/>
    <enumeration value="0030"/>
    <enumeration value="0031"/>
    <enumeration value="0032"/>
    <enumeration value="0033"/>
    <enumeration value="0034"/>
    <enumeration value="0035"/>
    <enumeration value="0036"/>
    <enumeration value="0037"/>
    <enumeration value="0038"/>
    <enumeration value="0039"/>
    <enumeration value="0040"/>
    <enumeration value="0041"/>
    <enumeration value="0042"/>
    <enumeration value="0043"/>
    <enumeration value="0044"/>
    <enumeration value="0045"/>
    <enumeration value="0046"/>
    <enumeration value="0047"/>
    <enumeration value="0048"/>
    <enumeration value="0049"/>
    <enumeration value="0050"/>
    <enumeration value="0051"/>
    <enumeration value="0052"/>
    <enumeration value="0053"/>
    <enumeration value="0054"/>
    <enumeration value="0055"/>
    <enumeration value="0056"/>
    <enumeration value="0057"/>
    <enumeration value="0058"/>
    <enumeration value="0059"/>
    <enumeration value="0060"/>
    <enumeration value="0061"/>
    <enumeration value="0062"/>
    <enumeration value="0063"/>
    <enumeration value="0064"/>
    <enumeration value="0065"/>
    <enumeration value="0066"/>
    <enumeration value="0067"/>
    <enumeration value="0068"/>
    <enumeration value="0069"/>
    <enumeration value="0070"/>
    <enumeration value="0071"/>
    <enumeration value="0072"/>
    <enumeration value="0073"/>
    <enumeration value="0074"/>
    <enumeration value="0075"/>
    <enumeration value="0076"/>
    <enumeration value="0077"/>
    <enumeration value="0078"/>
    <enumeration value="0079"/>
    <enumeration value="0080"/>
    <enumeration value="0081"/>
    <enumeration value="0082"/>
    <enumeration value="0083"/>
    <enumeration value="0084"/>
    <enumeration value="0085"/>
    <enumeration value="0086"/>
    <enumeration value="0087"/>
    <enumeration value="0088"/>
    <enumeration value="0089"/>
    <enumeration value="0090"/>
    <enumeration value="0091"/>
    <enumeration value="0093"/>
    <enumeration value="0094"/>
    <enumeration value="0095"/>
    <enumeration value="0096"/>
    <enumeration value="0097"/>
    <enumeration value="0098"/>
    <enumeration value="0099"/>
    <enumeration value="0100"/>
    <enumeration value="0101"/>
    <enumeration value="0102"/>
    <enumeration value="0104"/>
    <enumeration value="0105"/>
    <enumeration value="0106"/>
    <enumeration value="0107"/>
    <enumeration value="0108"/>
    <enumeration value="0109"/>
    <enumeration value="0110"/>
    <enumeration value="0111"/>
    <enumeration value="0112"/>
    <enumeration value="0113"/>
    <enumeration value="0114"/>
    <enumeration value="0115"/>
    <enumeration value="0116"/>
    <enumeration value="0117"/>
    <enumeration value="0118"/>
    <enumeration value="0119"/>
    <enumeration value="0120"/>
    <enumeration value="0121"/>
    <enumeration value="0122"/>
    <enumeration value="0123"/>
    <enumeration value="0124"/>
    <enumeration value="0125"/>
    <enumeration value="0126"/>
    <enumeration value="0127"/>
    <enumeration value="0128"/>
    <enumeration value="0129"/>
    <enumeration value="0130"/>
    <enumeration value="0131"/>
    <enumeration value="0132"/>
    <enumeration value="0133"/>
    <enumeration value="0134"/>
    <enumeration value="0135"/>
    <enumeration value="0136"/>
    <enumeration value="0137"/>
    <enumeration value="0138"/>
    <enumeration value="0139"/>
    <enumeration value="0140"/>
    <enumeration value="0141"/>
    <enumeration value="0142"/>
    <enumeration value="0143"/>
    <enumeration value="0144"/>
    <enumeration value="0145"/>
    <enumeration value="0146"/>
    <enumeration value="0147"/>
    <enumeration value="0148"/>
    <enumeration value="0149"/>
    <enumeration value="0150"/>
    <enumeration value="0151"/>
    <enumeration value="0152"/>
    <enumeration value="0153"/>
    <enumeration value="0154"/>
    <enumeration value="0155"/>
    <enumeration value="0156"/>
    <enumeration value="0157"/>
    <enumeration value="0158"/>
    <enumeration value="0159"/>
    <enumeration value="0160"/>
    <enumeration value="0161"/>
    <enumeration value="0162"/>
    <enumeration value="0163"/>
    <enumeration value="0164"/>
    <enumeration value="0165"/>
    <enumeration value="0166"/>
    <enumeration value="0167"/>
    <enumeration value="0168"/>
    <enumeration value="0169"/>
    <enumeration value="0170"/>
    <enumeration value="0171"/>
    <enumeration value="0172"/>
    <enumeration value="0173"/>
    <enumeration value="0174"/>
    <enumeration value="0175"/>
    <enumeration value="0176"/>
    <enumeration value="0177"/>
    <enumeration value="0178"/>
    <enumeration value="0179"/>
    <enumeration value="0180"/>
    <enumeration value="0183"/>
    <enumeration value="0184"/>
    <enumeration value="0185"/>
    <enumeration value="0186"/>
    <enumeration value="0187"/>
    <enumeration value="0188"/>
    <enumeration value="0189"/>
    <enumeration value="0190"/>
    <enumeration value="0191"/>
    <enumeration value="0192"/>
    <enumeration value="0193"/>
    <enumeration value="0194"/>
    <enumeration value="0195"/>
    <enumeration value="0196"/>
    <enumeration value="0197"/>
    <enumeration value="0198"/>
    <enumeration value="0199"/>
    <enumeration value="0200"/>
    <enumeration value="0201"/>
    <enumeration value="0202"/>
    <enumeration value="0203"/>
    <enumeration value="0204"/>
    <enumeration value="0205"/>
    <enumeration value="0206"/>
    <enumeration value="0207"/>
    <enumeration value="0208"/>
    <enumeration value="0209"/>
    <enumeration value="0210"/>
    <enumeration value="0211"/>
    <enumeration value="0212"/>
    <enumeration value="0213"/>
    <enumeration value="0214"/>
    <enumeration value="0215"/>
    <enumeration value="0216"/>
    <enumeration value="0217"/>
    <enumeration value="0218"/>
    <enumeration value="0219"/>
    <enumeration value="0220"/>
    <enumeration value="0221"/>
    <enumeration value="0222"/>
    <enumeration value="0223"/>
    <enumeration value="0224"/>
    <enumeration value="0225"/>
    <enumeration value="0226"/>
    <enumeration value="0227"/>
    <enumeration value="0228"/>
    <enumeration value="0229"/>
    <enumeration value="0230"/>
    <enumeration value="0231"/>
    <enumeration value="0232"/>
    <enumeration value="0233"/>
    <enumeration value="0234"/>
    <enumeration value="0235"/>
    <enumeration value="0236"/>
    <enumeration value="0237"/>
    <enumeration value="0238"/>
    <enumeration value="0239"/>
    <enumeration value="0240"/>
    <enumeration value="0241"/>
    <enumeration value="0242"/>
    <enumeration value="0243"/>
    <enumeration value="0244"/>
    <enumeration value="0245"/>
    <enumeration value="0246"/>
    <enumeration value="0247"/>
    <enumeration value="0248"/>
  </cl>
  <cl id="5">
    <enumeration value="1A"/>
    <enumeration value="AD"/>
    <enumeration value="AE"/>
    <enumeration value="AF"/>
    <enumeration value="AG"/>
    <enumeration value="AI"/>
    <enumeration value="AL"/>
    <enumeration value="AM"/>
    <enumeration value="AO"/>
    <enumeration value="AQ"/>
    <enumeration value="AR"/>
    <enumeration value="AS"/>
    <enumeration value="AT"/>
    <enumeration value="AU"/>
    <enumeration value="AW"/>
    <enumeration value="AX"/>
    <enumeration value="AZ"/>
    <enumeration value="BA"/>
    <enumeration value="BB"/>
    <enumeration value="BD"/>
    <enumeration value="BE"/>
    <enumeration value="BF"/>
    <enumeration value="BG"/>
    <enumeration value="BH"/>
    <enumeration value="BI"/>
    <enumeration value="BJ"/>
    <enumeration value="BL"/>
    <enumeration value="BM"/>
    <enumeration value="BN"/>
    <enumeration value="BO"/>
    <enumeration value="BQ"/>
    <enumeration value="BR"/>
    <enumeration value="BS"/>
    <enumeration value="BT"/>
    <enumeration value="BV"/>
    <enumeration value="BW"/>
    <enumeration value="BY"/>
    <enumeration value="BZ"/>
    <enumeration value="CA"/>
    <enumeration value="CC"/>
    <enumeration value="CD"/>
    <enumeration value="CF"/>
    <enumeration value="CG"/>
    <enumeration value="CH"/>
    <enumeration value="CI"/>
    <enumeration value="CK"/>
    <enumeration value="CL"/>
    <enumeration value="CM"/>
    <enumeration value="CN"/>
    <enumeration value="CO"/>
    <enumeration value="CR"/>
    <enumeration value="CU"/>
    <enumeration value="CV"/>
    <enumeration value="CW"/>
    <enumeration value="CX"/>
    <enumeration value="CY"/>
    <enumeration value="CZ"/>
    <enumeration value="DE"/>
    <enumeration value="DJ"/>
    <enumeration value="DK"/>
    <enumeration value="DM"/>
    <enumeration value="DO"/>
    <enumeration value="DZ"/>
    <enumeration value="EC"/>
    <enumeration value="EE"/>
    <enumeration value="EG"/>
    <enumeration value="EH"/>
    <enumeration value="ER"/>
    <enumeration value="ES"/>
    <enumeration value="ET"/>
    <enumeration value="FI"/>
    <enumeration value="FJ"/>
    <enumeration value="FK"/>
    <enumeration value="FM"/>
    <enumeration value="FO"/>
    <enumeration value="FR"/>
    <enumeration value="GA"/>
    <enumeration value="GB"/>
    <enumeration value="GD"/>
    <enumeration value="GE"/>
    <enumeration value="GF"/>
    <enumeration value="GG"/>
    <enumeration value="GH"/>
    <enumeration value="GI"/>
    <enumeration value="GL"/>
    <enumeration value="GM"/>
    <enumeration value="GN"/>
    <enumeration value="GP"/>
    <enumeration value="GQ"/>
    <enumeration value="GR"/>
    <enumeration value="GS"/>
    <enumeration value="GT"/>
    <enumeration value="GU"/>
    <enumeration value="GW"/>
    <enumeration value="GY"/>
    <enumeration value="HK"/>
    <enumeration value="HM"/>
    <enumeration value="HN"/>
    <enumeration value="HR"/>
    <enumeration value="HT"/>
    <enumeration value="HU"/>
    <enumeration value="ID"/>
    <enumeration value="IE"/>
    <enumeration value="IL"/>
    <enumeration value="IM"/>
    <enumeration value="IN"/>
    <enumeration value="IO"/>
    <enumeration value="IQ"/>
    <enumeration value="IR"/>
    <enumeration value="IS"/>
    <enumeration value="IT"/>
    <enumeration value="JE"/>
    <enumeration value="JM"/>
    <enumeration value="JO"/>
    <enumeration value="JP"/>
    <enumeration value="KE"/>
    <enumeration value="KG"/>
    <enumeration value="KH"/>
    <enumeration value="KI"/>
    <enumeration value="KM"/>
    <enumeration value="KN"/>
    <enumeration value="KP"/>
    <enumeration value="KR"/>
    <enumeration value="KW"/>
    <enumeration value="KY"/>
    <enumeration value="KZ"/>
    <enumeration value="LA"/>
    <enumeration value="LB"/>
    <enumeration value="LC"/>
    <enumeration value="LI"/>
    <enumeration value="LK"/>
    <enumeration value="LR"/>
    <enumeration value="LS"/>
    <enumeration value="LT"/>
    <enumeration value="LU"/>
    <enumeration value="LV"/>
    <enumeration value="LY"/>
    <enumeration value="MA"/>
    <enumeration value="MC"/>
    <enumeration value="MD"/>
    <enumeration value="ME"/>
    <enumeration value="MF"/>
    <enumeration value="MG"/>
    <enumeration value="MH"/>
    <enumeration value="MK"/>
    <enumeration value="ML"/>
    <enumeration value="MM"/>
    <enumeration value="MN"/>
    <enumeration value="MO"/>
    <enumeration value="MP"/>
    <enumeration value="MQ"/>
    <enumeration value="MR"/>
    <enumeration value="MS"/>
    <enumeration value="MT"/>
    <enumeration value="MU"/>
    <enumeration value="MV"/>
    <enumeration value="MW"/>
    <enumeration value="MX"/>
    <enumeration value="MY"/>
    <enumeration value="MZ"/>
    <enumeration value="NA"/>
    <enumeration value="NC"/>
    <enumeration value="NE"/>
    <enumeration value="NF"/>
    <enumeration value="NG"/>
    <enumeration value="NI"/>
    <enumeration value="NL"/>
    <enumeration value="NO"/>
    <enumeration value="NP"/>
    <enumeration value="NR"/>
    <enumeration value="NU"/>
    <enumeration value="NZ"/>
    <enumeration value="OM"/>
    <enumeration value="PA"/>
    <enumeration value="PE"/>
    <enumeration value="PF"/>
    <enumeration value="PG"/>
    <enumeration value="PH"/>
    <enumeration value="PK"/>
    <enumeration value="PL"/>
    <enumeration value="PM"/>
    <enumeration value="PN"/>
    <enumeration value="PR"/>
    <enumeration value="PS"/>
    <enumeration value="PT"/>
    <enumeration value="PW"/>
    <enumeration value="PY"/>
    <enumeration value="QA"/>
    <enumeration value="RE"/>
    <enumeration value="RO"/>
    <enumeration value="RS"/>
    <enumeration value="RU"/>
    <enumeration value="RW"/>
    <enumeration value="SA"/>
    <enumeration value="SB"/>
    <enumeration value="SC"/>
    <enumeration value="SD"/>
    <enumeration value="SE"/>
    <enumeration value="SG"/>
    <enumeration value="SH"/>
    <enumeration value="SI"/>
    <enumeration value="SJ"/>
    <enumeration value="SK"/>
    <enumeration value="SL"/>
    <enumeration value="SM"/>
    <enumeration value="SN"/>
    <enumeration value="SO"/>
    <enumeration value="SR"/>
    <enumeration value="SS"/>
    <enumeration value="ST"/>
    <enumeration value="SV"/>
    <enumeration value="SX"/>
    <enumeration value="SY"/>
    <enumeration value="SZ"/>
    <enumeration value="TC"/>
    <enumeration value="TD"/>
    <enumeration value="TF"/>
    <enumeration value="TG"/>
    <enumeration value="TH"/>
    <enumeration value="TJ"/>
    <enumeration value="TK"/>
    <enumeration value="TL"/>
    <enumeration value="TM"/>
    <enumeration value="TN"/>
    <enumeration value="TO"/>
    <enumeration value="TR"/>
    <enumeration value="TT"/>
    <enumeration value="TV"/>
    <enumeration value="TW"/>
    <enumeration value="TZ"/>
    <enumeration value="UA"/>
    <enumeration value="UG"/>
    <enumeration value="UM"/>
    <enumeration value="US"/>
    <enumeration value="UY"/>
    <enumeration value="UZ"/>
    <enumeration value="VA"/>
    <enumeration value="VC"/>
    <enumeration value="VE"/>
    <enumeration value="VG"/>
    <enumeration value="VI"/>
    <enumeration value="VN"/>
    <enumeration value="VU"/>
    <enumeration value="WF"/>
    <enumeration value="WS"/>
    <enumeration value="XI"/>
    <enumeration value="YE"/>
    <enumeration value="YT"/>
    <enumeration value="ZA"/>
    <enumeration value="ZM"/>
    <enumeration value="ZW"/>
    <enumeration value="1A"/>
    <enumeration value="XI"/>
  </cl>
  <cl id="6">
    <enumeration value="AED"/>
    <enumeration value="AFN"/>
    <enumeration value="ALL"/>
    <enumeration value="AMD"/>
    <enumeration value="AOA"/>
    <enumeration value="ARS"/>
    <enumeration value="AUD"/>
    <enumeration value="AWG"/>
    <enumeration value="AZN"/>
    <enumeration value="BAM"/>
    <enumeration value="BBD"/>
    <enumeration value="BDT"/>
    <enumeration value="BHD"/>
    <enumeration value="BIF"/>
    <enumeration value="BMD"/>
    <enumeration value="BND"/>
    <enumeration value="BOB"/>
    <enumeration value="BOV"/>
    <enumeration value="BRL"/>
    <enumeration value="BSD"/>
    <enumeration value="BTN"/>
    <enumeration value="BWP"/>
    <enumeration value="BYN"/>
    <enumeration value="BZD"/>
    <enumeration value="CAD"/>
    <enumeration value="CDF"/>
    <enumeration value="CHE"/>
    <enumeration value="CHF"/>
    <enumeration value="CHW"/>
    <enumeration value="CLF"/>
    <enumeration value="CLP"/>
    <enumeration value="CNH"/>
    <enumeration value="CNY"/>
    <enumeration value="COP"/>
    <enumeration value="COU"/>
    <enumeration value="CRC"/>
    <enumeration value="CUP"/>
    <enumeration value="CVE"/>
    <enumeration value="CZK"/>
    <enumeration value="DJF"/>
    <enumeration value="DKK"/>
    <enumeration value="DOP"/>
    <enumeration value="DZD"/>
    <enumeration value="EGP"/>
    <enumeration value="ERN"/>
    <enumeration value="ETB"/>
    <enumeration value="EUR"/>
    <enumeration value="FJD"/>
    <enumeration value="FKP"/>
    <enumeration value="GBP"/>
    <enumeration value="GEL"/>
    <enumeration value="GHS"/>
    <enumeration value="GIP"/>
    <enumeration value="GMD"/>
    <enumeration value="GNF"/>
    <enumeration value="GTQ"/>
    <enumeration value="GYD"/>
    <enumeration value="HKD"/>
    <enumeration value="HNL"/>
    <enumeration value="HTG"/>
    <enumeration value="HUF"/>
    <enumeration value="IDR"/>
    <enumeration value="ILS"/>
    <enumeration value="INR"/>
    <enumeration value="IQD"/>
    <enumeration value="IRR"/>
    <enumeration value="ISK"/>
    <enumeration value="JMD"/>
    <enumeration value="JOD"/>
    <enumeration value="JPY"/>
    <enumeration value="KES"/>
    <enumeration value="KGS"/>
    <enumeration value="KHR"/>
    <enumeration value="KMF"/>
    <enumeration value="KPW"/>
    <enumeration value="KRW"/>
    <enumeration value="KWD"/>
    <enumeration value="KYD"/>
    <enumeration value="KZT"/>
    <enumeration value="LAK"/>
    <enumeration value="LBP"/>
    <enumeration value="LKR"/>
    <enumeration value="LRD"/>
    <enumeration value="LSL"/>
    <enumeration value="LYD"/>
    <enumeration value="MAD"/>
    <enumeration value="MDL"/>
    <enumeration value="MGA"/>
    <enumeration value="MKD"/>
    <enumeration value="MMK"/>
    <enumeration value="MNT"/>
    <enumeration value="MOP"/>
    <enumeration value="MRU"/>
    <enumeration value="MUR"/>
    <enumeration value="MVR"/>
    <enumeration value="MWK"/>
    <enumeration value="MXN"/>
    <enumeration value="MXV"/>
    <enumeration value="MYR"/>
    <enumeration value="MZN"/>
    <enumeration value="NAD"/>
    <enumeration value="NGN"/>
    <enumeration value="NIO"/>
    <enumeration value="NOK"/>
    <enumeration value="NPR"/>
    <enumeration value="NZD"/>
    <enumeration value="OMR"/>
    <enumeration value="PAB"/>
    <enumeration value="PEN"/>
    <enumeration value="PGK"/>
    <enumeration value="PHP"/>
    <enumeration value="PKR"/>
    <enumeration value="PLN"/>
    <enumeration value="PYG"/>
    <enumeration value="QAR"/>
    <enumeration value="RON"/>
    <enumeration value="RSD"/>
    <enumeration value="RUB"/>
    <enumeration value="RWF"/>
    <enumeration value="SAR"/>
    <enumeration value="SBD"/>
    <enumeration value="SCR"/>
    <enumeration value="SDG"/>
    <enumeration value="SEK"/>
    <enumeration value="SGD"/>
    <enumeration value="SHP"/>
    <enumeration value="SLE"/>
    <enumeration value="SOS"/>
    <enumeration value="SRD"/>
    <enumeration value="SSP"/>
    <enumeration value="STN"/>
    <enumeration value="SVC"/>
    <enumeration value="SYP"/>
    <enumeration value="SZL"/>
    <enumeration value="THB"/>
    <enumeration value="TJS"/>
    <enumeration value="TMT"/>
    <enumeration value="TND"/>
    <enumeration value="TOP"/>
    <enumeration value="TRY"/>
    <enumeration value="TTD"/>
    <enumeration value="TWD"/>
    <enumeration value="TZS"/>
    <enumeration value="UAH"/>
    <enumeration value="UGX"/>
    <enumeration value="USD"/>
    <enumeration value="USN"/>
    <enumeration value="UYI"/>
    <enumeration value="UYU"/>
    <enumeration value="UYW"/>
    <enumeration value="UZS"/>
    <enumeration value="VED"/>
    <enumeration value="VES"/>
    <enumeration value="VND"/>
    <enumeration value="VUV"/>
    <enumeration value="WST"/>
    <enumeration value="XAF"/>
    <enumeration value="XAG"/>
    <enumeration value="XAU"/>
    <enumeration value="XBA"/>
    <enumeration value="XBB"/>
    <enumeration value="XBC"/>
    <enumeration value="XBD"/>
    <enumeration value="XCD"/>
    <enumeration value="XCG"/>
    <enumeration value="XDR"/>
    <enumeration value="XOF"/>
    <enumeration value="XPD"/>
    <enumeration value="XPF"/>
    <enumeration value="XPT"/>
    <enumeration value="XSU"/>
    <enumeration value="XTS"/>
    <enumeration value="XUA"/>
    <enumeration value="XXX"/>
    <enumeration value="YER"/>
    <enumeration value="ZAR"/>
    <enumeration value="ZMW"/>
    <enumeration value="ZWG"/>
  </cl>
  <cl id="7">
    <enumeration value="AED"/>
    <enumeration value="AFN"/>
    <enumeration value="ALL"/>
    <enumeration value="AMD"/>
    <enumeration value="AOA"/>
    <enumeration value="ARS"/>
    <enumeration value="AUD"/>
    <enumeration value="AWG"/>
    <enumeration value="AZN"/>
    <enumeration value="BAM"/>
    <enumeration value="BBD"/>
    <enumeration value="BDT"/>
    <enumeration value="BHD"/>
    <enumeration value="BIF"/>
    <enumeration value="BMD"/>
    <enumeration value="BND"/>
    <enumeration value="BOB"/>
    <enumeration value="BOV"/>
    <enumeration value="BRL"/>
    <enumeration value="BSD"/>
    <enumeration value="BTN"/>
    <enumeration value="BWP"/>
    <enumeration value="BYN"/>
    <enumeration value="BZD"/>
    <enumeration value="CAD"/>
    <enumeration value="CDF"/>
    <enumeration value="CHE"/>
    <enumeration value="CHF"/>
    <enumeration value="CHW"/>
    <enumeration value="CLF"/>
    <enumeration value="CLP"/>
    <enumeration value="CNH"/>
    <enumeration value="CNY"/>
    <enumeration value="COP"/>
    <enumeration value="COU"/>
    <enumeration value="CRC"/>
    <enumeration value="CUP"/>
    <enumeration value="CVE"/>
    <enumeration value="CZK"/>
    <enumeration value="DJF"/>
    <enumeration value="DKK"/>
    <enumeration value="DOP"/>
    <enumeration value="DZD"/>
    <enumeration value="EGP"/>
    <enumeration value="ERN"/>
    <enumeration value="ETB"/>
    <enumeration value="EUR"/>
    <enumeration value="FJD"/>
    <enumeration value="FKP"/>
    <enumeration value="GBP"/>
    <enumeration value="GEL"/>
    <enumeration value="GHS"/>
    <enumeration value="GIP"/>
    <enumeration value="GMD"/>
    <enumeration value="GNF"/>
    <enumeration value="GTQ"/>
    <enumeration value="GYD"/>
    <enumeration value="HKD"/>
    <enumeration value="HNL"/>
    <enumeration value="HTG"/>
    <enumeration value="HUF"/>
    <enumeration value="IDR"/>
    <enumeration value="ILS"/>
    <enumeration value="INR"/>
    <enumeration value="IQD"/>
    <enumeration value="IRR"/>
    <enumeration value="ISK"/>
    <enumeration value="JMD"/>
    <enumeration value="JOD"/>
    <enumeration value="JPY"/>
    <enumeration value="KES"/>
    <enumeration value="KGS"/>
    <enumeration value="KHR"/>
    <enumeration value="KMF"/>
    <enumeration value="KPW"/>
    <enumeration value="KRW"/>
    <enumeration value="KWD"/>
    <enumeration value="KYD"/>
    <enumeration value="KZT"/>
    <enumeration value="LAK"/>
    <enumeration value="LBP"/>
    <enumeration value="LKR"/>
    <enumeration value="LRD"/>
    <enumeration value="LSL"/>
    <enumeration value="LYD"/>
    <enumeration value="MAD"/>
    <enumeration value="MDL"/>
    <enumeration value="MGA"/>
    <enumeration value="MKD"/>
    <enumeration value="MMK"/>
    <enumeration value="MNT"/>
    <enumeration value="MOP"/>
    <enumeration value="MRU"/>
    <enumeration value="MUR"/>
    <enumeration value="MVR"/>
    <enumeration value="MWK"/>
    <enumeration value="MXN"/>
    <enumeration value="MXV"/>
    <enumeration value="MYR"/>
    <enumeration value="MZN"/>
    <enumeration value="NAD"/>
    <enumeration value="NGN"/>
    <enumeration value="NIO"/>
    <enumeration value="NOK"/>
    <enumeration value="NPR"/>
    <enumeration value="NZD"/>
    <enumeration value="OMR"/>
    <enumeration value="PAB"/>
    <enumeration value="PEN"/>
    <enumeration value="PGK"/>
    <enumeration value="PHP"/>
    <enumeration value="PKR"/>
    <enumeration value="PLN"/>
    <enumeration value="PYG"/>
    <enumeration value="QAR"/>
    <enumeration value="RON"/>
    <enumeration value="RSD"/>
    <enumeration value="RUB"/>
    <enumeration value="RWF"/>
    <enumeration value="SAR"/>
    <enumeration value="SBD"/>
    <enumeration value="SCR"/>
    <enumeration value="SDG"/>
    <enumeration value="SEK"/>
    <enumeration value="SGD"/>
    <enumeration value="SHP"/>
    <enumeration value="SLE"/>
    <enumeration value="SOS"/>
    <enumeration value="SRD"/>
    <enumeration value="SSP"/>
    <enumeration value="STN"/>
    <enumeration value="SVC"/>
    <enumeration value="SYP"/>
    <enumeration value="SZL"/>
    <enumeration value="THB"/>
    <enumeration value="TJS"/>
    <enumeration value="TMT"/>
    <enumeration value="TND"/>
    <enumeration value="TOP"/>
    <enumeration value="TRY"/>
    <enumeration value="TTD"/>
    <enumeration value="TWD"/>
    <enumeration value="TZS"/>
    <enumeration value="UAH"/>
    <enumeration value="UGX"/>
    <enumeration value="USD"/>
    <enumeration value="USN"/>
    <enumeration value="UYI"/>
    <enumeration value="UYU"/>
    <enumeration value="UYW"/>
    <enumeration value="UZS"/>
    <enumeration value="VED"/>
    <enumeration value="VES"/>
    <enumeration value="VND"/>
    <enumeration value="VUV"/>
    <enumeration value="WST"/>
    <enumeration value="XAF"/>
    <enumeration value="XAG"/>
    <enumeration value="XAU"/>
    <enumeration value="XBA"/>
    <enumeration value="XBB"/>
    <enumeration value="XBC"/>
    <enumeration value="XBD"/>
    <enumeration value="XCD"/>
    <enumeration value="XCG"/>
    <enumeration value="XDR"/>
    <enumeration value="XOF"/>
    <enumeration value="XPD"/>
    <enumeration value="XPF"/>
    <enumeration value="XPT"/>
    <enumeration value="XSU"/>
    <enumeration value="XTS"/>
    <enumeration value="XUA"/>
    <enumeration value="XXX"/>
    <enumeration value="YER"/>
    <enumeration value="ZAR"/>
    <enumeration value="ZMW"/>
    <enumeration value="ZWG"/>
  </cl>
  <cl id="8">
    <enumeration value="AED"/>
    <enumeration value="AFN"/>
    <enumeration value="ALL"/>
    <enumeration value="AMD"/>
    <enumeration value="AOA"/>
    <enumeration value="ARS"/>
    <enumeration value="AUD"/>
    <enumeration value="AWG"/>
    <enumeration value="AZN"/>
    <enumeration value="BAM"/>
    <enumeration value="BBD"/>
    <enumeration value="BDT"/>
    <enumeration value="BHD"/>
    <enumeration value="BIF"/>
    <enumeration value="BMD"/>
    <enumeration value="BND"/>
    <enumeration value="BOB"/>
    <enumeration value="BOV"/>
    <enumeration value="BRL"/>
    <enumeration value="BSD"/>
    <enumeration value="BTN"/>
    <enumeration value="BWP"/>
    <enumeration value="BYN"/>
    <enumeration value="BZD"/>
    <enumeration value="CAD"/>
    <enumeration value="CDF"/>
    <enumeration value="CHE"/>
    <enumeration value="CHF"/>
    <enumeration value="CHW"/>
    <enumeration value="CLF"/>
    <enumeration value="CLP"/>
    <enumeration value="CNH"/>
    <enumeration value="CNY"/>
    <enumeration value="COP"/>
    <enumeration value="COU"/>
    <enumeration value="CRC"/>
    <enumeration value="CUP"/>
    <enumeration value="CVE"/>
    <enumeration value="CZK"/>
    <enumeration value="DJF"/>
    <enumeration value="DKK"/>
    <enumeration value="DOP"/>
    <enumeration value="DZD"/>
    <enumeration value="EGP"/>
    <enumeration value="ERN"/>
    <enumeration value="ETB"/>
    <enumeration value="EUR"/>
    <enumeration value="FJD"/>
    <enumeration value="FKP"/>
    <enumeration value="GBP"/>
    <enumeration value="GEL"/>
    <enumeration value="GHS"/>
    <enumeration value="GIP"/>
    <enumeration value="GMD"/>
    <enumeration value="GNF"/>
    <enumeration value="GTQ"/>
    <enumeration value="GYD"/>
    <enumeration value="HKD"/>
    <enumeration value="HNL"/>
    <enumeration value="HTG"/>
    <enumeration value="HUF"/>
    <enumeration value="IDR"/>
    <enumeration value="ILS"/>
    <enumeration value="INR"/>
    <enumeration value="IQD"/>
    <enumeration value="IRR"/>
    <enumeration value="ISK"/>
    <enumeration value="JMD"/>
    <enumeration value="JOD"/>
    <enumeration value="JPY"/>
    <enumeration value="KES"/>
    <enumeration value="KGS"/>
    <enumeration value="KHR"/>
    <enumeration value="KMF"/>
    <enumeration value="KPW"/>
    <enumeration value="KRW"/>
    <enumeration value="KWD"/>
    <enumeration value="KYD"/>
    <enumeration value="KZT"/>
    <enumeration value="LAK"/>
    <enumeration value="LBP"/>
    <enumeration value="LKR"/>
    <enumeration value="LRD"/>
    <enumeration value="LSL"/>
    <enumeration value="LYD"/>
    <enumeration value="MAD"/>
    <enumeration value="MDL"/>
    <enumeration value="MGA"/>
    <enumeration value="MKD"/>
    <enumeration value="MMK"/>
    <enumeration value="MNT"/>
    <enumeration value="MOP"/>
    <enumeration value="MRU"/>
    <enumeration value="MUR"/>
    <enumeration value="MVR"/>
    <enumeration value="MWK"/>
    <enumeration value="MXN"/>
    <enumeration value="MXV"/>
    <enumeration value="MYR"/>
    <enumeration value="MZN"/>
    <enumeration value="NAD"/>
    <enumeration value="NGN"/>
    <enumeration value="NIO"/>
    <enumeration value="NOK"/>
    <enumeration value="NPR"/>
    <enumeration value="NZD"/>
    <enumeration value="OMR"/>
    <enumeration value="PAB"/>
    <enumeration value="PEN"/>
    <enumeration value="PGK"/>
    <enumeration value="PHP"/>
    <enumeration value="PKR"/>
    <enumeration value="PLN"/>
    <enumeration value="PYG"/>
    <enumeration value="QAR"/>
    <enumeration value="RON"/>
    <enumeration value="RSD"/>
    <enumeration value="RUB"/>
    <enumeration value="RWF"/>
    <enumeration value="SAR"/>
    <enumeration value="SBD"/>
    <enumeration value="SCR"/>
    <enumeration value="SDG"/>
    <enumeration value="SEK"/>
    <enumeration value="SGD"/>
    <enumeration value="SHP"/>
    <enumeration value="SLE"/>
    <enumeration value="SOS"/>
    <enumeration value="SRD"/>
    <enumeration value="SSP"/>
    <enumeration value="STN"/>
    <enumeration value="SVC"/>
    <enumeration value="SYP"/>
    <enumeration value="SZL"/>
    <enumeration value="THB"/>
    <enumeration value="TJS"/>
    <enumeration value="TMT"/>
    <enumeration value="TND"/>
    <enumeration value="TOP"/>
    <enumeration value="TRY"/>
    <enumeration value="TTD"/>
    <enumeration value="TWD"/>
    <enumeration value="TZS"/>
    <enumeration value="UAH"/>
    <enumeration value="UGX"/>
    <enumeration value="USD"/>
    <enumeration value="USN"/>
    <enumeration value="UYI"/>
    <enumeration value="UYU"/>
    <enumeration value="UYW"/>
    <enumeration value="UZS"/>
    <enumeration value="VED"/>
    <enumeration value="VES"/>
    <enumeration value="VND"/>
    <enumeration value="VUV"/>
    <enumeration value="WST"/>
    <enumeration value="XAF"/>
    <enumeration value="XAG"/>
    <enumeration value="XAU"/>
    <enumeration value="XBA"/>
    <enumeration value="XBB"/>
    <enumeration value="XBC"/>
    <enumeration value="XBD"/>
    <enumeration value="XCD"/>
    <enumeration value="XCG"/>
    <enumeration value="XDR"/>
    <enumeration value="XOF"/>
    <enumeration value="XPD"/>
    <enumeration value="XPF"/>
    <enumeration value="XPT"/>
    <enumeration value="XSU"/>
    <enumeration value="XTS"/>
    <enumeration value="XUA"/>
    <enumeration value="XXX"/>
    <enumeration value="YER"/>
    <enumeration value="ZAR"/>
    <enumeration value="ZMW"/>
    <enumeration value="ZWG"/>
  </cl>
</codedb>
',
  ),
);
