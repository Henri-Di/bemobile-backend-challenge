<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HandlesBrazilianDateTimes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use JsonException;

final class Gateway extends Model
{
    use HasFactory;
    use HandlesBrazilianDateTimes;

    private const NAME_MAX_LENGTH = 150;
    private const CODE_MAX_LENGTH = 100;

    protected $table = 'gateways';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'priority',
        'is_active',
        'settings_json',
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
            'priority' => 'integer',
            'is_active' => 'boolean',
            'settings_json' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Return the transactions associated with this gateway.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Return the refunds associated with this gateway.
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * Mutate and validate the gateway name before persistence.
     *
     * @throws InvalidArgumentException
     */
    public function setNameAttribute(mixed $value): void
    {
        $name = self::normalizeStringValue($value);

        if ($name === null) {
            throw new InvalidArgumentException('Gateway name is required.');
        }

        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Gateway name may not be greater than %d characters.', self::NAME_MAX_LENGTH)
            );
        }

        $this->attributes['name'] = $name;
    }

    /**
     * Mutate and validate the gateway code before persistence.
     *
     * @throws InvalidArgumentException
     */
    public function setCodeAttribute(mixed $value): void
    {
        $code = self::normalizeCodeValue($value);

        if ($code === null) {
            throw new InvalidArgumentException('Gateway code is required.');
        }

        if (mb_strlen($code) > self::CODE_MAX_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Gateway code may not be greater than %d characters.', self::CODE_MAX_LENGTH)
            );
        }

        $this->attributes['code'] = $code;
    }

    /**
     * Mutate and validate the gateway priority before persistence.
     *
     * @throws InvalidArgumentException
     */
    public function setPriorityAttribute(mixed $value): void
    {
        $priority = self::normalizePositiveIntegerValue($value);

        if ($priority === null) {
            throw new InvalidArgumentException('Gateway priority is required.');
        }

        $this->attributes['priority'] = $priority;
    }

    /**
     * Mutate the active flag before persistence.
     */
    public function setIsActiveAttribute(mixed $value): void
    {
        $this->attributes['is_active'] = self::normalizeBooleanValue($value);
    }

    /**
     * Mutate and normalize settings_json before persistence.
     *
     * Accepts:
     * - null
     * - array
     * - JSON string
     *
     * Stores canonical JSON in the raw model attribute while the cast exposes it as array.
     *
     * @throws InvalidArgumentException
     */
    public function setSettingsJsonAttribute(mixed $value): void
    {
        $this->attributes['settings_json'] = $this->normalizeSettingsJsonForStorage($value);
    }

    /**
     * Scope only active gateways.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope only inactive gateways.
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope gateways ordered by priority.
     */
    public function scopeOrderedByPriority(Builder $query): Builder
    {
        return $query->orderBy('priority')->orderBy('id');
    }

    /**
     * Scope gateways by code.
     *
     * @throws InvalidArgumentException
     */
    public function scopeByCode(Builder $query, string $code): Builder
    {
        $normalized = self::normalizeCodeValue($code);

        if ($normalized === null) {
            throw new InvalidArgumentException('Gateway code is required.');
        }

        return $query->where('code', $normalized);
    }

    /**
     * Determine whether the gateway is active.
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Return the normalized settings payload.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return is_array($this->settings_json)
            ? $this->settings_json
            : [];
    }

    /**
     * Determine whether the gateway has a specific settings key.
     */
    public function hasSetting(string $key): bool
    {
        return array_key_exists($key, $this->settings());
    }

    /**
     * Return a specific settings value when available.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings()[$key] ?? $default;
    }

    /**
     * Normalize settings_json input into a canonical JSON string or null.
     *
     * @throws InvalidArgumentException
     */
    private function normalizeSettingsJsonForStorage(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $this->encodeSettingsArray($value);
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '') {
                return null;
            }

            try {
                $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new InvalidArgumentException('Invalid settings_json value.');
            }

            if (! is_array($decoded)) {
                throw new InvalidArgumentException('Invalid settings_json value.');
            }

            return $this->encodeSettingsArray($decoded);
        }

        throw new InvalidArgumentException('Invalid settings_json value.');
    }

    /**
     * Encode the settings payload into canonical JSON.
     *
     * @param array<string, mixed> $settings
     *
     * @throws InvalidArgumentException
     */
    private function encodeSettingsArray(array $settings): string
    {
        try {
            return json_encode(
                $settings,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            throw new InvalidArgumentException('Invalid settings_json value.');
        }
    }

    /**
     * Normalize a generic string input.
     */
    private static function normalizeStringValue(mixed $value): ?string
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

        return $normalized;
    }

    /**
     * Normalize the gateway code.
     */
    private static function normalizeCodeValue(mixed $value): ?string
    {
        $code = self::normalizeStringValue($value);

        if ($code === null) {
            return null;
        }

        $code = mb_strtolower($code);
        $code = preg_replace('/[^a-z0-9_\-]/u', '', $code);

        if (! is_string($code) || $code === '') {
            return null;
        }

        return mb_substr($code, 0, self::CODE_MAX_LENGTH);
    }

    /**
     * Normalize a positive integer input.
     */
    private static function normalizePositiveIntegerValue(mixed $value): ?int
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

        return null;
    }

    /**
     * Normalize a boolean-like input.
     */
    private static function normalizeBooleanValue(mixed $value): bool
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