<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;

/**
 * The admitted combinations of SinRegistroPrevio and RechazoPrevio on a RegistroAnulacion, as
 * listed in the "B) Cuadro Operativa Anulación" sheet of DsRegistroVeriFactu.xlsx. A flag of "N"
 * and an absent flag are the same; "N" is never written.
 */
enum AnulacionOperation
{
    /** A cancellation of a record AEAT holds. */
    case Normal;

    /** A cancellation after AEAT rejected the previous cancellation. */
    case AfterRejection;

    /** A cancellation of a record AEAT never received. */
    case WithoutPriorRecord;

    /** A cancellation of a record AEAT never received, after a rejection. */
    case WithoutPriorRecordAfterRejection;

    public function sinRegistroPrevio(): ?string
    {
        return match ($this) {
            self::Normal, self::AfterRejection => null,
            self::WithoutPriorRecord, self::WithoutPriorRecordAfterRejection => 'S',
        };
    }

    public function rechazoPrevio(): ?string
    {
        return match ($this) {
            self::Normal, self::WithoutPriorRecord => null,
            self::AfterRejection, self::WithoutPriorRecordAfterRejection => 'S',
        };
    }

    /**
     * The sheet and row of the specification that admits this combination.
     */
    public function source(): string
    {
        $row = match ($this) {
            self::Normal => 6,
            self::AfterRejection => 7,
            self::WithoutPriorRecord => 9,
            self::WithoutPriorRecordAfterRejection => 10,
        };

        return 'DsRegistroVeriFactu.xlsx v1.0, B) Cuadro Operativa Anulación, row '.$row;
    }

    /**
     * @throws InvalidRecord when a flag is neither absent, "N" nor "S"
     */
    public static function fromFlags(?string $sinRegistroPrevio, ?string $rechazoPrevio): self
    {
        $sinRegistroPrevio ??= 'N';
        $rechazoPrevio ??= 'N';

        return match ([$sinRegistroPrevio, $rechazoPrevio]) {
            ['N', 'N'] => self::Normal,
            ['N', 'S'] => self::AfterRejection,
            ['S', 'N'] => self::WithoutPriorRecord,
            ['S', 'S'] => self::WithoutPriorRecordAfterRejection,
            default => throw new InvalidRecord(sprintf(
                'SinRegistroPrevio "%s" with RechazoPrevio "%s" is not admitted by the sheet "B) Cuadro Operativa Anulación" of DsRegistroVeriFactu.xlsx.',
                $sinRegistroPrevio,
                $rechazoPrevio,
            )),
        };
    }
}
