<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Pdf;

/**
 * An immutable PDF dictionary keyed by decoded names without the slash. Values are PDF values:
 * int, float, bool, null, PdfName, PdfString, PdfRef, PdfDictionary or a list of them.
 *
 * PHP stores a numeric-looking key such as /1 as an int array key, so code that walks entries()
 * and writes names should cast keys to string.
 */
final readonly class PdfDictionary
{
    /** @param array<string, mixed> $entries */
    public function __construct(private array $entries = []) {}

    /** The value of $key, or null when it is absent (as PDF treats a missing key). */
    public function get(string $key): mixed
    {
        return $this->entries[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->entries);
    }

    /** @return array<string, mixed> */
    public function entries(): array
    {
        return $this->entries;
    }

    /** A copy with $key set to $value. */
    public function with(string $key, mixed $value): self
    {
        $entries = $this->entries;
        $entries[$key] = $value;

        return new self($entries);
    }

    /** True when $key holds the name $name, such as isName('Type', 'Catalog'). */
    public function isName(string $key, string $name): bool
    {
        $value = $this->entries[$key] ?? null;

        return $value instanceof PdfName && $value->name === $name;
    }
}
