<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Model;

use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Syntax\Ubl\UblReader;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use stdClass;

/**
 * Every wither rebuilds the object through a hand-written copy(). A field missing from copy()
 * would fall back to its default. Calling each wither with the value the object already holds
 * must therefore return an equal object.
 */
final class WitherCopyTest extends TestCase
{
    public function test_every_invoice_wither_preserves_all_other_fields(): void
    {
        $invoice = $this->allTermsInvoice()->withExtension(new stdClass);
        $this->assertNoEmptyProperty($invoice);

        $checked = $this->assertWithersPreserve($invoice);

        self::assertGreaterThanOrEqual(30, $checked);
    }

    public function test_every_line_wither_preserves_all_other_fields(): void
    {
        $line = $this->allTermsInvoice()->lines[0]->withExtension(new stdClass);
        $this->assertNoEmptyProperty($line);

        $checked = $this->assertWithersPreserve($line);

        self::assertGreaterThanOrEqual(6, $checked);
    }

    private function allTermsInvoice(): Invoice
    {
        $xml = (string) file_get_contents(__DIR__.'/../fixtures/ubl/all-terms-invoice.xml');

        return (new UblReader)->read($xml)->invoice;
    }

    private function assertNoEmptyProperty(object $object): void
    {
        foreach ((new ReflectionClass($object))->getProperties() as $property) {
            $value = $property->getValue($object);
            self::assertNotNull($value, $object::class.'::$'.$property->getName().' is null in the fixture.');
            self::assertNotSame([], $value, $object::class.'::$'.$property->getName().' is empty in the fixture.');
        }
    }

    /**
     * Calls each wither that maps to a property of the same name with that property's value.
     */
    private function assertWithersPreserve(Invoice|InvoiceLine $object): int
    {
        $class = new ReflectionClass($object);
        $checked = 0;
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();
            if (! str_starts_with($name, 'with') || $name === 'with') {
                continue;
            }
            $propertyName = lcfirst(substr($name, 4));
            if ($propertyName === 'extensions') {
                $value = $object->extensions();
            } elseif ($class->hasProperty($propertyName)) {
                $value = (new ReflectionProperty($object, $propertyName))->getValue($object);
            } else {
                continue;
            }

            $copy = $method->invoke($object, $value);

            self::assertEquals($object, $copy, $name.'() lost or changed a field.');
            $checked++;
        }

        return $checked;
    }
}
