<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\France\Lifecycle;

use IntrAgile\EInvoicing\France\Lifecycle\CharacteristicType;
use IntrAgile\EInvoicing\France\Lifecycle\PartyRole;
use IntrAgile\EInvoicing\France\Lifecycle\RegulatoryScope;
use IntrAgile\EInvoicing\France\Lifecycle\RequestedAction;
use IntrAgile\EInvoicing\France\Lifecycle\StatusReason;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Tests\Validation\France\InstalledFranceRules;
use IntrAgile\EInvoicing\Validation\France\FranceRules;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Pins the lifecycle code lists to the closed lists of the installed fr-cdar rule set, so an enum
 * cannot drift from the rules a CDV message is validated against.
 *
 * Each list is read only from the part of the assert test that checks it: the role comparisons of
 * a role rule, the reason comparisons of a per-status reason rule, the ID list of the scope rule.
 * Other quoted values in the same tests (status codes, acknowledgement types, the PPF scheme
 * values) belong to other lists and are not taken as codes.
 */
#[Group('france-rules')]
final class CodeListsMatchRulesTest extends TestCase
{
    private static ?RuleSet $rules = null;

    public function test_status_reasons_match_the_reason_code_list(): void
    {
        self::assertSameCodes(self::functionList('BR-FR-CDV-CL-09_MDT-113'), self::values(StatusReason::cases()));
    }

    public function test_every_reason_allowed_for_a_status_is_a_status_reason(): void
    {
        $perStatus = array_filter(
            self::asserts(),
            static fn (string $id) => str_starts_with($id, 'BR-FR-CDV-CL-09_MDT-113_'),
            ARRAY_FILTER_USE_KEY,
        );
        self::assertCount(9, $perStatus);

        foreach ($perStatus as $id => $test) {
            $codes = self::quoted($test, '\(\.\)\s*=\s*');
            self::assertNotSame([], $codes, $id);
            foreach ($codes as $code) {
                self::assertNotNull(StatusReason::tryFrom($code), "{$id}: {$code}");
            }
        }
    }

    public function test_requested_actions_match_the_action_code_list(): void
    {
        self::assertSameCodes(self::functionList('BR-FR-CDV-CL-10_MDT-121'), self::values(RequestedAction::cases()));
    }

    public function test_characteristic_types_match_the_type_code_list(): void
    {
        self::assertSameCodes(self::quoted(self::test('BR-FR-CDV-CL-11_MDT-207'), '\.\s*=\s*'), self::values(CharacteristicType::cases()));
    }

    public function test_party_roles_match_the_recipient_role_lists(): void
    {
        $roles = self::values(PartyRole::cases());

        self::assertSameCodes(self::quoted(self::test('BR-FR-CDV-CL-04_MDT-59'), '\.\s*=\s*'), $roles);
        self::assertSameCodes(self::quoted(self::test('BR-FR-CDV-CL-08_MDT-158'), '\.\s*=\s*'), $roles);
    }

    public function test_every_sender_and_issuer_role_is_a_party_role(): void
    {
        foreach (['BR-FR-CDV-CL-02_MDT-21_305', 'BR-FR-CDV-CL-02_MDT-21_23', 'BR-FR-CDV-CL-03_MDT-40_305', 'BR-FR-CDV-CL-03_MDT-40_23'] as $id) {
            $codes = self::quoted(self::test($id), 'RoleCode\s*=\s*');
            self::assertNotSame([], $codes, $id);
            foreach ($codes as $code) {
                self::assertNotNull(PartyRole::tryFrom($code), "{$id}: {$code}");
            }
        }
    }

    public function test_regulatory_scopes_match_the_business_process_list(): void
    {
        $test = self::test('BR-FR-CDV-CL-01_MDT-2');
        if (preg_match('/ram:ID\s*=\s*\(([^)]*)\)/', $test, $match) !== 1) {
            self::fail('BR-FR-CDV-CL-01_MDT-2 has no ID list.');
        }

        self::assertSameCodes(self::quoted($match[1], ''), self::values(RegulatoryScope::cases()));
    }

    private static function rules(): RuleSet
    {
        return self::$rules ??= FranceRules::load(InstalledFranceRules::dir(), 'cdar');
    }

    /**
     * @return array<string, string> assert id => test
     */
    private static function asserts(): array
    {
        $asserts = [];
        foreach (self::rules()->patterns() as $pattern) {
            foreach ($pattern['rules'] as $rule) {
                foreach ($rule['asserts'] as $assert) {
                    $asserts[$assert['id']] = $assert['test'];
                }
            }
        }

        return $asserts;
    }

    private static function test(string $id): string
    {
        $asserts = self::asserts();
        self::assertArrayHasKey($id, $asserts);

        return $asserts[$id];
    }

    /**
     * The codes of an assert whose test calls a one-argument list function, such as
     * custom:is-valid-status-reason-code(.): the whitespace-separated string the function declares.
     *
     * @return list<string>
     */
    private static function functionList(string $id): array
    {
        if (preg_match('/^\s*([\w-]+):([\w-]+)\(\.\)\s*$/', self::test($id), $call) !== 1) {
            self::fail("{$id} does not call a list function.");
        }
        $namespaces = self::rules()->namespaces();
        self::assertArrayHasKey($call[1], $namespaces);
        $functions = self::rules()->functions();
        $key = '{'.$namespaces[$call[1]].'}'.$call[2].'#1';
        self::assertArrayHasKey($key, $functions);

        $lists = [];
        foreach ($functions[$key]['body'] as $instruction) {
            if (($instruction[0] ?? null) === 'variable'
                && is_array($instruction[3] ?? null) && ($instruction[3][0] ?? null) === 'literal' && is_string($instruction[3][2] ?? null)) {
                $lists[] = $instruction[3][2];
            }
        }
        self::assertCount(1, $lists, $key);

        return preg_split('/\s+/', trim($lists[0]), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * The single-quoted values that follow $prefix (a regular expression fragment) in $text.
     *
     * @return list<string>
     */
    private static function quoted(string $text, string $prefix): array
    {
        preg_match_all('/'.$prefix."'([^']*)'/", $text, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @param  list<\BackedEnum>  $cases
     * @return list<string>
     */
    private static function values(array $cases): array
    {
        return array_map(static fn (\BackedEnum $case) => (string) $case->value, $cases);
    }

    /**
     * @param  list<string>  $expected
     * @param  list<string>  $actual
     */
    private static function assertSameCodes(array $expected, array $actual): void
    {
        self::assertNotSame([], $expected);
        sort($expected);
        sort($actual);
        self::assertSame($expected, $actual);
    }
}
