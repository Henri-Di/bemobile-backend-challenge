<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RefundStatusEnum;
use App\Models\Concerns\HandlesBrazilianDateTimes;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use JsonException;

final class Refund extends Model
{
    use HasFactory;
    use HandlesBrazilianDateTimes;

    protected $table = 'refunds';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'transaction_id',
        'gateway_id',
        'external_refund_id',
        'status',
        'amount',
        'requested_by_user_id',
        'response_payload_json',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'transaction_id' => 'integer',
            'gateway_id' => 'integer',
            'requested_by_user_id' => 'integer',
            'amount' => 'integer',
            'status' => RefundStatusEnum::class,
            'response_payload_json' => 'array',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class);
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function setTransactionIdAttribute(mixed $value): void
    {
        $transactionId = $this->normalizePositiveInteger($value);

        if ($transactionId === null) {
            throw new InvalidArgumentException('Transaction id is required.');
        }

        $this->attributes['transaction_id'] = $transactionId;
    }

    public function setGatewayIdAttribute(mixed $value): void
    {
        $gatewayId = $this->normalizePositiveInteger($value);

        if ($gatewayId === null) {
            throw new InvalidArgumentException('Gateway id is required.');
        }

        $this->attributes['gateway_id'] = $gatewayId;
    }

    public function setRequestedByUserIdAttribute(mixed $value): void
    {
        $requestedByUserId = $this->normalizePositiveInteger($value);

        if ($requestedByUserId === null) {
            throw new InvalidArgumentException('Requested by user id is required.');
        }

        $this->attributes['requested_by_user_id'] = $requestedByUserId;
    }

    public function setAmountAttribute(mixed $value): void
    {
        $amount = $this->normalizePositiveInteger($value);

        if ($amount === null) {
            throw new InvalidArgumentException('Refund amount is required.');
        }

        $this->attributes['amount'] = $amount;
    }

    public function setExternalRefundIdAttribute(mixed $value): void
    {
        $this->attributes['external_refund_id'] = $this->normalizeString($value);
    }

    public function setStatusAttribute(mixed $value): void
    {
        if ($value instanceof RefundStatusEnum) {
            $this->attributes['status'] = $value->value;

            return;
        }

        $status = $this->normalizeEnumValue($value);

        if ($status === null) {
            throw new InvalidArgumentException('Refund status is required.');
        }

        $enum = RefundStatusEnum::tryFrom($status);

        if ($enum === null) {
            throw new InvalidArgumentException(sprintf('Invalid refund status [%s].', $status));
        }

        $this->attributes['status'] = $enum->value;
    }

    public function setProcessedAtAttribute(mixed $value): void
    {
        $date = $this->normalizeDateTimeInput($value);

        $this->attributes['processed_at'] = $date?->format('Y-m-d H:i:s');
    }

    public function setResponsePayloadJsonAttribute(mixed $value): void
    {
        $payload = $this->normalizeArrayPayload($value);

        if ($payload === null) {
            $this->attributes['response_payload_json'] = null;

            return;
        }

        try {
            $this->attributes['response_payload_json'] = json_encode(
                $this->maskSensitiveData($payload),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            throw new InvalidArgumentException('Invalid response_payload_json value.');
        }
    }

    public function scopeByStatus(Builder $query, RefundStatusEnum|string $status): Builder
    {
        $value = $status instanceof RefundStatusEnum
            ? $status->value
            : $this->normalizeEnumValue($status);

        if ($value === null) {
            throw new InvalidArgumentException('Refund status is required.');
        }

        return $query->where('status', $value);
    }

    public function scopeProcessed(Builder $query): Builder
    {
        return $query->where('status', RefundStatusEnum::PROCESSED->value);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', RefundStatusEnum::FAILED->value);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', RefundStatusEnum::PENDING->value);
    }

    public function isPending(): bool
    {
        return $this->status === RefundStatusEnum::PENDING;
    }

    public function isProcessed(): bool
    {
        return $this->status === RefundStatusEnum::PROCESSED;
    }

    public function isFailed(): bool
    {
        return $this->status === RefundStatusEnum::FAILED;
    }

    public function amountInDecimal(): float
    {
        return ((int) $this->amount) / 100;
    }

    /**
     * @param array<int|string, mixed> $data
     * @return array<int|string, mixed>
     */
    protected function maskSensitiveData(array $data): array
    {
        $masked = [];
        $sensitiveKeys = $this->sensitivePayloadKeys();

        foreach ($data as $key => $value) {
            $stringKey = (string) $key;
            $normalizedKey = mb_strtolower(trim($stringKey));

            if (in_array($normalizedKey, $sensitiveKeys, true)) {
                $masked[$key] = '***';
                continue;
            }

            if (is_array($value)) {
                $masked[$key] = $this->maskSensitiveData($value);
                continue;
            }

            if (is_object($value)) {
                $masked[$key] = $this->maskSensitiveData((array) $value);
                continue;
            }

            if (is_string($value) && $this->looksSensitiveValue($value)) {
                $masked[$key] = '***';
                continue;
            }

            $masked[$key] = $value;
        }

        return $masked;
    }

    /**
     * @return array<int, string>
     */
    protected function sensitivePayloadKeys(): array
    {
        return [
            'token',
            'secret',
            'password',
            'authorization',
            'access_token',
            'refresh_token',
            'client_secret',
            'api_key',
            'apikey',
            'private_key',
            'public_key',
            'card_number',
            'cvv',
            'cvc',
            'security_code',
            'gateway-auth-token',
            'gateway-auth-secret',
        ];
    }

    protected function looksSensitiveValue(string $value): bool
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return false;
        }

        return mb_strlen($normalized) >= 24
            && preg_match('/^[A-Za-z0-9\-\._=\/+]+$/', $normalized) === 1;
    }

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

        return $normalized;
    }

    private function normalizeEnumValue(mixed $value): ?string
    {
        $normalized = $this->normalizeString($value);

        if ($normalized === null) {
            return null;
        }

        return mb_strtolower($normalized);
    }

    /**
     * @return array<int|string, mixed>|null
     */
    private function normalizeArrayPayload(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '') {
                return null;
            }

            try {
                $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);

                return is_array($decoded)
                    ? $decoded
                    : throw new InvalidArgumentException('Invalid response_payload_json value.');
            } catch (JsonException) {
                throw new InvalidArgumentException('Invalid response_payload_json value.');
            }
        }

        throw new InvalidArgumentException('Invalid response_payload_json value.');
    }

    private function normalizeDateTimeInput(mixed $value): ?DateTimeInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        if (method_exists($this, 'normalizeDateTimeValue')) {
            /** @var \DateTimeInterface|null $date */
            $date = $this->normalizeDateTimeValue($value, 'processed_at');

            return $date;
        }

        throw new InvalidArgumentException('Invalid processed_at value.');
    }
}