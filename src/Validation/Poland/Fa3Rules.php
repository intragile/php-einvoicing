<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\Poland;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use IntrAgile\EInvoicing\Poland\KsefNumber;
use IntrAgile\EInvoicing\Schematron\LocationPath;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Format;
use IntrAgile\EInvoicing\Validation\Finding;
use IntrAgile\EInvoicing\Validation\Severity;

/**
 * The FA(3) rules that the XML Schema cannot express, taken from the Ministry of Finance FA(3)
 * information brochure ("Broszura informacyjna dotycząca struktury logicznej FA(3)", edition of
 * August 2026) and the KSeF API documentation.
 *
 * FA(3) publishes no rule codes, so the rules carry their own ids PL-FA3-01 to PL-FA3-21. Each
 * finding's location is the path of the node concerned and its test is the source the rule is
 * taken from. The rules work on any DOM, including one that fails the schema: a missing or
 * malformed field makes the rules that need it stay silent rather than fail.
 */
final class Fa3Rules
{
    public const ID = 'pl-fa3';

    public const VERSION = '2026-08';

    private const BROCHURE = 'Broszura FA(3) 2026-08, ';

    private const NAMESPACES = ['fa' => Fa3Format::NS];

    private const FA = '/fa:Faktura/fa:Fa';

    /** Corrective invoice kinds (RodzajFaktury). */
    private const CORRECTIONS = ['KOR', 'KOR_ZAL', 'KOR_ROZ'];

    /** Kinds that must list their rows (FaWiersz). */
    private const WITH_ROWS = ['VAT', 'ROZ', 'UPR'];

    /** Elements of Fa that belong to a corrective invoice only. */
    private const CORRECTION_ELEMENTS = [
        'PrzyczynaKorekty', 'TypKorekty', 'DaneFaKorygowanej', 'OkresFaKorygowanej', 'NrFaKorygowany',
        'Podmiot1K', 'Podmiot2K', 'P_15ZK', 'KursWalutyZK',
    ];

    /** The total of each rate code (P_12) that has one: rates 4 % and 3 % have no total in FA(3). */
    private const BUCKETS = [
        '23' => 'P_13_1', '22' => 'P_13_1',
        '8' => 'P_13_2', '7' => 'P_13_2',
        '5' => 'P_13_3',
        '0 KR' => 'P_13_6_1', '0 WDT' => 'P_13_6_2', '0 EX' => 'P_13_6_3',
        'zw' => 'P_13_7',
        'np I' => 'P_13_8', 'np II' => 'P_13_9',
        'oo' => 'P_13_10',
    ];

    /** Split payment is mandatory above this gross amount in PLN. */
    private const SPLIT_PAYMENT_THRESHOLD = '15000';

    private function __construct() {}

