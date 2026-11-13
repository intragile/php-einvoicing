<?php

// Generated from the KoSIT validator configuration for XRechnung 2026-08-31 (Apache-2.0) — do not edit.
// Source: scenarios.xml (SHA-256 3e8161a39dfaa6aea4d990b279aab848be4f4c6169e820f354a159bda7693fb0).
// Regenerate with: composer compile-rules

declare(strict_types=1);

return array (
  'format' => 1,
  'configuration' => '2026-08-31',
  'scenarios' => 
  array (
    0 => 
    array (
      'name' => 'EN16931 XRechnung (UBL Invoice)',
      'namespaces' => 
      array (
        'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
        'invoice' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
        'rep' => 'http://www.xoev.de/de/validator/varl/1',
      ),
      'match' => 'exists(/invoice:Invoice/cbc:CustomizationID[ . = \'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0\']) ',
      'schema' => 'ubl/2.1/xsd/maindoc/UBL-Invoice-2.1.xsd',
      'ruleSets' => 
      array (
        0 => 
        array (
          'id' => 'en16931-ubl',
          'version' => '1.3.16',
        ),
        1 => 
        array (
          'id' => 'xrechnung-ubl',
          'version' => '2.6.0',
        ),
      ),
      'customLevels' => 
      array (
        'BR-CL-23' => 'warning',
        'BR-CL-21' => 'warning',
        'UBL-CR-646' => 'error',
      ),
    ),
    1 => 
    array (
      'name' => 'EN16931 XRechnung Extension (UBL Invoice)',
      'namespaces' => 
      array (
        'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
        'invoice' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
        'rep' => 'http://www.xoev.de/de/validator/varl/1',
      ),
      'match' => 'exists(/invoice:Invoice/cbc:CustomizationID[ . = \'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0#conformant#urn:xeinkauf.de:kosit:extension:xrechnung_3.0\'])',
      'schema' => 'ubl/2.1/xsd/maindoc/UBL-Invoice-2.1.xsd',
      'ruleSets' => 
      array (
        0 => 
        array (
          'id' => 'en16931-ubl',
          'version' => '1.3.16',
        ),
        1 => 
        array (
          'id' => 'xrechnung-ubl',
          'version' => '2.6.0',
        ),
      ),
      'customLevels' => 
      array (
        'BR-CL-21' => 'information',
        'BR-CL-23' => 'warning',
        'BR-CL-24' => 'information',
        'BR-CL-10' => 'information',
        'BR-CL-11' => 'information',
        'BR-CL-25' => 'information',
        'BR-CL-26' => 'information',
        'BR-CO-16' => 'information',
        'UBL-CR-470' => 'information',
        'UBL-CR-646' => 'information',
      ),
    ),
    2 => 
    array (
      'name' => 'EN16931 XRechnung CVD (UBL Invoice)',
      'namespaces' => 
      array (
        'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
        'invoice' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
        'rep' => 'http://www.xoev.de/de/validator/varl/1',
      ),
      'match' => 'exists(/invoice:Invoice/cbc:CustomizationID[ . = \'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0#compliant#urn:xeinkauf.de:kosit:xrechnung:cvd_0.9\']) ',
      'schema' => 'ubl/2.1/xsd/maindoc/UBL-Invoice-2.1.xsd',
      'ruleSets' => 
      array (
        0 => 
        array (
          'id' => 'en16931-ubl',
          'version' => '1.3.16',
        ),
        1 => 
        array (
          'id' => 'xrechnung-ubl',
          'version' => '2.6.0',
        ),
      ),
      'customLevels' => 
      array (
        'BR-CL-13' => 'information',
        'BR-CL-23' => 'warning',
        'BR-CL-21' => 'warning',
        'UBL-CR-646' => 'error',
      ),
    ),
    3 => 
    array (
      'name' => 'EN16931 XRechnung (UBL CreditNote)',
      'namespaces' => 
      array (
        'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
        'creditnote' => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
        'rep' => 'http://www.xoev.de/de/validator/varl/1',
      ),
      'match' => 'exists(/creditnote:CreditNote[ cbc:CustomizationID/text() = \'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0\'])',
      'schema' => 'ubl/2.1/xsd/maindoc/UBL-CreditNote-2.1.xsd',
      'ruleSets' => 
      array (
        0 => 
        array (
          'id' => 'en16931-ubl',
          'version' => '1.3.16',
        ),
        1 => 
        array (
          'id' => 'xrechnung-ubl',
          'version' => '2.6.0',
        ),
      ),
      'customLevels' => 
      array (
        'BR-CL-23' => 'warning',
        'BR-CL-21' => 'warning',
      ),
    ),
    4 => 
    array (
      'name' => 'EN16931 XRechnung CVD (UBL CreditNote)',
      'namespaces' => 
      array (
        'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
        'creditnote' => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
        'rep' => 'http://www.xoev.de/de/validator/varl/1',
      ),
      'match' => 'exists(/creditnote:CreditNote[ cbc:CustomizationID/text() = \'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0#compliant#urn:xeinkauf.de:kosit:xrechnung:cvd_0.9\'])',
      'schema' => 'ubl/2.1/xsd/maindoc/UBL-CreditNote-2.1.xsd',
      'ruleSets' => 
      array (
        0 => 
        array (
          'id' => 'en16931-ubl',
          'version' => '1.3.16',
        ),
        1 => 
        array (
          'id' => 'xrechnung-ubl',
          'version' => '2.6.0',
        ),
      ),
      'customLevels' => 
      array (
        'BR-CL-13' => 'information',
        'BR-CL-23' => 'warning',
        'BR-CL-21' => 'warning',
        'UBL-CR-646' => 'error',
      ),
    ),
    5 => 
    array (
      'name' => 'EN16931 XRechnung (CII)',
      'namespaces' => 
      array (
        'rsm' => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
        'ram' => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
        'rep' => 'http://www.xoev.de/de/validator/varl/1',
      ),
      'match' => 'exists(/rsm:CrossIndustryInvoice[rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID/text() = \'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0\'])',
      'schema' => 'cii/16b/xsd/CrossIndustryInvoice_100pD16B.xsd',
      'ruleSets' => 
      array (
        0 => 
        array (
          'id' => 'en16931-cii',
          'version' => '1.3.16',
        ),
        1 => 
        array (
          'id' => 'xrechnung-cii',
          'version' => '2.6.0',
        ),
      ),
      'customLevels' => 
      array (
        'BR-CL-23' => 'warning',
        'BR-CL-21' => 'warning',
        'CII-SR-452' => 'error',
        'CII-SR-453' => 'error',
        'CII-SR-454' => 'error',
        'CII-SR-465' => 'error',
        'CII-SR-466' => 'error',
        'CII-SR-475' => 'information',
        'CII-SR-476' => 'information',
      ),
    ),
    6 => 
    array (
      'name' => 'EN16931 XRechnung Extension (CII)',
      'namespaces' => 
      array (
        'rsm' => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
        'ram' => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
        'rep' => 'http://www.xoev.de/de/validator/varl/1',
      ),
      'match' => 'exists(/rsm:CrossIndustryInvoice[rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID/text() = \'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0#conformant#urn:xeinkauf.de:kosit:extension:xrechnung_3.0\'])',
      'schema' => 'cii/16b/xsd/CrossIndustryInvoice_100pD16B.xsd',
      'ruleSets' => 
      array (
        0 => 
        array (
          'id' => 'en16931-cii',
          'version' => '1.3.16',
        ),
        1 => 
        array (
          'id' => 'xrechnung-cii',
          'version' => '2.6.0',
        ),
      ),
      'customLevels' => 
      array (
        'BR-CL-23' => 'warning',
        'BR-CL-21' => 'information',
        'BR-CL-11' => 'information',
        'BR-CL-10' => 'information',
        'BR-CL-24' => 'information',
        'BR-CL-25' => 'information',
        'BR-CL-26' => 'information',
        'CII-SR-452' => 'error',
        'CII-SR-453' => 'error',
        'CII-SR-454' => 'error',
        'CII-SR-465' => 'error',
        'CII-SR-466' => 'error',
        'CII-SR-475' => 'information',
        'CII-SR-476' => 'information',
      ),
    ),
    7 => 
    array (
      'name' => 'EN16931 XRechnung CVD (CII)',
      'namespaces' => 
      array (
        'rsm' => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
        'ram' => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
        'rep' => 'http://www.xoev.de/de/validator/varl/1',
      ),
      'match' => 'exists(/rsm:CrossIndustryInvoice[rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID/text() = \'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0#compliant#urn:xeinkauf.de:kosit:xrechnung:cvd_0.9\'])',
      'schema' => 'cii/16b/xsd/CrossIndustryInvoice_100pD16B.xsd',
      'ruleSets' => 
      array (
        0 => 
        array (
          'id' => 'en16931-cii',
          'version' => '1.3.16',
        ),
        1 => 
        array (
          'id' => 'xrechnung-cii',
          'version' => '2.6.0',
        ),
      ),
      'customLevels' => 
      array (
        'BR-CL-13' => 'information',
        'BR-CL-23' => 'warning',
        'BR-CL-21' => 'warning',
        'CII-SR-452' => 'error',
        'CII-SR-453' => 'error',
        'CII-SR-454' => 'error',
        'CII-SR-465' => 'error',
        'CII-SR-466' => 'error',
        'CII-SR-475' => 'information',
        'CII-SR-476' => 'information',
      ),
    ),
    8 => 
    array (
      'name' => 'EN16931 (UBL Invoice)',
      'namespaces' => 
      array (
        'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
        'invoice' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
        'rep' => 'http://www.xoev.de/de/validator/varl/1',
      ),
      'match' => 'exists(/invoice:Invoice[ cbc:CustomizationID/text() = \'urn:cen.eu:en16931:2017\' ])',
      'schema' => 'ubl/2.1/xsd/maindoc/UBL-Invoice-2.1.xsd',
      'ruleSets' => 
      array (
        0 => 
        array (
          'id' => 'en16931-ubl',
          'version' => '1.3.16',
        ),
      ),
      'customLevels' => 
      array (
      ),
    ),
    9 => 
    array (
      'name' => 'EN16931 (UBL CreditNote)',
      'namespaces' => 
      array (
        'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
        'creditnote' => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
        'rep' => 'http://www.xoev.de/de/validator/varl/1',
      ),
      'match' => 'exists(/creditnote:CreditNote[cbc:CustomizationID/text() = \'urn:cen.eu:en16931:2017\'])',
      'schema' => 'ubl/2.1/xsd/maindoc/UBL-CreditNote-2.1.xsd',
      'ruleSets' => 
      array (
        0 => 
        array (
          'id' => 'en16931-ubl',
          'version' => '1.3.16',
        ),
      ),
      'customLevels' => 
      array (
      ),
    ),
    10 => 
    array (
      'name' => 'EN16931 (CII)',
      'namespaces' => 
      array (
        'rsm' => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
        'ram' => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
        'rep' => 'http://www.xoev.de/de/validator/varl/1',
      ),
      'match' => 'exists(/rsm:CrossIndustryInvoice[ rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID/text() = \'urn:cen.eu:en16931:2017\'])',
      'schema' => 'cii/16b/xsd/CrossIndustryInvoice_100pD16B.xsd',
      'ruleSets' => 
      array (
        0 => 
        array (
          'id' => 'en16931-cii',
          'version' => '1.3.16',
        ),
      ),
      'customLevels' => 
      array (
      ),
    ),
  ),
);
