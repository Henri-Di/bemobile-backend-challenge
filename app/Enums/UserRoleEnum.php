<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRoleEnum: string
{
    /**
     * System administrator with full access to all modules and settings.
     */
    case ADMIN = 'ADMIN';

    /**
     * Manager responsible for supervising operations and users.
     */
    case MANAGER = 'MANAGER';

    /**
     * Finance role responsible for financial operations such as
     * payments, refunds and transaction monitoring.
     */
    case FINANCE = 'FINANCE';

    /**
     * Regular user with limited system permissions.
     */
    case USER = 'USER';

    /**
     * Returns a human-readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrator',
            self::MANAGER => 'Manager',
            self::FINANCE => 'Finance',
            self::USER => 'User',
        };
    }

    /**
     * Determines whether the role has administrative privileges.
     */
    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    /**
     * Determines whether the role belongs to management.
     */
    public function isManager(): bool
    {
        return $this === self::MANAGER;
    }

    /**
     * Determines whether the role belongs to the finance department.
     */
    public function isFinance(): bool
    {
        return $this === self::FINANCE;
    }

    /**
     * Determines whether the role is a regular user.
     */
    public function isUser(): bool
    {
        return $this === self::USER;
    }

    /**
     * Safely resolves the enum from a raw value.
     */
    public static function fromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }

    /**
     * Checks if the given value is a valid role.
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
     * Returns options useful for UI selects or admin panels.
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