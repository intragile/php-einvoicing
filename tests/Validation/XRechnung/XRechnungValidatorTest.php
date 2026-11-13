<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\XRechnung;

use Composer\Autoload\ClassLoader;
use DateTimeImmutable;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\ElectronicAddressScheme;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\PaymentMeansCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Model\Contact;
use IntrAgile\EInvoicing\Model\CreditTransfer;
use IntrAgile\EInvoicing\Model\ElectronicAddress;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PaymentInstructions;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Rules\En16931\RuleFiles as En16931RuleFiles;
use IntrAgile\EInvoicing\Rules\XRechnung\RuleFiles;
use IntrAgile\EInvoicing\Syntax\Cii\CiiReader;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\Syntax;
use IntrAgile\EInvoicing\Syntax\Ubl\UblReader;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Syntax\XRechnung;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;
use IntrAgile\EInvoicing\Validation\Finding;
use IntrAgile\EInvoicing\Validation\Severity;
use IntrAgile\EInvoicing\Validation\ValidationReport;
use IntrAgile\EInvoicing\Validation\XRechnung\XRechnungValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class XRechnungValidatorTest extends TestCase
{
    private const XRECHNUNG = __DIR__.'/../../fixtures/official/xrechnung';

    private const STANDARD = self::XRECHNUNG.'/testsuite-2026-08-31/instances/standard';

    private const UNIT = self::XRECHNUNG.'/unit-2.6.0';

    private const VALID_UBL = self::STANDARD.'/01.01a-INVOICE_ubl.xml';

    private static ?XRechnungValidator $validator = null;

    public function test_valid_testsuite_invoice_is_valid(): void
    {
        $report = self::validator()->validate(self::read(self::VALID_UBL));

        self::assertSame('EN16931 XRechnung (UBL Invoice)', $report->scenario);
        self::assertSame(['en16931-ubl 1.3.16', 'xrechnung-ubl 2.6.0'], $report->ruleSets);
        self::assertSame(Syntax::Ubl, $report->syntax);
        self::assertSame([], $report->schemaErrors);
        self::assertSame([], $report->dynamicErrors);
        self::assertSame(['BR-DE-TMP-32 information'], $report->verdict());
        self::assertTrue($report->isValid());
    }

    public function test_missing_buyer_reference_fires_br_de_15(): void
    {
        $xml = self::replaceOnce('#\s*<cbc:BuyerReference>[^<]*</cbc:BuyerReference>#', '', self::read(self::VALID_UBL));

        $report = self::validator()->validate($xml);

        self::assertSame([], $report->schemaErrors);
        self::assertContains('BR-DE-15 fatal', $report->verdict());
        self::assertFalse($report->isValid());
    }

    public function test_custom_level_overrides_flag(): void
    {
        $report = self::validator()->validate(self::read(self::UNIT.'/cii/cii-br-tmp-2-test.xml'));

        self::assertSame('EN16931 XRechnung (CII)', $report->scenario);
        self::assertSame([], $report->schemaErrors);
        $finding = self::finding($report, 'CII-SR-465');
        self::assertSame(Severity::Fatal, $finding->severity);
        self::assertFalse($report->isValid());
    }

    public function test_credit_note_uses_credit_note_scenario(): void
    {
        $report = self::validator()->validate(self::read(self::UNIT.'/ubl-cn/ubl-cn-br-de-15-test.xml'));

        self::assertSame('EN16931 XRechnung (UBL CreditNote)', $report->scenario);
        self::assertSame(['en16931-ubl 1.3.16', 'xrechnung-ubl 2.6.0'], $report->ruleSets);
    }

    /**
     * UBL-CR-646 is flagged "warning" by CEN. The KoSIT configuration makes it an error in the
     * XRechnung CIUS and CVD invoice scenarios and information in the Extension scenario. (It is
     * unset in the CreditNote CIUS scenario, but there it can only fire on a schema-invalid
     * document, where the Schematron does not run.)
     *
     * @return iterable<string, array{string, string, Severity}>
     */
    public static function ublCr646Scenarios(): iterable
    {
        yield 'extension' => [XRechnung::EXTENSION, 'EN16931 XRechnung Extension (UBL Invoice)', Severity::Information];
        yield 'cius' => [XRechnung::CIUS, 'EN16931 XRechnung (UBL Invoice)', Severity::Fatal];
        yield 'cvd' => [XRechnung::CVD, 'EN16931 XRechnung CVD (UBL Invoice)', Severity::Fatal];
    }

    #[DataProvider('ublCr646Scenarios')]
    public function test_custom_levels_differ_per_scenario(string $customizationId, string $scenario, Severity $expected): void
    {
        // An Extension unit test with sub-invoice lines, which fire UBL-CR-646.
        $xml = self::withCustomizationId(self::read(self::UNIT.'/ubl-inv/ubl-inv-br-dex-02-check-0-values.xml'), $customizationId);

        $report = self::validator()->validate($xml);

        self::assertSame($scenario, $report->scenario);
        self::assertSame([], $report->schemaErrors);
        self::assertSame($expected, self::finding($report, 'UBL-CR-646')->severity);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unmatchedCustomizationIds(): iterable
    {
        yield 'leading whitespace' => [' '.XRechnung::CIUS];
        yield 'trailing whitespace' => [XRechnung::CIUS.' '];
        yield 'XRechnung 2.3' => ['urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_2.3'];
        yield 'XRechnung 3.0.1' => ['urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0.1'];
        yield 'Peppol BIS' => ['urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0'];
    }

    #[DataProvider('unmatchedCustomizationIds')]
    public function test_unmatched_customization_id_lists_accepted_ids(string $customizationId): void
    {
        $xml = self::withCustomizationId(self::read(self::VALID_UBL), $customizationId);

        try {
            self::validator()->validate($xml);
            self::fail('No scenario should match.');
        } catch (UnsupportedDocument $exception) {
            $message = $exception->getMessage();
            self::assertStringContainsString(XRechnung::CIUS, $message);
            self::assertStringContainsString(XRechnung::EXTENSION, $message);
            self::assertStringContainsString(XRechnung::CVD, $message);
            self::assertStringContainsString('EN 16931', $message);
        }
    }

    public function test_schema_invalid_document_follows_kosit_schematron_behaviour(): void
    {
        // IssueDate after InvoiceTypeCode is out of order; without BuyerReference the Schematron
        // would also report BR-DE-15, but KoSIT does not run it after an XML Schema failure.
        $xml = self::replaceOnce(
            '#(\s*<cbc:IssueDate>[^<]*</cbc:IssueDate>)(\s*<cbc:InvoiceTypeCode>[^<]*</cbc:InvoiceTypeCode>)#',
            '$2$1',
            self::read(self::VALID_UBL),
        );
        $xml = self::replaceOnce('#\s*<cbc:BuyerReference>[^<]*</cbc:BuyerReference>#', '', $xml);

        $report = self::validator()->validate($xml);

        self::assertSame('EN16931 XRechnung (UBL Invoice)', $report->scenario);
        self::assertNotSame([], $report->schemaErrors);
        self::assertSame([], $report->findings);
        self::assertSame([], $report->ruleSets);
        self::assertFalse($report->isValid());
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function plainEn16931(): iterable
    {
        $cen = __DIR__.'/../../fixtures/official/cen-1.3.16';
        yield 'ubl invoice' => [$cen.'/ubl/examples/ubl-tc434-example1.xml', 'EN16931 (UBL Invoice)', ['en16931-ubl 1.3.16']];
        yield 'ubl credit note' => [$cen.'/ubl/examples/ubl-tc434-creditnote1.xml', 'EN16931 (UBL CreditNote)', ['en16931-ubl 1.3.16']];
        yield 'cii' => [$cen.'/cii/examples/CII_example3.xml', 'EN16931 (CII)', ['en16931-cii 1.3.16']];
    }

    /**
     * @param  list<string>  $ruleSets
     */
    #[DataProvider('plainEn16931')]
    public function test_plain_en16931_invoice_uses_en16931_scenario(string $file, string $scenario, array $ruleSets): void
    {
        $report = self::validator()->validate(self::read($file));

        self::assertSame($scenario, $report->scenario);
        self::assertSame($ruleSets, $report->ruleSets);
    }

    public function test_doctype_is_refused(): void
    {
        $xml = self::replaceOnce('#^(<\?xml[^>]*\?>)#', '$1<!DOCTYPE Invoice [<!ENTITY x "y">]>', self::read(self::VALID_UBL));

        $this->expectException(UnsafeXml::class);

        self::validator()->validate($xml);
    }

    public function test_missing_scenario_table_names_the_composer_command(): void
    {
        $this->expectException(RulesNotInstalled::class);
        $this->expectExceptionMessage('composer require intragile/einvoicing-rules-xrechnung');

        new XRechnungValidator(__DIR__.'/missing/scenarios.php', RuleFiles::schemaDir(), []);
    }

    #[RunInSeparateProcess]
    public function test_missing_rules_package_names_the_composer_command(): void
    {
        self::hideXRechnungRulesPackage();

        $this->expectException(RulesNotInstalled::class);
        $this->expectExceptionMessage('composer require intragile/einvoicing-rules-xrechnung');

        XRechnungValidator::create();
    }

    #[RunInSeparateProcess]
    public function test_create_without_scenario_table_class_names_the_package(): void
    {
        self::hideXRechnungRulesPackage();

        try {
            XRechnungValidator::create();
            self::fail('create() must refuse without the XRechnung rules package.');
        } catch (RulesNotInstalled $exception) {
            self::assertStringContainsString('KoSIT XRechnung rules', $exception->getMessage());
            self::assertStringContainsString('intragile/einvoicing-rules-xrechnung', $exception->getMessage());
        }
    }

    /**
     * The table pins CEN 1.3.16; a rule map that hands it the 1.3.17 file must not run that file
     * under the 1.3.16 name.
     */
    public function test_rule_set_mismatch_names_both_versions(): void
    {
        $validator = new XRechnungValidator(RuleFiles::scenarios(), RuleFiles::schemaDir(), [
            'en16931-ubl 1.3.16' => En16931RuleFiles::ubl('1.3.17'),
            'en16931-cii 1.3.16' => En16931RuleFiles::cii('1.3.16'),
            'xrechnung-ubl '.RuleFiles::VERSION => RuleFiles::ubl(),
            'xrechnung-cii '.RuleFiles::VERSION => RuleFiles::cii(),
        ]);

        try {
            $validator->validate(self::read(self::VALID_UBL));
            self::fail('A rule file of another version must be refused.');
        } catch (RulesNotInstalled $exception) {
            self::assertStringContainsString('en16931-ubl 1.3.16', $exception->getMessage());
            self::assertStringContainsString('en16931-ubl 1.3.17', $exception->getMessage());
            self::assertStringContainsString('composer require intragile/einvoicing-rules-xrechnung', $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{Syntax}>
     */
    public static function syntaxes(): iterable
    {
        yield 'ubl' => [Syntax::Ubl];
        yield 'cii' => [Syntax::Cii];
    }

    #[DataProvider('syntaxes')]
    public function test_model_written_as_xrechnung_is_valid(Syntax $syntax): void
    {
        $invoice = TotalsCalculator::complete(self::germanDomesticInvoice());
        $xml = $syntax === Syntax::Ubl ? (new UblWriter)->write($invoice) : (new CiiWriter)->write($invoice);

        $report = self::validator()->validate($xml);

        self::assertSame($syntax === Syntax::Ubl ? 'EN16931 XRechnung (UBL Invoice)' : 'EN16931 XRechnung (CII)', $report->scenario);
        self::assertSame([], $report->schemaErrors, self::describe($report));
        self::assertSame([], $report->fatal(), self::describe($report));
        self::assertTrue($report->isValid(), self::describe($report));
    }

    public function test_testsuite_standard_instances_survive_read_write(): void
    {
        $failures = [];
        $files = glob(self::STANDARD.'/*.xml') ?: [];
        self::assertNotSame([], $files);
        foreach ($files as $file) {
            $original = self::read($file);
            $before = self::validator()->validate($original);
            if (str_ends_with($file, '_ubl.xml')) {
                $written = (new UblWriter)->write((new UblReader)->read($original)->invoice);
            } else {
                $written = (new CiiWriter)->write((new CiiReader)->read($original)->invoice);
            }
            $after = self::validator()->validate($written);

            $knownIds = array_map(static fn (Finding $finding): string => $finding->ruleId, $before->findings);
            $new = [];
            foreach ($after->schemaErrors as $error) {
                $new[] = 'XSD: '.trim($error->message);
            }
            foreach ($after->findings as $finding) {
                if (! in_array($finding->ruleId, $knownIds, true)) {
                    $new[] = "{$finding->ruleId} {$finding->severity->value}";
                }
            }
            if ($after->scenario !== $before->scenario) {
                $new[] = "scenario {$after->scenario}";
            }
            if ($new !== []) {
                $failures[] = basename($file).': '.implode(', ', array_unique($new));
            }
        }

        self::assertSame([], $failures, implode("\n", $failures));
    }

    private static function germanDomesticInvoice(): Invoice
    {
        return new Invoice(
            number: 'RE-2026-0815',
            issueDate: new DateTimeImmutable('2026-11-13'),
            type: InvoiceTypeCode::C_380,
            currency: CurrencyCode::EUR,
            seller: new Party(
                name: 'Muster Software GmbH',
                vatIdentifier: 'DE123456789',
                electronicAddress: new ElectronicAddress('rechnung@muster-software.example', ElectronicAddressScheme::EM),
                address: new PostalAddress(country: CountryCode::DE, line1: 'Lindenstraße 12', city: 'Berlin', postCode: '10115'),
                contact: new Contact(name: 'Erika Mustermann', phone: '+49 30 1234567', email: 'erika.mustermann@muster-software.example'),
            ),
            buyer: new Party(
                name: 'Beispiel Handel AG',
                electronicAddress: new ElectronicAddress('eingang@beispiel-handel.example', ElectronicAddressScheme::EM),
                address: new PostalAddress(country: CountryCode::DE, line1: 'Marktplatz 3', city: 'Köln', postCode: '50667'),
            ),
            lines: [
                new InvoiceLine(
                    id: '1',
                    quantity: '10',
                    unit: UnitCode::HUR,
                    price: new Price(netPrice: '95.00'),
                    item: new Item(name: 'Softwareentwicklung'),
                    vat: new LineVat(category: VatCategory::StandardRate, rate: '19'),
                ),
            ],
            dueDate: new DateTimeImmutable('2026-12-13'),
            buyerReference: '04011000-12345-34',
            paymentTerms: 'Zahlbar innerhalb von 30 Tagen ohne Abzug.',
            processControl: XRechnung::processControl(),
            payment: new PaymentInstructions(
                meansCode: PaymentMeansCode::C_58,
                remittanceInformation: 'RE-2026-0815',
                creditTransfers: [new CreditTransfer(accountIdentifier: 'DE75512108001245126199', accountName: 'Muster Software GmbH')],
            ),
        );
    }

    /**
     * Makes the classes of intragile/einvoicing-rules-xrechnung unloadable, as if the package were
     * not installed. Only for tests that run in a separate process.
     */
    private static function hideXRechnungRulesPackage(): void
    {
        self::assertFalse(class_exists(RuleFiles::class, false), 'The rules package is already loaded in this process.');
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $loader->setPsr4('IntrAgile\\EInvoicing\\Rules\\XRechnung\\', []);
        }
        self::assertFalse(class_exists(RuleFiles::class));
    }

    private static function validator(): XRechnungValidator
    {
        return self::$validator ??= XRechnungValidator::create();
    }

    private static function finding(ValidationReport $report, string $ruleId): Finding
    {
        foreach ($report->findings as $finding) {
            if ($finding->ruleId === $ruleId) {
                return $finding;
            }
        }
        self::fail("{$ruleId} did not fire; findings: ".implode(', ', $report->verdict()));
    }

    private static function describe(ValidationReport $report): string
    {
        $lines = array_map(static fn (Finding $finding): string => "{$finding->ruleId} {$finding->severity->value} at {$finding->location}: {$finding->message}", $report->findings);
        foreach ($report->schemaErrors as $error) {
            $lines[] = 'XSD: '.trim($error->message);
        }

        return implode("\n", $lines);
    }

    private static function withCustomizationId(string $xml, string $customizationId): string
    {
        return self::replaceOnce(
            '#<cbc:CustomizationID>[^<]*</cbc:CustomizationID>#',
            '<cbc:CustomizationID>'.htmlspecialchars($customizationId, ENT_XML1).'</cbc:CustomizationID>',
            $xml,
        );
    }

    private static function replaceOnce(string $pattern, string $replacement, string $subject): string
    {
        $count = 0;
        $result = preg_replace($pattern, $replacement, $subject, 1, $count);
        self::assertIsString($result);
        self::assertSame(1, $count, "The pattern {$pattern} does not match.");

        return $result;
    }

    private static function read(string $path): string
    {
        $xml = file_get_contents($path);
        self::assertIsString($xml);

        return $xml;
    }
}
