<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

use InvalidArgumentException;

/**
 * One FA(3) contact entry (DaneKontaktowe) of a party.
 */
final readonly class ContactDetails
{
    /**
     * @throws InvalidArgumentException when neither an e-mail address nor a phone number is given,
     *                                  or one of them is an empty string
     */
    public function __construct(
        /** E-mail address (Email). */
        public ?string $email = null,
        /** Phone number (Telefon). */
        public ?string $phone = null,
    ) {
        if ($email === '' || $phone === '') {
            throw new InvalidArgumentException('A contact entry (DaneKontaktowe) takes no empty e-mail address or phone number; leave it null.');
        }
        if ($email === null && $phone === null) {
            throw new InvalidArgumentException('A contact entry (DaneKontaktowe) needs an e-mail address or a phone number.');
        }
    }
}
