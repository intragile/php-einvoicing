<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\FacturX;

use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\ProcessControl;
use IntrAgile\EInvoicing\Rules\FacturX\RuleFiles;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;
use IntrAgile\EInvoicing\Validation\Exception\UnusableSchema;

/**
 * Writes the CII document of a Factur-X / ZUGFeRD profile.
 *
 * The invoice is written with CiiWriter under the profile's guideline identifier (BT-24), keeping
 * its business process type (BT-23). For the Factur-X profiles the document is then reduced to
 * what the profile's XML Schema from intragile/einvoicing-rules-facturx allows; the result lists
 * every removed node. The XRechnung profile embeds the full CII document, so nothing is removed.
 */
final class FacturXXmlWriter
{
    private const COMPOSER_COMMAND = 'composer require intragile/einvoicing-rules-facturx';

    public function __construct(
        private readonly CiiWriter $writer = new CiiWriter,
        private readonly ProfileReducer $reducer = new ProfileReducer,
    ) {}

    /**
     * @throws RulesNotInstalled when a Factur-X profile is requested and intragile/einvoicing-rules-facturx is not installed
     * @throws UnusableSchema when the installed profile schema cannot be used for the reduction
     */
    public function write(Invoice $invoice, FacturXProfile $profile): ReducedXml
    {
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

        return $this->reducer->reduce($xml, RuleFiles::schema($ruleProfile));
    }
}
