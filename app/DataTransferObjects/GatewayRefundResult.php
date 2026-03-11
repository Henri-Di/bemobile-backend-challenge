<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

final class GatewayRefundResult
{
    /**
     * @param array<string, mixed> $responsePayload
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $externalRefundId,
        public readonly ?string $status,
        public readonly ?string $message,
        public readonly array $responsePayload = [],
    ) {
    }

    /**
     * Creates a successful refund result.
     *
     * @param array<string, mixed> $responsePayload
     */
    public static function success(
        ?string $externalRefundId = null,
        ?string $status = null,
        ?string $message = null,
        array $responsePayload = [],
    ): self {
        return new self(
            success: true,
            externalRefundId: self::normalizeNullableString($externalRefundId),
            status: self::normalizeNullableString($status),
            message: self::normalizeNullableString($message),
            responsePayload: self::sanitizePayload($responsePayload),
        );
    }

    /**
     * Creates a failed refund result.
     *
     * @param array<string, mixed> $responsePayload
     */
    public static function failure(
        ?string $message = null,
        array $responsePayload = [],
        ?string $status = null,
        ?string $externalRefundId = null,
    ): self {
        return new self(
            success: false,
            externalRefundId: self::normalizeNullableString($externalRefundId),
            status: self::normalizeNullableString($status),
            message: self::normalizeNullableString($message),
            responsePayload: self::sanitizePayload($responsePayload),
        );
    }

    /**
     * Indicates whether the refund operation failed.
     */
    public function failed(): bool
    {
        return $this->success === false;
    }

    /**
     * Indicates whether the result contains an external refund identifier.
     */
    public function hasExternalRefundId(): bool
    {
        return $this->externalRefundId !== null && $this->externalRefundId !== '';
    }

    /**
     * Converts the DTO to a structured array.
     *
     * @return array{
     *     success: bool,
     *     external_refund_id: ?string,
     *     status: ?string,
     *     message: ?string,
     *     response_payload: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'external_refund_id' => $this->externalRefundId,
            'status' => $this->status,
            'message' => $this->message,
            'response_payload' => $this->responsePayload,
        ];
    }

    /**
     * Converts the DTO to a safe array representation.
     *
     * Sensitive values in the payload are masked recursively.
     *
     * @return array{
     *     success: bool,
     *     external_refund_id: ?string,
     *     status: ?string,
     *     message: ?string,
     *     response_payload: array<string, mixed>
     * }
     */
    public function toSafeArray(): array
    {
        return [
            'success' => $this->success,
            'external_refund_id' => $this->externalRefundId,
            'status' => $this->status,
            'message' => $this->message,
            'response_payload' => self::maskSensitiveData($this->responsePayload),
        ];
    }

    /**
     * Normalizes nullable strings by trimming whitespace
     * and converting empty strings to null.
     */
    private static function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Ensures the payload is serializable and normalized as an array.
     *
     * @param array<mixed> $payload
     * @return array<string, mixed>
     */
    private static function sanitizePayload(array $payload): array
    {
        $normalized = json_decode(
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '[]',
            true
        );

        return is_array($normalized) ? $normalized : [];
    }

    /**
     * Recursively masks sensitive information from payload data.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function maskSensitiveData(array $payload): array
    {
        $sensitiveKeys = [
            'token',
            'access_token',
            'refresh_token',
            'authorization',
            'password',
            'secret',
            'api_key',
            'apikey',
            'client_secret',
            'document',
            'cpf',
            'cnpj',
            'card_number',
            'cardNumber',
            'number',
            'cvv',
            'cvc',
            'security_code',
        ];

        $masked = [];
        $normalizedSensitiveKeys = array_map('strtolower', $sensitiveKeys);

        foreach ($payload as $key => $value) {
            $stringKey = (string) $key;
            $normalizedKey = strtolower($stringKey);

            if (in_array($normalizedKey, $normalizedSensitiveKeys, true)) {
                $masked[$stringKey] = '***';
                continue;
            }

            if (is_array($value)) {
                $masked[$stringKey] = self::maskSensitiveData($value);
                continue;
            }

            $masked[$stringKey] = $value;
        }

        return $masked;
    }
}