<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use IntrAgile\EInvoicing\CodeList\CountryCode;

/**
 * Item information (BG-31).
 */
final readonly class Item
{
    public function __construct(
        /** Item name (BT-153). */
        public string $name,
        /** Item description (BT-154). */
        public ?string $description = null,
        /** Item seller's identifier (BT-155). */
        public ?string $sellersIdentifier = null,
        /** Item buyer's identifier (BT-156). */
        public ?string $buyersIdentifier = null,
        /** Item standard identifier (BT-157) with its scheme (BT-157-1). */
        public ?Identifier $standardIdentifier = null,
        /** @var list<ItemClassification> Item classification identifiers (BT-158). */
        public array $classifications = [],
        /** Item country of origin (BT-159). */
        public ?CountryCode $originCountry = null,
        /** @var list<ItemAttribute> Item attributes (BG-32). */
        public array $attributes = [],
    ) {}
}
