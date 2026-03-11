<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionStatusEnum: string
{
    /**
     * Transaction has been created but not yet processed.
     */
    case PENDING = 'pending';

    /**
     * Transaction is currently being processed by the payment gateway.
     */
    case PROCESSING = 'processing';

    /**
     * Transaction was successfully paid.
     */
    case PAID = 'paid';

    /**
     * Transaction failed during processing.
     */
    case FAILED = 'failed';

    /**
     * Transaction has been refunded after a successful payment.
     */
    case REFUNDED = 'refunded';

    /**
     * Returns a human-readable label for the transaction status.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PROCESSING => 'Processing',
            self::PAID => 'Paid',
            self::FAILED => 'Failed',
            self::REFUNDED => 'Refunded',
        };
    }

    /**
     * Indicates whether the transaction is still waiting to be processed.
     */
    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * Indicates whether the transaction is currently processing.
     */
    public function isProcessing(): bool
    {
        return $this === self::PROCESSING;
    }

    /**
     * Indicates whether the transaction was successfully paid.
     */
    public function isPaid(): bool
    {
        return $this === self::PAID;
    }

    /**
     * Indicates whether the transaction failed.
     */
    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }

    /**
     * Indicates whether the transaction has been refunded.
     */
    public function isRefunded(): bool
    {
        return $this === self::REFUNDED;
    }

    /**
     * Safely resolves the enum from a raw value.
     */
    public static function fromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }

    /**
     * Checks if the provided value is a valid transaction status.
     */
    public static function isValid(string $value): bool
    {
        return self::tryFrom($value) !== null;
    }

    /**
     * Returns all enum values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Returns key-value options useful for admin panels or selects.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}