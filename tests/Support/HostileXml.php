<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

/**
 * Hostile XML payloads that every inbound reader must refuse before any entity is resolved.
 */
final class HostileXml
{
    public const UBL_INVOICE_NAMESPACE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';

    /**
     * Payloads keyed by attack name, shaped for a PHPUnit data provider.
     *
     * The root element can be chosen so that a reader rejects the payload for its DOCTYPE and not
     * for an unexpected root.
     *
     * @return array<string, array{string}>
     */
    public static function cases(string $rootName = 'Invoice', string $namespace = self::UBL_INVOICE_NAMESPACE): array
    {
        $open = sprintf('<%s xmlns="%s">', $rootName, $namespace);
        $close = sprintf('</%s>', $rootName);

        $laughs = '<!ENTITY lol0 "lol">';
        for ($i = 1; $i <= 9; $i++) {
            $laughs .= sprintf('<!ENTITY lol%d "%s">', $i, str_repeat(sprintf('&lol%d;', $i - 1), 10));
        }

        $internal = "<?xml version=\"1.0\"?>\n<!DOCTYPE {$rootName} [<!ENTITY a \"expanded\">]>\n{$open}&a;{$close}";

        return [
            'internal entity' => [$internal],
            'external entity' => ["<?xml version=\"1.0\"?>\n<!DOCTYPE {$rootName} [<!ENTITY xxe SYSTEM \"file:///etc/passwd\">]>\n{$open}&xxe;{$close}"],
            'billion laughs' => ["<?xml version=\"1.0\"?>\n<!DOCTYPE {$rootName} [{$laughs}]>\n{$open}&lol9;{$close}"],
            'external parameter entity' => ["<?xml version=\"1.0\"?>\n<!DOCTYPE {$rootName} [<!ENTITY % remote SYSTEM \"http://127.0.0.1:9/evil.dtd\"> %remote;]>\n{$open}{$close}"],
            'external DTD' => ["<?xml version=\"1.0\"?>\n<!DOCTYPE {$rootName} SYSTEM \"http://127.0.0.1:9/evil.dtd\">\n{$open}{$close}"],
            'UTF-16 internal entity' => [self::utf16($internal)],
            'DOCTYPE after comments and PI' => ["<?xml version=\"1.0\"?>\n<!-- a --><?pi x?><!-- b -->\n<!DOCTYPE {$rootName} [<!ENTITY a \"expanded\">]>\n{$open}&a;{$close}"],
        ];
    }

    private static function utf16(string $xml): string
    {
        $converted = mb_convert_encoding(str_replace('<?xml version="1.0"?>', '<?xml version="1.0" encoding="UTF-16"?>', $xml), 'UTF-16LE', 'UTF-8');

        return "\xFF\xFE".$converted;
    }
}
