<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Spain\Verifactu;

use IntrAgile\EInvoicing\Spain\Verifactu\AltaOperation;
use IntrAgile\EInvoicing\Spain\Verifactu\AnulacionOperation;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OperationTableTest extends TestCase
{
    /**
     * @return iterable<string, array{AltaOperation, ?string, ?string, int}>
     */
    public static function altaRows(): iterable
    {
        yield 'initial' => [AltaOperation::Initial, null, null, 6];
        yield 'correction' => [AltaOperation::Correction, 'S', null, 9];
        yield 'after rejection' => [AltaOperation::AfterRejection, 'S', 'X', 7];
        yield 'correction after rejection' => [AltaOperation::CorrectionAfterRejection, 'S', 'S', 10];
    }

    #[DataProvider('altaRows')]
    public function test_alta_table(AltaOperation $operation, ?string $subsanacion, ?string $rechazo, int $row): void
    {
        self::assertSame($subsanacion, $operation->subsanacion());
        self::assertSame($rechazo, $operation->rechazoPrevio());
        self::assertSame($operation, AltaOperation::fromFlags($subsanacion, $rechazo));
        self::assertSame("DsRegistroVeriFactu.xlsx v1.0, A) Cuadro Operativa Alta, row $row", $operation->source());
    }

    public function test_absent_and_n_flags_are_alike(): void
    {
        self::assertSame(AltaOperation::Initial, AltaOperation::fromFlags(null, null));
        self::assertSame(AltaOperation::Initial, AltaOperation::fromFlags('N', 'N'));
        self::assertSame(AltaOperation::Correction, AltaOperation::fromFlags('S', 'N'));
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function refusedAltaPairs(): iterable
    {
        yield 'N,S' => ['N', 'S'];
        yield 'N,X' => ['N', 'X'];
        yield 'null,S' => [null, 'S'];
        yield 'null,X' => [null, 'X'];
        yield 'unknown flag' => ['Y', null];
    }

    #[DataProvider('refusedAltaPairs')]
    public function test_refused_alta_pairs(?string $subsanacion, ?string $rechazo): void
    {
        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessage('A) Cuadro Operativa Alta');
        AltaOperation::fromFlags($subsanacion, $rechazo);
    }

    /**
     * @return iterable<string, array{AnulacionOperation, ?string, ?string, int}>
     */
    public static function anulacionRows(): iterable
    {
        yield 'normal' => [AnulacionOperation::Normal, null, null, 6];
        yield 'after rejection' => [AnulacionOperation::AfterRejection, null, 'S', 7];
        yield 'without prior record' => [AnulacionOperation::WithoutPriorRecord, 'S', null, 9];
        yield 'without prior record after rejection' => [AnulacionOperation::WithoutPriorRecordAfterRejection, 'S', 'S', 10];
    }

    #[DataProvider('anulacionRows')]
    public function test_anulacion_table(AnulacionOperation $operation, ?string $sinRegistro, ?string $rechazo, int $row): void
    {
        self::assertSame($sinRegistro, $operation->sinRegistroPrevio());
        self::assertSame($rechazo, $operation->rechazoPrevio());
        self::assertSame($operation, AnulacionOperation::fromFlags($sinRegistro, $rechazo));
        self::assertSame($operation, AnulacionOperation::fromFlags($sinRegistro ?? 'N', $rechazo ?? 'N'));
        self::assertSame("DsRegistroVeriFactu.xlsx v1.0, B) Cuadro Operativa Anulación, row $row", $operation->source());
    }

    public function test_unknown_anulacion_flag_is_refused(): void
    {
        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessage('B) Cuadro Operativa Anulación');
        AnulacionOperation::fromFlags('X', null);
    }
}
