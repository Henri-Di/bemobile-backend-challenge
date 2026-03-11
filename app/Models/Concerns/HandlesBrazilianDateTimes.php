<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use Throwable;

trait HandlesBrazilianDateTimes
{
    protected string $brazilTimezone = 'America/Sao_Paulo';

    /**
     * Normalizes a datetime value into a Carbon instance using the Brazil timezone.
     *
     * Supported inputs:
     * - null / empty string
     * - Carbon
     * - DateTimeInterface
     * - unix timestamp
     * - supported datetime strings
     *
     * @throws InvalidArgumentException
     */
    protected function normalizeDateTimeValue(mixed $value, string $field): ?Carbon
    {
        if ($this->isNullLike($value)) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy()->setTimezone($this->timezone());
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->setTimezone($this->timezone());
        }

        if ($this->isTimestampValue($value)) {
            return Carbon::createFromTimestamp((int) $value, $this->timezone());
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException(
                sprintf('Invalid datetime value for field [%s].', $field)
            );
        }

        $value = trim($value);

        foreach ($this->supportedDateTimeFormats() as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value, $this->timezone());

                if ($date !== false) {
                    return $date->setTimezone($this->timezone());
                }
            } catch (Throwable) {
            }
        }

        try {
            return Carbon::parse($value, $this->timezone())->setTimezone($this->timezone());
        } catch (Throwable) {
            throw new InvalidArgumentException(
                sprintf('Invalid datetime format for field [%s] with value [%s].', $field, $value)
            );
        }
    }

    /**
     * Normalizes a date value and returns it in Y-m-d format.
     *
     * @throws InvalidArgumentException
     */
    protected function normalizeDateValue(mixed $value, string $field): ?string
    {
        $date = $this->normalizeDateTimeValue($value, $field);

        return $date?->format('Y-m-d');
    }

    /**
     * Normalizes a string value.
     *
     * Empty strings are converted to null.
     */
    protected function normalizeString(mixed $value, bool $lower = false): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        return $lower ? Str::lower($normalized) : $normalized;
    }

    /**
     * Normalizes an integer value with optional min/max limits.
     *
     * @throws InvalidArgumentException
     */
    protected function normalizeInteger(
        mixed $value,
        string $field,
        ?int $min = null,
        ?int $max = null
    ): ?int {
        if ($this->isNullLike($value)) {
            return null;
        }

        if (is_bool($value) || is_array($value) || is_object($value)) {
            throw new InvalidArgumentException(
                sprintf('Invalid integer value for field [%s].', $field)
            );
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if (! is_numeric($value) || (string) (int) $value !== (string) $value && ! preg_match('/^-?\d+$/', (string) $value)) {
            throw new InvalidArgumentException(
                sprintf('Invalid integer value for field [%s] with value [%s].', $field, (string) $value)
            );
        }

        $intValue = (int) $value;

        if ($min !== null && $intValue < $min) {
            throw new InvalidArgumentException(
                sprintf('Field [%s] must be greater than or equal to %d.', $field, $min)
            );
        }

        if ($max !== null && $intValue > $max) {
            throw new InvalidArgumentException(
                sprintf('Field [%s] must be less than or equal to %d.', $field, $max)
            );
        }

        return $intValue;
    }

    /**
     * Normalizes a boolean value.
     *
     * Accepted values include:
     * - true / false
     * - 1 / 0
     * - "1" / "0"
     * - "true" / "false"
     * - "yes" / "no"
     * - "on" / "off"
     */
    protected function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($this->isNullLike($value)) {
            return false;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $normalized ?? false;
    }

    /**
     * Normalizes an array value.
     *
     * Accepts:
     * - array
     * - JSON string representing an array
     *
     * @return array<int|string, mixed>|null
     *
     * @throws InvalidArgumentException
     */
    protected function normalizeArray(mixed $value, string $field): ?array
    {
        if ($this->isNullLike($value)) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException(
                sprintf('Invalid array/json value for field [%s].', $field)
            );
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

            if (is_array($decoded)) {
                return $decoded;
            }
        } catch (JsonException) {
        }

        throw new InvalidArgumentException(
            sprintf('Invalid array/json value for field [%s].', $field)
        );
    }

    /**
     * Serializes a date value using the Brazil timezone.
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return Carbon::instance($date)
            ->setTimezone($this->timezone())
            ->format('Y-m-d H:i:s');
    }

    /**
     * Returns the configured Brazil timezone.
     */
    protected function timezone(): string
    {
        return $this->brazilTimezone;
    }

    /**
     * Returns the supported datetime input formats.
     *
     * @return array<int, string>
     */
    protected function supportedDateTimeFormats(): array
    {
        return [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d',
            'Y-m-d\TH:i:sP',
            'Y-m-d\TH:i:s.uP',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd/m/Y',
        ];
    }

    /**
     * Determines whether the given value should be considered null-like.
     */
    protected function isNullLike(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    /**
     * Determines whether the given value can be treated as a unix timestamp.
     */
    protected function isTimestampValue(mixed $value): bool
    {
        return is_int($value)
            || (is_string($value) && preg_match('/^\d+$/', $value) === 1);
    }
}