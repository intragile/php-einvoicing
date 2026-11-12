<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\XPath;

/**
 * The shared truth table for XPath evaluation: ProbeTest checks the PHP evaluator against each
 * expected canonical result, ProbeOracleTest checks Saxon-HE against the same value. Every
 * expression is evaluated with the document node of its XML as the context item.
 */
final class ProbeCases
{
    public const NAMESPACES = [
        'ubl' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
        'cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
        'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
        'rsm' => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
        'ram' => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
        'udt' => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
    ];

    private const UBL = '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"'
        .' xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"'
        .' xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">'
        .'<cbc:ID>INV-1</cbc:ID><cbc:IssueDate>2026-11-11</cbc:IssueDate>'
        .'<cac:PaymentMeans><cbc:PaymentMeansCode>30</cbc:PaymentMeansCode></cac:PaymentMeans>'
        .'<cac:PaymentMeans><cbc:PaymentMeansCode>30</cbc:PaymentMeansCode></cac:PaymentMeans>'
        .'<cac:InvoiceLine><cbc:ID>1</cbc:ID><cbc:LineExtensionAmount currencyID="EUR">100.00</cbc:LineExtensionAmount>'
        .'<cac:Item><cac:ClassifiedTaxCategory><cbc:ID>S</cbc:ID><cbc:Percent>25</cbc:Percent></cac:ClassifiedTaxCategory></cac:Item></cac:InvoiceLine>'
        .'<cac:InvoiceLine><cbc:ID>2</cbc:ID><cbc:LineExtensionAmount currencyID="EUR">50.5</cbc:LineExtensionAmount>'
        .'<cac:Item><cac:ClassifiedTaxCategory><cbc:ID>S</cbc:ID><cbc:Percent>25</cbc:Percent><cbc:Percent>12</cbc:Percent></cac:ClassifiedTaxCategory></cac:Item></cac:InvoiceLine>'
        .'</Invoice>';

    /** The CBC URI as the default namespace of the child elements. */
    private const UBL_DEFAULT = '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">'
        .'<ID xmlns="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">D-1</ID></Invoice>';

    /** An unrelated prefix bound to the CBC URI. */
    private const UBL_X = '<ubl:Invoice xmlns:ubl="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"'
        .' xmlns:x="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"><x:ID>X-1</x:ID></ubl:Invoice>';

    private const CII = '<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"'
        .' xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100"'
        .' xmlns:udt="urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100">'
        .'<ram:SpecifiedTradeAllowanceCharge><ram:ChargeIndicator><udt:Indicator> true </udt:Indicator></ram:ChargeIndicator><ram:ActualAmount>5</ram:ActualAmount><ram:ActualAmount>7</ram:ActualAmount></ram:SpecifiedTradeAllowanceCharge>'
        .'<ram:SpecifiedTradeAllowanceCharge><ram:ChargeIndicator><udt:Indicator>0</udt:Indicator></ram:ChargeIndicator><ram:ActualAmount>3</ram:ActualAmount></ram:SpecifiedTradeAllowanceCharge>'
        .'<ram:SpecifiedTradeAllowanceCharge><ram:ChargeIndicator><udt:Indicator>yes</udt:Indicator></ram:ChargeIndicator></ram:SpecifiedTradeAllowanceCharge>'
        .'</rsm:CrossIndustryInvoice>';

    /** Untyped values in no namespace. */
    private const PLAIN = '<r><a>abc</a><a>1</a><b>1</b><b>abc</b><n9>9</n9><n10>10</n10><d>0.1</d><inf>+INF</inf><t>true</t><f>false</f><s> a&#9;&#10;b </s></r>';