    /**
     * Every rule by id, in id order.
     *
     * @return array<string, array{severity: Severity, source: string, message: string}>
     */
    public static function rules(): array
    {
        $fatal = Severity::Fatal;
        $warning = Severity::Warning;

        return [
            'PL-FA3-01' => self::rule($fatal, 'pp. 53, 67', 'A corrective invoice (KOR, KOR_ZAL, KOR_ROZ) must give the data of the corrected invoice (DaneFaKorygowanej).'),
            'PL-FA3-02' => self::rule($fatal, 'pp. 67-68', 'Correction data (PrzyczynaKorekty, TypKorekty, DaneFaKorygowanej, OkresFaKorygowanej, NrFaKorygowany, Podmiot1K, Podmiot2K, P_15ZK, KursWalutyZK) belongs to corrective invoices only.'),
            'PL-FA3-03' => self::rule($warning, 'p. 68', 'The amount before the correction (P_15ZK) belongs to corrections of advance or settlement invoices (KOR_ZAL, KOR_ROZ), and the exchange rate before the correction (KursWalutyZK) to corrections of advance invoices (KOR_ZAL).'),
            'PL-FA3-04' => self::rule($fatal, 'p. 99', 'The state-before marker (StanPrzed) belongs to rows of corrective invoices only.'),
            'PL-FA3-05' => self::rule($fatal, 'p. 86', 'A basic, settlement or simplified invoice (VAT, ROZ, UPR) must list its rows (FaWiersz).'),
            'PL-FA3-06' => self::rule($warning, 'pp. 86, 67', 'A corrective invoice without rows (FaWiersz) is a discount for a period and should name that period (OkresFaKorygowanej).'),
            'PL-FA3-07' => self::rule($fatal, 'pp. 79, 4', 'An advance invoice (ZAL) must give the order or contract (Zamowienie).'),
            'PL-FA3-08' => self::rule($warning, 'pp. 85, 54', 'A settlement invoice (ROZ) should name the advance invoices it settles (FakturaZaliczkowa), unless no advance invoice was issued (art. 106b ust. 1a).'),
            'PL-FA3-09' => self::rule($fatal, 'pp. 47-49', 'An invoice in a foreign currency must give the VAT of each rate total in PLN (P_14_1W to P_14_4W).'),
            'PL-FA3-10' => self::rule($warning, 'pp. 47-49', 'The VAT in PLN of a rate total (P_14_1W to P_14_4W) is given only on invoices in a foreign currency.'),
            'PL-FA3-11' => self::rule($fatal, 'p. 105', 'The total of charges (SumaObciazen) and of deductions (SumaOdliczen) must equal the sum of their amounts (Kwota).'),
            'PL-FA3-12' => self::rule($fatal, 'pp. 105-107', 'The amount due (DoZaplaty) or overpaid (DoRozliczenia) must equal P_15 plus the charges minus the deductions, as DoZaplaty when that balance is not negative and as DoRozliczenia (its absolute value) when it is.'),
            'PL-FA3-13' => self::rule($fatal, 'p. 17', 'A buyer marked as a subordinate unit of a local government (JST = 1) or a VAT group member (GV = 1) needs a third party (Podmiot3) in role 8 or 10 respectively.'),
            'PL-FA3-14' => self::rule($warning, 'p. 55', 'On a self-billed invoice (P_17 = 1) the buyer is not repeated as a third party (Podmiot3) in role 5 (issuer).'),
            'PL-FA3-15' => ['severity' => $fatal, 'source' => 'ksef-api faktury/numer-ksef.md', 'message' => 'A KSeF number (NrKSeFFaKorygowanej, NrKSeFFaZaliczkowej) must be 35 characters with a valid CRC-8 checksum.'],
            'PL-FA3-16' => self::rule($warning, 'pp. 45, 88', 'A common delivery date (P_6) and per-row dates (P_6A) exclude each other.'),
            'PL-FA3-17' => self::rule($fatal, 'pp. 46-52, 92', 'On a basic, settlement or simplified invoice (VAT, ROZ, UPR) each row rate (P_12) needs its total (P_13_x).'),
            'PL-FA3-18' => self::rule($fatal, 'pp. 51, 57', 'Exempt sales (P_13_7) need the exemption annotation (Zwolnienie/P_19 = 1).'),
            'PL-FA3-19' => self::rule($fatal, 'p. 55', 'A domestic reverse-charge row (P_12 = oo) needs the reverse-charge annotation (P_18 = 1).'),
            'PL-FA3-20' => self::rule($fatal, 'pp. 52, 63', 'Margin-scheme sales (P_13_11) need the margin annotation (PMarzy/P_PMarzy = 1).'),
            'PL-FA3-21' => self::rule($warning, 'pp. 55-56', 'The split payment annotation (P_18A = 1) applies only above 15,000 PLN gross (P_15).'),
        ];
    }

    /**
     * Checks one FA(3) invoice.
     *
     * @return list<Finding> in rule order, then document order
     */
    public static function check(DOMDocument $document): array
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('fa', Fa3Format::NS);
        $fa = self::first($xpath, self::FA);
        if ($fa === null) {
            return [];
        }

