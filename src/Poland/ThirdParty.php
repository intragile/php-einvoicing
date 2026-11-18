<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\Support\Decimal;
use InvalidArgumentException;

/**
 * A third party of the invoice (Podmiot3), such as a factor, a payer or an additional buyer.
 */
final readonly class ThirdParty
{
    /** Share of the party in percent (Udzial), 0 to 100. */
    public ?BigDecimal $share;

    /**
     * @param  BigDecimal|numeric-string|int|null  $share
     *
     * @throws InvalidArgumentException when neither or both of the role and the other role are
     *                                  given, or the share is outside 0 to 100
     */
    public function __construct(
        /** Name, identifiers and address of the party. */
        public Party $party,
        /** Role of the party (Rola). */
        public ?ThirdPartyRole $role = null,
        /** Description of a role outside the code list (RolaInna = 1 with OpisRoli). */
        public ?string $otherRole = null,
        /** Internal identifier of the party (IDWew). */
        public ?string $internalId = null,
        BigDecimal|string|int|float|null $share = null,
        /** FA(3) party data the EN 16931 party does not carry. */
        public ?PolishParty $extras = null,
    ) {
        if (($role === null) === ($otherRole === null)) {
            throw new InvalidArgumentException('A third party (Podmiot3) needs exactly one of a role (Rola) and an other role (OpisRoli).');
        }
        $this->share = Decimal::ofNullable($share, 'Udzial');
        if ($this->share !== null && ($this->share->isNegative() || $this->share->isGreaterThan(100))) {
            throw new InvalidArgumentException(sprintf('The share (Udzial) %s is outside 0 to 100.', $this->share));
        }
    }
}
