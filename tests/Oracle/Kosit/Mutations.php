<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Oracle\Kosit;

use Closure;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use IntrAgile\EInvoicing\Tests\Fixtures;
use RuntimeException;

/**
 * Deterministic variants of five XRechnung documents, each changed in one way that a scenario
 * match, the XML Schema or a Schematron rule is sensitive to. They are compared with the KoSIT
 * validator like the corpus.
 *
 * The bases are KoSIT test suite instances (UBL Invoice, CII, Extension and CVD). The test suite
 * has no UBL CreditNote, so that base is the CreditNote of the XRechnung Schematron unit tests
 * that checks BR-DE-1 positively.
 *
 * dynamicErrors() adds two schema-valid documents on which a Schematron assert raises an XPath
 * dynamic error.
 *
 * Each mutation must change its base: an XPath that selects nothing throws instead of yielding an
 * unchanged copy.
 */
final class Mutations
{
    private const BASES = [
        'ubl-invoice' => 'testsuite-2026-08-31/instances/standard/01.01a-INVOICE_ubl.xml',
        'ubl-creditnote' => 'unit-2.6.0/ubl-cn/ubl-cn-br-de-1-test.xml',
        'cii' => 'testsuite-2026-08-31/instances/standard/01.01a-INVOICE_uncefact.xml',
        'extension' => 'testsuite-2026-08-31/instances/extension/04.01a-INVOICE_ubl.xml',
        'cvd' => 'testsuite-2026-08-31/instances/technical-cases/cvd/02.01a-cvd_INVOICE_ubl.xml',
    ];

    private const NAMESPACES = [
        'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
        'cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
        'rsm' => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
        'ram' => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
    ];

    /** Paths per syntax of the nodes the mutations change. */
    private const PATHS = [
        'ubl' => [
            'customization' => '/*/cbc:CustomizationID',
            'buyerReference' => '/*/cbc:BuyerReference',
            'sellerContact' => '/*/cac:AccountingSupplierParty/cac:Party/cac:Contact',
            'iban' => '(/*/cac:PaymentMeans/cac:PayeeFinancialAccount/cbc:ID)[1]',
            'dueDateAndTerms' => '/*/cbc:DueDate | /*/cac:PaymentMeans/cbc:PaymentDueDate | /*/cac:PaymentTerms',
            'moved' => '/*/cbc:IssueDate',
            'movedBefore' => '/*/cbc:ID',
            'amount' => '/*/cac:LegalMonetaryTotal/cbc:PayableAmount',
        ],
        'cii' => [
            'customization' => '/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID',
            'buyerReference' => '//ram:ApplicableHeaderTradeAgreement/ram:BuyerReference',
            'sellerContact' => '//ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:DefinedTradeContact',
            'iban' => '(//ram:SpecifiedTradeSettlementPaymentMeans/ram:PayeePartyCreditorFinancialAccount/ram:IBANID)[1]',
            'dueDateAndTerms' => '//ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms',
            'moved' => '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:TypeCode',
            'movedBefore' => '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:ID',
            'amount' => '//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:DuePayableAmount',
        ],
    ];

    private const PEPPOL_BIS = 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0';

    /**
     * Name ("<base>/<mutation>") => XML of every mutated document, sorted by name.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        $documents = [];
        foreach (array_keys(self::BASES) as $base) {
            $xml = self::base($base);
            foreach (self::mutations() as $mutation => $change) {
                $documents["{$base}/{$mutation}"] = self::apply($xml, $change, "{$base}/{$mutation}");
            }
        }
        ksort($documents, SORT_STRING);

        return $documents;
    }

    /**
     * Schema-valid documents on which one Schematron assert cannot be evaluated: a repeated
     * element reaches a cast or function that accepts at most one item (XPTY0004). In the CII
     * document the failing assert belongs to the CEN rules, in the UBL CreditNote to the
     * XRechnung rules, so each of the two Schematron steps of a scenario is hit once.
     *
     * @return array<string, string>
     */
    public static function dynamicErrors(): array
    {
        $repeat = static function (string $expression): Closure {
            return static function (DOMXPath $xpath) use ($expression): void {
                $node = self::one($xpath, $expression);
                $node->parentNode?->insertBefore($node->cloneNode(true), $node->nextSibling);
            };
        };

        return [
            'cii/line-total-amount-repeated' => self::apply(
                self::base('cii'),
                $repeat('//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:LineTotalAmount'),
                'cii/line-total-amount-repeated',
            ),
            'ubl-creditnote/invoice-period-repeated' => self::apply(
                self::base('ubl-creditnote'),
                $repeat('/*/cac:InvoicePeriod'),
                'ubl-creditnote/invoice-period-repeated',
            ),
        ];
    }

    /**
     * A test suite UBL invoice with an internal DOCTYPE subset declaring one entity, inserted after
     * the XML declaration: KoSIT must not accept it and the engine must refuse it as unsafe.
     */
    public static function withDoctype(): string
    {
        $xml = self::base('ubl-invoice');
        $declaration = (string) preg_replace('/^(<\?xml[^>]*\?>).*$/s', '$1', $xml);
        if ($declaration === $xml || ! str_starts_with($xml, '<?xml')) {
            throw new RuntimeException('The DOCTYPE base has no XML declaration.');
        }

        return $declaration."\n".'<!DOCTYPE Invoice [<!ENTITY seller "Seller">]>'.substr($xml, strlen($declaration));
    }

