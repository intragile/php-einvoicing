<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\Install;

use IntrAgile\EInvoicing\Schematron\CompileOptions;

/**
 * One pinned Schematron file that LocalRuleInstaller compiles into "<id>-<version>.php".
 *
 * @internal
 */
final readonly class RuleSource
{
    /**
     * @param  string  $key  the name the caller knows the rule set by
     * @param  string  $file  path of the .sch file below the download base URL or source folder
     * @param  string  $sha256  pinned SHA-256 of the .sch file
     * @param  array<string, string>  $companions  files read by document(), stored next to the
     *                                             .sch file: file name => pinned SHA-256
     */
    public function __construct(
        public string $key,
        public string $file,
        public string $sha256,
        public string $id,
        public string $version,
        public ?CompileOptions $options = null,
        public array $companions = [],
    ) {}
}
