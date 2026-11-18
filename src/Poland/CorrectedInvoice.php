<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

use DateTimeImmutable;

/**
 * An invoice a correction corrects (DaneFaKorygowanej).
 */
final readonly class CorrectedInvoice
{
    public function __construct(
        /** Number of the corrected invoice (NrFaKorygowanej). */
        public string $number,
        /** Issue date of the corrected invoice (DataWystFaKorygowanej). */
        public DateTimeImmutable $issueDate,
        /** KSeF number of the corrected invoice (NrKSeFFaKorygowanej); null when it was issued outside KSeF (NrKSeFN = 1). */
        public ?KsefNumber $ksefNumber = null,
    ) {}
}
