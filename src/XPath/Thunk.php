<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\XPath;

use Closure;
use DOMNode;
use IntrAgile\EInvoicing\XPath\Value\AtomicValue;

/**
 * The value of a variable, computed on first use and then kept: a Schematron let or an XSLT local
 * variable that is never read is never evaluated, as with Saxon, so its dynamic error is never
 * raised. A dynamic error is kept as well and raised again, as the same exception, on every later
 * use. Reading the variable while it is being computed is a circular reference (XTDE0640).
 *
 * @internal
 */
final class Thunk
{
    /** @var (Closure(): list<DOMNode|AtomicValue>)|null */
    private ?Closure $compute;

    /** @var list<DOMNode|AtomicValue>|null */
    private ?array $value = null;

    private ?XPathError $error = null;

    private bool $computing = false;

    /**
     * @param  Closure(): list<DOMNode|AtomicValue>  $compute
     */
    public function __construct(Closure $compute)
    {
        $this->compute = $compute;
    }

    /**
     * @return list<DOMNode|AtomicValue>
     *
     * @throws XPathError
     */
    public function value(): array
    {
        if ($this->value !== null) {
            return $this->value;
        }
        if ($this->error !== null) {
            throw $this->error;
        }
        if ($this->computing || $this->compute === null) {
            throw new XPathError('XTDE0640', 'The value of a variable depends on itself (circular reference).');
        }

        $this->computing = true;
        try {
            $this->value = ($this->compute)();
        } catch (XPathError $error) {
            $this->error = $error;

            throw $error;
        } finally {
            $this->computing = false;
        }
        // The closure may hold the whole scope it was created in; it is not needed any more.
        $this->compute = null;

        return $this->value;
    }
}
