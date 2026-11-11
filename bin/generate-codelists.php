<?php

declare(strict_types=1);

/*
 * Generates the backed enums in src/CodeList/ from the code lists embedded in the CEN EN 16931
 * validation artefacts 1.3.17 (schematron/codelist/EN16931-{UBL,CII}-codes.sch). Those lists are
 * the allowed-value sets the official BR-CL-* rules check, so the enums accept exactly what the
 * official validator accepts.
 *
 * Run bin/fetch-official.php first. Usage:
 *
 *   php bin/generate-codelists.php           writes src/CodeList/*.php
 *   php bin/generate-codelists.php --check   exits 1 when a generated file is missing or stale
 *   --source=DIR                             reads the artefacts from DIR instead of SOURCE_DIR
 *
 * Where a rule exists in both syntaxes, the UBL and CII lists must contain the same codes, or the
 * generator fails. Each assert must hold the number of code literals in EXPECTED_LITERALS (one by
 * default), so a list added to a rule upstream cannot be dropped unnoticed. Codes keep the order in which the UBL Schematron lists them (CII for lists that
 * exist only there).
 *
 * Case names are derived from the code, never from its description:
 *   - every character outside [A-Za-z0-9] becomes "_"   ('VATEX-EU-79-C' => VATEX_EU_79_C,
 *                                                         'application/pdf' => application_pdf)
 *   - a name that does not start with a letter gets the prefix "C_"   ('380' => C_380, '1I' => C_1I)
 *   - the reserved name "class" (any case) also gets the prefix "C_"
 * VatCategory is the exception: its ten cases get the readable names in VAT_CATEGORY_NAMES.
 * A collision between two derived names aborts the run.
 */

const SOURCE_DIR = 'tests/fixtures/official/cen-1.3.17';
const TARGET_DIR = 'src/CodeList';
const SCHEMATRON = [
    'ubl' => 'ubl/schematron/codelist/EN16931-UBL-codes.sch',
    'cii' => 'cii/schematron/codelist/EN16931-CII-codes.sch',
];
const HEADER = 'Generated from CEN EN 16931 validation artefacts 1.3.17 — do not edit';

/**
 * Code literals per assert where a rule holds more than one, per syntax. UBL BR-CL-01 lists the
 * Invoice and the CreditNote type codes separately; UBL BR-CL-10 adds 'SEPA', which is allowed for
 * the creditor identifier only and is not part of ISO 6523.
 */
const EXPECTED_LITERALS = [
    'BR-CL-01' => ['ubl' => 2],
    'BR-CL-10' => ['ubl' => 2],
];

/**
 * Enum name => [BR-CL rule, syntaxes that carry the list, short description].
 */
const LISTS = [
    'InvoiceTypeCode' => ['BR-CL-01', ['ubl', 'cii'], 'Invoice and credit note type codes (UNTDID 1001 subset, BT-3)'],
    'CurrencyCode' => ['BR-CL-04', ['ubl', 'cii'], 'Currency codes (ISO 4217 alpha-3, BT-5 and BT-6)'],
    'CountryCode' => ['BR-CL-14', ['ubl', 'cii'], 'Country codes (ISO 3166-1 alpha-2)'],
    'PaymentMeansCode' => ['BR-CL-16', ['ubl', 'cii'], 'Payment means type codes (UNTDID 4461, BT-81)'],
    'VatCategory' => ['BR-CL-17', ['ubl', 'cii'], 'VAT category codes (UNTDID 5305 subset, BT-95, BT-102, BT-118, BT-151)'],
    'AllowanceReasonCode' => ['BR-CL-19', ['ubl', 'cii'], 'Allowance reason codes (UNTDID 5189 subset, BT-98 and BT-140)'],
    'ChargeReasonCode' => ['BR-CL-20', ['ubl', 'cii'], 'Charge reason codes (UNTDID 7161, BT-105 and BT-145)'],
    'VatExemptionReason' => ['BR-CL-22', ['ubl', 'cii'], 'VAT exemption reason codes (CEF VATEX, BT-121)'],
    'UnitCode' => ['BR-CL-23', ['ubl', 'cii'], 'Unit of measure codes (UN/ECE Recommendation 20 with Recommendation 21, BT-130 and BT-150)'],
    'MimeCode' => ['BR-CL-24', ['ubl', 'cii'], 'Attachment MIME codes (BT-125)'],
    'ElectronicAddressScheme' => ['BR-CL-25', ['ubl', 'cii'], 'Electronic address scheme identifiers (CEF EAS, BT-34 and BT-49)'],
    'IdentifierScheme' => ['BR-CL-10', ['ubl', 'cii'], 'Identifier scheme identifiers (ISO 6523 ICD)'],
    'TextSubjectCode' => ['BR-CL-08', ['cii'], 'Invoice note subject codes (UNTDID 4451 subset, BT-21)'],
];

