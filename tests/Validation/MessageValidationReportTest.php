<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation;

use IntrAgile\EInvoicing\Syntax\Syntax;
use IntrAgile\EInvoicing\Validation\DynamicError;
use IntrAgile\EInvoicing\Validation\Finding;
use IntrAgile\EInvoicing\Validation\MessageType;
use IntrAgile\EInvoicing\Validation\MessageValidationReport;
use IntrAgile\EInvoicing\Validation\SchemaError;
use IntrAgile\EInvoicing\Validation\Severity;
use IntrAgile\EInvoicing\Validation\ValidationReport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MessageValidationReportTest extends TestCase
{
    /**
     * @return iterable<string, array{list<Finding>, list<DynamicError>, list<SchemaError>}>
     */
    public static function outcomes(): iterable
    {
        $fatal = new Finding('G6.29', Severity::Fatal, '/Report', 'One report only.', 'true()');
        $warning = new Finding('BR-FR-CDV-01', Severity::Warning, '/rsm:CrossDomainAcknowledgementAndResponse', 'Check.', 'true()');
        $information = new Finding('BR-FR-CDV-02', Severity::Information, '/rsm:CrossDomainAcknowledgementAndResponse', 'Note.', 'true()');
        $dynamic = new DynamicError('G1.01', 'FORG0001', '/Report', 'Not a number.');
        $schema = new SchemaError(3, 7, 'Element "X" is not expected.', false);

        yield 'clean' => [[], [], []];
        yield 'warnings and information only' => [[$warning, $information, $warning], [], []];
        yield 'fatal' => [[$warning, $fatal, $fatal], [], []];
        yield 'dynamic error' => [[$information], [$dynamic], []];
        yield 'schema error' => [[], [], [$schema]];
    }

    /**
     * @param  list<Finding>  $findings
     * @param  list<DynamicError>  $dynamicErrors
     * @param  list<SchemaError>  $schemaErrors
     */
    #[DataProvider('outcomes')]
    public function test_report_semantics_match_validation_report(array $findings, array $dynamicErrors, array $schemaErrors): void
    {
        $invoice = new ValidationReport(['fr-flux2-cii 1.4.0.04'], Syntax::Cii, $findings, $dynamicErrors, $schemaErrors);
        $message = new MessageValidationReport(MessageType::Flux10, ['fr-flux10 1.4.0.04'], $findings, $dynamicErrors, $schemaErrors);

        self::assertSame($invoice->isValid(), $message->isValid());
        self::assertSame($invoice->verdict(), $message->verdict());
        self::assertSame($invoice->fatal(), $message->fatal());
        self::assertSame($invoice->warnings(), $message->warnings());
        self::assertSame($invoice->information(), $message->information());
    }

    public function test_verdict_is_sorted_and_distinct(): void
    {
        $report = new MessageValidationReport(MessageType::Cdar, ['fr-cdar 1.4.0.04'], [
            new Finding('B', Severity::Warning, '/', 'b', 'true()'),
            new Finding('A', Severity::Fatal, '/', 'a', 'true()'),
            new Finding('B', Severity::Warning, '/', 'b', 'true()'),
        ], []);

        self::assertSame(['A fatal', 'B warning'], $report->verdict());
        self::assertFalse($report->isValid());
        self::assertSame([], $report->schemaErrors);
    }
}
