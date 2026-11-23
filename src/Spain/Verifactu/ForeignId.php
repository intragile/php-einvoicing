<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\Spain\ForeignIdType;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;

/**
 * An identifier other than a Spanish NIF (IDOtro).
 */
final readonly class ForeignId
{
    public string $id;

    public function __construct(
        public ForeignIdType $type,
        string $id,
        public ?CountryCode $country,
    ) {
        $id = trim($id);

        if ($id === '' || mb_strlen($id) > 20) {
            throw new InvalidRecord('IDOtro/ID must be 1 to 20 characters.');
        }

        $this->id = $id;
    }
}