const VAT_CATEGORY_NAMES = [
    'S' => 'StandardRate',
    'Z' => 'ZeroRated',
    'E' => 'Exempt',
    'AE' => 'ReverseCharge',
    'K' => 'IntraCommunitySupply',
    'G' => 'ExportOutsideEu',
    'O' => 'NotSubjectToVat',
    'L' => 'CanaryIslands',
    'M' => 'CeutaAndMelilla',
    'B' => 'TransferredVat',
];

$root = dirname(__DIR__);
$arguments = array_slice($argv ?? [], 1);
$check = in_array('--check', $arguments, true);
$sourceDir = $root.'/'.SOURCE_DIR;
foreach ($arguments as $argument) {
    if (str_starts_with($argument, '--source=')) {
        $sourceDir = substr($argument, strlen('--source='));
    }
}

try {
    $documents = [];
    foreach (SCHEMATRON as $syntax => $path) {
        $documents[$syntax] = loadSchematron($sourceDir.'/'.$path);
    }

    $stale = [];
    foreach (LISTS as $enum => [$rule, $syntaxes, $description]) {
        [$codes, $creditNotes] = extractList($documents, $enum, $rule, $syntaxes);
        $sources = array_map(static fn (string $s): string => SOURCE_DIR.'/'.SCHEMATRON[$s], $syntaxes);
        $contents = renderEnum($enum, $rule, $description, $sources, $codes, $creditNotes);
        $target = $root.'/'.TARGET_DIR.'/'.$enum.'.php';

        if ($check) {
            if (! is_file($target) || file_get_contents($target) !== $contents) {
                $stale[] = TARGET_DIR.'/'.$enum.'.php';
            }

            continue;
        }

        if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0777, true) && ! is_dir(dirname($target))) {
            throw new RuntimeException('Cannot create '.dirname($target));
        }
        if (file_put_contents($target, $contents) === false) {
            throw new RuntimeException("Cannot write {$target}");
        }
        fwrite(STDOUT, sprintf("%s: %d codes (%s)\n", $enum, count($codes), $rule));
    }

    if ($stale !== []) {
        fwrite(STDERR, "generate-codelists: stale or missing, run bin/generate-codelists.php:\n  ".implode("\n  ", $stale)."\n");
        exit(1);
    }
} catch (RuntimeException $e) {
    fwrite(STDERR, 'generate-codelists: '.$e->getMessage()."\n");
    exit(1);
}

function loadSchematron(string $path): DOMXPath
{
    $xml = @file_get_contents($path);
    if ($xml === false) {
        throw new RuntimeException("Cannot read {$path}; run bin/fetch-official.php first.");
    }

    $document = new DOMDocument;
    if (! $document->loadXML($xml, LIBXML_NONET) || $document->doctype !== null) {
        throw new RuntimeException("{$path} is not a plain Schematron document.");
    }

    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('sch', 'http://purl.oclc.org/dsdl/schematron');

    return $xpath;
}

/**
 * Returns the code sets found in the test expression of the given assert: one list per
 * contains(' a b c ', ...) literal, or a single list of the @mimeCode = '...' comparisons.
 *
 * @return list<list<string>>
 */
function literals(DOMXPath $xpath, string $rule): array
{
    $asserts = $xpath->query('//sch:assert[@id="'.$rule.'"]');
    if ($asserts === false || $asserts->length !== 1 || ! $asserts->item(0) instanceof DOMElement) {
        throw new RuntimeException("Expected exactly one assert {$rule}.");
    }
    $test = $asserts->item(0)->getAttribute('test');

    $sets = [];
    if (preg_match_all("/contains\\(\\s*'([^']*)'/", $test, $matches) > 0) {
        foreach ($matches[1] as $literal) {
            $sets[] = splitCodes($literal);
        }
    } elseif (preg_match_all("/@mimeCode\\s*=\\s*'([^']*)'/", $test, $matches) > 0) {
        $sets[] = array_values(array_unique($matches[1]));
    }

    if ($sets === [] || in_array([], $sets, true)) {
        throw new RuntimeException("No code list found in assert {$rule}.");
    }

    return $sets;
}

/**
 * @return list<string>
 */
function splitCodes(string $literal): array
{
    $codes = preg_split('/\s+/', trim($literal), -1, PREG_SPLIT_NO_EMPTY);

    return $codes === false ? [] : array_values(array_unique($codes));
}

