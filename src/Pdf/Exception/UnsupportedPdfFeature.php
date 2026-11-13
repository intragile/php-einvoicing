<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Pdf\Exception;

/**
 * Thrown when a stream that must be decoded uses a filter or predictor the parser does not
 * implement; the message names it.
 */
final class UnsupportedPdfFeature extends UnreadablePdf {}
