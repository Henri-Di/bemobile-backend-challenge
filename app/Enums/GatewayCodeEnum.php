<?php

declare(strict_types=1);

namespace App\Enums;

enum GatewayCodeEnum: string
{
    /**
     * First payment gateway implementation.
     */
    case GATEWAY_ONE = 'gateway_1';

    /**
     * Second payment gateway implementation.
     */
    case GATEWAY_TWO = 'gateway_2';

    /**
     * Returns a human-readable gateway name.
     */
    public function label(): string
    {
        return match ($this) {
            self::GATEWAY_ONE => 'Gateway One',
            self::GATEWAY_TWO => 'Gateway Two',
        };
    }

    /**
     * Returns the enum instance from a raw value safely.
     */
    public static function fromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }

    /**
     * Checks if the given value is a valid gateway code.
     */
    public static function isValid(string $value): bool
    {
        return self::tryFrom($value) !== null;
    }

    /**
     * Returns a list of all gateway codes.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Returns a map of gateway codes and labels.
     *
     * Useful for admin panels or configuration screens.
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