        $kindElement = self::first($xpath, 'fa:RodzajFaktury', $fa);
        $kind = $kindElement === null ? null : trim($kindElement->textContent);
        $at = $kindElement ?? $fa;
        $isCorrection = in_array($kind, self::CORRECTIONS, true);
        $hasRows = self::first($xpath, 'fa:FaWiersz', $fa) !== null;
        $findings = [];
        $report = static function (string $id, DOMNode $node, string $detail = '') use (&$findings): void {
            $findings[] = self::finding($id, $node, $detail);
        };

        // PL-FA3-01
        if ($isCorrection && self::first($xpath, 'fa:DaneFaKorygowanej', $fa) === null) {
            $report('PL-FA3-01', $at, "RodzajFaktury is {$kind}.");
        }

        // PL-FA3-02
        if ($kind !== null && ! $isCorrection) {
            foreach (self::CORRECTION_ELEMENTS as $name) {
                foreach (self::all($xpath, "fa:{$name}", $fa) as $element) {
                    $report('PL-FA3-02', $element, "RodzajFaktury is {$kind}.");
                }
            }
        }

        // PL-FA3-03: on non-corrective kinds PL-FA3-02 already reports these fields.
        if ($isCorrection) {
            foreach (self::all($xpath, 'fa:P_15ZK', $fa) as $element) {
                if (! in_array($kind, ['KOR_ZAL', 'KOR_ROZ'], true)) {
                    $report('PL-FA3-03', $element, "RodzajFaktury is {$kind}.");
                }
            }
            foreach (self::all($xpath, 'fa:KursWalutyZK', $fa) as $element) {
                if ($kind !== 'KOR_ZAL') {
                    $report('PL-FA3-03', $element, "RodzajFaktury is {$kind}.");
                }
            }
        }

        // PL-FA3-04
        if ($kind !== null && ! $isCorrection) {
            foreach (self::all($xpath, 'fa:FaWiersz/fa:StanPrzed', $fa) as $element) {
                $report('PL-FA3-04', $element, "RodzajFaktury is {$kind}.");
            }
        }

        // PL-FA3-05
        if (in_array($kind, self::WITH_ROWS, true) && ! $hasRows) {
            $report('PL-FA3-05', $at, "RodzajFaktury is {$kind}.");
        }

        // PL-FA3-06
        if ($kind === 'KOR' && ! $hasRows && self::first($xpath, 'fa:OkresFaKorygowanej', $fa) === null) {
            $report('PL-FA3-06', $at);
        }

        // PL-FA3-07
        if ($kind === 'ZAL' && self::first($xpath, 'fa:Zamowienie', $fa) === null) {
            $report('PL-FA3-07', $at);
        }

        // PL-FA3-08
        if ($kind === 'ROZ' && self::first($xpath, 'fa:FakturaZaliczkowa', $fa) === null) {
            $report('PL-FA3-08', $at);
        }

        // PL-FA3-09 and PL-FA3-10
        $currencyElement = self::first($xpath, 'fa:KodWaluty', $fa);
        $currency = $currencyElement === null ? null : trim($currencyElement->textContent);
        if ($currency !== null && $currency !== 'PLN') {
            foreach (range(1, 4) as $n) {
                $vat = self::first($xpath, "fa:P_14_{$n}", $fa);
                if ($vat !== null && self::first($xpath, "fa:P_14_{$n}W", $fa) === null) {
                    $report('PL-FA3-09', $vat, "KodWaluty is {$currency}; P_14_{$n}W is missing.");
                }
            }
        }
        if ($currency === 'PLN') {
            foreach (range(1, 4) as $n) {
                foreach (self::all($xpath, "fa:P_14_{$n}W", $fa) as $element) {
                    $report('PL-FA3-10', $element);
                }
            }
        }

