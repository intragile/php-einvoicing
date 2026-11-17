<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\France;

use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;
use InvalidArgumentException;

/**
 * Loads the French rule sets and XML Schemas that RuleInstaller wrote into a folder.
 */
final class FranceRules
{
    /** Folder below the rules folder that holds the XML Schemas at their repository paths. */
    public const SCHEMA_DIR = 'france-rfe-'.FranceSources::VERSION;

    /**
     * Path of the compiled file of one rule set in a rules folder.
     */
    public static function path(string $dir, string $id): string
    {
        return rtrim($dir, '/\\').'/'.$id.'-'.FranceSources::VERSION.'.php';
    }

    /**
     * One compiled rule set, by its FranceSources::ruleSources() key.
     *
     * @throws InvalidArgumentException when the key names no French rule set
     * @throws RulesNotInstalled when the compiled file is missing from the folder
     * @throws UnsupportedSchematron when the file was compiled by an engine of another format
     *                               (the message asks to recompile it), is corrupt, or is not the
     *                               pinned rule set (the message asks to reinstall)
     */
    public static function load(string $dir, string $key): RuleSet
    {
        $source = FranceSources::ruleSources()[$key]
            ?? throw new InvalidArgumentException("Unknown French rule set \"{$key}\"; expected one of ".implode(', ', array_keys(FranceSources::ruleSources())).'.');
        $path = self::path($dir, $source['id']);
        if (! is_file($path)) {
            throw self::notInstalled($dir, 'rules');
        }

        $ruleSet = RuleSet::fromFile($path);
        if ($ruleSet->id() !== $source['id']
            || $ruleSet->version() !== FranceSources::VERSION
            || ($ruleSet->sourceSha256() !== null && $ruleSet->sourceSha256() !== $source['sha256'])) {
            throw new UnsupportedSchematron(sprintf(
                '"%s" holds the rule set "%s %s", not "%s %s" compiled from the pinned source; reinstall the rules.',
                $path,
                $ruleSet->id(),
                $ruleSet->version(),
                $source['id'],
                FranceSources::VERSION,
            ));
        }

        return $ruleSet;
    }

    /**
     * Absolute path of an installed XML Schema.
     *
     * @param  string  $tree  "cii", "ubl", "cdar" or "flux10"
     * @param  string  $file  path of the schema below the root of its tree
     *
     * @throws InvalidArgumentException when the tree or file is not part of the pinned schemas
     * @throws RulesNotInstalled when the schema file is missing from the folder
     */
    public static function schema(string $dir, string $tree, string $file): string
    {
        $relative = FranceSources::schemaRoot($tree).'/'.$file;
        if (! isset(FranceSources::files('schema')[$relative])) {
            throw new InvalidArgumentException("\"{$file}\" is not a France_RFE ".FranceSources::VERSION." schema of the \"{$tree}\" tree.");
        }
        $path = rtrim($dir, '/\\').'/'.self::SCHEMA_DIR.'/'.$relative;
        if (! is_file($path)) {
            throw self::notInstalled($dir, 'schemas');
        }

        return $path;
    }

    private static function notInstalled(string $dir, string $what): RulesNotInstalled
    {
        return new RulesNotInstalled(sprintf(
            'The France_RFE %1$s %2$s are not installed in "%3$s". Run: vendor/bin/einvoicing-rules install france --dir="%3$s"',
            FranceSources::VERSION,
            $what,
            $dir,
        ));
    }
}
