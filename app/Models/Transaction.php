<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransactionStatusEnum;
use App\Models\Concerns\HandlesBrazilianDateTimes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

final class Transaction extends Model
{
    use HasFactory;
    use HandlesBrazilianDateTimes;

    private const CARD_LAST_NUMBERS_LENGTH = 4;

    protected $table = 'transactions';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'client_id',
        'gateway_id',
        'external_id',
        'status',
        'amount',
        'card_last_numbers',
        'gateway_response_code',
        'gateway_message',
        'created_by_user_id',
        'paid_at',
        'refunded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'client_id' => 'integer',
            'gateway_id' => 'integer',
            'created_by_user_id' => 'integer',
            'amount' => 'integer',
            'status' => TransactionStatusEnum::class,
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'transaction_products')
            ->withPivot(['id', 'quantity', 'unit_amount', 'total_amount'])
            ->withTimestamps();
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionProduct::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(TransactionAttempt::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function setClientIdAttribute(mixed $value): void
    {
        $clientId = $this->normalizeInteger($value, 'client_id', 1);

        if ($clientId === null) {
            throw new InvalidArgumentException('Client id is required.');
        }

        $this->attributes['client_id'] = $clientId;
    }

    public function setGatewayIdAttribute(mixed $value): void
    {
        $this->attributes['gateway_id'] = $value === null
            ? null
            : $this->normalizeInteger($value, 'gateway_id', 1);
    }

    public function setCreatedByUserIdAttribute(mixed $value): void
    {
        $this->attributes['created_by_user_id'] = $value === null
            ? null
            : $this->normalizeInteger($value, 'created_by_user_id', 1);
    }

    public function setAmountAttribute(mixed $value): void
    {
        $amount = $this->normalizeInteger($value, 'amount', 1);

        if ($amount === null) {
            throw new InvalidArgumentException('Transaction amount is required.');
        }

        $this->attributes['amount'] = $amount;
    }

    public function setCardLastNumbersAttribute(mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['card_last_numbers'] = null;

            return;
        }

        $digits = preg_replace('/\D+/u', '', (string) $value);

        if (! is_string($digits) || strlen($digits) !== self::CARD_LAST_NUMBERS_LENGTH) {
            throw new InvalidArgumentException('card_last_numbers must contain exactly 4 digits.');
        }

        $this->attributes['card_last_numbers'] = $digits;
    }

    public function setExternalIdAttribute(mixed $value): void
    {
        $this->attributes['external_id'] = $this->normalizeString($value);
    }

    public function setGatewayResponseCodeAttribute(mixed $value): void
    {
        $this->attributes['gateway_response_code'] = $this->normalizeString($value);
    }

    public function setGatewayMessageAttribute(mixed $value): void
    {
        $this->attributes['gateway_message'] = $this->normalizeString($value);
    }

    public function setStatusAttribute(mixed $value): void
    {
        if ($value instanceof TransactionStatusEnum) {
            $this->attributes['status'] = $value->value;

            return;
        }

        $status = $this->normalizeEnumValue($value);

        if ($status === null) {
            throw new InvalidArgumentException('Transaction status is required.');
        }

        $enum = TransactionStatusEnum::tryFrom($status);

        if ($enum === null) {
            throw new InvalidArgumentException(sprintf('Invalid transaction status [%s].', $status));
        }

        $this->attributes['status'] = $enum->value;
    }

    public function setPaidAtAttribute(mixed $value): void
    {
        $date = $this->normalizeDateTimeValue($value, 'paid_at');

        $this->attributes['paid_at'] = $date?->format('Y-m-d H:i:s');
    }

    public function setRefundedAtAttribute(mixed $value): void
    {
        $date = $this->normalizeDateTimeValue($value, 'refunded_at');

        $this->attributes['refunded_at'] = $date?->format('Y-m-d H:i:s');
    }

    public function scopeByStatus(Builder $query, TransactionStatusEnum|string $status): Builder
    {
        $value = $status instanceof TransactionStatusEnum
            ? $status->value
            : $this->normalizeEnumValue($status);

        if ($value === null) {
            throw new InvalidArgumentException('Transaction status is required.');
        }

        return $query->where('status', $value);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', TransactionStatusEnum::PAID->value);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', TransactionStatusEnum::FAILED->value);
    }

    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', TransactionStatusEnum::PROCESSING->value);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', TransactionStatusEnum::PENDING->value);
    }

    public function scopeRefunded(Builder $query): Builder
    {
        return $query->where('status', TransactionStatusEnum::REFUNDED->value);
    }

    public function isPending(): bool
    {
        return $this->status === TransactionStatusEnum::PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === TransactionStatusEnum::PROCESSING;
    }

    public function isPaid(): bool
    {
        return $this->status === TransactionStatusEnum::PAID;
    }

    public function isFailed(): bool
    {
        return $this->status === TransactionStatusEnum::FAILED;
    }

    public function isRefunded(): bool
    {
        return $this->status === TransactionStatusEnum::REFUNDED;
    }

    public function amountInDecimal(): float
    {
        return ((int) $this->amount) / 100;
    }

    private function normalizeEnumValue(mixed $value): ?string
    {
        $normalized = $this->normalizeString($value);

        if ($normalized === null) {
            return null;
        }

        return mb_strtolower($normalized);
    }
}