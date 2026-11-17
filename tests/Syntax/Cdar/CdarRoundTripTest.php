<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\Cdar;

use DateTimeImmutable;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\France\Lifecycle\CharacteristicType;
use IntrAgile\EInvoicing\France\Lifecycle\LifecycleStatus;
use IntrAgile\EInvoicing\France\Lifecycle\PartyRole;
use IntrAgile\EInvoicing\France\Lifecycle\RegulatoryScope;
use IntrAgile\EInvoicing\Syntax\Cdar\CdarReader;
use IntrAgile\EInvoicing\Syntax\Cdar\CdarWriter;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\CanonicalXml;
use IntrAgile\EInvoicing\Tests\Support\LifecycleMessages;
use IntrAgile\EInvoicing\Tests\Validation\France\InstalledFranceRules;
use IntrAgile\EInvoicing\Validation\France\CdarValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Every FNFE CDAR example is read and written back to the same canonical XML.
 */
#[Group('france-rules')]
final class CdarRoundTripTest extends TestCase
{
    private const UC1_COLLECTED = 'UC1_F202500003_07-CDV-212_Encaissee.xml';

    private static ?CdarValidator $validator = null;

    private static function validator(): CdarValidator
    {
        return self::$validator ??= CdarValidator::create(InstalledFranceRules::dir());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function examples(): iterable
    {
        if (! Fixtures::hasFranceRfe()) {
            yield 'France_RFE not downloaded' => [''];

            return;
        }
        foreach (Fixtures::franceExamples('CDAR') as $name => $path) {
            yield $name => [$path];
        }
    }

    public function test_every_fnfe_example_is_covered(): void
    {
        Fixtures::franceRfeRoot();

        self::assertCount(59, Fixtures::franceExamples('CDAR'));
    }

    #[DataProvider('examples')]
    public function test_fnfe_examples_round_trip_exactly(string $path): void
    {
        $example = self::example($path);

        $result = (new CdarReader)->read($example);
        $written = (new CdarWriter)->write($result->message);

        self::assertSame([], $result->ignored);
        self::assertSame(CanonicalXml::of($example), CanonicalXml::of($written));
        $report = self::validator()->validate($written);
        self::assertSame([], $report->schemaErrors);
        self::assertSame([], $report->findings);
        self::assertTrue($report->isValid());
    }

    public function test_read_model_of_212_example(): void
    {
        $examples = Fixtures::franceExamples('CDAR');
        $message = (new CdarReader)->read(self::example($examples[self::UC1_COLLECTED]))->message;

        self::assertEquals(LifecycleMessages::uc1Collected(), $message);

        self::assertSame(LifecycleStatus::Collected, $message->status);
        self::assertSame('Encaissée', $message->statusLabel);
        self::assertSame('47', $message->statusCode());
        self::assertSame(RegulatoryScope::Regulated, $message->scope);
        self::assertSame('F202500003_212_20250701151000#380_20250701', $message->id);

        $characteristic = $message->details[0]->characteristics[0];
        self::assertSame(CharacteristicType::MEN, $characteristic->type);
        self::assertSame('12000', (string) $characteristic->amount);
        self::assertSame(0, $characteristic->amount?->getScale());
        self::assertSame('20.00', (string) $characteristic->percent);
        self::assertSame(CurrencyCode::EUR, $characteristic->currency);
        self::assertFalse($characteristic->changed);

        self::assertSame(PartyRole::Platform, $message->sender->role);
        self::assertNull($message->sender->globalId);
        self::assertSame('100000009', $message->issuer->globalId?->value);
        self::assertSame('0002', $message->issuer->globalId->scheme);
        self::assertSame([PartyRole::Buyer, PartyRole::PublicPortal], array_map(static fn ($party) => $party->role, $message->recipients));
        self::assertNotNull($message->recipients[0]->uri);
        self::assertSame('0225', $message->recipients[0]->uri->scheme);

        $paris = static fn (string $time): DateTimeImmutable => LifecycleMessages::at($time);
        self::assertEquals($paris('2025-08-02 10:05:00'), $message->issuedAt);
        self::assertSame('Europe/Paris', $message->issuedAt->getTimezone()->getName());
        self::assertEquals($paris('2025-08-02 10:00:00'), $message->statusAt);
        self::assertEquals($paris('2025-07-01 15:10:00'), $message->invoice->receivedAt);
        self::assertSame('Europe/Paris', $message->invoice->receivedAt->getTimezone()->getName());
        self::assertSame('20250701', $message->invoice->issueDate?->format('Ymd'));
    }

    private static function example(string $path): string
    {
        if ($path === '') {
            // Skips the test: the examples have not been downloaded.
            Fixtures::franceRfeRoot();
        }
        $xml = file_get_contents($path);
        self::assertIsString($xml);

        return $xml;
    }
}
