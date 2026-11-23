<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\Spain;

use IntrAgile\EInvoicing\Validation\Exception\PartialInstall;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;
use IntrAgile\EInvoicing\Validation\Exception\RuleSourceMismatch;
use IntrAgile\EInvoicing\Validation\Install\LocalRuleInstaller;
use InvalidArgumentException;
use RuntimeException;

/**
 * The AEAT VERI*FACTU schema set installed on this machine: the "tikeV1.0" XML Schemas, WSDL and
 * error catalogue together with the W3C XML Signature schema they import, all in
 * "<dir>/verifactu-tikeV1.0/".
 *
 * AEAT publishes no licence that allows redistribution, so the files are downloaded (or read from
 * a local copy) and verified against the pinned manifest of VerifactuSources; install them with
 * "vendor/bin/einvoicing-rules install spain --dir=DIR". The schemas import the signature schema
 * by its absolute W3C location; localPrefixes() maps that location onto the installed copy, so
 * validation never reaches the network.
 */
final class VerifactuSchemas
{
    public const DIR = 'verifactu-'.VerifactuSources::RELEASE;

    private const SIGNATURE_LOCATION = 'http://www.w3.org/TR/xmldsig-core/';

    private readonly string $root;

    /**
     * @param  string  $dir  the folder the set was installed into (the parent of DIR)
     */
    public function __construct(private readonly string $dir)
    {
        $this->root = rtrim($dir, '/\\').'/'.self::DIR;
    }

    /**
     * Downloads the runtime files (or reads them from $from, a folder holding them by name),
     * verifies every one against the manifest and installs them below "<dir>/verifactu-tikeV1.0/".
     * Nothing is replaced unless every file matches; if the swap fails, the previous set is
     * restored.
     *
     * @return list<string> written file paths
     *
     * @throws RuleSourceMismatch when a file does not have its pinned checksum
     * @throws PartialInstall when the new files could not be moved into place and restoring the
     *                        previous ones failed as well
     * @throws InvalidArgumentException|RuntimeException when a file cannot be read or downloaded,
     *                                                   or the folder cannot be written
     */
    public static function install(string $dir, ?string $from = null, ?LocalRuleInstaller $installer = null): array
    {
        return ($installer ?? new LocalRuleInstaller)->installFiles(
            $dir,
            VerifactuSources::files('runtime'),
            $from,
            self::DIR,
            'AEAT VERI*FACTU '.VerifactuSources::RELEASE,
            'VERI*FACTU',
        );
    }

    /**
     * @throws RulesNotInstalled naming the first runtime file that is missing or not the pinned
     *                           file, with the command that installs the set
     */
    public function check(): void
    {
        foreach (VerifactuSources::files('runtime') as $name => $file) {
            $path = $this->root.'/'.$name;
            $problem = match (true) {
                ! is_file($path) => 'is missing',
                hash_file('sha256', $path) !== $file['sha256'] => 'is not the pinned file',
                default => null,
            };
            if ($problem !== null) {
                throw new RulesNotInstalled(sprintf(
                    'The VERI*FACTU file "%s" %s. Run: vendor/bin/einvoicing-rules install spain --dir="%s"',
                    $path,
                    $problem,
                    $this->dir,
                ));
            }
        }
    }

    /** SuministroInformacion.xsd: the record types; bare RegistroAlta and RegistroAnulacion validate against it. */
    public function informationXsd(): string
    {
        return $this->root.'/SuministroInformacion.xsd';
    }

    /** SuministroLR.xsd: the RegFactuSistemaFacturacion submission. */
    public function submissionXsd(): string
    {
        return $this->root.'/SuministroLR.xsd';
    }

    /** RespuestaSuministro.xsd: the response to a submission. */
    public function responseXsd(): string
    {
        return $this->root.'/RespuestaSuministro.xsd';
    }

    /** ConsultaLR.xsd: the record query. */
    public function queryXsd(): string
    {
        return $this->root.'/ConsultaLR.xsd';
    }

    /** RespuestaConsultaLR.xsd: the response to a record query. */
    public function queryResponseXsd(): string
    {
        return $this->root.'/RespuestaConsultaLR.xsd';
    }

    public function wsdl(): string
    {
        return $this->root.'/SistemaFacturacion.wsdl';
    }

    /** errores.properties: the AEAT error codes and their descriptions. */
    public function errorCatalogue(): string
    {
        return $this->root.'/errores.properties';
    }

    /**
     * The prefix map for SchemaValidator that resolves the W3C signature schema import offline.
     *
     * @return array<string, string>
     */
    public function localPrefixes(): array
    {
        return [self::SIGNATURE_LOCATION => $this->root];
    }
}
