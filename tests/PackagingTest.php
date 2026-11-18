<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests;

use IntrAgile\EInvoicing\Tests\Support\Process;
use PHPUnit\Framework\TestCase;

/**
 * Guards the Composer metadata of the core and of the three rule packages released with it:
 * licences, the shared version line, the cross-package constraints and what ships in the
 * dist archives.
 */
final class PackagingTest extends TestCase
{
    private const ROOT = __DIR__.'/..';

    /** Directory under packages/ => SPDX licence id of that rule package. */
    private const RULE_PACKAGES = [
        'rules-en16931' => 'EUPL-1.2',
        'rules-xrechnung' => 'Apache-2.0',
        'rules-facturx' => 'Apache-2.0',
    ];

    private const CORE = 'intragile/php-einvoicing';

    private const RULE_CONFLICT = '<1.0 || >=2.0';

    public function test_every_package_has_matching_license_files(): void
    {
        self::assertSame('MIT', self::value(self::composer(''), 'license'));
        self::assertFileExists(self::ROOT.'/LICENSE');

        foreach (self::RULE_PACKAGES as $dir => $spdx) {
            $path = self::ROOT."/packages/{$dir}";
            self::assertSame($spdx, self::value(self::composer($dir), 'license'), $dir);
            self::assertFileExists("{$path}/LICENSE");
            if ($spdx === 'Apache-2.0') {
                self::assertFileExists("{$path}/NOTICE");
            }
        }
    }

    public function test_the_facturx_licence_is_the_plain_apache_text(): void
    {
        $license = (string) file_get_contents(self::ROOT.'/packages/rules-facturx/LICENSE');
        $notice = (string) file_get_contents(self::ROOT.'/packages/rules-facturx/NOTICE');

        self::assertStringStartsWith('Apache License', ltrim($license));
        self::assertStringEndsWith('limitations under the License.', rtrim($license));
        self::assertStringNotContainsString('EXTERNAL COMPONENTS', $license);
        self::assertStringContainsString('EXTERNAL COMPONENTS', $notice);
    }

    public function test_versions_are_aligned(): void
    {
        $alias = self::value(self::composer(''), 'extra', 'branch-alias', 'dev-main');
        self::assertSame('1.0.x-dev', $alias);

        foreach (array_keys(self::RULE_PACKAGES) as $dir) {
            self::assertSame($alias, self::value(self::composer($dir), 'extra', 'branch-alias', 'dev-main'), $dir);
        }
    }

    public function test_core_requires_and_conflicts(): void
    {
        $core = self::composer('');

        self::assertSame('^1.0', self::value($core, 'require', 'intragile/einvoicing-rules-en16931'));
        self::assertSame([
            'intragile/einvoicing-rules-facturx' => self::RULE_CONFLICT,
            'intragile/einvoicing-rules-xrechnung' => self::RULE_CONFLICT,
        ], self::value($core, 'conflict'));
        self::assertSame('^1.0', self::value($core, 'require-dev', 'intragile/einvoicing-rules-facturx'));
        self::assertSame('^1.0', self::value($core, 'require-dev', 'intragile/einvoicing-rules-xrechnung'));
        self::assertSame('stable', self::value($core, 'minimum-stability'));

        $suggestion = self::value($core, 'suggest', 'intragile/einvoicing-rules-facturx');
        self::assertIsString($suggestion);
        self::assertStringContainsString('needed for writing and validating Factur-X/ZUGFeRD profiles other than XRECHNUNG', $suggestion);
        self::assertIsString(self::value($core, 'suggest', 'intragile/einvoicing-rules-xrechnung'));
    }

    public function test_core_path_repositories_pin_the_rule_packages_to_the_release_version(): void
    {
        $repositories = self::value(self::composer(''), 'repositories');
        self::assertIsArray($repositories);

        $versions = [];
        foreach ($repositories as $repository) {
            self::assertIsArray($repository);
            self::assertSame('path', self::value($repository, 'type'));
            $pinned = self::value($repository, 'options', 'versions');
            self::assertIsArray($pinned);
            $versions += $pinned;
        }
        ksort($versions);

        self::assertSame([
            'intragile/einvoicing-rules-en16931' => '1.0.0',
            'intragile/einvoicing-rules-facturx' => '1.0.0',
            'intragile/einvoicing-rules-xrechnung' => '1.0.0',
        ], $versions);
    }

