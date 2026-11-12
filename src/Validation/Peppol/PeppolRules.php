<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\Peppol;

use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;

/**
 * Loads the Peppol BIS Billing rule sets that RuleInstaller compiled into a folder.
 */
final class PeppolRules
{
    /** The rule files of each syntax, in run order: OpenPeppol's copy of the CEN rules first. */
    private const LAYERS = [
        'ubl' => ['cen-ubl', 'peppol-ubl'],
        'cii' => ['cen-cii', 'peppol-cii'],
    ];

    /**
     * Path of the compiled file of one rule source in a rules folder.
     */
    public static function path(string $dir, string $id): string
    {
        return rtrim($dir, '/\\').'/'.$id.'-'.PeppolSources::VERSION.'.php';
    }

    /**
     * @return array{ubl: list<RuleSet>, cii: list<RuleSet>} CEN layer first
     *
     * @throws RulesNotInstalled when a compiled rule file is missing from the folder
     * @throws UnsupportedSchematron when a file was compiled by an engine of another format
     *                               (the message asks to recompile it), is corrupt, or is not the
     *                               pinned rule set (the message asks to reinstall)
     */
    public static function load(string $dir): array
    {
        $sources = PeppolSources::all();
        foreach ($sources as $source) {
            if (! is_file(self::path($dir, $source['id']))) {
                throw new RulesNotInstalled(sprintf(
                    'Peppol BIS %1$s rules are not installed in "%2$s". Run: vendor/bin/einvoicing-rules install peppol --dir="%2$s"',
                    PeppolSources::VERSION,
                    $dir,
                ));
            }
        }

        $load = static function (string $key) use ($dir, $sources): RuleSet {
            $source = $sources[$key];
            $path = self::path($dir, $source['id']);
            $ruleSet = RuleSet::fromFile($path);
            if ($ruleSet->id() !== $source['id']
                || $ruleSet->version() !== PeppolSources::VERSION
                || ($ruleSet->sourceSha256() !== null && $ruleSet->sourceSha256() !== $source['sha256'])) {
                throw new UnsupportedSchematron(sprintf(
                    '"%s" holds the rule set "%s %s", not "%s %s" compiled from the pinned source; reinstall the rules.',
                    $path,
                    $ruleSet->id(),
                    $ruleSet->version(),
                    $source['id'],
                    PeppolSources::VERSION,
                ));
            }

            return $ruleSet;
        };

        return [
            'ubl' => array_map($load, self::LAYERS['ubl']),
            'cii' => array_map($load, self::LAYERS['cii']),
        ];
    }
}
