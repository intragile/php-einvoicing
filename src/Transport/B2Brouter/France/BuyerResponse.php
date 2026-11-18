<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\France;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\France\Lifecycle\CharacteristicType;
use IntrAgile\EInvoicing\France\Lifecycle\LifecycleStatus;
use IntrAgile\EInvoicing\France\Lifecycle\StatusReason;
use InvalidArgumentException;

/**
 * The buyer's answer to a received French invoice, as B2Brouter's mark_as request sets it.
 *
 * B2Brouter emits the lifecycle message (CDV) for the status itself from the state change; no
 * CDAR document is uploaded.
 */
final readonly class BuyerResponse
{
    /** The amount variants of a partial approval (206). */
    private const AMOUNT_TYPES = [
        CharacteristicType::MAP,
        CharacteristicType::MAPTTC,
        CharacteristicType::MNA,
        CharacteristicType::MNATTC,
    ];

    /** The refusal reasons B2Brouter accepts for a refusal (210) the DGFiP allows. */
    private const REFUSAL_REASONS = [
        StatusReason::ADR_ERR,
        StatusReason::REF_CT_ABSENT,
        StatusReason::CMD_ERR,
        StatusReason::TX_TVA_ERR,
        StatusReason::MONTANTTOTAL_ERR,
        StatusReason::CALCUL_ERR,
        StatusReason::DOUBLON,
        StatusReason::NON_CONFORME,
        StatusReason::DEST_ERR,
        StatusReason::TRANSAC_INC,
        StatusReason::EMMET_INC,
        StatusReason::CONTRAT_TERM,
        StatusReason::DOUBLE_FACT,
    ];

    /**
     * @param  array<string, string>  $members  the request members as JSON texts, in order
     */
    private function __construct(
        private LifecycleStatus $status,
        private array $members,
    ) {}

    /**
     * In process (204): the invoice is taken into the buyer's system.
     */
    public static function inProcess(): self
    {
        return self::withState(LifecycleStatus::InProcess, 'read');
    }

    /**
     * Approved (205).
     */
    public static function approved(): self
    {
        return self::withState(LifecycleStatus::Approved, 'accepted');
    }

    /**
     * Partially approved (206), with the amount and what it stands for.
     *
     * @param  BigDecimal|string|int  $amount  greater than zero, below 10^13, at most 2 decimals
     * @param  CharacteristicType  $amountType  MAP or MNA for the approved or not approved amount
     *                                          without VAT, MAPTTC or MNATTC with VAT
     * @param  string  $reason  why the invoice is approved only in part
     *
     * @throws InvalidArgumentException when the amount, the amount type or the reason is not accepted
     */
    public static function partiallyApproved(BigDecimal|string|int $amount, CharacteristicType $amountType, string $reason): self
    {
        if (! in_array($amountType, self::AMOUNT_TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                'A partial approval amount type must be one of %s, %s given.',
                self::list(self::AMOUNT_TYPES),
                $amountType->value,
            ));
        }

        return new self(LifecycleStatus::PartiallyApproved, [
            'state' => FranceResponseMapper::json('accepted'),
            'amount' => FranceResponseMapper::jsonAmount($amount, 'The partial approval amount'),
            'amount_code' => FranceResponseMapper::json($amountType->value),
            'reason' => FranceResponseMapper::json(self::text($reason, 'The partial approval reason')),
        ]);
    }

    /**
     * Disputed (207), with the reason of the dispute.
     *
     * @throws InvalidArgumentException when the reason is blank
     */
    public static function disputed(string $reason): self
    {
        return new self(LifecycleStatus::Disputed, [
            'state' => FranceResponseMapper::json('annotated'),
            'dispute' => FranceResponseMapper::json(true),
            'reason' => FranceResponseMapper::json(self::text($reason, 'The dispute reason')),
        ]);
    }

    /**
     * Refused (210), with the reason from the list B2Brouter accepts and an optional comment.
     *
     * Without a comment B2Brouter writes one from the label of the reason, since the DGFiP refuses
     * a refusal without a comment.
     *
     * @throws InvalidArgumentException when the reason is not one B2Brouter accepts or the comment is blank
     */
    public static function refused(StatusReason $reason, ?string $comment = null): self
    {
        if (! in_array($reason, self::REFUSAL_REASONS, true)) {
            throw new InvalidArgumentException(sprintf(
                'A refusal through B2Brouter must give one of the reasons %s, %s given.',
                self::list(self::REFUSAL_REASONS),
                $reason->value,
            ));
        }

        $members = [
            'state' => FranceResponseMapper::json('refused'),
            'reason_code' => FranceResponseMapper::json($reason->value),
        ];
        if ($comment !== null) {
            $members['reason'] = FranceResponseMapper::json(self::text($comment, 'The refusal comment'));
        }

        return new self(LifecycleStatus::Refused, $members);
    }

    /**
     * Payment sent (211): the buyer tells the seller the invoice is paid. B2Brouter does not
     * report this status to the DGFiP.
     */
    public static function paymentSent(): self
    {
        return self::withState(LifecycleStatus::PaymentSent, 'paid');
    }

    /**
     * The lifecycle status the answer sends.
     */
    public function status(): LifecycleStatus
    {
        return $this->status;
    }

    /**
     * The JSON body of the mark_as request.
     */
    public function toJson(): string
    {
        return FranceResponseMapper::jsonObject($this->members);
    }

    private static function withState(LifecycleStatus $status, string $state): self
    {
        return new self($status, ['state' => FranceResponseMapper::json($state)]);
    }

    /**
     * The text without surrounding whitespace.
     *
     * @throws InvalidArgumentException when nothing but whitespace remains
     */
    private static function text(string $text, string $what): string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            throw new InvalidArgumentException(sprintf('%s must not be blank.', $what));
        }

        return $trimmed;
    }

    /**
     * @param  list<CharacteristicType|StatusReason>  $cases
     */
    private static function list(array $cases): string
    {
        return implode(', ', array_map(static fn (CharacteristicType|StatusReason $case): string => $case->value, $cases));
    }
}
