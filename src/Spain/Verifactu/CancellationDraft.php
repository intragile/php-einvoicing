<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use IntrAgile\EInvoicing\Spain\GeneratedBy;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;

/**
 * The content of a RegistroAnulacion apart from its chain link, computer system, generation
 * timestamp and fingerprint.
 */
final readonly class CancellationDraft
{
    private function __construct(
        public InvoiceId $annulled,
        public AnulacionOperation $operation,
        public ?GeneratedBy $generatedBy,
        public ?TaxParty $generator,
        public ?string $externalReference,
    ) {}

    public static function of(
        InvoiceId $annulled,
        AnulacionOperation $operation = AnulacionOperation::Normal,
        ?GeneratedBy $generatedBy = null,
        ?TaxParty $generator = null,
        ?string $externalReference = null,
    ): self {
        if ($externalReference !== null) {
            $externalReference = trim($externalReference);

            if (mb_strlen($externalReference) > 60) {
                throw new InvalidRecord('RefExterna must be at most 60 characters.');
            }
        }

        return new self($annulled, $operation, $generatedBy, $generator, $externalReference);
    }
}
