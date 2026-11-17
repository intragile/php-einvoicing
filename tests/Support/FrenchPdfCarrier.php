<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use IntrAgile\EInvoicing\Pdf\IncrementalUpdate;
use IntrAgile\EInvoicing\Pdf\NameTree;
use IntrAgile\EInvoicing\Pdf\PdfDictionary;
use IntrAgile\EInvoicing\Pdf\PdfDocument;
use IntrAgile\EInvoicing\Pdf\PdfRef;
use IntrAgile\EInvoicing\Tests\Fixtures;
use RuntimeException;

/**
 * A French Factur-X PDF from the FNFE examples with its embedded invoice removed, so that another
 * invoice can be embedded into a real French PDF/A-3 page.
 *
 * The FNFE files are read from the downloaded fixtures at test time and never committed. The
 * invoice is removed by an incremental update that drops the catalog /AF array and the
 * /EmbeddedFiles name tree and overwrites the removed objects with null; the original bytes stay
 * unchanged.
 */
final class FrenchPdfCarrier
{
    /** The FNFE sub-line example (EXTENDED), whose only attachment is its factur-x.xml. */
    public const EXTENDED_EXAMPLE = 'FNFE_RFE_INVOICE/Z.example/TEST/FX/EXTENDED/UC12_F202600025_SOUS-LIGNE_EXTENDED_FX.pdf';

    private function __construct() {}

    /**
     * Skips the test when the France_RFE fixtures are not downloaded.
     *
     * @throws RuntimeException when the PDF carries another attachment besides factur-x.xml
     */
    public static function pdf(string $example = self::EXTENDED_EXAMPLE): string
    {
        $path = Fixtures::franceRfeRoot().'/'.$example;
        $bytes = file_get_contents($path) ?: throw new RuntimeException("Cannot read {$path}.");
        $document = PdfDocument::parse($bytes);
        $catalog = $document->catalog();

        $names = $document->resolve($catalog->get('Names'));
        if (! $names instanceof PdfDictionary) {
            throw new RuntimeException("{$example} has no /Names dictionary.");
        }
        $tree = $document->resolve($names->get('EmbeddedFiles'));
        $files = $tree instanceof PdfDictionary ? NameTree::entries($document, $tree) : [];
        $attachments = array_map(static fn (array $entry): string => $entry[0]->text(), $files);
        if ($attachments !== ['factur-x.xml']) {
            throw new RuntimeException("{$example} carries the attachments [".implode(', ', $attachments).'], not only factur-x.xml.');
        }

        $entries = $catalog->entries();
        unset($entries['AF'], $entries['Names']);
        $nameEntries = $names->entries();
        unset($nameEntries['EmbeddedFiles']);
        if ($nameEntries !== []) {
            $entries['Names'] = new PdfDictionary($nameEntries);
        }

        $root = $document->trailer()->get('Root');
        if (! $root instanceof PdfRef) {
            throw new RuntimeException("{$example} has no /Root reference.");
        }
        $update = new IncrementalUpdate($document);
        $update->replace($root->number, new PdfDictionary($entries));

        // The removed file specification, its embedded file and the /AF array are overwritten with
        // null too: veraPDF still finds an unreferenced embedded file and reports it as not
        // associated (ISO 19005-3 6.8-4).
        $removed = [$catalog->get('AF'), $files[0][1]];
        $filespec = $document->resolve($files[0][1]);
        if ($filespec instanceof PdfDictionary) {
            $embedded = $document->resolve($filespec->get('EF'));
            if ($embedded instanceof PdfDictionary) {
                $removed = [...$removed, $embedded->get('F'), $embedded->get('UF')];
            }
        }
        foreach (array_unique(array_map(static fn (mixed $ref): int => $ref instanceof PdfRef ? $ref->number : 0, $removed)) as $number) {
            if ($number > 0) {
                $update->replace($number, null);
            }
        }

        return $update->toBytes();
    }
}
