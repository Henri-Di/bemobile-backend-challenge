<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionAttemptStatusEnum: string
{
    /**
     * Transaction attempt has been created but not yet processed.
     */
    case PENDING = 'pending';

    /**
     * Transaction attempt was successfully processed by the gateway.
     */
    case SUCCESS = 'success';

    /**
     * Transaction attempt failed during processing.
     */
    case FAILED = 'failed';

    /**
     * Returns a human-readable label for the transaction attempt status.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::SUCCESS => 'Success',
            self::FAILED => 'Failed',
        };
    }

    /**
     * Indicates whether the transaction attempt is still pending.
     */
    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * Indicates whether the transaction attempt succeeded.
     */
    public function isSuccess(): bool
    {
        return $this === self::SUCCESS;
    }

    /**
     * Indicates whether the transaction attempt failed.
     */
    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }

    /**
     * Safely resolves the enum from a raw value.
     */
    public static function fromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }

    /**
     * Checks whether the provided value is a valid status.
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
     * Returns options useful for UI selects or configuration panels.
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