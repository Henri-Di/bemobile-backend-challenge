<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRoleEnum;
use App\Models\Concerns\HandlesBrazilianDateTimes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use InvalidArgumentException;
use Laravel\Sanctum\HasApiTokens;

final class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use HandlesBrazilianDateTimes;

    private const NAME_MAX_LENGTH = 150;
    private const EMAIL_MAX_LENGTH = 150;
    private const PASSWORD_MIN_LENGTH = 8;

    protected $table = 'users';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'role' => UserRoleEnum::class,
            'is_active' => 'boolean',
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Return the transactions created by this user.
     */
    public function transactionsCreated(): HasMany
    {
        return $this->hasMany(Transaction::class, 'created_by_user_id');
    }

    /**
     * Return the refunds requested by this user.
     */
    public function refundsRequested(): HasMany
    {
        return $this->hasMany(Refund::class, 'requested_by_user_id');
    }

    /**
     * Mutate and validate the user name before persistence.
     *
     * @throws InvalidArgumentException
     */
    public function setNameAttribute(mixed $value): void
    {
        $name = $this->normalizeString($value);

        if ($name === null) {
            throw new InvalidArgumentException('User name is required.');
        }

        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('User name may not be greater than %d characters.', self::NAME_MAX_LENGTH)
            );
        }

        $this->attributes['name'] = $name;
    }

    /**
     * Mutate and validate the user email before persistence.
     *
     * @throws InvalidArgumentException
     */
    public function setEmailAttribute(mixed $value): void
    {
        $email = $this->normalizeString($value, true);

        if ($email === null || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid user email.');
        }

        if (mb_strlen($email) > self::EMAIL_MAX_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('User email may not be greater than %d characters.', self::EMAIL_MAX_LENGTH)
            );
        }

        $this->attributes['email'] = $email;
    }

    /**
     * Mutate and validate the user password before persistence.
     *
     * The password value is validated before being hashed by the model cast.
     *
     * @throws InvalidArgumentException
     */
    public function setPasswordAttribute(mixed $value): void
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('Password must be a valid string.');
        }

        $password = trim($value);

        if ($password === '') {
            throw new InvalidArgumentException('Password is required.');
        }

        if (mb_strlen($password) < self::PASSWORD_MIN_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Password must be at least %d characters long.', self::PASSWORD_MIN_LENGTH)
            );
        }

        $this->attributes['password'] = $password;
    }

    /**
     * Mutate and validate the user role before persistence.
     *
     * Accept enum instances or raw string values.
     *
     * @throws InvalidArgumentException
     */
    public function setRoleAttribute(mixed $value): void
    {
        if ($value instanceof UserRoleEnum) {
            $this->attributes['role'] = $value->value;

            return;
        }

        $role = $this->normalizeString($value, true);

        if ($role === null) {
            throw new InvalidArgumentException('User role is required.');
        }

        $enum = UserRoleEnum::tryFrom($role);

        if ($enum === null) {
            throw new InvalidArgumentException(sprintf('Invalid user role [%s].', $role));
        }

        $this->attributes['role'] = $enum->value;
    }

    /**
     * Mutate the active flag before persistence.
     */
    public function setIsActiveAttribute(mixed $value): void
    {
        $this->attributes['is_active'] = $this->normalizeBoolean($value);
    }

    /**
     * Scope only active users.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope users by role.
     */
    public function scopeByRole(Builder $query, UserRoleEnum|string $role): Builder
    {
        $value = $role instanceof UserRoleEnum
            ? $role->value
            : strtoupper(trim((string) $role));

        return $query->where('role', $value);
    }

    /**
     * Determine whether the user is active.
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Determine whether the user is an administrator.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRoleEnum::ADMIN;
    }

    /**
     * Determine whether the user is a manager.
     */
    public function isManager(): bool
    {
        return $this->role === UserRoleEnum::MANAGER;
    }

    /**
     * Determine whether the user belongs to finance.
     */
    public function isFinance(): bool
    {
        return $this->role === UserRoleEnum::FINANCE;
    }

    /**
     * Determine whether the user is a regular user.
     */
    public function isUser(): bool
    {
        return $this->role === UserRoleEnum::USER;
    }

    /**
     * Determine whether the user can request refunds.
     */
    public function canRefund(): bool
    {
        return in_array($this->role, [
            UserRoleEnum::ADMIN,
            UserRoleEnum::FINANCE,
        ], true);
    }

    /**
     * Determine whether the user can manage other users.
     */
    public function canManageUsers(): bool
    {
        return in_array($this->role, [
            UserRoleEnum::ADMIN,
            UserRoleEnum::MANAGER,
        ], true);
    }

    /**
     * Determine whether the user can manage gateways and transactions broadly.
     */
    public function canManagePayments(): bool
    {
        return in_array($this->role, [
            UserRoleEnum::ADMIN,
            UserRoleEnum::MANAGER,
            UserRoleEnum::FINANCE,
        ], true);
    }

    /**
     * Normalize a string input.
     */
    private function normalizeString(mixed $value, bool $uppercase = false): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_scalar($value)) {
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

        if (filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            $normalized = mb_strtolower($normalized);
        }

        if ($uppercase) {
            $normalized = mb_strtoupper($normalized);
        }

        return $normalized;
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