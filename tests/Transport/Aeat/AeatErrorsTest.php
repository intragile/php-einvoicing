<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Aeat;

use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Transport\Aeat\AeatErrors;
use IntrAgile\EInvoicing\Transport\Aeat\ErrorAction;
use IntrAgile\EInvoicing\Transport\Aeat\ErrorScope;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\FailureCategory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class AeatErrorsTest extends TestCase
{
    /**
     * @return iterable<string, array{int, ErrorScope, FailureCategory, ErrorAction, bool}>
     */
    public static function classifications(): iterable
    {
        yield '4104 submission validation' => [4104, ErrorScope::Submission, FailureCategory::Validation, ErrorAction::FixConfiguration, false];
        yield '4108 authentication' => [4108, ErrorScope::Submission, FailureCategory::Other, ErrorAction::FixConfiguration, true];
        yield '4112 authentication' => [4112, ErrorScope::Submission, FailureCategory::Other, ErrorAction::FixConfiguration, true];
        yield '4132 authentication' => [4132, ErrorScope::Submission, FailureCategory::Other, ErrorAction::FixConfiguration, true];
        yield '4110 transient' => [4110, ErrorScope::Submission, FailureCategory::Transport, ErrorAction::RetrySameBytes, false];
        yield '3500 transient' => [3500, ErrorScope::Submission, FailureCategory::Transport, ErrorAction::RetrySameBytes, false];
        yield '4141 submission validation' => [4141, ErrorScope::Submission, FailureCategory::Validation, ErrorAction::FixConfiguration, false];
        yield '3000 duplicate' => [3000, ErrorScope::Record, FailureCategory::Duplicate, ErrorAction::ResolveDuplicate, false];
        yield '3001 duplicate' => [3001, ErrorScope::Record, FailureCategory::Duplicate, ErrorAction::ResolveDuplicate, false];
        yield '3002 no prior record' => [3002, ErrorScope::Record, FailureCategory::Validation, ErrorAction::RegenerateAfterRejection, false];
        yield '3003 record configuration' => [3003, ErrorScope::Record, FailureCategory::Validation, ErrorAction::FixConfiguration, false];
        yield '1142 record validation' => [1142, ErrorScope::Record, FailureCategory::Validation, ErrorAction::RegenerateAfterRejection, false];
        yield '1199 record validation' => [1199, ErrorScope::Record, FailureCategory::Validation, ErrorAction::RegenerateAfterRejection, false];
        yield '2000 accepted with errors' => [2000, ErrorScope::AcceptedWithErrors, FailureCategory::Validation, ErrorAction::CorrectAcceptedRecord, false];
        yield '2005 accepted with errors' => [2005, ErrorScope::AcceptedWithErrors, FailureCategory::Validation, ErrorAction::CorrectAcceptedRecord, false];
        yield '9999 unknown' => [9999, ErrorScope::Record, FailureCategory::Other, ErrorAction::Investigate, false];
    }

    #[DataProvider('classifications')]
    public function test_classification_table(int $code, ErrorScope $scope, FailureCategory $category, ErrorAction $action, bool $authentication): void
    {
        $error = AeatErrors::classify($code);

        $this->assertSame($code, $error->code);
        $this->assertSame($scope, $error->scope);
        $this->assertSame($category, $error->category);
        $this->assertSame($action, $error->action);
        $this->assertSame($authentication, $error->authentication);
    }

    public function test_failure_keeps_aeat_text_and_code(): void
    {
        $failure = AeatErrors::failure(3000, 'Texto tal como lo envía la AEAT');

        $this->assertSame(FailureCategory::Duplicate, $failure->category);
        $this->assertSame('3000', $failure->providerCode);
        $this->assertSame('Texto tal como lo envía la AEAT', $failure->message);
        $this->assertNull(AeatErrors::failure(4104, null)->message);
    }

    public function test_code_of_reads_the_code_carried_by_the_exception(): void
    {
        $this->assertSame(4112, AeatErrors::codeOf(new AuthenticationFailed('refused', null, 4112)));
        $this->assertSame(3000, AeatErrors::codeOf(new DocumentRejected('rejected', [], null, 3000)));
        $this->assertNull(AeatErrors::codeOf(new AuthenticationFailed('refused')));
        $this->assertNull(AeatErrors::codeOf(new DocumentRejected('rejected')));
        $this->assertNull(AeatErrors::codeOf(new OutcomeUnknown('unknown')));
        $this->assertNull(AeatErrors::codeOf(new TransportUnavailable('down')));
    }

    #[Group('verifactu-official')]
    public function test_scope_matches_the_catalogue_section(): void
    {
        $catalogue = iconv('ISO-8859-1', 'UTF-8', (string) file_get_contents(Fixtures::verifactuSchemas()->errorCatalogue()));
        $this->assertIsString($catalogue);

        $section = 0;
        $codes = [];
        foreach (preg_split('/\R/', $catalogue) ?: [] as $line) {
            if (str_starts_with($line, '*')) {
                $section++;

                continue;
            }
            if (preg_match('/^\s*(\d{4})\s*=/', $line, $match) === 1) {
                $codes[(int) $match[1]] = $section;
            }
        }

        $expected = [1 => ErrorScope::Submission, 2 => ErrorScope::Record, 3 => ErrorScope::AcceptedWithErrors];
        $this->assertCount(247, $codes);
        $this->assertSame([1 => 44, 2 => 193, 3 => 10], array_count_values($codes));
        foreach ($codes as $code => $index) {
            $error = AeatErrors::classify($code);
            $this->assertSame($expected[$index], $error->scope, (string) $code);
            $this->assertNotSame(ErrorAction::Investigate, $error->action, (string) $code);
        }
    }

    public function test_source_copies_no_aeat_description(): void
    {
        $source = (string) file_get_contents(__DIR__.'/../../../src/Transport/Aeat/AeatErrors.php');

        $this->assertStringNotContainsString('titular del certificado', $source);
        $this->assertStringNotContainsString('Registro de facturación duplicado', $source);
    }
}
