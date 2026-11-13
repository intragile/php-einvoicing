<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax;

use DOMDocument;
use IntrAgile\EInvoicing\Rules\XRechnung\RuleFiles;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Syntax\XRechnung;
use IntrAgile\EInvoicing\XPath\DocumentIndex;
use IntrAgile\EInvoicing\XPath\Evaluator;
use IntrAgile\EInvoicing\XPath\Functions;
use IntrAgile\EInvoicing\XPath\XPathError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class XRechnungTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function ruleFiles(): iterable
    {
        yield 'ubl' => [RuleFiles::ubl()];
        yield 'cii' => [RuleFiles::cii()];
    }

    #[DataProvider('ruleFiles')]
    public function test_constants_equal_compiled_lets(string $ruleFile): void
    {
        $lets = self::evaluateLets(RuleSet::fromFile($ruleFile));

        self::assertSame(XRechnung::CIUS, $lets['XR-CIUS-ID']);
        self::assertSame(XRechnung::EXTENSION, $lets['XR-EXTENSION-ID']);
        self::assertSame(XRechnung::CVD, $lets['XR-CVD-ID']);
    }

    public function test_process_control_defaults_to_the_cius_and_the_peppol_profile(): void
    {
        $control = XRechnung::processControl();

        self::assertSame(XRechnung::CIUS, $control->specificationIdentifier);
        self::assertSame(XRechnung::PEPPOL_PROFILE, $control->businessProcessType);
        self::assertSame(XRechnung::EXTENSION, XRechnung::processControl(XRechnung::EXTENSION)->specificationIdentifier);
    }

    /**
     * The string values of the schema-level lets that do not depend on the document, evaluated in
     * declaration order on an empty document.
     *
     * @return array<string, string>
     */
    private static function evaluateLets(RuleSet $rules): array
    {
        $document = new DOMDocument;
        $document->loadXML('<empty/>');
        $evaluator = new Evaluator(DocumentIndex::build($document));
        $variables = [];
        $values = [];
        foreach ($rules->lets() as $let) {
            try {
                $variables[$let['name']] = $evaluator->evaluateGlobal($let['ast'], $document, $variables);
            } catch (XPathError) {
                continue;
            }
            $items = Functions::data($variables[$let['name']]);
            if (count($items) === 1) {
                $values[$let['name']] = $items[0]->lexical();
            }
        }

        return $values;
    }
}
