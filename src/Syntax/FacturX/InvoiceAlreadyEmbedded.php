<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\FacturX;

use IntrAgile\EInvoicing\Exception\UnsupportedDocument;

/**
 * Thrown when a PDF already carries an embedded Factur-X, ZUGFeRD or XRechnung invoice, so
 * embedding another would leave two competing invoices in one document.
 */
final class InvoiceAlreadyEmbedded extends UnsupportedDocument {}