    private static function base(string $base): string
    {
        $name = self::BASES[$base];
        $path = Fixtures::xrechnungCorpus()[$name] ?? throw new RuntimeException("The mutation base {$name} is not in the corpus.");
        $xml = file_get_contents($path);
        if ($xml === false) {
            throw new RuntimeException("Cannot read the mutation base {$name}.");
        }

        return $xml;
    }

    /**
     * @return array<string, Closure(DOMXPath, string): void>
     */
    private static function mutations(): array
    {
        return [
            'customization-id-trailing-space' => static function (DOMXPath $xpath, string $syntax): void {
                $node = self::one($xpath, self::path($syntax, 'customization'));
                $node->textContent .= ' ';
            },
            'customization-id-xrechnung-2.3' => static function (DOMXPath $xpath, string $syntax): void {
                $node = self::one($xpath, self::path($syntax, 'customization'));
                $node->textContent = str_replace('xrechnung_3.0', 'xrechnung_2.3', $node->textContent);
            },
            'customization-id-peppol-bis' => static function (DOMXPath $xpath, string $syntax): void {
                self::one($xpath, self::path($syntax, 'customization'))->textContent = self::PEPPOL_BIS;
            },
            'buyer-reference-removed' => static function (DOMXPath $xpath, string $syntax): void {
                self::remove(self::select($xpath, self::path($syntax, 'buyerReference')));
            },
            'seller-contact-removed' => static function (DOMXPath $xpath, string $syntax): void {
                self::remove(self::select($xpath, self::path($syntax, 'sellerContact')));
            },
            'iban-check-digit-broken' => static function (DOMXPath $xpath, string $syntax): void {
                $node = self::one($xpath, self::path($syntax, 'iban'));
                $iban = $node->textContent;
                // The check digits are the third and fourth character of an IBAN.
                $digit = (int) $iban[3];
                $node->textContent = substr($iban, 0, 3).(($digit + 1) % 10).substr($iban, 4);
            },
            'due-date-and-payment-terms-removed' => static function (DOMXPath $xpath, string $syntax): void {
                self::remove(self::select($xpath, self::path($syntax, 'dueDateAndTerms')));
            },
            'element-out-of-schema-order' => static function (DOMXPath $xpath, string $syntax): void {
                $moved = self::one($xpath, self::path($syntax, 'moved'));
                $before = self::one($xpath, self::path($syntax, 'movedBefore'));
                $before->parentNode?->insertBefore($moved, $before);
            },
            'amount-not-a-number' => static function (DOMXPath $xpath, string $syntax): void {
                self::one($xpath, self::path($syntax, 'amount'))->textContent = 'abc';
            },
        ];
    }

    /**
     * Applies one change to a parsed copy of $xml and returns the serialised result. The result
     * is compared with the serialisation of the unchanged parse, not with the source text, which
     * re-serialisation alone already alters.
     *
     * @param  Closure(DOMXPath, string): void  $change  receives an XPath with the cbc, cac, rsm and
     *                                                   ram prefixes bound, and "ubl" or "cii"
     *
     * @throws RuntimeException when $xml is not well-formed or the change leaves it unchanged
     */
    public static function apply(string $xml, Closure $change, string $name): string
    {
        $document = new DOMDocument;
        $document->preserveWhiteSpace = true;
        if (! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException("The mutation base of {$name} is not well-formed.");
        }
        $xpath = new DOMXPath($document);
        foreach (self::NAMESPACES as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }
        $syntax = $document->documentElement?->namespaceURI === self::NAMESPACES['rsm'] ? 'cii' : 'ubl';

        $unchanged = $document->saveXML();

        $change($xpath, $syntax);

        $mutated = $document->saveXML();
        if ($mutated === false || $mutated === $unchanged) {
            throw new RuntimeException("The mutation {$name} did not change its base.");
        }

        return $mutated;
    }

    private static function path(string $syntax, string $key): string
    {
        return self::PATHS[$syntax][$key] ?? throw new RuntimeException("No mutation path {$key} for {$syntax}.");
    }

    private static function one(DOMXPath $xpath, string $expression): DOMElement
    {
        $nodes = self::select($xpath, $expression);
        if (count($nodes) !== 1) {
            throw new RuntimeException(sprintf('The mutation path %s selects %d nodes, expected 1.', $expression, count($nodes)));
        }

        return $nodes[0];
    }

    /**
     * @return non-empty-list<DOMElement>
     */
    private static function select(DOMXPath $xpath, string $expression): array
    {
        $result = $xpath->query($expression);
        $nodes = [];
        if ($result !== false) {
            foreach ($result as $node) {
                if ($node instanceof DOMElement) {
                    $nodes[] = $node;
                }
            }
        }
        if ($nodes === []) {
            throw new RuntimeException("The mutation path {$expression} selects nothing.");
        }

        return $nodes;
    }

    /**
     * @param  list<DOMNode>  $nodes
     */
    private static function remove(array $nodes): void
    {
        foreach ($nodes as $node) {
            $node->parentNode?->removeChild($node);
        }
    }
}
