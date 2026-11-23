<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Spain\Verifactu;

use DateTimeImmutable;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\Spain\ExemptOperation;
use IntrAgile\EInvoicing\Spain\ForeignIdType;
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\OperationQualification;
use IntrAgile\EInvoicing\Spain\RegimeKey;
use IntrAgile\EInvoicing\Spain\TaxType;
use IntrAgile\EInvoicing\Spain\Verifactu\Amount;
use IntrAgile\EInvoicing\Spain\Verifactu\ChainLink;
use IntrAgile\EInvoicing\Spain\Verifactu\ComputerSystem;
use IntrAgile\EInvoicing\Spain\Verifactu\CorrectionAmounts;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\ForeignId;
use IntrAgile\EInvoicing\Spain\Verifactu\InvoiceId;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxBreakdownLine;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxParty;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxRate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ValueObjectsTest extends TestCase
{
    private function nif(): Nif
    {
        return Nif::parse('89890001K');
    }

    private function id(string $number = 'A1'): InvoiceId
    {
        return new InvoiceId($this->nif(), $number, new DateTimeImmutable('2024-01-01'));
    }

    public function test_series_is_trimmed_and_charset_checked(): void
    {
        $this->assertSame('12345678/G33', $this->id(' 12345678/G33 ')->number);
        $this->assertSame('12345678&G33', $this->id('12345678&G33')->number);

        foreach (['A=1', 'A"1', "A'1", 'A<1', 'A>1', '', str_repeat('A', 61), 'é'] as $bad) {
            try {
                $this->id($bad);
                $this->fail('Accepted: '.$bad);
            } catch (InvalidRecord $e) {
                $this->assertStringContainsString('NumSerieFactura', $e->getMessage());
                $this->assertStringContainsString('Validaciones v1.2.2 §3.1.3.1', $e->getMessage());
            }
        }
    }

    public function test_date_text_is_zero_padded(): void
    {
        $this->assertSame('01-01-2024', $this->id()->dateText());
    }

    public function test_invoice_id_equality(): void
    {
        $this->assertTrue($this->id('A1')->equals($this->id(' A1')));
        $this->assertFalse($this->id('A1')->equals($this->id('A2')));
    }

    public function test_party_needs_exactly_one_identifier(): void
    {
        $foreign = new ForeignId(ForeignIdType::VatNumber, 'FR123', CountryCode::FR);
        $this->assertSame('Acme', (new TaxParty(' Acme ', $this->nif(), null))->name);
        new TaxParty('Acme', null, $foreign);

        $this->expectException(InvalidRecord::class);
        new TaxParty('Acme', $this->nif(), $foreign);
    }

    public function test_party_without_identifier_is_refused(): void
    {
        $this->expectException(InvalidRecord::class);
        new TaxParty('Acme', null, null);
    }

    public function test_party_and_foreign_id_limits(): void
    {
        try {
            new TaxParty(str_repeat('a', 121), $this->nif(), null);
            $this->fail('name accepted');
        } catch (InvalidRecord) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(InvalidRecord::class);
        new ForeignId(ForeignIdType::Passport, str_repeat('1', 21), null);
    }

    private function system(
        ?Nif $nif = null,
        ?ForeignId $foreignId = null,
        string $systemName = 'Sys',
        string $systemId = '01',
        string $installationNumber = 'I-1',
        string $name = 'Vendor SL',
    ): ComputerSystem {
        return new ComputerSystem(
            $name, $nif, $foreignId, $systemName, $systemId, '1.0', $installationNumber, true, false, false,
        );
    }

    public function test_computer_system_has_no_defaults(): void
    {
        foreach ((new ReflectionClass(ComputerSystem::class))->getConstructor()?->getParameters() ?? [] as $p) {
            $this->assertFalse($p->isDefaultValueAvailable(), $p->getName());
        }
    }

    public function test_computer_system_needs_one_identifier(): void
    {
        $foreign = new ForeignId(ForeignIdType::VatNumber, 'FR123', CountryCode::FR);
        $this->assertSame('Sys', $this->system($this->nif())->systemName);

        foreach ([[null, null], [$this->nif(), $foreign]] as [$nif, $other]) {
            try {
                $this->system($nif, $other);
                $this->fail('accepted');
            } catch (InvalidRecord) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_system_field_limits(): void
    {
        $nif = $this->nif();
        $attempts = [
            fn (): ComputerSystem => $this->system($nif, null, str_repeat('a', 31)),
            fn (): ComputerSystem => $this->system($nif, null, 'Sys', '001'),
            fn (): ComputerSystem => $this->system($nif, null, 'Sys', '01', str_repeat('a', 101)),
            fn (): ComputerSystem => $this->system($nif, null, 'Sys', '01', ' '),
            fn (): ComputerSystem => $this->system($nif, null, 'Sys', '01', 'I-1', ''),
        ];

        foreach ($attempts as $attempt) {
            try {
                $attempt();
                $this->fail('accepted');
            } catch (InvalidRecord) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function line(?OperationQualification $q, ?ExemptOperation $e): TaxBreakdownLine
    {
        return new TaxBreakdownLine(
            TaxType::Iva, RegimeKey::Key01, $q, $e, TaxRate::of('21'), Amount::of('100'), null,
            Amount::of('21'), null, null,
        );
    }

    public function test_breakdown_needs_exactly_one_qualification_or_exemption(): void
    {
        $this->line(OperationQualification::S1, null);
        $this->line(null, ExemptOperation::E1);

        foreach ([[null, null], [OperationQualification::S1, ExemptOperation::E1]] as [$q, $e]) {
            try {
                $this->line($q, $e);
                $this->fail('accepted');
            } catch (InvalidRecord) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_breakdown_negation(): void
    {
        $n = $this->line(OperationQualification::S1, null)->negated();

        $this->assertSame('-100.00', $n->base->text);
        $this->assertSame('-21.00', $n->quota?->text);
        $this->assertSame('21.00', $n->rate?->text);
        $this->assertNull($n->costBase);
    }

    public function test_correction_amounts(): void
    {
        $c = new CorrectionAmounts(Amount::of('1'), Amount::of('2'), null);
        $this->assertSame('2.00', $c->quota->text);
    }

    /** @return iterable<string, array{string}> */
    public static function badFingerprints(): iterable
    {
        yield 'lower case' => [str_repeat('a', 64)];
        yield '63 chars' => [str_repeat('A', 63)];
        yield '65 chars' => [str_repeat('A', 65)];
    }

    #[DataProvider('badFingerprints')]
    public function test_chain_link_fingerprint_format(string $fingerprint): void
    {
        $this->expectException(InvalidRecord::class);
        ChainLink::after($this->id(), $fingerprint);
    }

    public function test_chain_link_after_still_refuses_lower_case(): void
    {
        $this->expectException(InvalidRecord::class);
        ChainLink::after($this->id(), strtolower(str_repeat('0A', 32)));
    }

    public function test_chain_link_restore_keeps_any_text_of_1_to_64_characters(): void
    {
        foreach (['x', str_repeat('a', 63), str_repeat('z', 64), strtolower(str_repeat('0A', 32))] as $fingerprint) {
            $link = ChainLink::restore($this->id(), $fingerprint);
            $this->assertFalse($link->isFirst());
            $this->assertSame($fingerprint, $link->previousFingerprint());
        }
    }

    /** @return iterable<string, array{string}> */
    public static function unrestorableFingerprints(): iterable
    {
        yield 'empty' => [''];
        yield '65 chars' => [str_repeat('A', 65)];
    }

    #[DataProvider('unrestorableFingerprints')]
    public function test_chain_link_restore_refuses_empty_or_too_long(string $fingerprint): void
    {
        $this->expectException(InvalidRecord::class);
        ChainLink::restore($this->id(), $fingerprint);
    }

    public function test_chain_link_states(): void
    {
        $first = ChainLink::first();
        $this->assertTrue($first->isFirst());
        $this->assertNull($first->previous());
        $this->assertSame('', $first->previousFingerprint());

        $next = ChainLink::after($this->id(), str_repeat('0A', 32));
        $this->assertFalse($next->isFirst());
        $this->assertSame(str_repeat('0A', 32), $next->previousFingerprint());
    }
}
