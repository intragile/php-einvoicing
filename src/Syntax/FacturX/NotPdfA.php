<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\FacturX;

use IntrAgile\EInvoicing\Exception\UnsupportedDocument;

/**
 * Thrown in strict mode when the input PDF does not declare PDF/A conformance in its XMP
 * metadata. The writer never converts a PDF to PDF/A; the caller has to supply one.
 */
final class NotPdfA extends UnsupportedDocument {}
