<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\Spain;

/**
 * The result of cross-checking one B2Brouter VERI*FACTU tax report against our own engine.
 */
final readonly class CrossCheckResult
{
    /**
     * @param  array<int, CheckStatus>  $steps  the status of every step, keyed by CrossCheckStep value
     * @param  list<Difference>  $differences  every difference found, in step order
     * @param  list<string>  $notes  remarks that are not differences, such as why a step was skipped
     */
    public function __construct(
        public string $taxReportId,
        public array $steps,
        public array $differences,
        public array $notes,
    ) {}

    /** Whether no step failed; skipped steps and known divergences do not count as failures. */
    public function passed(): bool
    {
        return ! in_array(CheckStatus::Failed, $this->steps, true);
    }

    public function failsIn(CrossCheckMode $mode): bool
    {
        return match ($mode) {
            CrossCheckMode::Strict => ! $this->passed(),
            CrossCheckMode::Report => false,
        };
    }

    /**
     * @return list<Difference>
     */
    public function failures(): array
    {
        return $this->withStatus(CheckStatus::Failed);
    }

    /**
     * @return list<Difference>
     */
    public function knownDivergences(): array
    {
        return $this->withStatus(CheckStatus::KnownDivergence);
    }

    /**
     * One line per step, then one line per difference and per note.
     */
    public function summary(): string
    {
        $lines = [sprintf('Cross-check of B2Brouter tax report %s: %s', $this->taxReportId, $this->passed() ? 'passed' : 'failed')];
        foreach (CrossCheckStep::cases() as $step) {
            $status = $this->steps[$step->value] ?? CheckStatus::Skipped;
            $lines[] = sprintf('Step %d %s: %s', $step->value, $step->name, $status->value);
        }
        foreach ($this->differences as $difference) {
            $lines[] = sprintf(
                '[%s] %s %s: ours "%s", theirs "%s" - %s',
                $difference->status->value,
                $difference->step->name,
                $difference->field,
                $difference->ours,
                $difference->theirs,
                $difference->message,
            );
        }
        foreach ($this->notes as $note) {
            $lines[] = 'Note: '.$note;
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<Difference>
     */
    private function withStatus(CheckStatus $status): array
    {
        return array_values(array_filter(
            $this->differences,
            static fn (Difference $difference): bool => $difference->status === $status,
        ));
    }
}
