<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

use DOMElement;

/**
 * A SOAP 1.1 fault returned by the AEAT web service.
 *
 * The fault string of an AEAT fault starts with "Codigo[NNNN]."; the number is exposed as $code.
 */
final readonly class SoapFault
{
    public function __construct(
        /** The local part of faultcode: "Client", "Server", a dotted subcode of either or another value. */
        public string $faultCode,
        /** The fault string, trimmed, with inner whitespace collapsed. */
        public string $faultString,
        /** The AEAT error number from "Codigo[NNNN]", when the fault string carries one. */
        public ?int $code,
    ) {}

    /**
     * Reads a soap:Fault element; faultcode and faultstring carry no namespace in SOAP 1.1.
     */
    public static function fromElement(DOMElement $fault): self
    {
        $rawCode = '';
        $rawString = '';
        foreach ($fault->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }
            if ($child->localName === 'faultcode') {
                $rawCode = trim($child->textContent);
            } elseif ($child->localName === 'faultstring') {
                $rawString = $child->textContent;
            }
        }

        $colon = strrpos($rawCode, ':');
        $faultCode = $colon === false ? $rawCode : substr($rawCode, $colon + 1);
        $faultString = trim((string) preg_replace('/\s+/u', ' ', $rawString));
        $code = preg_match('/Codigo\[(\d+)\]/', $faultString, $matches) === 1 ? (int) $matches[1] : null;

        return new self($faultCode, $faultString, $code);
    }

    /**
     * Whether the fault class is Client, a SOAP 1.1 dotted subcode such as "Client.Foo" included.
     */
    public function isClient(): bool
    {
        return $this->hasClass('Client');
    }

    /**
     * Whether the fault class is Server, a SOAP 1.1 dotted subcode such as "Server.Bar" included.
     */
    public function isServer(): bool
    {
        return $this->hasClass('Server');
    }

    private function hasClass(string $class): bool
    {
        return $this->faultCode === $class || str_starts_with($this->faultCode, $class.'.');
    }
}