    /**
     * @return array<string, array{string, string, string}> name => [expression, xml, expected canonical result]
     */
    public static function all(): array
    {
        $u = self::UBL;
        $p = self::PLAIN;
        $cenDate = '^\s*(\d{4})(1[0-2]|0[1-9]){1}(3[01]|[12][0-9]|0[1-9]){1}\s*$';

        return [
            // fn:not, fn:exists, fn:empty, fn:boolean, fn:true, fn:false
            'not empty' => ['not(())', $u, '(xs:boolean:true)'],
            'not nodes' => ['not(//cbc:ID)', $u, '(xs:boolean:false)'],
            'not empty string' => ["not('')", $u, '(xs:boolean:true)'],
            'not two atomics' => ['not((1, 2))', $u, 'error:FORG0006'],
            'exists nodes' => ['exists(//cbc:Percent)', $u, '(xs:boolean:true)'],
            'exists empty' => ['exists(//cbc:Nothing)', $u, '(xs:boolean:false)'],
            'empty empty' => ['empty(())', $u, '(xs:boolean:true)'],
            'empty nodes' => ['empty(//cbc:ID)', $u, '(xs:boolean:false)'],
            'boolean nodes' => ['boolean(//cbc:ID)', $u, '(xs:boolean:true)'],
            'boolean two strings' => ["boolean(('a', 'b'))", $u, 'error:FORG0006'],
            'boolean zero' => ['boolean(0)', $u, '(xs:boolean:false)'],
            'true false' => ['(true(), false())', $u, '(xs:boolean:true, xs:boolean:false)'],

            // fn:normalize-space
            'normalize-space spaces' => ["normalize-space('  a   b ')", $u, '(xs:string:a b)'],
            'normalize-space xml whitespace' => ['normalize-space(/r/s)', $p, '(xs:string:a b)'],
            'normalize-space keeps nbsp' => ["normalize-space('\u{A0}S\u{A0}')", $u, "(xs:string:\u{A0}S\u{A0})"],
            'normalize-space empty' => ['normalize-space(())', $u, '(xs:string:)'],
            'normalize-space two nodes' => ['normalize-space(//cbc:ID)', $u, 'error:XPTY0004'],
            'normalize-space context' => ['/*/cbc:ID/normalize-space()', $u, '(xs:string:INV-1)'],

            // xs:decimal
            // Saxon casts an integral lexical form of at most 15 characters to an xs:integer (a subtype of xs:decimal).
            'decimal one node' => ['xs:decimal(//cac:InvoiceLine[1]//cbc:Percent)', $u, '(xs:integer:25)'],
            'decimal short integral' => ["xs:decimal('-77777777777777')", $u, '(xs:integer:-77777777777777)'],
            'decimal short integral plus' => ["xs:decimal('+777777777777777')", $u, '(xs:integer:777777777777777)'],
            'decimal long integral' => ["xs:decimal('-777777777777777')", $u, '(xs:decimal:-777777777777777)'],
            'decimal leading zeros' => ["xs:decimal('0000000000000007')", $u, '(xs:decimal:7)'],
            'decimal trailing point' => ["xs:decimal('25.')", $u, '(xs:decimal:25)'],
            'cast as decimal short integral' => ["'25' cast as xs:decimal", $u, '(xs:integer:25)'],
            'decimal of decimal' => ['xs:decimal(25.0)', $u, '(xs:decimal:25)'],
            'decimal of boolean' => ['xs:decimal(true())', $u, '(xs:decimal:1)'],
            'decimal empty' => ['xs:decimal(())', $u, '()'],
            'decimal repeated singleton' => ['xs:decimal(//cac:InvoiceLine[2]//cbc:Percent)', $u, 'error:XPTY0004'],
            'decimal comma' => ["xs:decimal('12,50')", $u, 'error:FORG0001'],
            'decimal exponent' => ["xs:decimal('1e3')", $u, 'error:FORG0001'],
            'decimal whitespace' => ["xs:decimal(' 1.50 ')", $u, '(xs:decimal:1.5)'],
            'decimal of double' => ['xs:decimal(0.1e0)', $u, '(xs:decimal:0.1000000000000000055511151231257827021181583404541015625)'],
            'decimal of double exact' => ['xs:decimal(3.3e0)', $u, '(xs:decimal:3.29999999999999982236431605997495353221893310546875)'],
            'decimal of large double' => ['xs:decimal(1e20)', $u, '(xs:decimal:100000000000000000000)'],
            'decimal of infinity' => ['xs:decimal(1 div 0e0)', $u, 'error:FOCA0002'],

            // fn:upper-case
            'upper-case vat' => ["upper-case('vat')", $u, '(xs:string:VAT)'],
            'upper-case sharp s' => ["upper-case('ß')", $u, '(xs:string:SS)'],
            'upper-case empty' => ['upper-case(())', $u, '(xs:string:)'],
            'upper-case two nodes' => ['upper-case(//cbc:ID)', $u, 'error:XPTY0004'],

            // fn:count
            'count all elements' => ['count(//*)', $u, '(xs:integer:22)'],
            'count empty' => ['count(())', $u, '(xs:integer:0)'],
            'count parents deduplicated' => ['count(//cbc:ID/..)', $u, '(xs:integer:5)'],

            // fn:sum
            'sum empty' => ['sum(())', $u, '(xs:integer:0)'],
            'sum untyped is double' => ['sum(//cbc:LineExtensionAmount)', $u, '(xs:double:150.5)'],
            'sum decimals' => ['sum(//cac:InvoiceLine/xs:decimal(cbc:LineExtensionAmount))', $u, '(xs:decimal:150.5)'],
            'sum integers' => ['sum((1, 2))', $u, '(xs:integer:3)'],
            'sum mixed numerics' => ['sum((1, 2.5))', $u, '(xs:decimal:3.5)'],
            'sum string' => ["sum(('a', 1))", $u, 'error:FORG0006'],
            'sum zero argument' => ['sum((), ())', $u, '()'],

            // fn:contains, fn:ends-with, fn:substring-before, fn:substring-after
            'contains' => ["contains('abc', 'b')", $u, '(xs:boolean:true)'],
            'contains empty input' => ["contains((), '')", $u, '(xs:boolean:true)'],
            'contains empty search' => ["contains('abc', ())", $u, '(xs:boolean:true)'],
            'contains two nodes' => ["contains(//cbc:ID, 'x')", $u, 'error:XPTY0004'],
            'ends-with' => ["ends-with('abc', 'bc')", $u, '(xs:boolean:true)'],
            'ends-with empty' => ["ends-with((), 'a')", $u, '(xs:boolean:false)'],
            'substring-after' => ["substring-after('a.b.c', '.')", $u, '(xs:string:b.c)'],
            'substring-after empty' => ["substring-after((), 'a')", $u, '(xs:string:)'],
            'substring-before' => ["substring-before('a.b', '.')", $u, '(xs:string:a)'],
            'substring-before missing' => ["substring-before('abc', 'x')", $u, '(xs:string:)'],
            'substring-before node' => ["substring-before(/*/cbc:ID, '-')", $u, '(xs:string:INV)'],

            // fn:string-length, fn:substring
            'string-length code points' => ["string-length('😀a')", $u, '(xs:integer:2)'],
            'string-length empty' => ['string-length(())', $u, '(xs:integer:0)'],
            'string-length context' => ['/*/cbc:ID/string-length()', $u, '(xs:integer:5)'],
            'string-length integer' => ['string-length(12)', $u, 'error:XPTY0004'],
            'substring rounding' => ["substring('12345', 1.5, 2.6)", $u, '(xs:string:234)'],
            'substring zero start' => ["substring('12345', 0, 3)", $u, '(xs:string:12)'],
            'substring no length' => ["substring('12345', 2)", $u, '(xs:string:2345)'],
            'substring code points' => ["substring('😀abc', 2, 2)", $u, '(xs:string:ab)'],
            'substring infinite length' => ["substring('12345', -42, 1 div 0e0)", $u, '(xs:string:12345)'],
            'substring NaN start' => ["substring('12345', 0 div 0e0, 3)", $u, '(xs:string:)'],
            'substring empty' => ['substring((), 1)', $u, '(xs:string:)'],
            'substring untyped start' => ["substring('12345', /r/b[1])", $p, '(xs:string:12345)'],

            // fn:round
            'round half up' => ['round(2.5)', $u, '(xs:decimal:3)'],
            'round negative half' => ['round(-2.5)', $u, '(xs:decimal:-2)'],
            'round negative half decimal' => ["round(xs:decimal('-0.5'))", $u, '(xs:decimal:0)'],
            'round large double' => ['round(1.0e20)', $u, '(xs:double:1.0E20)'],
            'round double' => ['round(2.5e0)', $u, '(xs:double:3)'],
            'round negative half double' => ['round(-0.5e0)', $u, '(xs:double:-0)'],
            'round integer' => ['round(3)', $u, '(xs:integer:3)'],
            'round empty' => ['round(())', $u, '()'],
            'round untyped is double' => ['round(//cac:InvoiceLine[1]//cbc:Percent)', $u, '(xs:double:25)'],
            'round just below half' => ['round(0.49999999999999994e0)', $u, '(xs:double:0)'],
            'round negative small' => ['round(-0.4e0)', $u, '(xs:double:-0)'],
            'round negative half double odd' => ['round(-1.5e0)', $u, '(xs:double:-1)'],
            'round two nodes' => ['round(//cbc:Percent)', $u, 'error:XPTY0004'],

            // fn:concat, fn:abs
            'concat atomics' => ["concat('a', 1, (), 2.50)", $u, '(xs:string:a12.5)'],
            'concat two nodes' => ["concat('a', //cbc:Percent)", $u, 'error:XPTY0004'],
            'abs decimal' => ['abs(-2.5)', $u, '(xs:decimal:2.5)'],
            'abs integer' => ['abs(-3)', $u, '(xs:integer:3)'],
            'abs empty' => ['abs(())', $u, '()'],
            'abs untyped is double' => ['abs(//cac:InvoiceLine[1]//cbc:Percent)', $u, '(xs:double:25)'],

            // xs:date
            'date' => ["xs:date('2026-11-11')", $u, '(xs:date:2026-11-11)'],
            'date invalid month' => ["xs:date('2026-13-01')", $u, 'error:FORG0001'],
            'date empty' => ['xs:date(())', $u, '()'],
            'date comparison' => ['xs:date(/*/cbc:IssueDate) > xs:date(\'2026-11-10\')', $u, '(xs:boolean:true)'],
            'date implicit timezone' => ["xs:date('2026-11-11') = xs:date('2026-11-11Z')", $u, '(xs:boolean:true)'],
            'date other timezone' => ["xs:date('2026-11-11+01:00') = xs:date('2026-11-11')", $u, '(xs:boolean:false)'],
            'untyped against date' => ["/*/cbc:IssueDate = xs:date('2026-11-11')", $u, '(xs:boolean:true)'],

            // fn:name, fn:local-name
            'name prefixed' => ['/*/cbc:ID/name()', $u, '(xs:string:cbc:ID)'],
            'name of root' => ['name(/*)', $u, '(xs:string:Invoice)'],
            'name of attribute' => ['//cac:InvoiceLine[1]/cbc:LineExtensionAmount/@currencyID/name()', $u, '(xs:string:currencyID)'],
            'name empty' => ['name(())', $u, '(xs:string:)'],
            'name two nodes' => ['name(//cbc:ID)', $u, 'error:XPTY0004'],
            'local-name' => ['local-name(/*/cbc:ID)', $u, '(xs:string:ID)'],
            'name other prefix' => ['name(/*/cbc:ID)', self::UBL_X, '(xs:string:x:ID)'],

            // fn:distinct-values
            'distinct-values untyped' => ['distinct-values(//cbc:Percent)', $u, '(xs:untypedAtomic:25, xs:untypedAtomic:12)'],
            'distinct-values numerics' => ["distinct-values((1, 1.0, 1e0, 'a', 'a'))", $u, '(xs:integer:1, xs:string:a)'],
            'distinct-values NaN' => ['distinct-values((0 div 0e0, 0 div 0e0))', $u, '(xs:double:NaN)'],
            'distinct-values empty' => ['distinct-values(())', $u, '()'],

            // fn:matches
            'matches CEN date' => ["matches('20261111', '{$cenDate}')", $u, '(xs:boolean:true)'],
            'matches CEN date invalid' => ["matches('20261311', '{$cenDate}')", $u, '(xs:boolean:false)'],
            'matches case-insensitive' => ["matches('abc', 'B', 'i')", $u, '(xs:boolean:true)'],
            'matches unanchored' => ["matches('abc', 'b')", $u, '(xs:boolean:true)'],
            'matches word excludes underscore' => ["matches('_', '^\\w$')", $u, '(xs:boolean:false)'],
            'matches empty input' => ["matches((), 'a')", $u, '(xs:boolean:false)'],
            'matches multiline' => ["matches(/r/s, '^b', 'm')", $p, '(xs:boolean:true)'],
            'matches dollar is end' => ["matches(/r/s, 'b \$')", $p, '(xs:boolean:true)'],

            // fn:string
            'string of decimal' => ['string(1.0)', $u, '(xs:string:1)'],
            'string empty' => ['string(())', $u, '(xs:string:)'],
            'string context' => ['/*/cbc:ID/string()', $u, '(xs:string:INV-1)'],
            'string two nodes' => ['string(//cbc:ID)', $u, 'error:XPTY0004'],

            // double to string
            'double 1e6' => ['string(1e6)', $u, '(xs:string:1.0E6)'],
            'double below 1e6' => ['string(999999e0)', $u, '(xs:string:999999)'],
            'double 1e-6' => ['string(0.000001e0)', $u, '(xs:string:0.000001)'],
            'double below 1e-6' => ['string(1.5e-7)', $u, '(xs:string:1.5E-7)'],
            'double sum' => ['string(0.1e0 + 0.2e0)', $u, '(xs:string:0.30000000000000004)'],
            'double smallest' => ['string(4.9e-324)', $u, '(xs:string:4.9E-324)'],
            'double negative zero' => ['string(-0e0)', $u, '(xs:string:-0)'],
            'double infinity' => ['string(-1 div 0e0)', $u, '(xs:string:-INF)'],
            'double 1e21' => ['string(1e21)', $u, '(xs:string:1.0E21)'],
            'double above 1e6' => ['string(1234567.0e0)', $u, '(xs:string:1.234567E6)'],
            'double below 1e-6 digits' => ['string(9.999999e-7)', $u, '(xs:string:9.999999E-7)'],
            'double 17 digits' => ['string(123456789012345678e0)', $u, '(xs:string:1.2345678901234568E17)'],
            'double largest' => ['string(1.7976931348623157e308)', $u, '(xs:string:1.7976931348623157E308)'],
            'double untyped plus infinity' => ['/r/inf + 0', self::PLAIN, '(xs:double:INF)'],

            // decimal division and arithmetic
            'div one third' => ['1 div 3', $u, '(xs:decimal:0.333333333333333333)'],
            'div two thirds' => ['2 div 3', $u, '(xs:decimal:0.666666666666666667)'],
            'div negative two thirds' => ['-2 div 3', $u, '(xs:decimal:-0.666666666666666667)'],
            'div exact' => ['10 div 4', $u, '(xs:decimal:2.5)'],
            'div long scale' => ['1.0000000000000000005 div 1', $u, '(xs:decimal:1.0000000000000000005)'],
            'div by zero' => ['1 div 0', $u, 'error:FOAR0001'],
            // Saxon divides decimals at scale max(18, scale(a) - scale(b) + 18) with trailing zeros stripped, rounding HALF_DOWN.
            'div dividend scale' => ['0.000001 div 3', $u, '(xs:decimal:0.000000333333333333333333)'],
            'div trailing zeros stripped' => ['1.00000000000000000000 div 3', $u, '(xs:decimal:0.333333333333333333)'],
            'div divisor scale' => ['1 div 0.3', $u, '(xs:decimal:3.333333333333333333)'],
            'div integral decimal divisor' => ['1 div 300.0', $u, '(xs:decimal:0.00333333333333333333)'],
            'div integer divisor' => ['1 div 300', $u, '(xs:decimal:0.003333333333333333)'],
            'div integer beyond 32 bits' => ['1 div 3000000000', $u, '(xs:decimal:0.000000000333333333333333333)'],
            'div integer at 32 bits' => ['1 div 2147483650', $u, '(xs:decimal:0.0000000004656612869)'],
            'div computed decimal' => ['(1.5 * 1.5) div 7', $u, '(xs:decimal:0.32142857142857142857)'],
            'div half down' => ['1 div 524288', $u, '(xs:decimal:0.000001907348632812)'],
            'div half down odd' => ['3 div 524288', $u, '(xs:decimal:0.000005722045898437)'],
            'div half down negative' => ['-3 div 524288', $u, '(xs:decimal:-0.000005722045898437)'],
            'div terminating' => ['0.000000000000000003 div 2', $u, '(xs:decimal:0.0000000000000000015)'],
            'double div by zero' => ['1 div 0e0', $u, '(xs:double:INF)'],
            'decimal times double' => ["xs:decimal('1') * 0.1e0", $u, '(xs:double:0.1)'],

            // integer division and modulus; Saxon raises FOAR0001 for idiv by a double zero and returns NaN for a double mod by zero
            'idiv integers' => ['7 idiv 2', $u, '(xs:integer:3)'],
            'idiv negative dividend' => ['-7 idiv 2', $u, '(xs:integer:-3)'],
            'idiv negative divisor' => ['7 idiv -2', $u, '(xs:integer:-3)'],
            'idiv decimal' => ['7.5 idiv 2', $u, '(xs:integer:3)'],
            'idiv by zero' => ['1 idiv 0', $u, 'error:FOAR0001'],
            'idiv double by zero' => ['1 idiv 0e0', $u, 'error:FOAR0001'],
            'idiv double' => ['-7.5e0 idiv 2', $u, '(xs:integer:-3)'],
            'idiv double NaN' => ['(0 div 0e0) idiv 2', $u, 'error:FOAR0002'],
            'idiv double infinite dividend' => ['(1 div 0e0) idiv 2', $u, 'error:FOAR0002'],
            'idiv double infinite divisor' => ['5e0 idiv (1 div 0e0)', $u, '(xs:integer:0)'],
            'idiv untyped' => ['/r/n10 idiv 3', $p, '(xs:integer:3)'],
            'mod integers' => ['7 mod 2', $u, '(xs:integer:1)'],
            'mod negative dividend' => ['-7 mod 2', $u, '(xs:integer:-1)'],
            'mod negative divisor' => ['7 mod -2', $u, '(xs:integer:1)'],
            'mod decimal' => ['7.5 mod 2', $u, '(xs:decimal:1.5)'],
            'mod negative decimal' => ['-7.5 mod 2', $u, '(xs:decimal:-1.5)'],
            'mod by zero' => ['5 mod 0', $u, 'error:FOAR0001'],
            'mod decimal by zero' => ['5.5 mod 0.0', $u, 'error:FOAR0001'],
            'mod double by zero' => ['5e0 mod 0', $u, '(xs:double:NaN)'],
            'mod double' => ['-7.5e0 mod 2', $u, '(xs:double:-1.5)'],
            'mod double infinite divisor' => ['5e0 mod (1 div 0e0)', $u, '(xs:double:5)'],
            'mod untyped' => ['/r/n10 mod 3', $p, '(xs:double:1)'],
            'mod string' => ["'10' mod 3", $u, 'error:XPTY0004'],

            // type label of a cast to xs:decimal; Saxon labels a cast from xs:integer xs:decimal, from a short integral string xs:integer
            'decimal of integer literal' => ['xs:decimal(5)', $u, '(xs:decimal:5)'],
            // Stands in for xs:decimal(xs:integer('5')) until the xs:integer constructor exists: the operand is a computed xs:integer.
            'decimal of computed integer' => ['xs:decimal(2 + 3)', $u, '(xs:decimal:5)'],
            'decimal of integral string' => ["xs:decimal('5')", $u, '(xs:integer:5)'],
            'decimal of integral string div' => ["xs:decimal('5') div 3", $u, '(xs:decimal:1.666666666666666667)'],
            'decimal of integer literal div' => ['xs:decimal(5) div 3', $u, '(xs:decimal:1.666666666666666667)'],
            'integer arithmetic' => ['5 - 7', $u, '(xs:integer:-2)'],
            'decimal product' => ['2 * 3.0', $u, '(xs:decimal:6)'],
            'arithmetic empty' => ['1 + ()', $u, '()'],
            // Saxon raises a static type error: xs:string is not promoted in arithmetic (only untyped values are cast).
            'string plus integer' => ["'abc' + 1", $u, 'error:XPTY0004'],
            'untyped plus integer invalid' => ['/*/cbc:ID + 1', $u, 'error:FORG0001'],
            'untyped plus integer' => ['/r/n9 + 1', $p, '(xs:double:10)'],
            'negate double zero' => ['-(0e0)', $u, '(xs:double:-0)'],
            'negate integer zero' => ['-(0)', $u, '(xs:integer:0)'],
            'negate untyped' => ['-(//cac:InvoiceLine[1]/cbc:LineExtensionAmount)', $u, '(xs:double:-100)'],
            'arithmetic two nodes' => ['//cbc:Percent + 1', $u, 'error:XPTY0004'],

            // comparisons
            'general existential' => ['//cbc:Percent = 12', $u, '(xs:boolean:true)'],
            'general not equal' => ['//cbc:Percent != 25', $u, '(xs:boolean:true)'],
            'general untyped decimal' => ['//cbc:LineExtensionAmount = 50.5', $u, '(xs:boolean:true)'],
            'general untyped double' => ['/r/d = 0.1', $p, '(xs:boolean:true)'],
            'general untyped promoted to double' => ['/r/d = 0.1000000000000000055511151231257827021181583404541015625', $p, '(xs:boolean:true)'],
            'general untyped against double' => ['/r/d = 0.1e0', $p, '(xs:boolean:true)'],
            'arithmetic untyped plus decimal is double' => ['/r/d + 0.2', $p, '(xs:double:0.30000000000000004)'],
            'general untyped strings' => ['/r/n9 < /r/n10', $p, '(xs:boolean:false)'],
            'general error before true' => ['/r/a = 1', $p, 'error:FORG0001'],
            'general true before error' => ['/r/b = 1', $p, '(xs:boolean:true)'],
            'general boolean string' => ["true() = 'abc'", $u, 'error:XPTY0004'],
            'general boolean untyped' => ['true() = /*/cbc:ID', $u, 'error:FORG0001'],
            'general NaN' => ['(0 div 0e0) = (0 div 0e0)', $u, '(xs:boolean:false)'],
            'value comparison' => ['1 eq 1.0', $u, '(xs:boolean:true)'],
            'value comparison empty' => ['() eq 1', $u, '()'],
            'value comparison two items' => ['(1, 2) eq 1', $u, 'error:XPTY0004'],
            'value comparison untyped string' => ["/*/cbc:ID eq 'INV-1'", $u, '(xs:boolean:true)'],
            'attribute comparison' => ["//cbc:LineExtensionAmount/@currencyID = 'EUR'", $u, '(xs:boolean:true)'],

            // cast as
            'cast boolean true' => ["'true' cast as xs:boolean", $u, '(xs:boolean:true)'],
            'cast boolean untyped whitespace' => ['//ram:SpecifiedTradeAllowanceCharge[1]/ram:ChargeIndicator/udt:Indicator cast as xs:boolean', self::CII, '(xs:boolean:true)'],
            'cast boolean zero' => ['(//ram:SpecifiedTradeAllowanceCharge[2]/ram:ChargeIndicator/udt:Indicator cast as xs:boolean) = true()', self::CII, '(xs:boolean:false)'],
            'cast boolean invalid' => ['//ram:SpecifiedTradeAllowanceCharge[3]/ram:ChargeIndicator/udt:Indicator cast as xs:boolean', self::CII, 'error:FORG0001'],
            'cast boolean integer' => ['1 cast as xs:boolean', $u, '(xs:boolean:true)'],
            'cast boolean two nodes' => ['//udt:Indicator cast as xs:boolean', self::CII, 'error:XPTY0004'],
            'cast optional empty' => ['//cbc:Nothing cast as xs:decimal?', $u, '()'],
            'cast required empty' => ['//cbc:Nothing cast as xs:decimal', $u, 'error:XPTY0004'],
            'cast date' => ["'2026-11-11' cast as xs:date", $u, '(xs:date:2026-11-11)'],

            // logic and quantifiers
            'and two atomics' => ['(1, 2) and true()', $u, 'error:FORG0006'],
            'or short-circuit' => ["true() or xs:decimal('x')", $u, '(xs:boolean:true)'],
            'and short-circuit' => ["false() and xs:decimal('x')", $u, '(xs:boolean:false)'],
            'every empty' => ['every $x in () satisfies false()', $u, '(xs:boolean:true)'],
            'some' => ['some $x in (1, 2) satisfies $x = 2', $u, '(xs:boolean:true)'],
            'every lines' => ['every $l in //cac:InvoiceLine satisfies exists($l/cbc:ID)', $u, '(xs:boolean:true)'],
            'every two bindings' => ['every $a in (1, 2), $b in ($a, 3) satisfies $b >= $a', $u, '(xs:boolean:true)'],

            // conditionals: only the chosen branch is evaluated
            'if untaken error branch' => ["if (true()) then 1 else xs:decimal('x')", $u, '(xs:integer:1)'],
            'if else branch' => ["if (//cbc:Nothing) then xs:decimal('x') else 'no'", $u, '(xs:string:no)'],
            'if condition two atomics' => ['if ((1, 2)) then 1 else 2', $u, 'error:FORG0006'],
            'if else if' => ['if (false()) then 1 else if (exists(//cbc:ID)) then 2 else 3', $u, '(xs:integer:2)'],
            'if returns nodes' => ['if (true()) then //cac:InvoiceLine/cbc:ID else ()', $u, '(node:/Invoice[1]/cac:InvoiceLine[1]/cbc:ID[1], node:/Invoice[1]/cac:InvoiceLine[2]/cbc:ID[1])'],

            // for: results in iteration order, never re-sorted
            'for atomics' => ['for $i in (1, 2, 3) return $i * 2', $u, '(xs:integer:2, xs:integer:4, xs:integer:6)'],
            'for nodes' => ['for $l in //cac:InvoiceLine return string($l/cbc:ID)', $u, '(xs:string:1, xs:string:2)'],
            'for keeps iteration order' => ['for $l in (//cac:InvoiceLine[2], //cac:InvoiceLine[1]) return $l/cbc:ID', $u, '(node:/Invoice[1]/cac:InvoiceLine[2]/cbc:ID[1], node:/Invoice[1]/cac:InvoiceLine[1]/cbc:ID[1])'],
            'for two bindings' => ['for $a in (1, 2), $b in (10, 20) return $a + $b', $u, '(xs:integer:11, xs:integer:21, xs:integer:12, xs:integer:22)'],
            'for dependent binding' => ['for $a in (1, 2), $b in ($a to 2) return $b', $u, '(xs:integer:1, xs:integer:2, xs:integer:2)'],
            'for empty' => ['for $x in () return 1', $u, '()'],
            'for mixed result' => ['for $x in (1, /*/cbc:ID) return $x', $u, '(xs:integer:1, node:/Invoice[1]/cbc:ID[1])'],

            // ranges
            'range' => ['1 to 3', $u, '(xs:integer:1, xs:integer:2, xs:integer:3)'],
            'range descending is empty' => ['3 to 1', $u, '()'],
            'range empty operand' => ['() to 3', $u, '()'],
            'range single' => ['2 to 2', $u, '(xs:integer:2)'],
            'range negative' => ['-1 to 0', $u, '(xs:integer:-1, xs:integer:0)'],
            'range sum' => ['sum(1 to 4)', $u, '(xs:integer:10)'],
            'range untyped' => ['/r/b[1] to 3', $p, '(xs:integer:1, xs:integer:2, xs:integer:3)'],
            'range untyped invalid' => ['/r/a[1] to 3', $p, 'error:FORG0001'],
            'range decimal operand' => ['1.5 to 3', $u, 'error:XPTY0004'],
            'range string operand' => ["'1' to 3", $u, 'error:XPTY0004'],
            'range two items' => ['(1, 2) to 3', $u, 'error:XPTY0004'],
            'range beyond 64 bits' => ['count(99999999999999999999 to 100000000000000000001)', $u, '(xs:integer:3)'],

            // castable as
            'castable string integer' => ["'2' castable as xs:integer", $u, '(xs:boolean:true)'],
            'castable untyped integer' => ['/r/b[1] castable as xs:integer', $p, '(xs:boolean:true)'],
            'castable untyped invalid' => ['/r/a[1] castable as xs:integer', $p, '(xs:boolean:false)'],
            'castable decimal lexical as integer' => ["'1.0' castable as xs:integer", $u, '(xs:boolean:false)'],
            'castable decimal value as integer' => ['1.5 castable as xs:integer', $u, '(xs:boolean:true)'],
            'castable decimal invalid' => ["'abc' castable as xs:decimal", $u, '(xs:boolean:false)'],
            'castable date invalid day' => ["'2026-02-30' castable as xs:date", $u, '(xs:boolean:false)'],
            'castable date' => ["'2026-02-28' castable as xs:date", $u, '(xs:boolean:true)'],
            'castable boolean invalid' => ["'yes' castable as xs:boolean", $u, '(xs:boolean:false)'],
            'castable date as integer' => ["xs:date('2026-01-01') castable as xs:integer", $u, '(xs:boolean:false)'],
            'castable infinity as integer' => ['(1 div 0e0) castable as xs:integer', $u, '(xs:boolean:false)'],
            'castable empty' => ['//cbc:Nothing castable as xs:integer', $u, '(xs:boolean:false)'],
            'castable empty allowed' => ['//cbc:Nothing castable as xs:integer?', $u, '(xs:boolean:true)'],
            'castable string' => ['(//cbc:Percent)[1] castable as xs:string', $u, '(xs:boolean:true)'],
            // Saxon answers false, not XPTY0004, when the operand atomizes to more than one item.
            'castable two items' => ['(1, 2) castable as xs:integer', $u, '(xs:boolean:false)'],
            'castable two nodes' => ['//cbc:Percent castable as xs:integer', $u, '(xs:boolean:false)'],

            // cast as xs:integer and xs:string
            'cast integer whitespace' => ["' 7 ' cast as xs:integer", $u, '(xs:integer:7)'],
            'cast integer plus sign' => ["'+7' cast as xs:integer", $u, '(xs:integer:7)'],
            'cast integer decimal lexical' => ["'1.0' cast as xs:integer", $u, 'error:FORG0001'],
            'cast integer truncates decimal' => ['1.9 cast as xs:integer', $u, '(xs:integer:1)'],
            'cast integer truncates negative decimal' => ['-1.9 cast as xs:integer', $u, '(xs:integer:-1)'],
            'cast integer truncates double' => ['1.9e0 cast as xs:integer', $u, '(xs:integer:1)'],
            'cast integer boolean' => ['true() cast as xs:integer', $u, '(xs:integer:1)'],
            'cast integer untyped' => ['/r/n10 cast as xs:integer', $p, '(xs:integer:10)'],
            'cast integer infinity' => ['(1 div 0e0) cast as xs:integer', $u, 'error:FOCA0002'],
            'cast integer date' => ["xs:date('2026-01-01') cast as xs:integer", $u, 'error:XPTY0004'],
            'cast string decimal' => ['1.0 cast as xs:string', $u, '(xs:string:1)'],
            'cast string computed decimal' => ["xs:decimal('1.50') cast as xs:string", $u, '(xs:string:1.5)'],
            'cast string double' => ['1e6 cast as xs:string', $u, '(xs:string:1.0E6)'],
            'cast string untyped' => ['/r/d cast as xs:string', $p, '(xs:string:0.1)'],
            'cast string date' => ["xs:date('2026-01-01') cast as xs:string", $u, '(xs:string:2026-01-01)'],

            // errors in "and" / "or": Saxon raises an operand's error only when the other operand
            // does not decide the result, and when both fail it raises the right operand's error
            'and error decided by right' => ["(normalize-space(/r/a) = 'x') and not(/r/b)", $p, '(xs:boolean:false)'],
            'and error decided by left' => ["not(/r/b) and (normalize-space(/r/a) = 'x')", $p, '(xs:boolean:false)'],
            'and error undecided' => ["(normalize-space(/r/a) = 'x') and exists(/r/b)", $p, 'error:XPTY0004'],
            'and right error undecided' => ['exists(/r/b) and (xs:decimal(/r/a[1]) = 1)', $p, 'error:FORG0001'],
            'and both errors' => ["(xs:decimal(/r/a[1]) = 1) and (normalize-space(/r/a) = 'x')", $p, 'error:XPTY0004'],
            'and both errors reversed' => ["(normalize-space(/r/a) = 'x') and (xs:decimal(/r/a[1]) = 1)", $p, 'error:FORG0001'],
            'or error decided by right' => ['(xs:decimal(/r/a[1]) = 1) or exists(/r/b)', $p, '(xs:boolean:true)'],
            'or error undecided' => ['(xs:decimal(/r/a[1]) = 1) or empty(/r/b)', $p, 'error:FORG0001'],
            'or both errors' => ["(normalize-space(/r/a) = 'x') or (xs:decimal(/r/a[1]) = 1)", $p, 'error:FORG0001'],
            'or literal true decides' => ["xs:decimal('x') = 1 or true()", $u, '(xs:boolean:true)'],
            'or literal false keeps error' => ["xs:decimal('x') = 1 or false()", $u, 'error:FORG0001'],
            'predicate error decided' => ["count(/r[(normalize-space(a) = 'x' or normalize-space(a) = 'y') and not(b)])", $p, '(xs:integer:0)'],

            // paths and axes
            'root' => ['/', $u, '(node:/)'],
            'context' => ['.', $u, '(node:/)'],
            'child elements' => ['/*/cbc:IssueDate', $u, '(node:/Invoice[1]/cbc:IssueDate[1])'],
            'descendant same names' => ['//cac:InvoiceLine/cbc:ID', $u, '(node:/Invoice[1]/cac:InvoiceLine[1]/cbc:ID[1], node:/Invoice[1]/cac:InvoiceLine[2]/cbc:ID[1])'],
            'text node' => ['/*/cbc:ID/text()', $u, '(node:/Invoice[1]/cbc:ID[1]/text()[1])'],
            'attribute' => ['//cac:InvoiceLine[2]/cbc:LineExtensionAmount/@currencyID', $u, '(node:/Invoice[1]/cac:InvoiceLine[2]/cbc:LineExtensionAmount[1]/@currencyID)'],
            'parent of parent from attribute' => ['//cac:InvoiceLine[1]/cbc:LineExtensionAmount/@currencyID/../..', $u, '(node:/Invoice[1]/cac:InvoiceLine[1])'],
            'ancestor' => ['//cbc:Percent/ancestor::cac:InvoiceLine', $u, '(node:/Invoice[1]/cac:InvoiceLine[1], node:/Invoice[1]/cac:InvoiceLine[2])'],
            'preceding compare' => ['//cbc:PaymentMeansCode/(preceding::cbc:PaymentMeansCode/. = .)', $u, '(xs:boolean:false, xs:boolean:true)'],
            'union document order' => ['/*/cbc:IssueDate | /*/cbc:ID | /*/cbc:IssueDate', $u, '(node:/Invoice[1]/cbc:ID[1], node:/Invoice[1]/cbc:IssueDate[1])'],
            'union step' => ['//(cbc:IssueDate | cbc:Percent)', $u, '(node:/Invoice[1]/cbc:IssueDate[1], node:/Invoice[1]/cac:InvoiceLine[1]/cac:Item[1]/cac:ClassifiedTaxCategory[1]/cbc:Percent[1], node:/Invoice[1]/cac:InvoiceLine[2]/cac:Item[1]/cac:ClassifiedTaxCategory[1]/cbc:Percent[1], node:/Invoice[1]/cac:InvoiceLine[2]/cac:Item[1]/cac:ClassifiedTaxCategory[1]/cbc:Percent[2])'],
            'union atomics' => ['(1, 2) | //cbc:ID', $u, 'error:XPTY0004'],
            'mixed path result' => ['//cac:InvoiceLine/(cbc:ID, xs:decimal(cbc:LineExtensionAmount))', $u, 'error:XPTY0018'],
            'path over atomics' => ['(1, 2)/cbc:ID', $u, 'error:XPTY0019'],
            'default namespace' => ['/*/cbc:ID', self::UBL_DEFAULT, '(node:/Invoice[1]/ID[1])'],
            'other prefix' => ['/ubl:Invoice/cbc:ID', self::UBL_X, '(node:/ubl:Invoice[1]/x:ID[1])'],

            // predicates
            'boolean predicate' => ['//cac:InvoiceLine[cbc:ID = 2]/cbc:ID', $u, '(node:/Invoice[1]/cac:InvoiceLine[2]/cbc:ID[1])'],
            'numeric predicate on step' => ['//cbc:Percent[2]', $u, '(node:/Invoice[1]/cac:InvoiceLine[2]/cac:Item[1]/cac:ClassifiedTaxCategory[1]/cbc:Percent[2])'],
            'numeric predicate on filter' => ['(//cbc:Percent)[2]', $u, '(node:/Invoice[1]/cac:InvoiceLine[2]/cac:Item[1]/cac:ClassifiedTaxCategory[1]/cbc:Percent[1])'],
            'first of each parent' => ['//ram:ActualAmount[1]', self::CII, '(node:/rsm:CrossIndustryInvoice[1]/ram:SpecifiedTradeAllowanceCharge[1]/ram:ActualAmount[1], node:/rsm:CrossIndustryInvoice[1]/ram:SpecifiedTradeAllowanceCharge[2]/ram:ActualAmount[1])'],
            'first of all' => ['(//ram:ActualAmount)[1]/xs:decimal(.)', self::CII, '(xs:integer:5)'],
            'ancestor counts backwards' => ['//cbc:Percent[2]/ancestor::*[1]', $u, '(node:/Invoice[1]/cac:InvoiceLine[2]/cac:Item[1]/cac:ClassifiedTaxCategory[1])'],
            'ancestor filter in document order' => ['(//cbc:Percent[2]/ancestor::*)[1]', $u, '(node:/Invoice[1])'],
            'preceding counts backwards' => ['//cac:PaymentMeans[2]/cbc:PaymentMeansCode/preceding::*[1]', $u, '(node:/Invoice[1]/cac:PaymentMeans[1]/cbc:PaymentMeansCode[1])'],
            'preceding second' => ['//cac:PaymentMeans[2]/cbc:PaymentMeansCode/preceding::*[2]', $u, '(node:/Invoice[1]/cac:PaymentMeans[1])'],
            'following-sibling first' => ['/*/cac:PaymentMeans[1]/following-sibling::*[1]', $u, '(node:/Invoice[1]/cac:PaymentMeans[2])'],
            'following-sibling named' => ['/*/cbc:ID/following-sibling::cac:InvoiceLine', $u, '(node:/Invoice[1]/cac:InvoiceLine[1], node:/Invoice[1]/cac:InvoiceLine[2])'],
            'following-sibling last has none' => ['/*/cac:InvoiceLine[2]/following-sibling::*', $u, '()'],
            'preceding-sibling nearest' => ['/*/cac:InvoiceLine[2]/preceding-sibling::*[1]', $u, '(node:/Invoice[1]/cac:InvoiceLine[1])'],
            'preceding-sibling second' => ['/*/cac:InvoiceLine[2]/preceding-sibling::*[2]', $u, '(node:/Invoice[1]/cac:PaymentMeans[2])'],
            'preceding-sibling filter in document order' => ['(/*/cac:InvoiceLine[2]/preceding-sibling::*)[1]', $u, '(node:/Invoice[1]/cbc:ID[1])'],
            'preceding-sibling named' => ['/*/cac:InvoiceLine[1]/preceding-sibling::cac:PaymentMeans', $u, '(node:/Invoice[1]/cac:PaymentMeans[1], node:/Invoice[1]/cac:PaymentMeans[2])'],
            'sibling of an attribute' => ['//cac:InvoiceLine[1]/cbc:LineExtensionAmount/@currencyID/following-sibling::node()', $u, '()'],
            'sibling of the root element' => ['/*/preceding-sibling::node()', $u, '()'],
            'sibling compare' => ['//cbc:PaymentMeansCode/(../preceding-sibling::cac:PaymentMeans/cbc:PaymentMeansCode = .)', $u, '(xs:boolean:false, xs:boolean:true)'],
            'atomic filter numeric' => ['(10, 20, 30)[2]', $u, '(xs:integer:20)'],
            'atomic filter boolean' => ['(1, 2, 3)[. > 1]', $u, '(xs:integer:2, xs:integer:3)'],
            'predicate out of range' => ['(1, 2)[3]', $u, '()'],
            'decimal predicate' => ['(1, 2)[1.5]', $u, '()'],

            // CEN expression shapes
            'expression step' => ['//cac:InvoiceLine/xs:decimal(cbc:LineExtensionAmount)', $u, '(xs:decimal:100, xs:decimal:50.5)'],
            'expression step arithmetic' => ['//cac:InvoiceLine[1]/xs:decimal(cbc:LineExtensionAmount - 1)', $u, '(xs:decimal:99)'],
            'rounded total' => ['round(sum(//cac:InvoiceLine/xs:decimal(cbc:LineExtensionAmount)) * 10 * 10) div 100', $u, '(xs:decimal:150.5)'],
            'empty expression step' => ['//cbc:Nothing/xs:decimal(.)', $u, '()'],
        ];
    }
}
