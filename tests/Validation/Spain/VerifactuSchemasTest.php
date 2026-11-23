<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\Spain;

use DOMDocument;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Validation\Peppol\BuildDir;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;
use IntrAgile\EInvoicing\Validation\Exception\RuleSourceMismatch;
use IntrAgile\EInvoicing\Validation\Install\LocalRuleInstaller;
use IntrAgile\EInvoicing\Validation\Peppol\Downloader;
use IntrAgile\EInvoicing\Validation\SchemaValidator;
use IntrAgile\EInvoicing\Validation\Spain\VerifactuSchemas;
use IntrAgile\EInvoicing\Validation\Spain\VerifactuSources;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class VerifactuSchemasTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = BuildDir::create('verifactu-schemas-test');
    }

    protected function tearDown(): void
    {
        BuildDir::remove($this->dir);
    }

    #[Group('verifactu-official')]
    public function test_install_from_local_folder(): void
    {
        $from = $this->copyOfficialFiles();

        $written = VerifactuSchemas::install("{$this->dir}/rules", $from);

        $installed = "{$this->dir}/rules/".VerifactuSchemas::DIR;
        self::assertCount(8, $written);
        self::assertSame(
            array_map(static fn (string $name): string => "{$installed}/{$name}", array_keys(VerifactuSources::files('runtime'))),
            $written,
        );
        $schemas = new VerifactuSchemas("{$this->dir}/rules");
        $schemas->check();
        self::assertSame("{$installed}/SuministroInformacion.xsd", $schemas->informationXsd());
        self::assertSame("{$installed}/SuministroLR.xsd", $schemas->submissionXsd());
        self::assertSame("{$installed}/RespuestaSuministro.xsd", $schemas->responseXsd());
        self::assertSame("{$installed}/ConsultaLR.xsd", $schemas->queryXsd());
        self::assertSame("{$installed}/RespuestaConsultaLR.xsd", $schemas->queryResponseXsd());
        self::assertSame("{$installed}/SistemaFacturacion.wsdl", $schemas->wsdl());
        self::assertSame("{$installed}/errores.properties", $schemas->errorCatalogue());
        self::assertSame(['http://www.w3.org/TR/xmldsig-core/' => $installed], $schemas->localPrefixes());
    }

    #[Group('verifactu-official')]
    public function test_tampered_file_installs_nothing(): void
    {
        $from = $this->copyOfficialFiles();
        $tampered = "{$from}/SuministroInformacion.xsd";
        $contents = (string) file_get_contents($tampered);
        file_put_contents($tampered, substr_replace($contents, $contents[100] === 'a' ? 'b' : 'a', 100, 1));
        $previous = "{$this->dir}/rules/".VerifactuSchemas::DIR.'/SuministroLR.xsd';
        mkdir(dirname($previous), 0775, true);
        file_put_contents($previous, '<previous/>');

        $exception = null;
        try {
            VerifactuSchemas::install("{$this->dir}/rules", $from);
        } catch (RuleSourceMismatch $caught) {
            $exception = $caught;
        }

        self::assertNotNull($exception);
        self::assertStringContainsString('SuministroInformacion.xsd', $exception->getMessage());
        self::assertSame(['SuministroLR.xsd'], array_values(array_diff(scandir(dirname($previous)) ?: [], ['.', '..'])));
        self::assertSame('<previous/>', file_get_contents($previous));
        self::assertSame([VerifactuSchemas::DIR], array_values(array_diff(scandir("{$this->dir}/rules") ?: [], ['.', '..'])));
    }

    public function test_check_names_the_missing_file(): void
    {
        $schemas = new VerifactuSchemas($this->dir);

        try {
            $schemas->check();
            self::fail('A missing installation must be reported.');
        } catch (RulesNotInstalled $exception) {
            self::assertStringContainsString('/'.VerifactuSchemas::DIR.'/SuministroLR.xsd', $exception->getMessage());
            self::assertStringContainsString('missing', $exception->getMessage());
            self::assertStringContainsString("vendor/bin/einvoicing-rules install spain --dir=\"{$this->dir}\"", $exception->getMessage());
        }

        // Every file present but one altered: that file is named.
        $installed = "{$this->dir}/".VerifactuSchemas::DIR;
        mkdir($installed);
        foreach (array_keys(VerifactuSources::files('runtime')) as $name) {
            file_put_contents("{$installed}/{$name}", 'not the published file');
        }
        try {
            $schemas->check();
            self::fail('An altered file must be reported.');
        } catch (RulesNotInstalled $exception) {
            self::assertStringContainsString('/'.VerifactuSchemas::DIR.'/SuministroLR.xsd', $exception->getMessage());
            self::assertStringContainsString('not the pinned file', $exception->getMessage());
        }
    }

    #[Group('network')]
    public function test_download_matches_the_manifest(): void
    {
        $written = VerifactuSchemas::install($this->dir, null, new LocalRuleInstaller(new Downloader(60)));

        self::assertCount(8, $written);
        (new VerifactuSchemas($this->dir))->check();
    }

    #[Group('verifactu-official')]
    public function test_fixture_schemas_validate_aeat_example(): void
    {
        $schemas = Fixtures::verifactuSchemas();
        $schemas->check();
        $document = new DOMDocument;
        self::assertTrue($document->load(Fixtures::verifactuExample(), LIBXML_NONET));

        self::assertSame([], (new SchemaValidator)->validate($document, $schemas->informationXsd(), $schemas->localPrefixes()));
    }

    /**
     * A folder holding the eight runtime files, copied from the downloaded fixtures.
     */
    private function copyOfficialFiles(): string
    {
        $fixture = Fixtures::verifactuSchemas();
        $fixture->check();
        $from = "{$this->dir}/from";
        mkdir($from);
        foreach (array_keys(VerifactuSources::files('runtime')) as $name) {
            copy(dirname($fixture->informationXsd())."/{$name}", "{$from}/{$name}");
        }

        return $from;
    }
}
