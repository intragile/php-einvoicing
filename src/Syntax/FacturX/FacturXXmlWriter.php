<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\FacturX;

use DOMDocument;
use IntrAgile\EInvoicing\France\FrenchInvoice;
use IntrAgile\EInvoicing\France\FrenchProfile;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\ProcessControl;
use IntrAgile\EInvoicing\Rules\FacturX\RuleFiles;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;
use IntrAgile\EInvoicing\Validation\Exception\UnusableSchema;
use IntrAgile\EInvoicing\Validation\SchemaValidator;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * Writes the CII document of a Factur-X / ZUGFeRD profile.
 *
 * The invoice is written with CiiWriter under the profile's guideline identifier (BT-24), keeping
 * its business process type (BT-23). For the Factur-X profiles the document is then reduced to
 * what the profile's XML Schema from intragile/einvoicing-rules-facturx allows, and the nodes the
 * profile's Schematron rules prohibit unconditionally are removed too; the result lists
 * every removed node. The result is then validated against the profile schema once more, since
 * the reduction only removes names and does not check what remains. The XRechnung profile embeds
 * the full CII document, so nothing is removed.
 */
final class FacturXXmlWriter
{
    private const COMPOSER_COMMAND = 'composer require intragile/einvoicing-rules-facturx';

    public function __construct(
        private readonly CiiWriter $writer = new CiiWriter,
        private readonly ProfileReducer $reducer = new ProfileReducer,
        private readonly ProhibitionPruner $pruner = new ProhibitionPruner,
        private readonly SchemaValidator $validator = new SchemaValidator,
    ) {}

    /**
     * @throws InvalidArgumentException when the invoice is an EXTENDED-CTC-FR French invoice and the
     *                                  profile is not EXTENDED
     * @throws RulesNotInstalled when a Factur-X profile is requested and intragile/einvoicing-rules-facturx is not installed
     * @throws UnusableSchema when the installed profile schema cannot be used for the reduction
     * @throws LogicException when the reduced document does not validate against the profile
     *                        schema, which is a defect of this writer
     */
    public function write(Invoice $invoice, FacturXProfile $profile): ReducedXml
    {
        // EXTENDED-CTC-FR data (EXT-FR-FE) exists only in the EXTENDED profile; reducing it away
        // would silently drop mandatory data, so the combination is refused instead.
        if ($profile !== FacturXProfile::Extended
            && $invoice->extensions()->get(FrenchInvoice::class)?->profile === FrenchProfile::ExtendedCtcFr) {
            throw new InvalidArgumentException(sprintf(
                'An EXTENDED-CTC-FR invoice cannot be written to the Factur-X %s profile: EXTENDED-CTC-FR needs Factur-X EXTENDED.',
                $profile->value,
            ));
        }

        $xml = $this->writer->write($invoice->withProcessControl(
            new ProcessControl($profile->guidelineId(), $invoice->processControl?->businessProcessType),
        ));

        $ruleProfile = $profile->ruleProfile();
        if ($ruleProfile === null) {
            return new ReducedXml($xml, []);
        }
        if (! class_exists(RuleFiles::class)) {
            throw new RulesNotInstalled("The Factur-X {$profile->value} profile needs the Factur-X profile schemas. Run: ".self::COMPOSER_COMMAND);
        }

        $schema = RuleFiles::schema($ruleProfile);
        $reduced = $this->reducer->reduce($xml, $schema);
        $document = SafeXml::loadPreservingWhitespace($reduced->xml);
        $pruned = $this->pruner->prune(RuleSet::fromFile(RuleFiles::rules($ruleProfile)), $document);
        $this->assertSchemaValid($document, $profile, $schema);
        if ($pruned === []) {
            return $reduced;
        }

        $removed = array_values(array_unique([...$reduced->removed, ...$pruned]));
        sort($removed, SORT_STRING);

        return new ReducedXml($document->saveXML() ?: throw new RuntimeException('The pruned document could not be serialised.'), $removed);
    }

    /**
     * Checks a reduced document against the profile schema at $xsdPath.
     *
     * @internal Called by write() on every reduced document; public so the failure can be tested.
     *
     * @throws LogicException naming the profile and the first violation when the document is invalid
     * @throws UnusableSchema when the schema cannot be used
     */
    public function assertSchemaValid(DOMDocument $reduced, FacturXProfile $profile, string $xsdPath): void
    {
        $errors = $this->validator->validate($reduced, $xsdPath);
        if ($errors !== []) {
            throw new LogicException(sprintf(
                'The reduced Factur-X %s document does not validate against its profile schema (%d violation(s), first: %s). This is a defect of the writer.',
                $profile->value,
                count($errors),
                $errors[0]->message,
            ));
        }
    }
}
