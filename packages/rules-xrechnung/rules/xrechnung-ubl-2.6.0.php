<?php

// Generated from KoSIT XRechnung Schematron 2.6.0 (Apache-2.0) — do not edit.
// Source: XRechnung-UBL-validation.sch (SHA-256 e143facbe9e6a39cc89d68de0576e81806f21a64830b102250c706a524f35ee6).
// Regenerate with: composer compile-rules

declare(strict_types=1);

return array (
  'format' => 4,
  'id' => 'xrechnung-ubl',
  'version' => '2.6.0',
  'source' => 'XRechnung-UBL-validation.sch',
  'sha256' => 'e143facbe9e6a39cc89d68de0576e81806f21a64830b102250c706a524f35ee6',
  'namespaces' => 
  array (
    'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
    'cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
    'ext' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2',
    'ubl' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
    'cn' => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
    'ubl-invoice' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
    'ubl-creditnote' => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
    'xs' => 'http://www.w3.org/2001/XMLSchema',
    'xr' => 'xrechnung',
    'u' => 'utils',
  ),
  'lets' => 
  array (
    0 => 
    array (
      'name' => 'profile',
      'value' => '        if (normalize-space(/*/cbc:ProfileID) = (         \'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0\',         \'urn:peppol:france:billing:regulated\',         \'urn:peppol:france:billing:non-regulated\'        ))        then \'01\'        else if (normalize-space(/*/cbc:ProfileID) = \'urn:peppol:bis:billing_with_response\')        then \'02\'        else \'Unknown\'      ',
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
                    0 => 'root',
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
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                    2 => 'ProfileID',
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
                2 => 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0',
              ),
              1 => 
              array (
                0 => 'literal',
                1 => 'xs:string',
                2 => 'urn:peppol:france:billing:regulated',
              ),
              2 => 
              array (
                0 => 'literal',
                1 => 'xs:string',
                2 => 'urn:peppol:france:billing:non-regulated',
              ),
            ),
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
                      0 => 'root',
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
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                      2 => 'ProfileID',
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
      'value' => '       if (/*/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme[cac:TaxScheme/cbc:ID = \'VAT\']/substring(cbc:CompanyID, 1, 2)) then         upper-case(normalize-space(/*/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme[cac:TaxScheme/cbc:ID = \'VAT\']/substring(cbc:CompanyID, 1, 2)))       else         if (/*/cac:TaxRepresentativeParty/cac:PartyTaxScheme[cac:TaxScheme/cbc:ID = \'VAT\']/substring(cbc:CompanyID, 1, 2)) then           upper-case(normalize-space(/*/cac:TaxRepresentativeParty/cac:PartyTaxScheme[cac:TaxScheme/cbc:ID = \'VAT\']/substring(cbc:CompanyID, 1, 2)))         else           if (/*/cac:AccountingSupplierParty/cac:Party/cac:PostalAddress/cac:Country/cbc:IdentificationCode) then             upper-case(normalize-space(/*/cac:AccountingSupplierParty/cac:Party/cac:PostalAddress/cac:Country/cbc:IdentificationCode))           else             \'XX\'',
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
                    0 => 'root',
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
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'AccountingSupplierParty',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'Party',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                2 => 'PartyTaxScheme',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'TaxScheme',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'CompanyID',
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
                            0 => 'root',
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
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'AccountingSupplierParty',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'Party',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'PartyTaxScheme',
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                2 => 'TaxScheme',
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                          2 => 'CompanyID',
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
                    0 => 'root',
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
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'TaxRepresentativeParty',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'PartyTaxScheme',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'TaxScheme',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                    2 => 'CompanyID',
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
                            0 => 'root',
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
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'TaxRepresentativeParty',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'PartyTaxScheme',
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
                                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                  2 => 'TaxScheme',
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
                                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                            2 => 'CompanyID',
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
                            0 => 'any-name',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'AccountingSupplierParty',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'Party',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'PostalAddress',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'Country',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'IdentificationCode',
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
                                    0 => 'any-name',
                                  ),
                                  3 => 
                                  array (
                                  ),
                                ),
                              ),
                              2 => 
                              array (
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                  2 => 'AccountingSupplierParty',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                2 => 'Party',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                              2 => 'PostalAddress',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'Country',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                          2 => 'IdentificationCode',
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
      'name' => 'customerCountry',
      'value' => '   if (/*/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme[cac:TaxScheme/cbc:ID = \'VAT\']/substring(cbc:CompanyID, 1, 2)) then   upper-case(normalize-space(/*/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme[cac:TaxScheme/cbc:ID = \'VAT\']/substring(cbc:CompanyID, 1, 2)))   else   if (/*/cac:AccountingCustomerParty/cac:Party/cac:PostalAddress/cac:Country/cbc:IdentificationCode) then   upper-case(normalize-space(/*/cac:AccountingCustomerParty/cac:Party/cac:PostalAddress/cac:Country/cbc:IdentificationCode))   else   \'XX\'',
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
                    0 => 'root',
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
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'AccountingCustomerParty',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'Party',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                2 => 'PartyTaxScheme',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'TaxScheme',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'CompanyID',
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
                            0 => 'root',
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
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'AccountingCustomerParty',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'Party',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'PartyTaxScheme',
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                2 => 'TaxScheme',
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                          2 => 'CompanyID',
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
                          0 => 'any-name',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'AccountingCustomerParty',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'Party',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'PostalAddress',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'Country',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                2 => 'IdentificationCode',
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
                                  0 => 'any-name',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                2 => 'AccountingCustomerParty',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                              2 => 'Party',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'PostalAddress',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'Country',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                        2 => 'IdentificationCode',
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
    3 => 
    array (
      'name' => 'supplierCountryIsDE',
      'value' => '(upper-case(normalize-space(/*/cac:AccountingSupplierParty/cac:Party/cac:PostalAddress/cac:Country/cbc:IdentificationCode)) = \'DE\')',
      'ast' => 
      array (
        0 => 'gcmp',
        1 => '=',
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
                                0 => 'any-name',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                              2 => 'AccountingSupplierParty',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'Party',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'PostalAddress',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'Country',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                      2 => 'IdentificationCode',
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
          2 => 'DE',
        ),
      ),
    ),
    4 => 
    array (
      'name' => 'customerCountryIsDE',
      'value' => '(upper-case(normalize-space(/*/cac:AccountingCustomerParty/cac:Party/cac:PostalAddress/cac:Country/cbc:IdentificationCode)) = \'DE\')',
      'ast' => 
      array (
        0 => 'gcmp',
        1 => '=',
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
                                0 => 'any-name',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                              2 => 'AccountingCustomerParty',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'Party',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'PostalAddress',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'Country',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                      2 => 'IdentificationCode',
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
          2 => 'DE',
        ),
      ),
    ),
    5 => 
    array (
      'name' => 'documentCurrencyCode',
      'value' => '/*/cbc:DocumentCurrencyCode',
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
        2 => 
        array (
          0 => 'step',
          1 => 'child',
          2 => 
          array (
            0 => 'name',
            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
            2 => 'DocumentCurrencyCode',
          ),
          3 => 
          array (
          ),
        ),
      ),
    ),
    6 => 
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
    7 => 
    array (
      'name' => 'isGreekSender',
      'value' => '($supplierCountry =\'GR\') or ($supplierCountry =\'EL\')',
      'ast' => 
      array (
        0 => 'or',
        1 => 
        array (
          0 => 'gcmp',
          1 => '=',
          2 => 
          array (
            0 => 'var',
            1 => 'supplierCountry',
          ),
          3 => 
          array (
            0 => 'literal',
            1 => 'xs:string',
            2 => 'GR',
          ),
        ),
        2 => 
        array (
          0 => 'gcmp',
          1 => '=',
          2 => 
          array (
            0 => 'var',
            1 => 'supplierCountry',
          ),
          3 => 
          array (
            0 => 'literal',
            1 => 'xs:string',
            2 => 'EL',
          ),
        ),
      ),
    ),
    8 => 
    array (
      'name' => 'isGreekReceiver',
      'value' => '($customerCountry =\'GR\') or ($customerCountry =\'EL\')',
      'ast' => 
      array (
        0 => 'or',
        1 => 
        array (
          0 => 'gcmp',
          1 => '=',
          2 => 
          array (
            0 => 'var',
            1 => 'customerCountry',
          ),
          3 => 
          array (
            0 => 'literal',
            1 => 'xs:string',
            2 => 'GR',
          ),
        ),
        2 => 
        array (
          0 => 'gcmp',
          1 => '=',
          2 => 
          array (
            0 => 'var',
            1 => 'customerCountry',
          ),
          3 => 
          array (
            0 => 'literal',
            1 => 'xs:string',
            2 => 'EL',
          ),
        ),
      ),
    ),
    9 => 
    array (
      'name' => 'isGreekSenderandReceiver',
      'value' => '$isGreekSender and $isGreekReceiver',
      'ast' => 
      array (
        0 => 'and',
        1 => 
        array (
          0 => 'var',
          1 => 'isGreekSender',
        ),
        2 => 
        array (
          0 => 'var',
          1 => 'isGreekReceiver',
        ),
      ),
    ),
    10 => 
    array (
      'name' => 'accountingSupplierCountry',
      'value' => '     if (/*/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme[cac:TaxScheme/cbc:ID = \'VAT\']/substring(cbc:CompanyID, 1, 2)) then     upper-case(normalize-space(/*/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme[cac:TaxScheme/cbc:ID = \'VAT\']/substring(cbc:CompanyID, 1, 2)))     else     if (/*/cac:AccountingSupplierParty/cac:Party/cac:PostalAddress/cac:Country/cbc:IdentificationCode) then     upper-case(normalize-space(/*/cac:AccountingSupplierParty/cac:Party/cac:PostalAddress/cac:Country/cbc:IdentificationCode))     else     \'XX\'',
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
                    0 => 'root',
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
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'AccountingSupplierParty',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'Party',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                2 => 'PartyTaxScheme',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'TaxScheme',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'CompanyID',
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
                            0 => 'root',
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
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'AccountingSupplierParty',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'Party',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'PartyTaxScheme',
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                2 => 'TaxScheme',
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                          2 => 'CompanyID',
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
                          0 => 'any-name',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'AccountingSupplierParty',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'Party',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'PostalAddress',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'Country',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                2 => 'IdentificationCode',
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
                                  0 => 'any-name',
                                ),
                                3 => 
                                array (
                                ),
                              ),
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                2 => 'AccountingSupplierParty',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                              2 => 'Party',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'PostalAddress',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'Country',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                        2 => 'IdentificationCode',
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
    11 => 
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
    12 => 
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
    13 => 
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
    14 => 
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
    15 => 
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
    16 => 
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
    17 => 
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
    18 => 
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
    19 => 
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
    20 => 
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
    21 => 
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
    22 => 
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
    23 => 
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
    24 => 
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
    25 => 
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
    26 => 
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
    27 => 
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
    28 => 
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
    29 => 
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
    30 => 
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
    31 => 
    array (
      'name' => 'isExtension',
      'value' => 'exists(/ubl:Invoice/cbc:CustomizationID[text() = concat( \'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_\', $XR-MAJOR-MINOR-VERSION ,\'#conformant#urn:xeinkauf.de:kosit:extension:xrechnung_\', $XR-MAJOR-MINOR-VERSION) ] | /cn:CreditNote/cbc:CustomizationID[text() = concat( \'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_\', $XR-MAJOR-MINOR-VERSION ,\'#conformant#urn:xeinkauf.de:kosit:extension:xrechnung_\', $XR-MAJOR-MINOR-VERSION) ] )',
      'ast' => 
      array (
        0 => 'call',
        1 => 'fn:exists',
        2 => 
        array (
          0 => 
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
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                    2 => 'Invoice',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'CustomizationID',
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
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
                    2 => 'CreditNote',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'CustomizationID',
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
    ),
    32 => 
    array (
      'name' => 'isCVD',
      'value' => '(/ubl:Invoice | /cn:CreditNote)/cbc:CustomizationID/text() = $XR-CVD-ID',
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
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                    2 => 'Invoice',
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
                  0 => 'root',
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
                    2 => 'CreditNote',
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
                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                2 => 'CustomizationID',
              ),
              3 => 
              array (
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
    '{utils}TinVerification#1' => 
    array (
      'name' => 'u:TinVerification',
      'as' => 'xs:boolean',
      'params' => 
      array (
        0 => 
        array (
          'name' => 'val',
          'as' => 'xs:string',
        ),
      ),
      'body' => 
      array (
        0 => 
        array (
          0 => 'variable',
          1 => 'digits',
          2 => NULL,
          3 => 
          array (
            0 => 'for',
            1 => 
            array (
              0 => 
              array (
                0 => 'ch',
                1 => 
                array (
                  0 => 'call',
                  1 => 'fn:string-to-codepoints',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'var',
                      1 => 'val',
                    ),
                  ),
                ),
              ),
            ),
            2 => 
            array (
              0 => 'call',
              1 => 'fn:codepoints-to-string',
              2 => 
              array (
                0 => 
                array (
                  0 => 'var',
                  1 => 'ch',
                ),
              ),
            ),
          ),
        ),
        1 => 
        array (
          0 => 'variable',
          1 => 'checksum',
          2 => NULL,
          3 => 
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
                            0 => 'call',
                            1 => 'fn:number',
                            2 => 
                            array (
                              0 => 
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
                            2 => '2',
                          ),
                        ),
                        3 => 
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
                                    0 => 'literal',
                                    1 => 'xs:integer',
                                    2 => '7',
                                  ),
                                ),
                              ),
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
                      3 => 
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
                                  0 => 'literal',
                                  1 => 'xs:integer',
                                  2 => '6',
                                ),
                              ),
                            ),
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
                    3 => 
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
                                0 => 'literal',
                                1 => 'xs:integer',
                                2 => '5',
                              ),
                            ),
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
                  ),
                  3 => 
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
                              0 => 'literal',
                              1 => 'xs:integer',
                              2 => '4',
                            ),
                          ),
                        ),
                      ),
                    ),
                    3 => 
                    array (
                      0 => 'literal',
                      1 => 'xs:integer',
                      2 => '32',
                    ),
                  ),
                ),
                3 => 
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
                            0 => 'literal',
                            1 => 'xs:integer',
                            2 => '3',
                          ),
                        ),
                      ),
                    ),
                  ),
                  3 => 
                  array (
                    0 => 'literal',
                    1 => 'xs:integer',
                    2 => '64',
                  ),
                ),
              ),
              3 => 
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
                          0 => 'literal',
                          1 => 'xs:integer',
                          2 => '2',
                        ),
                      ),
                    ),
                  ),
                ),
                3 => 
                array (
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '128',
                ),
              ),
            ),
            3 => 
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
                2 => '256',
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
              0 => 'arith',
              1 => 'mod',
              2 => 
              array (
                0 => 'arith',
                1 => 'mod',
                2 => 
                array (
                  0 => 'var',
                  1 => 'checksum',
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
                      0 => 'literal',
                      1 => 'xs:integer',
                      2 => '9',
                    ),
                  ),
                ),
              ),
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
      'id' => 'peppol-ubl-pattern-1',
      'lets' => 
      array (
      ),
      'rules' => 
      array (
        0 => 
        array (
          'context' => '//*[not(*) and not(normalize-space())]',
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
              'test' => 'self::cbc:ID[parent::cac:OrderReference]',
              'ast' => 
              array (
                0 => 'step',
                1 => 'self',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'ID',
                ),
                3 => 
                array (
                  0 => 
                  array (
                    0 => 'step',
                    1 => 'parent',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'OrderReference',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
              ),
              'message' => '[PEPPOL-EN16931-R008]-Document MUST not contain empty elements.',
            ),
          ),
        ),
      ),
    ),
    2 => 
    array (
      'id' => 'peppol-ubl-pattern-2',
      'lets' => 
      array (
      ),
      'rules' => 
      array (
        0 => 
        array (
          'context' => 'ubl-creditnote:CreditNote | ubl-invoice:Invoice',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
                    2 => 'CreditNote',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                    2 => 'Invoice',
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
              'id' => 'PEPPOL-EN16931-R001',
              'flag' => 'fatal',
              'test' => 'cbc:ProfileID',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'ProfileID',
                ),
                3 => 
                array (
                ),
              ),
              'message' => '[PEPPOL-EN16931-R001]-Business process MUST be provided.',
            ),
            1 => 
            array (
              'id' => 'PEPPOL-EN16931-R053',
              'flag' => 'fatal',
              'test' => 'count(cac:TaxTotal[cac:TaxSubtotal]) = 1',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'TaxTotal',
                      ),
                      3 => 
                      array (
                        0 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'TaxSubtotal',
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
                  0 => 'literal',
                  1 => 'xs:integer',
                  2 => '1',
                ),
              ),
              'message' => '[PEPPOL-EN16931-R053]-Only one tax total with tax subtotals MUST be provided.',
            ),
            2 => 
            array (
              'id' => 'PEPPOL-EN16931-R054',
              'flag' => 'fatal',
              'test' => 'count(cac:TaxTotal[not(cac:TaxSubtotal)]) = (if (cbc:TaxCurrencyCode) then 1 else 0)',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'TaxTotal',
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
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                2 => 'TaxSubtotal',
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
                  0 => 'if',
                  1 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
              'message' => '[PEPPOL-EN16931-R054]-Only one tax total without tax subtotals MUST be provided when tax currency code is provided.',
            ),
            3 => 
            array (
              'id' => 'PEPPOL-EN16931-R055',
              'flag' => 'fatal',
              'test' => 'not(cbc:TaxCurrencyCode) or (cac:TaxTotal/cbc:TaxAmount[@currencyID=normalize-space(../../cbc:TaxCurrencyCode)] <= 0 and cac:TaxTotal/cbc:TaxAmount[@currencyID=normalize-space(../../cbc:DocumentCurrencyCode)] <= 0) or (cac:TaxTotal/cbc:TaxAmount[@currencyID=normalize-space(../../cbc:TaxCurrencyCode)] >= 0 and cac:TaxTotal/cbc:TaxAmount[@currencyID=normalize-space(../../cbc:DocumentCurrencyCode)] >= 0) ',
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
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
                    0 => 'and',
                    1 => 
                    array (
                      0 => 'gcmp',
                      1 => '<=',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'TaxTotal',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                            2 => 'TaxAmount',
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
                                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
                      1 => '<=',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'TaxTotal',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                            2 => 'TaxAmount',
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
                                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                        2 => 'DocumentCurrencyCode',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'TaxTotal',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                          2 => 'TaxAmount',
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
                                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'TaxTotal',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                          2 => 'TaxAmount',
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
                                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                      2 => 'DocumentCurrencyCode',
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
        1 => 
        array (
          'context' => 'cbc:TaxCurrencyCode',
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
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'TaxCurrencyCode',
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
              'test' => 'not(normalize-space(text()) = normalize-space(../cbc:DocumentCurrencyCode/text()))',
              'ast' => 
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
                            0 => 'text',
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                2 => 'DocumentCurrencyCode',
                              ),
                              3 => 
                              array (
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
                  ),
                ),
              ),
              'message' => '[PEPPOL-EN16931-R005]-VAT accounting currency code MUST be different from invoice currency code when provided.',
            ),
          ),
        ),
        2 => 
        array (
          'context' => 'cac:AccountingCustomerParty/cac:Party',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'AccountingCustomerParty',
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
                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                2 => 'Party',
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
              'id' => 'PEPPOL-EN16931-R010',
              'flag' => 'fatal',
              'test' => 'cbc:EndpointID',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'EndpointID',
                ),
                3 => 
                array (
                ),
              ),
              'message' => '[PEPPOL-EN16931-R010]-Buyer electronic address MUST be provided',
            ),
          ),
        ),
        3 => 
        array (
          'context' => 'cac:AccountingSupplierParty/cac:Party',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'AccountingSupplierParty',
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
                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                2 => 'Party',
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
              'id' => 'PEPPOL-EN16931-R020',
              'flag' => 'fatal',
              'test' => 'cbc:EndpointID',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'EndpointID',
                ),
                3 => 
                array (
                ),
              ),
              'message' => '[PEPPOL-EN16931-R020]-Seller electronic address MUST be provided',
            ),
          ),
        ),
        4 => 
        array (
          'context' => 'ubl-invoice:Invoice/cac:AllowanceCharge[cbc:MultiplierFactorNumeric and not(cbc:BaseAmount)] | ubl-invoice:Invoice/cac:InvoiceLine/cac:AllowanceCharge[cbc:MultiplierFactorNumeric and not(cbc:BaseAmount)] | ubl-creditnote:CreditNote/cac:AllowanceCharge[cbc:MultiplierFactorNumeric and not(cbc:BaseAmount)] | ubl-creditnote:CreditNote/cac:CreditNoteLine/cac:AllowanceCharge[cbc:MultiplierFactorNumeric and not(cbc:BaseAmount)]',
          'match' => 
          array (
            0 => 'union',
            1 => 
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                          2 => 'Invoice',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'AllowanceCharge',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                            2 => 'MultiplierFactorNumeric',
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                2 => 'BaseAmount',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                            2 => 'Invoice',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'InvoiceLine',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'AllowanceCharge',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                            2 => 'MultiplierFactorNumeric',
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                2 => 'BaseAmount',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
                        2 => 'CreditNote',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'AllowanceCharge',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                          2 => 'MultiplierFactorNumeric',
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
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                              2 => 'BaseAmount',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
                        2 => 'CreditNote',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'CreditNoteLine',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'AllowanceCharge',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                        2 => 'MultiplierFactorNumeric',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                            2 => 'BaseAmount',
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
          'context' => 'ubl-invoice:Invoice/cac:AllowanceCharge[not(cbc:MultiplierFactorNumeric) and cbc:BaseAmount] | ubl-invoice:Invoice/cac:InvoiceLine/cac:AllowanceCharge[not(cbc:MultiplierFactorNumeric) and cbc:BaseAmount] | ubl-creditnote:CreditNote/cac:AllowanceCharge[not(cbc:MultiplierFactorNumeric) and cbc:BaseAmount] | ubl-creditnote:CreditNote/cac:CreditNoteLine/cac:AllowanceCharge[not(cbc:MultiplierFactorNumeric) and cbc:BaseAmount]',
          'match' => 
          array (
            0 => 'union',
            1 => 
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                          2 => 'Invoice',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'AllowanceCharge',
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                2 => 'MultiplierFactorNumeric',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                            2 => 'BaseAmount',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                            2 => 'Invoice',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'InvoiceLine',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'AllowanceCharge',
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                2 => 'MultiplierFactorNumeric',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                            2 => 'BaseAmount',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
                        2 => 'CreditNote',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'AllowanceCharge',
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
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                              2 => 'MultiplierFactorNumeric',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                          2 => 'BaseAmount',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
                        2 => 'CreditNote',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'CreditNoteLine',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'AllowanceCharge',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                            2 => 'MultiplierFactorNumeric',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                        2 => 'BaseAmount',
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
          'context' => 'ubl-invoice:Invoice/cac:AllowanceCharge | ubl-invoice:Invoice/cac:InvoiceLine/cac:AllowanceCharge | ubl-creditnote:CreditNote/cac:AllowanceCharge | ubl-creditnote:CreditNote/cac:CreditNoteLine/cac:AllowanceCharge',
          'match' => 
          array (
            0 => 'union',
            1 => 
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                          2 => 'Invoice',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'AllowanceCharge',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                            2 => 'Invoice',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'InvoiceLine',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'AllowanceCharge',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
                        2 => 'CreditNote',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'AllowanceCharge',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
                        2 => 'CreditNote',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'CreditNoteLine',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'AllowanceCharge',
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
              'test' => 'not(cbc:MultiplierFactorNumeric and cbc:BaseAmount) or u:slack(if (cbc:Amount) then cbc:Amount else 0, (xs:decimal(cbc:BaseAmount) * xs:decimal(cbc:MultiplierFactorNumeric)) div 100, $slackValue)',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                          2 => 'MultiplierFactorNumeric',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                          2 => 'BaseAmount',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                          2 => 'Amount',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                          2 => 'Amount',
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                2 => 'BaseAmount',
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                2 => 'MultiplierFactorNumeric',
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
              'id' => 'PEPPOL-EN16931-R043',
              'flag' => 'fatal',
              'test' => 'normalize-space(cbc:ChargeIndicator/text()) = \'true\' or normalize-space(cbc:ChargeIndicator/text()) = \'false\'',
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
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
          'context' => '         cac:PaymentMeans[some $code in tokenize(\'49 59\', \'\\s\')           satisfies normalize-space(cbc:PaymentMeansCode) = $code]',
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
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'PaymentMeans',
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
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                              2 => 'PaymentMeansCode',
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
              'test' => 'cac:PaymentMandate/cbc:ID',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'PaymentMandate',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                    2 => 'ID',
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
        8 => 
        array (
          'context' => 'ubl-invoice:Invoice[cac:InvoicePeriod/cbc:StartDate]/cac:InvoiceLine/cac:InvoicePeriod/cbc:StartDate | ubl-creditnote:CreditNote[cac:InvoicePeriod/cbc:StartDate]/cac:CreditNoteLine/cac:InvoicePeriod/cbc:StartDate',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                          2 => 'Invoice',
                        ),
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                2 => 'InvoicePeriod',
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                2 => 'StartDate',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'InvoiceLine',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'InvoicePeriod',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'StartDate',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
                          2 => 'CreditNote',
                        ),
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                2 => 'InvoicePeriod',
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                2 => 'StartDate',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'CreditNoteLine',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'InvoicePeriod',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'StartDate',
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
              'id' => 'PEPPOL-EN16931-R110',
              'flag' => 'fatal',
              'test' => 'xs:date(text()) >= xs:date(../../../cac:InvoicePeriod/cbc:StartDate)',
              'ast' => 
              array (
                0 => 'gcmp',
                1 => '>=',
                2 => 
                array (
                  0 => 'call',
                  1 => 'xs:date',
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
                3 => 
                array (
                  0 => 'call',
                  1 => 'xs:date',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'InvoicePeriod',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                          2 => 'StartDate',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[PEPPOL-EN16931-R110]-Start date of line period MUST be within invoice period.',
            ),
          ),
        ),
        9 => 
        array (
          'context' => 'ubl-invoice:Invoice[cac:InvoicePeriod/cbc:EndDate]/cac:InvoiceLine/cac:InvoicePeriod/cbc:EndDate | ubl-creditnote:CreditNote[cac:InvoicePeriod/cbc:EndDate]/cac:CreditNoteLine/cac:InvoicePeriod/cbc:EndDate',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                          2 => 'Invoice',
                        ),
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                2 => 'InvoicePeriod',
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                2 => 'EndDate',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'InvoiceLine',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'InvoicePeriod',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'EndDate',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
                          2 => 'CreditNote',
                        ),
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                2 => 'InvoicePeriod',
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                2 => 'EndDate',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'CreditNoteLine',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'InvoicePeriod',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'EndDate',
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
              'id' => 'PEPPOL-EN16931-R111',
              'flag' => 'fatal',
              'test' => 'xs:date(text()) <= xs:date(../../../cac:InvoicePeriod/cbc:EndDate)',
              'ast' => 
              array (
                0 => 'gcmp',
                1 => '<=',
                2 => 
                array (
                  0 => 'call',
                  1 => 'xs:date',
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
                3 => 
                array (
                  0 => 'call',
                  1 => 'xs:date',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'InvoicePeriod',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                          2 => 'EndDate',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[PEPPOL-EN16931-R111]-End date of line period MUST be within invoice period.',
            ),
          ),
        ),
        10 => 
        array (
          'context' => 'cac:InvoiceLine | cac:CreditNoteLine',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'InvoiceLine',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'CreditNoteLine',
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
            0 => 
            array (
              'name' => 'lineExtensionAmount',
              'value' => '           if (cbc:LineExtensionAmount) then             xs:decimal(cbc:LineExtensionAmount)           else             0',
              'ast' => 
              array (
                0 => 'if',
                1 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                    2 => 'LineExtensionAmount',
                  ),
                  3 => 
                  array (
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
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                        2 => 'LineExtensionAmount',
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
            1 => 
            array (
              'name' => 'quantity',
              'value' => '           if (/ubl-invoice:Invoice) then             (if (cbc:InvoicedQuantity) then               xs:decimal(cbc:InvoicedQuantity)             else               1)           else             (if (cbc:CreditedQuantity) then               xs:decimal(cbc:CreditedQuantity)             else               1)',
              'ast' => 
              array (
                0 => 'if',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                      2 => 'Invoice',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'if',
                  1 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                      2 => 'InvoicedQuantity',
                    ),
                    3 => 
                    array (
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
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                          2 => 'InvoicedQuantity',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                      2 => 'CreditedQuantity',
                    ),
                    3 => 
                    array (
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
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                          2 => 'CreditedQuantity',
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
              ),
            ),
            2 => 
            array (
              'name' => 'priceAmount',
              'value' => '           if (cac:Price/cbc:PriceAmount) then             xs:decimal(cac:Price/cbc:PriceAmount)           else             0',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'Price',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                      2 => 'PriceAmount',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'Price',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                          2 => 'PriceAmount',
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
              'value' => '           if (cac:Price/cbc:BaseQuantity and xs:decimal(cac:Price/cbc:BaseQuantity) != 0) then             xs:decimal(cac:Price/cbc:BaseQuantity)           else             1',
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
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'Price',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                        2 => 'BaseQuantity',
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
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                              2 => 'Price',
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
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                              2 => 'BaseQuantity',
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
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'Price',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                          2 => 'BaseQuantity',
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
            4 => 
            array (
              'name' => 'allowancesTotal',
              'value' => '           if (cac:AllowanceCharge[normalize-space(cbc:ChargeIndicator) = \'false\']) then             round(sum(cac:AllowanceCharge[normalize-space(cbc:ChargeIndicator) = \'false\']/cbc:Amount/xs:decimal(.)) * 10 * 10) div 100           else             0',
              'ast' => 
              array (
                0 => 'if',
                1 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'AllowanceCharge',
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
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                              2 => 'ChargeIndicator',
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
                        2 => 'false',
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
                                    0 => 'step',
                                    1 => 'child',
                                    2 => 
                                    array (
                                      0 => 'name',
                                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                      2 => 'AllowanceCharge',
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
                                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                                2 => 'ChargeIndicator',
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
                                          2 => 'false',
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
                                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                      2 => 'Amount',
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
              'value' => '           if (cac:AllowanceCharge[normalize-space(cbc:ChargeIndicator) = \'true\']) then             round(sum(cac:AllowanceCharge[normalize-space(cbc:ChargeIndicator) = \'true\']/cbc:Amount/xs:decimal(.)) * 10 * 10) div 100           else             0',
              'ast' => 
              array (
                0 => 'if',
                1 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'AllowanceCharge',
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
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                              2 => 'ChargeIndicator',
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
                        2 => 'true',
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
                                    0 => 'step',
                                    1 => 'child',
                                    2 => 
                                    array (
                                      0 => 'name',
                                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                      2 => 'AllowanceCharge',
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
                                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                                2 => 'ChargeIndicator',
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
                                          2 => 'true',
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
                                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                      2 => 'Amount',
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
              'id' => 'PEPPOL-EN16931-R121',
              'flag' => 'fatal',
              'test' => 'not(cac:Price/cbc:BaseQuantity) or xs:decimal(cac:Price/cbc:BaseQuantity) > 0',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'Price',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                          2 => 'BaseQuantity',
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
                  1 => '>',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'Price',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                            2 => 'BaseQuantity',
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
              'message' => '[PEPPOL-EN16931-R121]-Base quantity MUST be a positive number above zero.',
            ),
            2 => 
            array (
              'id' => 'PEPPOL-EN16931-R101',
              'flag' => 'fatal',
              'test' => '(not(cac:DocumentReference) or (cac:DocumentReference/cbc:DocumentTypeCode=\'130\'))',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'DocumentReference',
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
                    0 => 'path',
                    1 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'DocumentReference',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                        2 => 'DocumentTypeCode',
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
              'message' => '[PEPPOL-EN16931-R101]-Element Document reference can only be used for Invoice line object',
            ),
          ),
        ),
        11 => 
        array (
          'context' => 'cac:Price/cac:AllowanceCharge',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'Price',
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
                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                2 => 'AllowanceCharge',
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
              'test' => 'normalize-space(cbc:ChargeIndicator) = \'false\'',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                        2 => 'ChargeIndicator',
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
                  2 => 'false',
                ),
              ),
              'message' => '[PEPPOL-EN16931-R044]-Charge on price level is NOT allowed. Only value \'false\' allowed.',
            ),
            1 => 
            array (
              'id' => 'PEPPOL-EN16931-R046',
              'flag' => 'fatal',
              'test' => 'not(cbc:BaseAmount) or xs:decimal(../cbc:PriceAmount) = xs:decimal(cbc:BaseAmount) - xs:decimal(cbc:Amount)',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                        2 => 'BaseAmount',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                            2 => 'PriceAmount',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                            2 => 'BaseAmount',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                            2 => 'Amount',
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
              'message' => '[PEPPOL-EN16931-R046]-Item net price MUST equal (Gross price - Allowance amount) when gross price is provided.',
            ),
          ),
        ),
        12 => 
        array (
          'context' => 'cac:Price/cbc:BaseQuantity[@unitCode]',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'Price',
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
                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                2 => 'BaseQuantity',
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
          'lets' => 
          array (
            0 => 
            array (
              'name' => 'hasQuantity',
              'value' => '../../cbc:InvoicedQuantity or ../../cbc:CreditedQuantity',
              'ast' => 
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                      2 => 'InvoicedQuantity',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                      2 => 'CreditedQuantity',
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
              'name' => 'quantity',
              'value' => '           if (/ubl-invoice:Invoice) then             ../../cbc:InvoicedQuantity           else             ../../cbc:CreditedQuantity',
              'ast' => 
              array (
                0 => 'if',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                      2 => 'Invoice',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                      2 => 'InvoicedQuantity',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                      2 => 'CreditedQuantity',
                    ),
                    3 => 
                    array (
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
              'id' => 'PEPPOL-EN16931-R130',
              'flag' => 'fatal',
              'test' => 'not($hasQuantity) or @unitCode = $quantity/@unitCode',
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
                      0 => 'var',
                      1 => 'hasQuantity',
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
                      0 => 'var',
                      1 => 'quantity',
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
              'message' => '[PEPPOL-EN16931-R130]-Unit code of price base quantity MUST be same as invoiced quantity.',
            ),
          ),
        ),
      ),
    ),
    3 => 
    array (
      'id' => 'ubl-pattern',
      'lets' => 
      array (
      ),
      'rules' => 
      array (
        0 => 
        array (
          'context' => '/ubl:Invoice | /cn:CreditNote',
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
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                  2 => 'Invoice',
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
                0 => 'root',
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
                  2 => 'CreditNote',
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
              'name' => 'supportedVATCodes',
              'value' => '(\'S\', \'Z\', \'E\', \'AE\', \'K\', \'G\', \'L\', \'M\')',
              'ast' => 
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
            1 => 
            array (
              'name' => 'BT-31orBT-32Path',
              'value' => 'cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID[boolean(normalize-space(.))]',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'AccountingSupplierParty',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'Party',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'PartyTaxScheme',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                    2 => 'CompanyID',
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
            ),
            2 => 
            array (
              'name' => 'BT-95-UBL-Inv',
              'value' => 'cac:AllowanceCharge/cac:TaxCategory/cbc:ID[ancestor::cac:AllowanceCharge/cbc:ChargeIndicator = \'false\' and         following-sibling::cac:TaxScheme/cbc:ID = \'VAT\']',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'AllowanceCharge',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'TaxCategory',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                    2 => 'ID',
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
                            0 => 'step',
                            1 => 'ancestor',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                              2 => 'AllowanceCharge',
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
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                              2 => 'ChargeIndicator',
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
                            1 => 'following-sibling',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                              2 => 'TaxScheme',
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
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
                          2 => 'VAT',
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
            3 => 
            array (
              'name' => 'BT-95-UBL-CN',
              'value' => 'cac:AllowanceCharge/cac:TaxCategory/cbc:ID[ancestor::cac:AllowanceCharge/cbc:ChargeIndicator = \'false\']',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'AllowanceCharge',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'TaxCategory',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'step',
                          1 => 'ancestor',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'AllowanceCharge',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                            2 => 'ChargeIndicator',
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
                ),
              ),
            ),
            4 => 
            array (
              'name' => 'BT-102',
              'value' => 'cac:AllowanceCharge/cac:TaxCategory/cbc:ID[ancestor::cac:AllowanceCharge/cbc:ChargeIndicator = \'true\']',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'AllowanceCharge',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'TaxCategory',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
                        0 => 'path',
                        1 => 
                        array (
                          0 => 'step',
                          1 => 'ancestor',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'AllowanceCharge',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                            2 => 'ChargeIndicator',
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
                        2 => 'true',
                      ),
                    ),
                  ),
                ),
              ),
            ),
            5 => 
            array (
              'name' => 'BT-151',
              'value' => '(cac:InvoiceLine | cac:CreditNoteLine)/cac:Item/cac:ClassifiedTaxCategory/cbc:ID',
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
                      0 => 'union',
                      1 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'InvoiceLine',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'CreditNoteLine',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'Item',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'ClassifiedTaxCategory',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                    2 => 'ID',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
            ),
            6 => 
            array (
              'name' => 'supportedInvAndCNTypeCodes',
              'value' => '(\'326\', \'380\', \'384\', \'389\', \'381\', \'875\', \'876\', \'877\')',
              'ast' => 
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
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-DE-1',
              'flag' => 'fatal',
              'test' => 'cac:PaymentMeans',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'PaymentMeans',
                ),
                3 => 
                array (
                ),
              ),
              'message' => '[BR-DE-1] Eine Rechnung (INVOICE) muss Angaben zu "PAYMENT INSTRUCTIONS" (BG-16) enthalten.',
            ),
            1 => 
            array (
              'id' => 'BR-DE-15',
              'flag' => 'fatal',
              'test' => 'cbc:BuyerReference[boolean(normalize-space(.))]',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
              'message' => '[BR-DE-15] Das Element "Buyer reference" (BT-10) muss übermittelt werden.',
            ),
            2 => 
            array (
              'id' => 'BR-DE-16',
              'flag' => 'fatal',
              'test' => '         (not(           ($BT-95-UBL-Inv = $supportedVATCodes or $BT-95-UBL-CN = $supportedVATCodes) or           ($BT-102 = $supportedVATCodes) or           ($BT-151 = $supportedVATCodes)         ) or         (cac:TaxRepresentativeParty, $BT-31orBT-32Path))         ',
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
                          0 => 'or',
                          1 => 
                          array (
                            0 => 'gcmp',
                            1 => '=',
                            2 => 
                            array (
                              0 => 'var',
                              1 => 'BT-95-UBL-Inv',
                            ),
                            3 => 
                            array (
                              0 => 'var',
                              1 => 'supportedVATCodes',
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'gcmp',
                            1 => '=',
                            2 => 
                            array (
                              0 => 'var',
                              1 => 'BT-95-UBL-CN',
                            ),
                            3 => 
                            array (
                              0 => 'var',
                              1 => 'supportedVATCodes',
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'gcmp',
                          1 => '=',
                          2 => 
                          array (
                            0 => 'var',
                            1 => 'BT-102',
                          ),
                          3 => 
                          array (
                            0 => 'var',
                            1 => 'supportedVATCodes',
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'gcmp',
                        1 => '=',
                        2 => 
                        array (
                          0 => 'var',
                          1 => 'BT-151',
                        ),
                        3 => 
                        array (
                          0 => 'var',
                          1 => 'supportedVATCodes',
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
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'TaxRepresentativeParty',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                    1 => 
                    array (
                      0 => 'var',
                      1 => 'BT-31orBT-32Path',
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-16] Wenn in einer Rechnung die Steuercodes S, Z, E, AE, K, G, L oder M verwendet werden, muss mindestens eines der Elemente "Seller VAT identifier" (BT-31), "Seller tax registration identifier" (BT-32) oder "SELLER TAX REPRESENTATIVE PARTY" (BG-11) übermittelt werden.',
            ),
            3 => 
            array (
              'id' => 'BR-DE-17',
              'flag' => 'warning',
              'test' => 'normalize-space(cbc:InvoiceTypeCode) = $supportedInvAndCNTypeCodes         or normalize-space(cbc:CreditNoteTypeCode) = $supportedInvAndCNTypeCodes',
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
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                          2 => 'InvoiceTypeCode',
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
                    1 => 'supportedInvAndCNTypeCodes',
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
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                          2 => 'CreditNoteTypeCode',
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
                    1 => 'supportedInvAndCNTypeCodes',
                  ),
                ),
              ),
              'message' => '[BR-DE-17] Mit dem Element "Invoice type code" (BT-3) sollen ausschließlich folgende Codes aus der Codeliste UNTDID 1001 übermittelt werden: 326 (Partial invoice), 380 (Commercial invoice), 384 (Corrected invoice), 389 (Self-billed invoice) und 381 (Credit note),875 (Partial construction invoice), 876 (Partial final construction invoice), 877 (Final construction invoice).',
            ),
            4 => 
            array (
              'id' => 'BR-DE-18',
              'flag' => 'fatal',
              'test' => 'every $line in             cac:PaymentTerms/cbc:Note[1]/tokenize(. , \'(\\r?\\n)\')[starts-with( normalize-space(.) , \'#\')]              satisfies matches ( normalize-space ($line), $XR-SKONTO-REGEX)                                  and                                 matches( cac:PaymentTerms/cbc:Note[1]/tokenize(. ,  \'#.+#\')[last()], \'^\\s*\\n\' )',
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
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'PaymentTerms',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                            2 => 'Note',
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
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                              2 => 'PaymentTerms',
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
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                              2 => 'Note',
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
              'message' => '[BR-DE-18] Skonto Zeilen in müssen diesem regulärem Ausdruck entsprechen: . Die Informationen zur Gewährung von Skonto müssen wie folgt im Element "Payment terms" (BT-20) übermittelt werden: Anzugeben ist im ersten Segment "SKONTO", im zweiten "TAGE=n", im dritten "PROZENT=n". Prozentzahlen sind ohne Vorzeichen sowie mit Punkt getrennt von zwei Nachkommastellen anzugeben. Liegt dem zu berechnenden Betrag nicht BT-115, "fälliger Betrag" zugrunde, sondern nur ein Teil des fälligen Betrags der Rechnung, ist der Grundwert zur Berechnung von Skonto als viertes Segment "BASISBETRAG=n" gemäß dem semantischen Datentypen Amount anzugeben. Jeder Eintrag beginnt mit einer #, die Segmente sind mit einer # getrennt und eine Zeile schließt mit einer # ab. Am Ende einer vollständigen Skontoangabe muss ein XML-konformer Zeilenumbruch folgen. Alle Angaben zur Gewährung von Skonto müssen in Großbuchstaben gemacht werden. Zusätzliches Whitespace (Leerzeichen, Tabulatoren oder Zeilenumbrüche) ist nicht zulässig. Andere Zeichen oder Texte als in den oberen Vorgaben genannt sind nicht zulässig.',
              'messageParts' => 
              array (
                0 => '[BR-DE-18] Skonto Zeilen in ',
                1 => 
                array (
                  'name' => true,
                ),
                2 => ' müssen diesem regulärem Ausdruck entsprechen: ',
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
            5 => 
            array (
              'id' => 'BR-DE-21',
              'flag' => 'warning',
              'test' => 'cbc:CustomizationID = $XR-CIUS-ID or                     cbc:CustomizationID = $XR-EXTENSION-ID or                     cbc:CustomizationID = $XR-CVD-ID',
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
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                        2 => 'CustomizationID',
                      ),
                      3 => 
                      array (
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
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                        2 => 'CustomizationID',
                      ),
                      3 => 
                      array (
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
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                      2 => 'CustomizationID',
                    ),
                    3 => 
                    array (
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
            6 => 
            array (
              'id' => 'BR-DE-22',
              'flag' => 'fatal',
              'test' => 'count(cac:AdditionalDocumentReference) =                      count(cac:AdditionalDocumentReference[not(./cac:Attachment/cbc:EmbeddedDocumentBinaryObject/@filename = preceding-sibling::cac:AdditionalDocumentReference/cac:Attachment/cbc:EmbeddedDocumentBinaryObject/@filename)])',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'AdditionalDocumentReference',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'AdditionalDocumentReference',
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
                                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                        2 => 'Attachment',
                                      ),
                                      3 => 
                                      array (
                                      ),
                                    ),
                                  ),
                                  2 => 
                                  array (
                                    0 => 'step',
                                    1 => 'child',
                                    2 => 
                                    array (
                                      0 => 'name',
                                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                      2 => 'EmbeddedDocumentBinaryObject',
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
                                    0 => 'path',
                                    1 => 
                                    array (
                                      0 => 'step',
                                      1 => 'preceding-sibling',
                                      2 => 
                                      array (
                                        0 => 'name',
                                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                        2 => 'AdditionalDocumentReference',
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
                                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                        2 => 'Attachment',
                                      ),
                                      3 => 
                                      array (
                                      ),
                                    ),
                                  ),
                                  2 => 
                                  array (
                                    0 => 'step',
                                    1 => 'child',
                                    2 => 
                                    array (
                                      0 => 'name',
                                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                      2 => 'EmbeddedDocumentBinaryObject',
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
              'message' => '[BR-DE-22] Das "filename"-Attribut aller "EmbeddedDocumentBinaryObject"-Elemente muss eindeutig sein',
            ),
            7 => 
            array (
              'id' => 'BR-DE-26',
              'flag' => 'warning',
              'test' => '((not(normalize-space(cbc:InvoiceTypeCode) = \'384\' or normalize-space(cbc:CreditNoteTypeCode) = \'384\') or                     (cac:BillingReference/cac:InvoiceDocumentReference)))',
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                2 => 'InvoiceTypeCode',
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
                          2 => '384',
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
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                2 => 'CreditNoteTypeCode',
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
                          2 => '384',
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
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'BillingReference',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'InvoiceDocumentReference',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-26] Wenn im Element "Invoice type code" (BT-3) der Code 384 (Corrected invoice) übergeben wird, soll PRECEDING INVOICE REFERENCE BG-3 mind. einmal vorhanden sein.',
            ),
            8 => 
            array (
              'id' => 'BR-DE-30',
              'flag' => 'fatal',
              'test' => 'not(cac:PaymentMeans/cac:PaymentMandate)                        or (cac:AccountingSupplierParty/cac:Party/cac:PartyIdentification/cbc:ID[@schemeID=\'SEPA\']                          | cac:PayeeParty/cac:PartyIdentification/cbc:ID[@schemeID=\'SEPA\'])',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'PaymentMeans',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'PaymentMandate',
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
                  0 => 'union',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'AccountingSupplierParty',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'Party',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'PartyIdentification',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
                            2 => 'SEPA',
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
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'PayeeParty',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'PartyIdentification',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
                            2 => 'SEPA',
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-30] Wenn "DIRECT DEBIT" BG-19 vorhanden ist, dann muss "Bank assigned creditor identifier" BT-90 übermittelt werden.',
            ),
            9 => 
            array (
              'id' => 'BR-DE-31',
              'flag' => 'fatal',
              'test' => 'not(cac:PaymentMeans/cac:PaymentMandate) or (cac:PaymentMeans/cac:PaymentMandate/cac:PayerFinancialAccount/cbc:ID)',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'PaymentMeans',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'PaymentMandate',
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
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'PaymentMeans',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'PaymentMandate',
                        ),
                        3 => 
                        array (
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'PayerFinancialAccount',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                      2 => 'ID',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-31] Wenn "DIRECT DEBIT" BG-19 vorhanden ist, dann muss "Debited account identifier" BT-91 übermittelt werden.',
            ),
            10 => 
            array (
              'id' => 'BR-DE-TMP-32',
              'flag' => 'information',
              'test' => 'cac:Delivery/cbc:ActualDeliveryDate         or cac:InvoicePeriod         or (every $line in (cac:InvoiceLine | cac:CreditNoteLine) satisfies $line/cac:InvoicePeriod)',
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
                      0 => 'step',
                      1 => 'child',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'Delivery',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                        2 => 'ActualDeliveryDate',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'InvoicePeriod',
                    ),
                    3 => 
                    array (
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
                        0 => 'union',
                        1 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'InvoiceLine',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'CreditNoteLine',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'InvoicePeriod',
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
        1 => 
        array (
          'context' => '/ubl:Invoice/cac:AdditionalDocumentReference/cac:Attachment/cac:ExternalReference | /cn:CreditNote/cac:AdditionalDocumentReference/cac:Attachment/cac:ExternalReference',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                        2 => 'Invoice',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'AdditionalDocumentReference',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'Attachment',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'ExternalReference',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
                        2 => 'CreditNote',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'AdditionalDocumentReference',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'Attachment',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'ExternalReference',
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
              'id' => 'BR-TMP-2',
              'flag' => 'fatal',
              'test' => 'matches(cbc:URI, $XR-URL-REGEX)',
              'ast' => 
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                      2 => 'URI',
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
              'message' => '[BR-TMP-2] BT-124 "External document location" muss eine absolute URL mit gültigem Schema enthalten.',
            ),
          ),
        ),
        2 => 
        array (
          'context' => 'cbc:IssueDate | cbc:DueDate | cbc:StartDate | cbc:EndDate | cbc:ActualDeliveryDate |        cbc:IssueDate | cbc:TaxPointDate | cbc:PaymentDueDate',
          'match' => 
          array (
            0 => 'union',
            1 => 
            array (
              0 => 'union',
              1 => 
              array (
                0 => 'union',
                1 => 
                array (
                  0 => 'union',
                  1 => 
                  array (
                    0 => 'union',
                    1 => 
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                2 => 'IssueDate',
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                2 => 'DueDate',
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
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                              2 => 'StartDate',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                            2 => 'EndDate',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                          2 => 'ActualDeliveryDate',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                        2 => 'IssueDate',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                      2 => 'TaxPointDate',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                    2 => 'PaymentDueDate',
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
              'id' => 'BR-TMP-6',
              'flag' => 'warning',
              'test' => 'matches(normalize-space(text()), \'^\\d{4}-\\d{2}-\\d{2}$\')',
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
                    2 => '^\\d{4}-\\d{2}-\\d{2}$',
                  ),
                ),
              ),
              'message' => '[BR-TMP-6] Datumsangaben in UBL müssen im Format JJJJ-MM-TT (YYYY-MM-DD) übermittelt werden.',
            ),
          ),
        ),
        3 => 
        array (
          'context' => '/ubl:Invoice/cac:AccountingSupplierParty | /cn:CreditNote/cac:AccountingSupplierParty',
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
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                    2 => 'Invoice',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'AccountingSupplierParty',
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
                  0 => 'root',
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
                    2 => 'CreditNote',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'AccountingSupplierParty',
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
              'id' => 'BR-DE-2',
              'flag' => 'fatal',
              'test' => 'cac:Party/cac:Contact',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'Party',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'Contact',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              'message' => '[BR-DE-2] Die Gruppe "SELLER CONTACT" (BG-6) muss übermittelt werden.',
            ),
          ),
        ),
        4 => 
        array (
          'context' => '/ubl:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PostalAddress | /cn:CreditNote/cac:AccountingSupplierParty/cac:Party/cac:PostalAddress',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                        2 => 'Invoice',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'AccountingSupplierParty',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'Party',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'PostalAddress',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
                        2 => 'CreditNote',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'AccountingSupplierParty',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'Party',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'PostalAddress',
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
              'id' => 'BR-DE-3',
              'flag' => 'fatal',
              'test' => 'cbc:CityName[boolean(normalize-space(.))]',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
              'test' => 'cbc:PostalZone[boolean(normalize-space(.))]',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'PostalZone',
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
        5 => 
        array (
          'context' => '/ubl:Invoice/cac:AccountingSupplierParty/cac:Party/cac:Contact | /cn:CreditNote/cac:AccountingSupplierParty/cac:Party/cac:Contact',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                        2 => 'Invoice',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'AccountingSupplierParty',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'Party',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'Contact',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
                        2 => 'CreditNote',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'AccountingSupplierParty',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'Party',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'Contact',
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
              'id' => 'BR-DE-5',
              'flag' => 'fatal',
              'test' => 'cbc:Name[boolean(normalize-space(.))]',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'Name',
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
              'message' => '[BR-DE-5] Das Element "Seller contact point" (BT-41) muss übermittelt werden.',
            ),
            1 => 
            array (
              'id' => 'BR-DE-6',
              'flag' => 'fatal',
              'test' => 'cbc:Telephone[boolean(normalize-space(.))]',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'Telephone',
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
              'message' => '[BR-DE-6] Das Element "Seller contact telephone number" (BT-42) muss übermittelt werden.',
            ),
            2 => 
            array (
              'id' => 'BR-DE-7',
              'flag' => 'fatal',
              'test' => 'cbc:ElectronicMail[boolean(normalize-space(.))]',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'ElectronicMail',
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
              'message' => '[BR-DE-7] Das Element "Seller contact email address" (BT-43) muss übermittelt werden.',
            ),
            3 => 
            array (
              'id' => 'BR-DE-27',
              'flag' => 'warning',
              'test' => 'matches(normalize-space(cbc:Telephone), $XR-TELEPHONE-REGEX)',
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
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                          2 => 'Telephone',
                        ),
                        3 => 
                        array (
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
              'test' => 'matches(normalize-space(cbc:ElectronicMail), $XR-EMAIL-REGEX)',
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
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                          2 => 'ElectronicMail',
                        ),
                        3 => 
                        array (
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
        6 => 
        array (
          'context' => '/ubl:Invoice/cac:AccountingCustomerParty/cac:Party/cac:PostalAddress | /cn:CreditNote/cac:AccountingCustomerParty/cac:Party/cac:PostalAddress',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                        2 => 'Invoice',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'AccountingCustomerParty',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'Party',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'PostalAddress',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
                        2 => 'CreditNote',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'AccountingCustomerParty',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'Party',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'PostalAddress',
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
              'id' => 'BR-DE-8',
              'flag' => 'fatal',
              'test' => 'cbc:CityName[boolean(normalize-space(.))]',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
              'test' => 'cbc:PostalZone[boolean(normalize-space(.))]',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'PostalZone',
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
        7 => 
        array (
          'context' => '/ubl:Invoice/cac:Delivery/cac:DeliveryLocation/cac:Address | /cn:CreditNote/cac:Delivery/cac:DeliveryLocation/cac:Address',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                        2 => 'Invoice',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'Delivery',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'DeliveryLocation',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'Address',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
                        2 => 'CreditNote',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'Delivery',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'DeliveryLocation',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'Address',
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
              'id' => 'BR-DE-10',
              'flag' => 'fatal',
              'test' => 'cbc:CityName[boolean(normalize-space(.))]',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
              'test' => 'cbc:PostalZone[boolean(normalize-space(.))]',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'PostalZone',
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
        8 => 
        array (
          'context' => '/ubl:Invoice/cac:PaymentMeans[normalize-space(cbc:PaymentMeansCode) = (\'30\',\'58\')] | /cn:CreditNote/cac:PaymentMeans[normalize-space(cbc:PaymentMeansCode) = (\'30\',\'58\')]',
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
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                    2 => 'Invoice',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'PaymentMeans',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                            2 => 'PaymentMeansCode',
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
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
                    2 => 'CreditNote',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'PaymentMeans',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                            2 => 'PaymentMeansCode',
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
              'test' => 'not(normalize-space(cbc:PaymentMeansCode) = \'58\') or                     xr:checkIBAN(string(cac:PayeeFinancialAccount/cbc:ID))',
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
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                              2 => 'PaymentMeansCode',
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
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                              2 => 'PayeeFinancialAccount',
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
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                              2 => 'ID',
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
              'test' => 'cac:PayeeFinancialAccount',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'PayeeFinancialAccount',
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
              'test' => 'not(cac:CardAccount) and                     not(cac:PaymentMandate)',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'CardAccount',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'PaymentMandate',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-23-b] Wenn BT-81 "Payment means type code" einen Schlüssel für Überweisungen enthält (30, 58), dürfen BG-18 und BG-19 nicht übermittelt werden.',
            ),
          ),
        ),
        9 => 
        array (
          'context' => '/ubl:Invoice/cac:PaymentMeans[normalize-space(cbc:PaymentMeansCode) = (\'48\',\'54\',\'55\')] |/cn:CreditNote/cac:PaymentMeans[normalize-space(cbc:PaymentMeansCode) = (\'48\',\'54\',\'55\')]',
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
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                    2 => 'Invoice',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'PaymentMeans',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                            2 => 'PaymentMeansCode',
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
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
                    2 => 'CreditNote',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'PaymentMeans',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                            2 => 'PaymentMeansCode',
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
              'test' => 'cac:CardAccount',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'CardAccount',
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
              'test' => 'not(cac:PayeeFinancialAccount) and                     not(cac:PaymentMandate)',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'PayeeFinancialAccount',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'PaymentMandate',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DE-24-b] Wenn BT-81 "Payment means type code" einen Schlüssel für Kartenzahlungen enthält (48, 54, 55), dürfen BG-17 und BG-19 nicht übermittelt werden.',
            ),
          ),
        ),
        10 => 
        array (
          'context' => '/ubl:Invoice/cac:PaymentMeans[normalize-space(cbc:PaymentMeansCode) = \'59\'] | /cn:CreditNote/cac:PaymentMeans[normalize-space(cbc:PaymentMeansCode) = \'59\']',
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
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                    2 => 'Invoice',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'PaymentMeans',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                            2 => 'PaymentMeansCode',
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
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
                    2 => 'CreditNote',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'PaymentMeans',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                            2 => 'PaymentMeansCode',
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
              'test' => 'not(normalize-space(cbc:PaymentMeansCode) = \'59\') or                     xr:checkIBAN(string(cac:PaymentMandate/cac:PayerFinancialAccount/cbc:ID))',
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
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                              2 => 'PaymentMeansCode',
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
                            0 => 'path',
                            1 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                2 => 'PaymentMandate',
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                2 => 'PayerFinancialAccount',
                              ),
                              3 => 
                              array (
                              ),
                            ),
                          ),
                          2 => 
                          array (
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                              2 => 'ID',
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
              'test' => 'cac:PaymentMandate',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'PaymentMandate',
                ),
                3 => 
                array (
                ),
              ),
              'message' => '[BR-DE-25-a] Wenn BT-81 "Payment means type code" einen Schlüssel für Lastschriften enthält (59), muss genau BG-19 "DIRECT DEBIT" übermittelt werden.',
            ),
            2 => 
            array (
              'id' => 'BR-DE-25-b',
              'flag' => 'fatal',
              'test' => 'not(cac:PayeeFinancialAccount) and                     not(cac:CardAccount)',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'PayeeFinancialAccount',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'CardAccount',
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
        11 => 
        array (
          'context' => '/ubl:Invoice/cac:TaxTotal/cac:TaxSubtotal | /cn:CreditNote/cac:TaxTotal/cac:TaxSubtotal',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                      2 => 'Invoice',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'TaxTotal',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'TaxSubtotal',
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
                    0 => 'root',
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
                      2 => 'CreditNote',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'TaxTotal',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'TaxSubtotal',
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
              'id' => 'BR-DE-14',
              'flag' => 'fatal',
              'test' => 'cac:TaxCategory/cbc:Percent[boolean(normalize-space(.))]',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'TaxCategory',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                    2 => 'Percent',
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
              'message' => '[BR-DE-14] Das Element "VAT category rate" (BT-119) muss übermittelt werden.',
            ),
          ),
        ),
      ),
    ),
    4 => 
    array (
      'id' => 'ubl-extension-pattern',
      'lets' => 
      array (
      ),
      'rules' => 
      array (
        0 => 
        array (
          'context' => 'cbc:EmbeddedDocumentBinaryObject[$isExtension]',
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
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'EmbeddedDocumentBinaryObject',
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
              'test' => '.[@mimeCode = \'application/pdf\' or         @mimeCode = \'image/png\' or         @mimeCode = \'image/jpeg\' or         @mimeCode = \'text/csv\' or         @mimeCode = \'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet\' or         @mimeCode = \'application/vnd.oasis.opendocument.spreadsheet\' or         @mimeCode = \'application/xml\']',
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
        1 => 
        array (
          'context' => '/ubl:Invoice[$isExtension]',
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
                1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                2 => 'Invoice',
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
              'id' => 'BR-DEX-02',
              'flag' => 'warning',
              'test' => '(every $invoiceline          in /ubl:Invoice/cac:InvoiceLine[ exists (./cac:SubInvoiceLine) ]          satisfies $invoiceline/xs:decimal(cbc:LineExtensionAmount) = sum($invoiceline/cac:SubInvoiceLine/xs:decimal(cbc:LineExtensionAmount))) and         (count( //cac:SubInvoiceLine [count(cac:SubInvoiceLine) > 0 and xs:decimal(cbc:LineExtensionAmount) = sum(cac:SubInvoiceLine/xs:decimal(cbc:LineExtensionAmount))]) = count(//cac:SubInvoiceLine [count(cac:SubInvoiceLine) > 0]))',
              'ast' => 
              array (
                0 => 'and',
                1 => 
                array (
                  0 => 'quant',
                  1 => 'every',
                  2 => 
                  array (
                    0 => 
                    array (
                      0 => 'invoiceline',
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
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                              2 => 'Invoice',
                            ),
                            3 => 
                            array (
                            ),
                          ),
                        ),
                        2 => 
                        array (
                          0 => 'step',
                          1 => 'child',
                          2 => 
                          array (
                            0 => 'name',
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'InvoiceLine',
                          ),
                          3 => 
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
                                    0 => 'context',
                                  ),
                                  2 => 
                                  array (
                                    0 => 'step',
                                    1 => 'child',
                                    2 => 
                                    array (
                                      0 => 'name',
                                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                      2 => 'SubInvoiceLine',
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
                  3 => 
                  array (
                    0 => 'gcmp',
                    1 => '=',
                    2 => 
                    array (
                      0 => 'path',
                      1 => 
                      array (
                        0 => 'var',
                        1 => 'invoiceline',
                      ),
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
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                              2 => 'LineExtensionAmount',
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
                              0 => 'var',
                              1 => 'invoiceline',
                            ),
                            2 => 
                            array (
                              0 => 'step',
                              1 => 'child',
                              2 => 
                              array (
                                0 => 'name',
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                2 => 'SubInvoiceLine',
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
                                0 => 'step',
                                1 => 'child',
                                2 => 
                                array (
                                  0 => 'name',
                                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                  2 => 'LineExtensionAmount',
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
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                              2 => 'SubInvoiceLine',
                            ),
                            3 => 
                            array (
                              0 => 
                              array (
                                0 => 'and',
                                1 => 
                                array (
                                  0 => 'gcmp',
                                  1 => '>',
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
                                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                          2 => 'SubInvoiceLine',
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
                                        0 => 'step',
                                        1 => 'child',
                                        2 => 
                                        array (
                                          0 => 'name',
                                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                          2 => 'LineExtensionAmount',
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
                                    1 => 'fn:sum',
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
                                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                            2 => 'SubInvoiceLine',
                                          ),
                                          3 => 
                                          array (
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
                                              0 => 'step',
                                              1 => 'child',
                                              2 => 
                                              array (
                                                0 => 'name',
                                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                                2 => 'LineExtensionAmount',
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
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                              2 => 'SubInvoiceLine',
                            ),
                            3 => 
                            array (
                              0 => 
                              array (
                                0 => 'gcmp',
                                1 => '>',
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
                                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                        2 => 'SubInvoiceLine',
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
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DEX-02] Der Wert von "Invoice line net amount" (BT-131) einer "INVOICE LINE" (BG-25) oder einer "SUB INVOICE LINE" (BG-DEX-01) soll der Summe der "Invoice line net amount" (BT-131) der direkt darunterliegenden "SUB INVOICE LINE" (BG-DEX-01) entsprechen.',
            ),
            1 => 
            array (
              'id' => 'BR-DEX-03',
              'flag' => 'fatal',
              'test' => 'not(exists(//cac:SubInvoiceLine/cac:Item[ count ( cac:ClassifiedTaxCategory) != 1]))',
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                2 => 'SubInvoiceLine',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'Item',
                          ),
                          3 => 
                          array (
                            0 => 
                            array (
                              0 => 'gcmp',
                              1 => '!=',
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
                                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                                      2 => 'ClassifiedTaxCategory',
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
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DEX-03] Eine Sub Invoice Line (BG-DEX-01) muss genau eine "SUB INVOICE LINE VAT INFORMATION" (BG-DEX-06) enthalten.',
            ),
          ),
        ),
        2 => 
        array (
          'context' => 'cac:LegalMonetaryTotal[$isExtension]',
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
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'LegalMonetaryTotal',
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
            0 => 
            array (
              'name' => 'prepaidamount',
              'value' => 'if (exists(cbc:PrepaidAmount)) then (xs:decimal(cbc:PrepaidAmount)) else (0)',
              'ast' => 
              array (
                0 => 'if',
                1 => 
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                        2 => 'PrepaidAmount',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                        2 => 'PrepaidAmount',
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
            1 => 
            array (
              'name' => 'payableroundingamount',
              'value' => 'if (exists(cbc:PayableRoundingAmount)) then (xs:decimal(cbc:PayableRoundingAmount)) else (0)',
              'ast' => 
              array (
                0 => 'if',
                1 => 
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                        2 => 'PayableRoundingAmount',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                        2 => 'PayableRoundingAmount',
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
            2 => 
            array (
              'name' => 'thirdpartyprepaidamount',
              'value' => 'if (exists(../cac:PrepaidPayment/cbc:PaidAmount[boolean(normalize-space(xs:string(.)))])) then (sum(../cac:PrepaidPayment/xs:decimal(cbc:PaidAmount))) else (0)',
              'ast' => 
              array (
                0 => 'if',
                1 => 
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'PrepaidPayment',
                          ),
                          3 => 
                          array (
                          ),
                        ),
                      ),
                      2 => 
                      array (
                        0 => 'step',
                        1 => 'child',
                        2 => 
                        array (
                          0 => 'name',
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                          2 => 'PaidAmount',
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
                                    0 => 'call',
                                    1 => 'xs:string',
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
                      ),
                    ),
                  ),
                ),
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'PrepaidPayment',
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
                            0 => 'step',
                            1 => 'child',
                            2 => 
                            array (
                              0 => 'name',
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                              2 => 'PaidAmount',
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
              'id' => 'BR-DEX-09',
              'flag' => 'fatal',
              'test' => '(round((xs:decimal(cbc:PayableAmount) - $payableroundingamount) * 10 * 10) div 100) = (round((xs:decimal(cbc:TaxInclusiveAmount) - $prepaidamount + $thirdpartyprepaidamount) * 10 * 10) div 100)',
              'ast' => 
              array (
                0 => 'gcmp',
                1 => '=',
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
                                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                    2 => 'PayableAmount',
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
                              1 => 'payableroundingamount',
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
                            0 => 'arith',
                            1 => '+',
                            2 => 
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
                                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                      2 => 'TaxInclusiveAmount',
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
                                1 => 'prepaidamount',
                              ),
                            ),
                            3 => 
                            array (
                              0 => 'var',
                              1 => 'thirdpartyprepaidamount',
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
              ),
              'message' => '[BR-DEX-09] Amount due for payment (BT-115) = Invoice total amount with VAT (BT-112) - Paid amount (BT-113) + Rounding amount (BT-114) + Σ Third party payment amount (BT-DEX-002).',
            ),
          ),
        ),
        3 => 
        array (
          'context' => 'cac:PartyIdentification/cbc:ID[@schemeID and $isExtension]',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'PartyIdentification',
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
                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
              'test' => '((not(contains(normalize-space(@schemeID), \' \')) and contains($ISO-6523-ICD-EXT-CODES, concat(\' \', normalize-space(@schemeID), \' \'))))  or ((not(contains(normalize-space(@schemeID), \' \')) and contains(\' SEPA \', concat(\' \', normalize-space(@schemeID), \' \'))) and ((ancestor::cac:AccountingSupplierParty) or (ancestor::cac:PayeeParty)))',
              'ast' => 
              array (
                0 => 'or',
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
                2 => 
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
                          0 => 'literal',
                          1 => 'xs:string',
                          2 => ' SEPA ',
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
                  2 => 
                  array (
                    0 => 'or',
                    1 => 
                    array (
                      0 => 'step',
                      1 => 'ancestor',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'AccountingSupplierParty',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                    2 => 
                    array (
                      0 => 'step',
                      1 => 'ancestor',
                      2 => 
                      array (
                        0 => 'name',
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'PayeeParty',
                      ),
                      3 => 
                      array (
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
        4 => 
        array (
          'context' => 'cac:PartyLegalEntity/cbc:CompanyID[@schemeID and $isExtension]',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'PartyLegalEntity',
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
                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                2 => 'CompanyID',
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
        5 => 
        array (
          'context' => 'cac:StandardItemIdentification/cbc:ID[@schemeID and $isExtension]',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'StandardItemIdentification',
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
                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
        6 => 
        array (
          'context' => 'cbc:EndpointID[@schemeID and $isExtension]',
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
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'EndpointID',
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
        7 => 
        array (
          'context' => 'cac:DeliveryLocation/cbc:ID[@schemeID and $isExtension]',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'DeliveryLocation',
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
                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
        8 => 
        array (
          'context' => '/ubl:Invoice/cac:PrepaidPayment[$isExtension]',
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
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                  2 => 'Invoice',
                ),
                3 => 
                array (
                ),
              ),
            ),
            2 => 
            array (
              0 => 'step',
              1 => 'child',
              2 => 
              array (
                0 => 'name',
                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                2 => 'PrepaidPayment',
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
              'id' => 'BR-DEX-10',
              'flag' => 'fatal',
              'test' => 'cbc:ID[boolean(normalize-space(xs:string(.)))]',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'ID',
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
                            0 => 'call',
                            1 => 'xs:string',
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
              ),
              'message' => '[BR-DEX-10] Das Element "Third party payment type" BT-DEX-001 muss übermittelt werden, wenn die Gruppe "THIRD PARTY PAYMENT" (BG-DEX-09) übermittelt wird.',
            ),
            1 => 
            array (
              'id' => 'BR-DEX-11',
              'flag' => 'fatal',
              'test' => 'cbc:PaidAmount[boolean(normalize-space(xs:string(.)))]',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'PaidAmount',
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
                            0 => 'call',
                            1 => 'xs:string',
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
              ),
              'message' => '[BR-DEX-11] Das Element "Third party payment amount" BT-DEX-002 muss übermittelt werden, wenn die Gruppe "THIRD PARTY PAYMENT" (BG-DEX-09) übermittelt wird.',
            ),
            2 => 
            array (
              'id' => 'BR-DEX-12',
              'flag' => 'fatal',
              'test' => 'cbc:InstructionID[boolean(normalize-space(xs:string(.)))]',
              'ast' => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'InstructionID',
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
                            0 => 'call',
                            1 => 'xs:string',
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
              ),
              'message' => '[BR-DEX-12] Das Element "Third party payment description" BT-DEX-003 muss übermittelt werden, wenn die Gruppe "THIRD PARTY PAYMENT" (BG-DEX-09) übermittelt wird.',
            ),
            3 => 
            array (
              'id' => 'BR-DEX-13',
              'flag' => 'fatal',
              'test' => 'string-length(substring-after(cbc:PaidAmount, \'.\')) <= 2',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                            2 => 'PaidAmount',
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
              'message' => '[BR-DEX-13] Die maximale Anzahl zulässiger Nachkommastellen für das Element "Third party payment amount" (BT-DEX-002) ist 2.',
            ),
            4 => 
            array (
              'id' => 'BR-DEX-14',
              'flag' => 'fatal',
              'test' => 'cbc:PaidAmount/@currencyID = parent::node()/cbc:DocumentCurrencyCode',
              'ast' => 
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                      2 => 'PaidAmount',
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
                      2 => 'currencyID',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                      2 => 'DocumentCurrencyCode',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
              ),
              'message' => '[BR-DEX-14] Die Währungsangabe von "Third party payment amount" BT-DEX-002 muss BT-5 ("Invoice currency code") entsprechen.',
            ),
          ),
        ),
      ),
    ),
    5 => 
    array (
      'id' => 'ubl-cvd-pattern',
      'lets' => 
      array (
      ),
      'rules' => 
      array (
        0 => 
        array (
          'context' => '(/ubl:Invoice | /cn:CreditNote)[$isCVD]',
          'match' => 
          array (
            0 => 'filter',
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
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                    2 => 'Invoice',
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
                  0 => 'root',
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
                    2 => 'CreditNote',
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
                0 => 'var',
                1 => 'isCVD',
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
              'id' => 'BR-DE-CVD-02',
              'flag' => 'fatal',
              'test' => 'cac:OriginatorDocumentReference/cbc:ID[boolean(normalize-space(.))]',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'OriginatorDocumentReference',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                    2 => 'ID',
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
            1 => 
            array (
              'id' => 'BR-DE-CVD-01',
              'flag' => 'fatal',
              'test' => 'cac:ContractDocumentReference/cbc:ID[boolean(normalize-space(.))]',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'ContractDocumentReference',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                    2 => 'ID',
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
            2 => 
            array (
              'id' => 'BR-DE-CVD-03',
              'flag' => 'fatal',
              'test' => '(cac:InvoiceLine/cac:Item | cac:CreditNoteLine/cac:Item)[cac:CommodityClassification/cbc:ItemClassificationCode/@listID = \'CVD\'         and         cac:AdditionalItemProperty/cbc:Name = \'cva\']',
              'ast' => 
              array (
                0 => 'filter',
                1 => 
                array (
                  0 => 'union',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'InvoiceLine',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'Item',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'CreditNoteLine',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'Item',
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
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                              2 => 'CommodityClassification',
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
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                              2 => 'ItemClassificationCode',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'AdditionalItemProperty',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
                        2 => 'cva',
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
          'context' => '(/ubl:Invoice[$isCVD]/cac:InvoiceLine | /cn:CreditNote[$isCVD]/cac:CreditNoteLine)/cac:Item',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                      2 => 'Invoice',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'InvoiceLine',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'Item',
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
                    0 => 'root',
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
                      2 => 'CreditNote',
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
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'CreditNoteLine',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'Item',
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
              'id' => 'BR-DE-CVD-06-a',
              'flag' => 'fatal',
              'test' => 'not(cac:CommodityClassification/cbc:ItemClassificationCode[@listID = \'CVD\']) or count(cac:AdditionalItemProperty[cbc:Name = \'cva\']) = 1',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'CommodityClassification',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                          2 => 'ItemClassificationCode',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                          2 => 'AdditionalItemProperty',
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
                                1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                                2 => 'Name',
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
            1 => 
            array (
              'id' => 'BR-DE-CVD-06-b',
              'flag' => 'fatal',
              'test' => 'not(cac:AdditionalItemProperty[cbc:Name = \'cva\']) or count(cac:CommodityClassification/cbc:ItemClassificationCode[@listID = \'CVD\']) = 1',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'AdditionalItemProperty',
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
                              1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                              2 => 'Name',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                            2 => 'CommodityClassification',
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
                            1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                            2 => 'ItemClassificationCode',
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
          ),
        ),
        2 => 
        array (
          'context' => '(/ubl:Invoice[$isCVD]/cac:InvoiceLine | /cn:CreditNote[$isCVD]/cac:CreditNoteLine)/cac:Item/cac:CommodityClassification/cbc:ItemClassificationCode',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                          2 => 'Invoice',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'InvoiceLine',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'Item',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'CommodityClassification',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'ItemClassificationCode',
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
                          1 => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
                          2 => 'CreditNote',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        2 => 'CreditNoteLine',
                      ),
                      3 => 
                      array (
                      ),
                    ),
                  ),
                  2 => 
                  array (
                    0 => 'step',
                    1 => 'child',
                    2 => 
                    array (
                      0 => 'name',
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'Item',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'CommodityClassification',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                  2 => 'ItemClassificationCode',
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
          'context' => '(/ubl:Invoice[$isCVD]/cac:InvoiceLine | /cn:CreditNote[$isCVD]/cac:CreditNoteLine)/cac:Item/cac:AdditionalItemProperty[cbc:Name = \'cva\']',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                        2 => 'Invoice',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'InvoiceLine',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'Item',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'AdditionalItemProperty',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                        2 => 'Name',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
                        2 => 'CreditNote',
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
                      1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                      2 => 'CreditNoteLine',
                    ),
                    3 => 
                    array (
                    ),
                  ),
                ),
                2 => 
                array (
                  0 => 'step',
                  1 => 'child',
                  2 => 
                  array (
                    0 => 'name',
                    1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    2 => 'Item',
                  ),
                  3 => 
                  array (
                  ),
                ),
              ),
              2 => 
              array (
                0 => 'step',
                1 => 'child',
                2 => 
                array (
                  0 => 'name',
                  1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                  2 => 'AdditionalItemProperty',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                        2 => 'Name',
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
          'lets' => 
          array (
          ),
          'asserts' => 
          array (
            0 => 
            array (
              'id' => 'BR-DE-CVD-05',
              'flag' => 'fatal',
              'test' => 'normalize-space(cbc:Value) = $CVA-CODES',
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
                        1 => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
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
      ),
    ),
  ),
);