    public function test_rule_packages_conflict_with_old_cores(): void
    {
        foreach (array_keys(self::RULE_PACKAGES) as $dir) {
            $package = self::composer($dir);

            self::assertSame([self::CORE => self::RULE_CONFLICT], self::value($package, 'conflict'), $dir);
            // Each rule package is published from its own split repository.
            $repository = 'https://github.com/intragile/einvoicing-'.$dir;
            self::assertSame($repository.'/issues', self::value($package, 'support', 'issues'), $dir);
            self::assertSame($repository, self::value($package, 'support', 'source'), $dir);
            self::assertSame('https://intragile.eu', self::value($package, 'homepage'), $dir);
        }
    }

    public function test_dist_excludes_development_files(): void
    {
        $ignored = self::exportIgnored(self::ROOT.'/.gitattributes');

        foreach (['/tests', '/docker', '/packages', '/bin/compile-schematron.php', '/bin/compile-xrechnung.php', '/bin/compile-facturx.php'] as $path) {
            self::assertContains($path, $ignored);
        }
        self::assertNotContains('/bin/einvoicing-rules', $ignored);
        // The bundled FA(3) schemas are used at run time, so the dist archive keeps them.
        foreach (['/resources', '/resources/poland', '/resources/poland/**'] as $path) {
            self::assertNotContains($path, $ignored);
        }

        // A rule package ships everything it contains; only its own attributes file stays out.
        foreach (array_keys(self::RULE_PACKAGES) as $dir) {
            $attributes = self::ROOT."/packages/{$dir}/.gitattributes";
            self::assertFileExists($attributes);
            self::assertSame(['/.gitattributes'], self::exportIgnored($attributes), $dir);
            self::assertStringContainsString('* text=auto eol=lf', (string) file_get_contents($attributes), $dir);
        }
    }

    public function test_no_peppol_or_jar_files_are_tracked(): void
    {
        $result = Process::run(['git', '-C', self::ROOT, 'ls-files', '-z'], 60.0);
        if ($result['status'] !== 0) {
            self::markTestSkipped('Not a git checkout: '.trim($result['stderr']));
        }

        $offending = [];
        foreach (array_filter(explode("\0", $result['stdout'])) as $path) {
            if (str_ends_with(strtolower($path), '.jar')) {
                $offending[] = $path;

                continue;
            }
            if (str_starts_with($path, 'src/') || str_starts_with($path, 'docs/')) {
                continue;
            }
            // OpenPeppol artefacts live in folders and files named peppol-*, and are downloaded,
            // never committed. The SHA-256 manifest of a downloaded folder is a list of hashes.
            foreach (explode('/', $path) as $segment) {
                if (str_starts_with($segment, 'peppol') && ! str_ends_with($segment, '.sha256')) {
                    $offending[] = $path;
                }
            }
            if (preg_match('~(^|/)[^/]*peppol[^/]*\.(sch|xsl|xslt)$~i', $path) === 1) {
                $offending[] = $path;
            }
        }

        self::assertSame([], array_values(array_unique($offending)));
    }

    /**
     * @return array<mixed>
     */
    private static function composer(string $package): array
    {
        $file = $package === '' ? self::ROOT.'/composer.json' : self::ROOT."/packages/{$package}/composer.json";
        $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    /**
     * @param  array<mixed>  $data
     * @return mixed the value at the key path, or null when a key on the way is missing
     */
    private static function value(array $data, string ...$keys): mixed
    {
        $value = $data;
        foreach ($keys as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * @return list<string> the patterns of $file that carry the export-ignore attribute
     */
    private static function exportIgnored(string $file): array
    {
        $patterns = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $fields = preg_split('/\s+/', trim($line)) ?: [];
            if ($fields === [] || str_starts_with($fields[0], '#')) {
                continue;
            }
            if (in_array('export-ignore', array_slice($fields, 1), true)) {
                $patterns[] = $fields[0];
            }
        }

        return $patterns;
    }
}
