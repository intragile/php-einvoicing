<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\FacturX;

use IntrAgile\EInvoicing\Exception\UnsupportedDocument;

/**
 * Thrown when the input PDF declares a PDF/A part above 3 (PDF/A-4 or later). Factur-X needs a
 * PDF/A-3 declaration, and a later part cannot be lowered to it.
 */
final class UnsupportedPdfAPart extends UnsupportedDocument {}
