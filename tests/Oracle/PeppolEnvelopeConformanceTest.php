<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Oracle;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Validation\Peppol\InstalledRules;
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\Peppol\DocumentTypeId;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use IntrAgile\EInvoicing\Transport\Peppol\ProcessId;
use IntrAgile\EInvoicing\Transport\Sbdh\EnvelopeWriter;
use IntrAgile\EInvoicing\Validation\DynamicError;
use IntrAgile\EInvoicing\Validation\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Differential conformance for the Peppol envelope rules: Validator::peppolEnvelope() must give
 * exactly the verdict of OpenPeppol's PeppolBIS-Envelope-Rules.sch compiled by the ISO skeleton
 * and run by Saxon-HE, as sorted sets of "<rule id> <flag>" strings, with no dynamic error.
 *
 * The documents are the official envelope tests (rules/unit-SBDH) and envelopes written by
 * EnvelopeWriter for Peppol examples, some altered afterwards: another sender or receiver, letter
 * case, padding and self-billing. A mismatch is written to the git-ignored
 * build/conformance/peppol-envelope/<key>/ for inspection.
 */
#[Group('oracle')]
final class PeppolEnvelopeConformanceTest extends TestCase
{
    private const DUMP_DIR = __DIR__.'/../../build/conformance/peppol-envelope';

    private const SELF_BILLING_CUSTOMIZATION = 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:selfbilling:3.0';

    private const SELF_BILLING_PROFILE = 'urn:fdc:peppol.eu:2017:poacc:selfbilling:01:1.0';

    private const BILLING_CUSTOMIZATION = 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0';

    private const BILLING_PROFILE = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';

    /** The electronic addresses of the seller and the buyer in the Peppol base example. */
    private const SELLER = ['<cbc:EndpointID schemeID="0088">9482348239847239874</cbc:EndpointID>', '0088:9482348239847239874'];

    private const BUYER = ['<cbc:EndpointID schemeID="0002">FR23342</cbc:EndpointID>', '0002:FR23342'];

    private static ?Validator $validator = null;

    /**
     * @return array<string, array{string}>
     */
    public static function officialTests(): array
    {
        $cases = [];
        foreach (Fixtures::peppolEnvelopeTests() as $document) {
            $cases['unit '.$document->name()] = [$document->xml];
        }

        return $cases;
    }

    /**
     * Envelopes written by EnvelopeWriter, by label; the documents are built in the test.
     *
     * @return array<string, array{string}>
     */
    public static function writtenEnvelopes(): array
    {
        $labels = [
            'as written',
            'other receiver',
            'receiver in upper case',
            'other sender',
            'sender with another scheme',
            'padded endpoint in the invoice',
            'padded receiver in the envelope',
            'self-billing as written',
            'self-billing with swapped parties',
            'credit note with swapped parties',
        ];

        return array_combine(array_map(static fn (string $label): string => "written {$label}", $labels), array_map(static fn (string $label): array => [$label], $labels));
    }

    public function test_corpus_sizes(): void
    {
        self::assertCount(14, self::officialTests());
        self::assertCount(10, self::writtenEnvelopes());
    }

    #[DataProvider('officialTests')]
    public function test_official_envelope_tests(string $xml): void
    {
        $this->assertParity($xml);
    }

    #[DataProvider('writtenEnvelopes')]
    public function test_written_envelopes(string $label): void
    {
        $this->assertParity(self::written($label));
    }

    private static function written(string $label): string
    {
        $invoice = self::example('examples/base-example.xml');

        return match ($label) {
            'as written' => self::wrap($invoice),
            'other receiver' => self::swap(self::wrap($invoice), 'Receiver', self::BUYER[1], '0002:FR99999'),
            'receiver in upper case' => self::swap(
                self::wrap(self::replace($invoice, self::BUYER[0], '<cbc:EndpointID schemeID="0002">fr23342</cbc:EndpointID>')),
                'Receiver',
                '0002:fr23342',
                '0002:FR23342',
            ),
            'other sender' => self::swap(self::wrap($invoice), 'Sender', self::SELLER[1], '0088:9482348239847239875'),
            'sender with another scheme' => self::swap(self::wrap($invoice), 'Sender', self::SELLER[1], '0208:9482348239847239874'),
            // The writer trims the address; the rule compares normalize-space() of it.
            'padded endpoint in the invoice' => self::wrap(self::replace($invoice, self::BUYER[0], "<cbc:EndpointID schemeID=\"0002\">\n  FR23342  </cbc:EndpointID>")),
            // The rule compares the envelope identifier as written, without normalising it.
            'padded receiver in the envelope' => self::swap(self::wrap($invoice), 'Receiver', self::BUYER[1], ' 0002:FR23342 '),
            'self-billing as written' => self::wrapSelfBilling(self::selfBilling($invoice)),
            'self-billing with swapped parties' => self::swapParties(self::wrapSelfBilling(self::selfBilling($invoice)), self::SELLER[1], self::BUYER[1]),
            'credit note with swapped parties' => self::swapCreditNoteParties(),
            default => throw new RuntimeException("Unknown envelope \"{$label}\"."),
        };
    }

