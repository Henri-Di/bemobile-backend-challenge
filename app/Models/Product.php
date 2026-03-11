<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HandlesBrazilianDateTimes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class Product extends Model
{
    use HasFactory;
    use HandlesBrazilianDateTimes;

    private const NAME_MAX_LENGTH = 255;

    protected $table = 'products';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'amount',
        'is_active',
    ];

    /**
     * Get the model attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'amount' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Mutate and validate the product name before persistence.
     *
     * @throws InvalidArgumentException
     */
    public function setNameAttribute(mixed $value): void
    {
        $name = $this->normalizeString($value);

        if ($name === null) {
            throw new InvalidArgumentException('Product name is required.');
        }

        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Product name may not be greater than %d characters.', self::NAME_MAX_LENGTH)
            );
        }

        $this->attributes['name'] = $name;
    }

    /**
     * Mutate and validate the amount before persistence.
     *
     * Amount must be stored in minor units and greater than zero.
     *
     * @throws InvalidArgumentException
     */
    public function setAmountAttribute(mixed $value): void
    {
        $amount = $this->normalizePositiveInteger($value);

        if ($amount === null) {
            throw new InvalidArgumentException('Product amount is required.');
        }

        $this->attributes['amount'] = $amount;
    }

    /**
     * Mutate the active flag before persistence.
     */
    public function setIsActiveAttribute(mixed $value): void
    {
        $this->attributes['is_active'] = $this->normalizeBoolean($value);
    }

    /**
     * Scope only active products.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope only inactive products.
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    /**
     * Determine whether the product is active.
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Return the amount in major units.
     *
     * Example:
     * 1099 => 10.99
     */
    public function amountInDecimal(): float
    {
        return ((int) $this->amount) / 100;
    }

    /**
     * Normalize a generic string input.
     */
    private function normalizeString(mixed $value): ?string
    {
        if ($value === null || ! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', $normalized);

        if (! is_string($normalized) || $normalized === '') {
            return null;
        }

        return mb_substr($normalized, 0, self::NAME_MAX_LENGTH);
    }

    /**
     * Normalize a positive integer input.
     */
    private function normalizePositiveInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value >= 1 ? $value : null;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '' || preg_match('/^\d+$/', $value) !== 1) {
                return null;
            }

            $normalized = (int) $value;

            return $normalized >= 1 ? $normalized : null;
        }

        if (is_float($value)) {
            $normalized = (int) $value;

            return $normalized >= 1 ? $normalized : null;
        }

        return null;
    }

    /**
     * Normalize a boolean-like input.
     */
    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(
                mb_strtolower(trim($value)),
                ['1', 'true', 'yes', 'on'],
                true
            );
        }

        return false;
    }
}