        // PL-FA3-11 and PL-FA3-12
        $settlement = self::first($xpath, 'fa:Rozliczenie', $fa);
        if ($settlement !== null) {
            $charges = self::sum($xpath, 'fa:Obciazenia/fa:Kwota', $settlement);
            $deductions = self::sum($xpath, 'fa:Odliczenia/fa:Kwota', $settlement);
            foreach (['SumaObciazen' => $charges, 'SumaOdliczen' => $deductions] as $name => $sum) {
                $total = self::first($xpath, "fa:{$name}", $settlement);
                $value = self::decimal($total);
                if ($total !== null && $value !== null && $sum !== null && ! $value->isEqualTo($sum)) {
                    $report('PL-FA3-11', $total, "{$name} is {$value}; the amounts add up to {$sum}.");
                }
            }

            $gross = self::decimal(self::first($xpath, 'fa:P_15', $fa));
            $due = self::first($xpath, 'fa:DoZaplaty', $settlement);
            $overpaid = self::first($xpath, 'fa:DoRozliczenia', $settlement);
            $given = $due ?? $overpaid;
            $givenValue = self::decimal($given);
            if ($given !== null && $givenValue !== null && $gross !== null && $charges !== null && $deductions !== null) {
                $balance = $gross->plus($charges)->minus($deductions);
                $expected = $balance->isNegative() ? 'DoRozliczenia '.$balance->negated() : 'DoZaplaty '.$balance;
                $matches = $due !== null
                    ? ! $balance->isNegative() && $givenValue->isEqualTo($balance)
                    : ! $balance->isPositive() && $givenValue->isEqualTo($balance->negated());
                if (! $matches) {
                    $report('PL-FA3-12', $given, "P_15 {$gross} + {$charges} - {$deductions} = {$balance}, so expected {$expected}.");
                }
            }
        }

        // PL-FA3-13
        foreach (['JST' => '8', 'GV' => '10'] as $marker => $role) {
            foreach (self::all($xpath, "/fa:Faktura/fa:Podmiot2/fa:{$marker}") as $element) {
                if (trim($element->textContent) === '1'
                    && self::first($xpath, "/fa:Faktura/fa:Podmiot3[normalize-space(fa:Rola) = '{$role}']") === null) {
                    $report('PL-FA3-13', $element, "{$marker} is 1 and no Podmiot3 has Rola {$role}.");
                }
            }
        }

        // PL-FA3-14
        if (self::flag($xpath, 'fa:Adnotacje/fa:P_17', $fa)) {
            foreach (self::all($xpath, "/fa:Faktura/fa:Podmiot3/fa:Rola[normalize-space(.) = '5']") as $element) {
                $report('PL-FA3-14', $element);
            }
        }

        // PL-FA3-15
        foreach (self::all($xpath, 'fa:DaneFaKorygowanej/fa:NrKSeFFaKorygowanej | fa:FakturaZaliczkowa/fa:NrKSeFFaZaliczkowej', $fa) as $element) {
            if (! KsefNumber::isValid(trim($element->textContent))) {
                $report('PL-FA3-15', $element, '"'.trim($element->textContent).'" is not a valid KSeF number.');
            }
        }

        // PL-FA3-16
        $commonDate = self::first($xpath, 'fa:P_6', $fa);
        if ($commonDate !== null && self::first($xpath, 'fa:FaWiersz/fa:P_6A', $fa) !== null) {
            $report('PL-FA3-16', $commonDate);
        }

        // PL-FA3-17
        if (in_array($kind, self::WITH_ROWS, true)) {
            foreach (self::all($xpath, 'fa:FaWiersz/fa:P_12', $fa) as $element) {
                $code = trim($element->textContent);
                $bucket = self::BUCKETS[$code] ?? null;
                if ($bucket !== null && self::first($xpath, "fa:{$bucket}", $fa) === null) {
                    $report('PL-FA3-17', $element, "P_12 {$code} needs {$bucket}.");
                }
            }
        }

