<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

/**
 * One invoice from a unit-test corpus (CEN or Peppol), extracted as a standalone XML document.
 */
final readonly class UnitTestDocument
{
    /**
     * @param  string  $file  absolute path of the corpus file the document comes from
     * @param  int  $index  zero-based position among the file's tests; 0 for a plain invoice file
     * @param  'ubl'|'cii'  $syntax
     * @param  string  $corpus  name prefix that keeps keys unique across corpora, such as "peppol/"
     */
    public function __construct(
        public string $file,
        public int $index,
        public string $syntax,
        public string $xml,
        public string $corpus = '',
    ) {}

    /**
     * Stable key such as "Invoice-unit-UBL/BR-01.xml#1" or "peppol/unit-UBL-PEPPOL/PEPPOL-EN16931-R001.xml#0",
     * unique across the corpus.
     */
    public function name(): string
    {
        return $this->corpus.basename(dirname($this->file)).'/'.basename($this->file).'#'.$this->index;
    }
}
