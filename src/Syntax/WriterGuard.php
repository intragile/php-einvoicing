<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax;

use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\Party;
use InvalidArgumentException;

/**
 * Refusals shared by the UBL and CII writers for models that neither syntax can carry in a form
 * the EN 16931 rules accept.
 *
 * @internal
 */
final class WriterGuard
{
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
}
