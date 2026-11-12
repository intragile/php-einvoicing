<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\Peppol;

use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;

/**
 * Loads the Peppol BIS Billing rule sets that RuleInstaller compiled into a folder.
 *
 * The invoice rules and the envelope rules are loaded separately, so a folder written before the
 * envelope rules were installed still serves invoice validation.
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
     * @throws RulesNotInstalled when a compiled invoice rule file is missing from the folder (the
     *                           envelope rules are not needed)
     * @throws UnsupportedSchematron when a file was compiled by an engine of another format
     *                               (the message asks to recompile it), is corrupt, or is not the
     *                               pinned rule set (the message asks to reinstall)
     */
    public static function load(string $dir): array
    {
        foreach (self::LAYERS as $keys) {
            foreach ($keys as $key) {
                self::requireInstalled($dir, $key, 'rules');
            }
        }

        return [
            'ubl' => array_map(static fn (string $key): RuleSet => self::ruleSet($dir, $key), self::LAYERS['ubl']),
            'cii' => array_map(static fn (string $key): RuleSet => self::ruleSet($dir, $key), self::LAYERS['cii']),
        ];
    }

    /**
     * The rules for the Peppol Business Message Envelope (SBDH) around a Peppol BIS invoice.
     *
     * @throws RulesNotInstalled when the compiled envelope rule file is missing from the folder,
     *                           for example one written by an installer that knew only the
     *                           invoice rules
     * @throws UnsupportedSchematron when the file was compiled by an engine of another format, is
     *                               corrupt, or is not the pinned rule set
     */
    public static function loadEnvelope(string $dir): RuleSet
    {
        self::requireInstalled($dir, 'envelope', 'envelope rules');

        return self::ruleSet($dir, 'envelope');
    }

    /**
     * @throws RulesNotInstalled
     */
    private static function requireInstalled(string $dir, string $key, string $what): void
    {
        if (! is_file(self::path($dir, PeppolSources::all()[$key]['id']))) {
            throw new RulesNotInstalled(sprintf(
                'Peppol BIS %1$s %2$s are not installed in "%3$s". Run: vendor/bin/einvoicing-rules install peppol --dir="%3$s"',
                PeppolSources::VERSION,
                $what,
                $dir,
            ));
        }
    }

    /**
     * @throws UnsupportedSchematron
     */
    private static function ruleSet(string $dir, string $key): RuleSet
    {
        $source = PeppolSources::all()[$key];
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
    }
}
