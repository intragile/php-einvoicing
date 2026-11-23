<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\Spain;

/**
 * Collects the differences and notes of one cross-check and derives each step's status.
 *
 * @internal used by VerifactuCrossCheck only
 */
final class CrossCheckRun
{
    /** @var array<int, CheckStatus> */
    private array $steps = [];

    /** @var list<Difference> */
    private array $differences = [];

    /** @var list<string> */
    private array $notes = [];

    public function __construct(private readonly string $taxReportId) {}

    public function fail(CrossCheckStep $step, string $field, string $ours, string $theirs, string $message): void
    {
        $this->differences[] = new Difference($step, CheckStatus::Failed, $field, $ours, $theirs, $message);
    }

    public function diverge(CrossCheckStep $step, string $field, string $ours, string $theirs, string $message): void
    {
        $this->differences[] = new Difference($step, CheckStatus::KnownDivergence, $field, $ours, $theirs, $message);
    }

    /** Whether the step recorded a known divergence on the field. */
    public function hasDivergence(CrossCheckStep $step, string $field): bool
    {
        foreach ($this->differences as $difference) {
            if ($difference->step === $step && $difference->field === $field && $difference->status === CheckStatus::KnownDivergence) {
                return true;
            }
        }

        return false;
    }

    public function note(string $note): void
    {
        $this->notes[] = $note;
    }

    public function skip(CrossCheckStep $step, ?string $note = null): void
    {
        $this->steps[$step->value] = CheckStatus::Skipped;
        if ($note !== null) {
            $this->note($note);
        }
    }

    /**
     * Ends the step: Failed with any failure, KnownDivergence with only known divergences,
     * Passed otherwise. Returns null so that a step can end and return in one statement.
     */
    public function close(CrossCheckStep $step): null
    {
        $status = CheckStatus::Passed;
        foreach ($this->differences as $difference) {
            if ($difference->step !== $step) {
                continue;
            }
            if ($difference->status === CheckStatus::Failed) {
                $status = CheckStatus::Failed;
                break;
            }
            $status = CheckStatus::KnownDivergence;
        }
        $this->steps[$step->value] = $status;

        return null;
    }

    public function result(): CrossCheckResult
    {
        $steps = $this->steps;
        ksort($steps);

        return new CrossCheckResult($this->taxReportId, $steps, $this->differences, $this->notes);
    }
}
