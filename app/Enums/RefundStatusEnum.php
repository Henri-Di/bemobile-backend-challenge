<?php

declare(strict_types=1);

namespace App\Enums;

enum RefundStatusEnum: string
{
    /**
     * Refund request has been created but not yet processed.
     */
    case PENDING = 'pending';

    /**
     * Refund has been successfully processed by the gateway.
     */
    case PROCESSED = 'processed';

    /**
     * Refund failed during processing.
     */
    case FAILED = 'failed';

    /**
     * Returns a human-readable label for the refund status.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PROCESSED => 'Processed',
            self::FAILED => 'Failed',
        };
    }

    /**
     * Determines if the refund is still in progress.
     */
    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * Determines if the refund has been completed successfully.
     */
    public function isProcessed(): bool
    {
        return $this === self::PROCESSED;
    }

    /**
     * Determines if the refund has failed.
     */
    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }

    /**
     * Safely creates an enum instance from a raw value.
     */
    public static function fromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }

    /**
     * Checks if the given value is a valid refund status.
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
     * Returns key-value options useful for UI or configuration.
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