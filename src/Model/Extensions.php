<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

/**
 * Immutable bag of typed extension objects keyed by class, for syntax or national data that
 * EN 16931 does not model (for example a CIUS specific field or a writer override).
 */
final readonly class Extensions
{
    /**
     * @param  array<class-string, object>  $items
     */
    private function __construct(private array $items) {}

    public static function none(): self
    {
        return new self([]);
    }

    /**
     * Returns a copy holding the extension, replacing any earlier extension of the same class.
     */
    public function with(object $extension): self
    {
        return new self([...$this->items, $extension::class => $extension]);
    }

    /**
     * @param  class-string  $class
     */
    public function without(string $class): self
    {
        $items = $this->items;
        unset($items[$class]);

        return new self($items);
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    public function get(string $class): ?object
    {
        $extension = $this->items[$class] ?? null;

        return $extension instanceof $class ? $extension : null;
    }

    /**
     * @param  class-string  $class
     */
    public function has(string $class): bool
    {
        return isset($this->items[$class]);
    }

    /**
     * @return list<object>
     */
    public function all(): array
    {
        return array_values($this->items);
    }
}
