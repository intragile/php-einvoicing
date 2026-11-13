<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\FacturX;

use IntrAgile\EInvoicing\Exception\UnsupportedDocument;

/**
 * Thrown when a Factur-X profile the caller names is not the one the document declares in its
 * guideline identifier (BT-24).
 */
final class ProfileMismatch extends UnsupportedDocument {}
