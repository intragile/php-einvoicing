<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

use InvalidArgumentException;

/**
 * A bank account for the payment (RachunekBankowy or RachunekBankowyFaktora, XSD type
 * TRachunekBankowy).
 */
final readonly class BankAccount
{
    /**
     * @throws InvalidArgumentException when the own-bank-account code is outside 1 to 3
     */
    public function __construct(
        /** Account number (NrRB). */
        public string $number,
        /** SWIFT code (SWIFT). */
        public ?string $swift = null,
        /** Own bank account marking (RachunekWlasnyBanku): 1, 2 or 3. */
        public ?int $ownBankAccount = null,
        /** Bank name (NazwaBanku). */
        public ?string $bankName = null,
        /** Account description (OpisRachunku). */
        public ?string $description = null,
    ) {
        if ($ownBankAccount !== null && ($ownBankAccount < 1 || $ownBankAccount > 3)) {
            throw new InvalidArgumentException(sprintf(
                'The own bank account marking (RachunekWlasnyBanku) %d is outside 1 to 3.',
                $ownBankAccount,
            ));
        }
    }
}
