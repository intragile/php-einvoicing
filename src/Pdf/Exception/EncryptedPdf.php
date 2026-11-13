<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Pdf\Exception;

/**
 * Thrown when the PDF is encrypted (its trailer has /Encrypt). PDF/A forbids encryption, and the
 * parser does not decrypt.
 */
final class EncryptedPdf extends UnreadablePdf {}