    private function assertParity(string $xml): void
    {
        $report = self::validator()->validate($xml);
        $ours = $report->verdict();
        $official = OfficialRules::verdict(OfficialRules::run(SchematronToXslt::compile(Fixtures::peppolSchematron('envelope')), $xml, false));

        if ($official === $ours && $report->dynamicErrors === []) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->dump($xml, implode("\n", $official), implode("\n", $ours));
        self::fail(sprintf(
            "Verdicts differ.\nOnly Saxon: %s\nOnly ours: %s\nOur dynamic errors: %s",
            implode(', ', array_diff($official, $ours)) ?: '-',
            implode(', ', array_diff($ours, $official)) ?: '-',
            implode('; ', array_map(static fn (DynamicError $error): string => "{$error->errorCode} {$error->ruleId} at {$error->location}: {$error->message}", $report->dynamicErrors)) ?: '-',
        ));
    }

    private static function validator(): Validator
    {
        return self::$validator ??= Validator::peppolEnvelope(InstalledRules::dir());
    }

    private static function wrap(string $xml): string
    {
        return (new EnvelopeWriter)->wrap(
            OutboundDocument::fromXml($xml, 'BE'),
            'f81d4fae-7dec-41d0-a765-00a0c91e6bf6',
            new DateTimeImmutable('2026-11-12T09:30:00Z'),
        );
    }

    /**
     * Envelopes a self-billing invoice the way the billing envelope is written: seller as sender,
     * buyer as receiver. OutboundDocument::fromXml() refuses self-billing, so the routing metadata
     * is given explicitly; the BR-SBDH-3/4 rules expect the roles the other way round.
     */
    private static function wrapSelfBilling(string $xml): string
    {
        return (new EnvelopeWriter)->wrap(
            new OutboundDocument(
                $xml,
                ParticipantId::parse(self::SELLER[1]),
                ParticipantId::parse(self::BUYER[1]),
                new DocumentTypeId(PeppolBis3::INVOICE),
                new ProcessId(self::SELF_BILLING_PROFILE),
                'BE',
            ),
            'f81d4fae-7dec-41d0-a765-00a0c91e6bf6',
            new DateTimeImmutable('2026-11-12T09:30:00Z'),
        );
    }

    private static function selfBilling(string $xml): string
    {
        return self::replace(
            self::replace($xml, '<cbc:CustomizationID>'.self::BILLING_CUSTOMIZATION.'</cbc:CustomizationID>', '<cbc:CustomizationID>'.self::SELF_BILLING_CUSTOMIZATION.'</cbc:CustomizationID>'),
            '<cbc:ProfileID>'.self::BILLING_PROFILE.'</cbc:ProfileID>',
            '<cbc:ProfileID>'.self::SELF_BILLING_PROFILE.'</cbc:ProfileID>',
        );
    }

    private static function swapCreditNoteParties(): string
    {
        $xml = self::example('examples/base-creditnote-correction.xml');
        $envelope = self::wrap($xml);
        $sender = self::identifier($envelope, 'Sender');
        $receiver = self::identifier($envelope, 'Receiver');

        return self::swapParties($envelope, $sender, $receiver);
    }

    private static function swapParties(string $envelope, string $sender, string $receiver): string
    {
        return self::swap(self::swap($envelope, 'Sender', $sender, $receiver), 'Receiver', $receiver, $sender);
    }

    private static function identifier(string $envelope, string $party): string
    {
        if (preg_match("~<{$party}><Identifier Authority=\"iso6523-actorid-upis\">([^<]*)</Identifier></{$party}>~", $envelope, $match) !== 1) {
            throw new RuntimeException("The envelope has no {$party} identifier.");
        }

        return $match[1];
    }

    private static function swap(string $envelope, string $party, string $from, string $to): string
    {
        $element = "<{$party}><Identifier Authority=\"iso6523-actorid-upis\">%s</Identifier></{$party}>";

        return self::replace($envelope, sprintf($element, $from), sprintf($element, $to));
    }

    private static function replace(string $xml, string $search, string $replacement): string
    {
        if (substr_count($xml, $search) !== 1) {
            throw new RuntimeException("Expected exactly one \"{$search}\" in the document.");
        }

        return str_replace($search, $replacement, $xml);
    }

    private static function example(string $name): string
    {
        return Fixtures::peppolExamples()[$name] ?? throw new RuntimeException("{$name} is not in the Peppol corpus.");
    }

    private function dump(string $xml, string $official, string $ours): void
    {
        $dir = self::DUMP_DIR.'/'.preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $this->dataName());
        if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            throw new RuntimeException("Cannot create {$dir}.");
        }
        file_put_contents($dir.'/document.xml', $xml);
        file_put_contents($dir.'/saxon.txt', $official."\n");
        file_put_contents($dir.'/ours.txt', $ours."\n");
    }
}
