<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax;

use IntrAgile\EInvoicing\Model\Attachment;
use IntrAgile\EInvoicing\Model\Contact;
use IntrAgile\EInvoicing\Model\Delivery;
use IntrAgile\EInvoicing\Model\ElectronicAddress;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PostalAddress;
use InvalidArgumentException;
use ReflectionProperty;
use UnitEnum;

/**
 * Refusals shared by the UBL and CII writers for models that neither syntax can carry in a form
 * the EN 16931 rules accept.
 *
 * @internal
 */
final class WriterGuard
{
    private const MODEL_NAMESPACE = 'IntrAgile\\EInvoicing\\Model\\';

    /**
     * Roles in the order the model classes list their term ids ("BT-27 / BT-44 / ...").
     */
    private const ROLE_ORDER = [
        Party::class => ['seller', 'buyer', 'payee', 'taxRepresentative'],
        PostalAddress::class => ['seller', 'buyer', 'taxRepresentative', 'deliverTo'],
        Contact::class => ['seller', 'buyer'],
        ElectronicAddress::class => ['seller', 'buyer'],
    ];

    /**
     * Every text of the model must be valid UTF-8 made of XML 1.0 characters: a document holding
     * anything else is not well-formed, and no receiver (our readers included) can parse it. The
     * refusal names the model path and the business term, which the writers no longer know once
     * the text sits in an element.
     *
     * @throws InvalidArgumentException
     */
    public static function xmlText(Invoice $invoice): void
    {
        self::scan($invoice, '', null, null);
    }

    /**
     * BT-111 is written as a second VAT total in the VAT accounting currency (BT-6). Without
     * BT-6, or with BT-6 equal to BT-5, it would be a second total in the invoice currency, which
     * BR-CO-15 rejects.
     *
     * @throws InvalidArgumentException
     */
    public static function accountingCurrencyVat(Invoice $invoice): void
    {
        if ($invoice->totals?->totalVatInAccountingCurrency === null) {
            return;
        }
        if ($invoice->taxCurrency === null) {
            throw new InvalidArgumentException(
                'The invoice has a VAT total in accounting currency (BT-111) but no VAT accounting currency (BT-6).',
            );
        }
        if ($invoice->taxCurrency === $invoice->currency) {
            throw new InvalidArgumentException(sprintf(
                'The VAT total in accounting currency (BT-111) needs a VAT accounting currency (BT-6) other than the invoice currency (BT-5, %s).',
                $invoice->currency->value,
            ));
        }
    }

    /**
     * EN 16931 allows at most one buyer identifier (BT-46) and one payee identifier (BT-60).
     *
     * @param  'buyer'|'payee'  $role
     *
     * @throws InvalidArgumentException
     */
    public static function singlePartyIdentifier(Party $party, string $role): void
    {
        $count = count($party->identifiers);
        if ($count > 1) {
            throw new InvalidArgumentException(sprintf(
                'EN 16931 allows one %s identifier (%s); the %s has %d.',
                $role,
                $role === 'buyer' ? 'BT-46' : 'BT-60',
                $role,
                $count,
            ));
        }
    }

    /**
     * @param  ?string  $role  the party or address role the object belongs to
     * @param  ?ReflectionProperty  $holder  the property that holds the object, for term ids its
     *                                       own properties do not document
     */
    private static function scan(object $object, string $path, ?string $role, ?ReflectionProperty $holder): void
    {
        foreach (get_object_vars($object) as $name => $value) {
            $here = $path === '' ? $name : $path.'.'.$name;
            $childRole = match (true) {
                $object instanceof Invoice && in_array($name, ['seller', 'buyer', 'payee', 'taxRepresentative'], true) => $name,
                $object instanceof Delivery && $name === 'address' => 'deliverTo',
                default => $role,
            };
            foreach (is_array($value) ? $value : [$value] as $index => $item) {
                $at = is_array($value) ? $here.'['.$index.']' : $here;
                if (is_string($item)) {
                    self::checkText($object, $name, $item, $at, $childRole, $holder);
                } elseif (is_object($item) && ! $item instanceof UnitEnum && str_starts_with($item::class, self::MODEL_NAMESPACE)) {
                    self::scan($item, $at, $childRole, new ReflectionProperty($object, $name));
                }
            }
        }
    }

    private static function checkText(object $object, string $name, string $value, string $path, ?string $role, ?ReflectionProperty $holder): void
    {
        if ($object instanceof Attachment && $name === 'content') {
            return;
        }
        $problem = XmlOutput::textProblem($value);
        if ($problem === null) {
            return;
        }
        $term = self::term(new ReflectionProperty($object, $name), $role, $holder);

        throw new InvalidArgumentException(sprintf(
            'The invoice text at %s%s %s.',
            $path,
            $term === null ? '' : ' ('.$term.')',
            $problem,
        ));
    }

    /**
     * The business term of a text property, read from the PHPDoc of the model. Where the property
     * documents one id per role ("BT-27 / BT-44"), the role picks it; where it documents none, or
     * a list that does not depend on the role (Identifier::$scheme), the property holding the
     * object does: its first id group for the value, its second for the scheme.
     */
    private static function term(ReflectionProperty $property, ?string $role, ?ReflectionProperty $holder): ?string
    {
        $own = self::termGroups($property)[0] ?? null;
        if ($own !== null && (count($own) > 1 || count($own[0]) === 1)) {
            return self::pick($own, $property->getDeclaringClass()->getName(), $role);
        }
        if ($holder === null) {
            return null;
        }
        $inherited = self::termGroups($holder)[$property->getName() === 'scheme' ? 1 : 0] ?? null;

        return $inherited === null ? null : self::pick($inherited, $holder->getDeclaringClass()->getName(), $role);
    }

    /**
     * Parenthesised term groups of the property PHPDoc: each group is split at " / " into one
     * part per role, each part holding the term ids found in it.
     *
     * @return list<list<list<string>>>
     */
    private static function termGroups(ReflectionProperty $property): array
    {
        $doc = (string) $property->getDocComment();
        if (preg_match_all('/\(([^()]*\bBT-\d[^()]*)\)/', $doc, $matches) === 0) {
            return [];
        }
        $groups = [];
        foreach ($matches[1] as $group) {
            $parts = [];
            foreach (explode('/', $group) as $part) {
                preg_match_all('/\bBT-\d+(?:-\d+)?\b/', $part, $ids);
                $parts[] = $ids[0];
            }
            $groups[] = $parts;
        }

        return $groups;
    }

    /**
     * The single id of a group, or the id of the role in a group with one part per role.
     *
     * @param  list<list<string>>  $parts
     */
    private static function pick(array $parts, string $class, ?string $role): ?string
    {
        $index = count($parts) === 1 ? 0 : array_search($role, self::ROLE_ORDER[$class] ?? [], true);

        return is_int($index) && count($parts[$index] ?? []) === 1 ? $parts[$index][0] : null;
    }
}