/**
 * @param  array<string, DOMXPath>  $documents
 * @param  list<string>  $syntaxes
 * @return array{list<string>, list<string>} the codes and, for InvoiceTypeCode, the credit note codes
 */
function extractList(array $documents, string $enum, string $rule, array $syntaxes): array
{
    $sets = [];
    foreach ($syntaxes as $syntax) {
        $sets[$syntax] = literals($documents[$syntax], $rule);
        $expected = EXPECTED_LITERALS[$rule][$syntax] ?? 1;
        if (count($sets[$syntax]) !== $expected) {
            throw new RuntimeException(sprintf(
                '%s has %d code literals in %s, expected %d.',
                $rule,
                count($sets[$syntax]),
                $syntax,
                $expected,
            ));
        }
    }

    $creditNotes = [];
    if ($enum === 'InvoiceTypeCode') {
        // UBL lists invoice and credit note type codes separately (Invoice vs CreditNote root);
        // CII carries their union in one list.
        [$invoices, $creditNotes] = $sets['ubl'];
        $codes = $sets['cii'][0];
        assertSameCodes($rule, array_values(array_unique([...$invoices, ...$creditNotes])), $codes);
    } else {
        // The first literal is the code list; see EXPECTED_LITERALS for the second one of BR-CL-10.
        if ($rule === 'BR-CL-10' && $sets['ubl'][1] !== ['SEPA']) {
            throw new RuntimeException("Unexpected second code literal in {$rule}.");
        }
        $primary = $syntaxes[0];
        $codes = $sets[$primary][0];
        foreach ($syntaxes as $syntax) {
            assertSameCodes($rule, $codes, $sets[$syntax][0]);
        }
    }

    return [$codes, $creditNotes];
}

/**
 * @param  list<string>  $expected
 * @param  list<string>  $actual
 */
function assertSameCodes(string $rule, array $expected, array $actual): void
{
    $missing = array_diff($expected, $actual);
    $extra = array_diff($actual, $expected);
    if ($missing !== [] || $extra !== []) {
        throw new RuntimeException(sprintf(
            '%s differs between sources (only in first: %s; only in second: %s).',
            $rule,
            implode(' ', $missing),
            implode(' ', $extra),
        ));
    }
}

function caseName(string $enum, string $code): string
{
    if ($enum === 'VatCategory') {
        return VAT_CATEGORY_NAMES[$code] ?? throw new RuntimeException("No readable name for VAT category {$code}.");
    }

    $name = (string) preg_replace('/[^A-Za-z0-9]/', '_', $code);
    if (preg_match('/^[A-Za-z]/', $name) !== 1 || strcasecmp($name, 'class') === 0) {
        $name = 'C_'.$name;
    }

    return $name;
}

/**
 * @param  list<string>  $sources
 * @param  list<string>  $codes
 * @param  list<string>  $creditNotes
 */
function renderEnum(string $enum, string $rule, string $description, array $sources, array $codes, array $creditNotes): string
{
    $names = [];
    $cases = '';
    foreach ($codes as $code) {
        $name = caseName($enum, $code);
        if (isset($names[$name])) {
            throw new RuntimeException("{$enum}: codes '{$names[$name]}' and '{$code}' both map to case {$name}.");
        }
        $names[$name] = $code;
        $cases .= "    case {$name} = '".addcslashes($code, "'\\")."';\n";
    }

    $methods = <<<'PHP'

            public static function isValid(string $code): bool
            {
                return self::tryFrom($code) !== null;
            }

        PHP;

    if ($creditNotes !== []) {
        $arms = implode(', ', array_map(static fn (string $c): string => 'self::'.caseName($enum, $c), $creditNotes));
        $methods .= <<<PHP

                /**
                 * Whether the code is one of the credit note type codes of BR-CL-01 (a UBL CreditNote).
                 */
                public function isCreditNote(): bool
                {
                    return match (\$this) {
                        {$arms} => true,
                        default => false,
                    };
                }

            PHP;
    }

    $sourceLines = implode('', array_map(static fn (string $s): string => " *   {$s}\n", $sources));
    $header = HEADER;

    return <<<PHP
        <?php

        declare(strict_types=1);

        /*
         * {$header}.
         *
         * Source ({$rule}), regenerate with bin/generate-codelists.php:
        {$sourceLines} */

        namespace IntrAgile\\EInvoicing\\CodeList;

        /**
         * {$description}.
         */
        enum {$enum}: string
        {
        {$cases}{$methods}}

        PHP;
}
