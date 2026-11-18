<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Format;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use InvalidArgumentException;

/**
 * One FA(3) element carried verbatim, for the rarely used blocks the model does not type
 * (transport details, the authorised party, the attachment). The writer re-emits it in its
 * schema position and the schema validates it.
 */
final readonly class RawFragment
{
    /** Local name of the element. */
    public string $name;

    /**
     * @param  string  $xml  A single element in the FA(3) namespace, without a DOCTYPE.
     *
     * @throws InvalidArgumentException when the text is not one well-formed, safe element in the
     *                                  FA(3) namespace
     */
    public function __construct(public string $xml)
    {
        try {
            $element = SafeXml::load($xml)->documentElement;
        } catch (UnsafeXml|UnsupportedDocument $e) {
            throw new InvalidArgumentException('A raw FA(3) fragment must be one well-formed element: '.$e->getMessage(), 0, $e);
        }
        if ($element === null || $element->localName === null) {
            throw new InvalidArgumentException('A raw FA(3) fragment must be one named element.');
        }
        if ($element->namespaceURI !== Fa3Format::NS) {
            throw new InvalidArgumentException(sprintf(
                'A raw FA(3) fragment must be an element in the namespace %s, got "%s".',
                Fa3Format::NS,
                $element->namespaceURI ?? '',
            ));
        }
        $this->name = $element->localName;
    }
}
