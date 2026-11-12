<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath;

use RuntimeException;

/**
 * A dynamic XPath error, identified by its W3C error code (for example FORG0001).
 *
 * @internal
 */
final class XPathError extends RuntimeException
{
    /**
     * Codes of the dynamic and type errors caused by the data being evaluated. Within an XSLT match
     * pattern such an error makes the pattern not match; any other code (an unbound variable, a guard
     * such as a recursion or range limit) always propagates.
     *
     * @var list<string>
     */
    public const RECOVERABLE = ['FORG0001', 'XPTY0004', 'FORG0006', 'FOAR0001', 'FOAR0002', 'FOCA0002', 'FOCH0001', 'FOCH0002', 'FORX0002', 'FORX0003', 'FORX0004', 'FODT0001', 'FOCA0001', 'FOCA0003', 'XPTY0018', 'XPTY0019', 'XPTY0020', 'XPDY0050', 'XTTE0570', 'XTTE0780'];

    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct("{$errorCode}: {$message}");
    }

    /**
     * Whether the error is one of the data-driven dynamic errors in RECOVERABLE.
     */
    public function isRecoverable(): bool
    {
        return in_array($this->errorCode, self::RECOVERABLE, true);
    }
}
