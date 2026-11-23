<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;

/**
 * The admitted combinations of Subsanacion and RechazoPrevio on a RegistroAlta, as listed in the
 * "A) Cuadro Operativa Alta" sheet of DsRegistroVeriFactu.xlsx. A flag of "N" and an absent flag
 * are the same; "N" is never written.
 */
enum AltaOperation
{
    /** A plain first submission. */
    case Initial;

    /** A correction of a record AEAT accepted. */
    case Correction;

    /** A new submission after AEAT rejected the record. */
    case AfterRejection;

    /** A correction after AEAT rejected a previous correction. */
    case CorrectionAfterRejection;

    public function subsanacion(): ?string
    {
        return match ($this) {
            self::Initial => null,
            self::Correction, self::AfterRejection, self::CorrectionAfterRejection => 'S',
        };
    }

    public function rechazoPrevio(): ?string
    {
        return match ($this) {
            self::Initial, self::Correction => null,
            self::AfterRejection => 'X',
            self::CorrectionAfterRejection => 'S',
        };
    }

    /**
     * The sheet and row of the specification that admits this combination.
     */
    public function source(): string
    {
        $row = match ($this) {
            self::Initial => 6,
            self::AfterRejection => 7,
            self::Correction => 9,
            self::CorrectionAfterRejection => 10,
        };

        return 'DsRegistroVeriFactu.xlsx v1.0, A) Cuadro Operativa Alta, row '.$row;
    }

    /**
     * @throws InvalidRecord when the pair is not in the sheet
     */
    public static function fromFlags(?string $subsanacion, ?string $rechazoPrevio): self
    {
        $subsanacion ??= 'N';
        $rechazoPrevio ??= 'N';

        return match ([$subsanacion, $rechazoPrevio]) {
            ['N', 'N'] => self::Initial,
            ['S', 'N'] => self::Correction,
            ['S', 'X'] => self::AfterRejection,
            ['S', 'S'] => self::CorrectionAfterRejection,
            default => throw new InvalidRecord(sprintf(
                'Subsanacion "%s" with RechazoPrevio "%s" is not admitted by the sheet "A) Cuadro Operativa Alta" of DsRegistroVeriFactu.xlsx.',
                $subsanacion,
                $rechazoPrevio,
            )),
        };
    }
}
