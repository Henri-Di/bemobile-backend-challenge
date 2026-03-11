<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransactionAttemptStatusEnum;
use App\Models\Concerns\HandlesBrazilianDateTimes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use JsonException;

class TransactionAttempt extends Model
{
    use HasFactory;
    use HandlesBrazilianDateTimes;

    protected $table = 'transaction_attempts';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'transaction_id',
        'gateway_id',
        'attempt_number',
        'status',
        'external_id',
        'request_payload_json',
        'response_payload_json',
        'error_message',
        'processed_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'transaction_id' => 'integer',
        'gateway_id' => 'integer',
        'attempt_number' => 'integer',
        'status' => TransactionAttemptStatusEnum::class,
        'request_payload_json' => 'array',
        'response_payload_json' => 'array',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Returns the transaction associated with this attempt.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Returns the gateway associated with this attempt.
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class);
    }

    /**
     * Mutator for transaction_id.
     *
     * @throws InvalidArgumentException
     */
    public function setTransactionIdAttribute(mixed $value): void
    {
        $transactionId = $this->normalizeInteger($value, 'transaction_id', 1);

        if ($transactionId === null) {
            throw new InvalidArgumentException('Transaction id is required.');
        }

        $this->attributes['transaction_id'] = $transactionId;
    }

    /**
     * Mutator for gateway_id.
     *
     * @throws InvalidArgumentException
     */
    public function setGatewayIdAttribute(mixed $value): void
    {
        $gatewayId = $this->normalizeInteger($value, 'gateway_id', 1);

        if ($gatewayId === null) {
            throw new InvalidArgumentException('Gateway id is required.');
        }

        $this->attributes['gateway_id'] = $gatewayId;
    }

    /**
     * Mutator for attempt_number.
     *
     * @throws InvalidArgumentException
     */
    public function setAttemptNumberAttribute(mixed $value): void
    {
        $attemptNumber = $this->normalizeInteger($value, 'attempt_number', 1);

        if ($attemptNumber === null) {
            throw new InvalidArgumentException('Attempt number is required.');
        }

        $this->attributes['attempt_number'] = $attemptNumber;
    }

    /**
     * Mutator for status.
     *
     * Accepts enum instances or raw string values.
     *
     * @throws InvalidArgumentException
     */
    public function setStatusAttribute(mixed $value): void
    {
        if ($value instanceof TransactionAttemptStatusEnum) {
            $this->attributes['status'] = $value->value;

            return;
        }

        $status = $this->normalizeString($value, true);

        if ($status === null) {
            throw new InvalidArgumentException('Transaction attempt status is required.');
        }

        $enum = TransactionAttemptStatusEnum::tryFrom($status);

        if ($enum === null) {
            throw new InvalidArgumentException(
                sprintf('Invalid transaction attempt status [%s].', $status)
            );
        }

        $this->attributes['status'] = $enum->value;
    }

    /**
     * Mutator for external_id.
     */
    public function setExternalIdAttribute(mixed $value): void
    {
        $this->attributes['external_id'] = $this->normalizeString($value);
    }

    /**
     * Mutator for error_message.
     */
    public function setErrorMessageAttribute(mixed $value): void
    {
        $this->attributes['error_message'] = $this->normalizeString($value);
    }

    /**
     * Mutator for processed_at.
     *
     * @throws InvalidArgumentException
     */
    public function setProcessedAtAttribute(mixed $value): void
    {
        $date = $this->normalizeDateTimeValue($value, 'processed_at');

        $this->attributes['processed_at'] = $date?->format('Y-m-d H:i:s');
    }

    /**
     * Mutator for request_payload_json.
     *
     * @throws InvalidArgumentException
     */
    public function setRequestPayloadJsonAttribute(mixed $value): void
    {
        $payload = $this->normalizeArray($value, 'request_payload_json');

        if ($payload === null) {
            $this->attributes['request_payload_json'] = null;

            return;
        }

        try {
            $this->attributes['request_payload_json'] = json_encode(
                $this->maskSensitiveData($payload),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            throw new InvalidArgumentException('Invalid request_payload_json value.');
        }
    }

    /**
     * Mutator for response_payload_json.
     *
     * @throws InvalidArgumentException
     */
    public function setResponsePayloadJsonAttribute(mixed $value): void
    {
        $payload = $this->normalizeArray($value, 'response_payload_json');

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

    /**
     * Scope attempts by status.
     */
    public function scopeByStatus(Builder $query, TransactionAttemptStatusEnum|string $status): Builder
    {
        $value = $status instanceof TransactionAttemptStatusEnum
            ? $status->value
            : (string) $status;

        return $query->where('status', $value);
    }

    /**
     * Scope successful attempts.
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', TransactionAttemptStatusEnum::SUCCESS->value);
    }

    /**
     * Scope failed attempts.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', TransactionAttemptStatusEnum::FAILED->value);
    }

    /**
     * Scope pending attempts.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', TransactionAttemptStatusEnum::PENDING->value);
    }

    /**
     * Indicates whether the attempt is pending.
     */
    public function isPending(): bool
    {
        return $this->status === TransactionAttemptStatusEnum::PENDING;
    }

    /**
     * Indicates whether the attempt was successful.
     */
    public function isSuccess(): bool
    {
        return $this->status === TransactionAttemptStatusEnum::SUCCESS;
    }

    /**
     * Indicates whether the attempt has failed.
     */
    public function isFailed(): bool
    {
        return $this->status === TransactionAttemptStatusEnum::FAILED;
    }

    /**
     * Recursively masks sensitive payload fields.
     *
     * @param array<int|string, mixed> $data
     * @return array<int|string, mixed>
     */
    protected function maskSensitiveData(array $data): array
    {
        $sensitiveKeys = array_map('strtolower', [
            'cardNumber',
            'card_number',
            'numeroCartao',
            'number',
            'cvv',
            'cvc',
            'security_code',
            'token',
            'secret',
            'password',
            'authorization',
            'access_token',
            'refresh_token',
            'client_secret',
            'api_key',
            'apikey',
            'Gateway-Auth-Token',
            'Gateway-Auth-Secret',
        ]);

        $masked = [];

        foreach ($data as $key => $value) {
            $stringKey = (string) $key;
            $normalizedKey = strtolower($stringKey);

            if (in_array($normalizedKey, $sensitiveKeys, true)) {
                $masked[$key] = '***';
                continue;
            }

            if (is_array($value)) {
                $masked[$key] = $this->maskSensitiveData($value);
                continue;
            }

            $masked[$key] = $value;
        }

        return $masked;
    }
}