        // PL-FA3-18
        $exempt = self::first($xpath, 'fa:P_13_7', $fa);
        if ($exempt !== null && ! self::flag($xpath, 'fa:Adnotacje/fa:Zwolnienie/fa:P_19', $fa)) {
            $report('PL-FA3-18', $exempt);
        }

        // PL-FA3-19
        if (! self::flag($xpath, 'fa:Adnotacje/fa:P_18', $fa)) {
            foreach (self::all($xpath, "fa:FaWiersz/fa:P_12[normalize-space(.) = 'oo']", $fa) as $element) {
                $report('PL-FA3-19', $element);
            }
        }

        // PL-FA3-20
        $margin = self::first($xpath, 'fa:P_13_11', $fa);
        if ($margin !== null && ! self::flag($xpath, 'fa:Adnotacje/fa:PMarzy/fa:P_PMarzy', $fa)) {
            $report('PL-FA3-20', $margin);
        }

        // PL-FA3-21
        $splitPayment = self::first($xpath, 'fa:Adnotacje/fa:P_18A', $fa);
        $gross = self::decimal(self::first($xpath, 'fa:P_15', $fa));
        if ($splitPayment !== null && trim($splitPayment->textContent) === '1' && $currency === 'PLN'
            && $gross !== null && $gross->isLessThanOrEqualTo(self::SPLIT_PAYMENT_THRESHOLD)) {
            $report('PL-FA3-21', $splitPayment, "P_15 is {$gross}.");
        }

        return $findings;
    }

    /**
     * @return array{severity: Severity, source: string, message: string}
     */
    private static function rule(Severity $severity, string $pages, string $message): array
    {
        return ['severity' => $severity, 'source' => self::BROCHURE.$pages, 'message' => $message];
    }

    private static function finding(string $id, DOMNode $node, string $detail): Finding
    {
        $rule = self::rules()[$id];

        return new Finding(
            $id,
            $rule['severity'],
            LocationPath::of($node, self::NAMESPACES),
            $detail === '' ? $rule['message'] : $rule['message'].' '.$detail,
            $rule['source'],
        );
    }

    private static function first(DOMXPath $xpath, string $expression, ?DOMNode $context = null): ?DOMElement
    {
        return self::all($xpath, $expression, $context)[0] ?? null;
    }

    /**
     * @return list<DOMElement>
     */
    private static function all(DOMXPath $xpath, string $expression, ?DOMNode $context = null): array
    {
        $nodes = $xpath->query($expression, $context);
        $elements = [];
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if ($node instanceof DOMElement) {
                    $elements[] = $node;
                }
            }
        }

        return $elements;
    }

    /**
     * True when the element exists and holds "1".
     */
    private static function flag(DOMXPath $xpath, string $expression, DOMNode $context): bool
    {
        $element = self::first($xpath, $expression, $context);

        return $element !== null && trim($element->textContent) === '1';
    }

    /**
     * The element's value as a decimal; null when the element is absent or not a plain decimal.
     */
    private static function decimal(?DOMElement $element): ?BigDecimal
    {
        if ($element === null) {
            return null;
        }
        $text = trim($element->textContent);
        if (preg_match('/\A-?\d+(\.\d+)?\z/', $text) !== 1) {
            return null;
        }
        try {
            return BigDecimal::of($text);
        } catch (MathException) {
            return null;
        }
    }

    /**
     * The sum of the selected amounts; null when one of them is not a plain decimal.
     */
    private static function sum(DOMXPath $xpath, string $expression, DOMNode $context): ?BigDecimal
    {
        $sum = BigDecimal::zero();
        foreach (self::all($xpath, $expression, $context) as $element) {
            $value = self::decimal($element);
            if ($value === null) {
                return null;
            }
            $sum = $sum->plus($value);
        }

        return $sum;
    }
}
