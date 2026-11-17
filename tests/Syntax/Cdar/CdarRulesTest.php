<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\Cdar;

use IntrAgile\EInvoicing\France\Lifecycle\Lifecycle;
use IntrAgile\EInvoicing\France\Lifecycle\LifecycleMessage;
use IntrAgile\EInvoicing\France\Lifecycle\LifecycleParty;
use IntrAgile\EInvoicing\France\Lifecycle\LifecycleStatus;
use IntrAgile\EInvoicing\Syntax\Cdar\CdarWriter;
use IntrAgile\EInvoicing\Tests\Support\LifecycleMessages;
use IntrAgile\EInvoicing\Tests\Validation\France\InstalledFranceRules;
use IntrAgile\EInvoicing\Validation\France\CdarValidator;
use IntrAgile\EInvoicing\Validation\MessageValidationReport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('france-rules')]
final class CdarRulesTest extends TestCase
{
    private static ?CdarValidator $validator = null;

    private static function validator(): CdarValidator
    {
        return self::$validator ??= CdarValidator::create(InstalledFranceRules::dir());
    }

    /**
     * @return iterable<string, array{LifecycleMessage}>
     */
    public static function messages(): iterable
    {
        foreach (LifecycleMessages::all() as $name => $message) {
            yield $name => [$message];
        }
    }

    public function test_every_status_has_a_factory(): void
    {
        $statuses = array_map(static fn (LifecycleMessage $message): LifecycleStatus => $message->status, LifecycleMessages::all());

        foreach (LifecycleStatus::cases() as $status) {
            self::assertContains($status, $statuses, $status->name);
        }
    }

    #[DataProvider('messages')]
    public function test_every_factory_output_is_valid(LifecycleMessage $message): void
    {
        $report = self::validator()->validate((new CdarWriter)->write($message));

        self::assertSame([], $report->schemaErrors, self::describe($report));
        self::assertSame([], $report->dynamicErrors, self::describe($report));
        self::assertSame([], $report->findings, self::describe($report));
        self::assertTrue($report->isValid());
    }

    public function test_collected_without_men_fires_cdv_14(): void
    {
        $message = Lifecycle::status(
            LifecycleStatus::Collected,
            LifecycleMessages::invoice(),
            LifecycleMessages::platform(),
            LifecycleMessages::sellerIssuer(),
            [LifecycleMessages::buyer(), LifecycleParty::ppf()],
            LifecycleMessages::at('2025-08-02 10:00:00'),
        );

        $report = self::validator()->validate((new CdarWriter)->write($message));

        self::assertFalse($report->isValid());
        self::assertContains('BR-FR-CDV-14_MDT-207 fatal', $report->verdict(), self::describe($report));
    }

    private static function describe(MessageValidationReport $report): string
    {
        $lines = [];
        foreach ($report->schemaErrors as $error) {
            $lines[] = "schema {$error->line}: {$error->message}";
        }
        foreach ($report->dynamicErrors as $error) {
            $lines[] = "dynamic: {$error->ruleId} {$error->errorCode}: {$error->message}";
        }
        foreach ($report->findings as $finding) {
            $lines[] = "{$finding->ruleId} {$finding->severity->value} at {$finding->location}: {$finding->message}";
        }

        return implode("\n", $lines);
    }
}
