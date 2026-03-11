<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

final class GatewayChargeResult
{
    /**
     * @param array<string, mixed> $requestPayload
     * @param array<string, mixed> $responsePayload
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $externalId,
        public readonly ?string $status,
        public readonly ?string $message,
        public readonly ?string $responseCode,
        public readonly array $requestPayload = [],
        public readonly array $responsePayload = [],
    ) {
    }

    /**
     * Creates a successful charge result.
     *
     * @param array<string, mixed> $requestPayload
     * @param array<string, mixed> $responsePayload
     */
    public static function success(
        string $externalId,
        ?string $status = null,
        ?string $message = null,
        ?string $responseCode = null,
        array $requestPayload = [],
        array $responsePayload = [],
    ): self {
        return new self(
            success: true,
            externalId: trim($externalId),
            status: self::normalizeNullableString($status),
            message: self::normalizeNullableString($message),
            responseCode: self::normalizeNullableString($responseCode),
            requestPayload: self::sanitizePayload($requestPayload),
            responsePayload: self::sanitizePayload($responsePayload),
        );
    }

    /**
     * Creates a failed charge result.
     *
     * @param array<string, mixed> $requestPayload
     * @param array<string, mixed> $responsePayload
     */
    public static function failure(
        ?string $message = null,
        ?string $responseCode = null,
        array $requestPayload = [],
        array $responsePayload = [],
        ?string $status = null,
        ?string $externalId = null,
    ): self {
        return new self(
            success: false,
            externalId: self::normalizeNullableString($externalId),
            status: self::normalizeNullableString($status),
            message: self::normalizeNullableString($message),
            responseCode: self::normalizeNullableString($responseCode),
            requestPayload: self::sanitizePayload($requestPayload),
            responsePayload: self::sanitizePayload($responsePayload),
        );
    }

    /**
     * Indicates whether the operation failed.
     */
    public function failed(): bool
    {
        return $this->success === false;
    }

    /**
     * Indicates whether the result contains an external transaction reference.
     */
    public function hasExternalId(): bool
    {
        return $this->externalId !== null && $this->externalId !== '';
    }

    /**
     * Returns the result as a structured array.
     *
     * @return array{
     *     success: bool,
     *     external_id: ?string,
     *     status: ?string,
     *     message: ?string,
     *     response_code: ?string,
     *     request_payload: array<string, mixed>,
     *     response_payload: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'external_id' => $this->externalId,
            'status' => $this->status,
            'message' => $this->message,
            'response_code' => $this->responseCode,
            'request_payload' => $this->requestPayload,
            'response_payload' => $this->responsePayload,
        ];
    }

    /**
     * Returns a safe array version suitable for logs and API output.
     *
     * Sensitive keys are masked recursively.
     *
     * @return array{
     *     success: bool,
     *     external_id: ?string,
     *     status: ?string,
     *     message: ?string,
     *     response_code: ?string,
     *     request_payload: array<string, mixed>,
     *     response_payload: array<string, mixed>
     * }
     */
    public function toSafeArray(): array
    {
        return [
            'success' => $this->success,
            'external_id' => $this->externalId,
            'status' => $this->status,
            'message' => $this->message,
            'response_code' => $this->responseCode,
            'request_payload' => self::maskSensitiveData($this->requestPayload),
            'response_payload' => self::maskSensitiveData($this->responsePayload),
        ];
    }

    /**
     * @param array<mixed> $payload
     * @return array<string, mixed>
     */
    private static function sanitizePayload(array $payload): array
    {
        /** @var array<string, mixed> $normalized */
        $normalized = json_decode(
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '[]',
            true
        );

        return is_array($normalized) ? $normalized : [];
    }

    private static function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function maskSensitiveData(array $payload): array
    {
        $sensitiveKeys = [
            'card_number',
            'cardNumber',
            'number',
            'cvv',
            'cvc',
            'security_code',
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
        ];

        $masked = [];

        foreach ($payload as $key => $value) {
            $normalizedKey = is_string($key) ? strtolower($key) : (string) $key;

            if (in_array($normalizedKey, array_map('strtolower', $sensitiveKeys), true)) {
                $masked[(string) $key] = '***';
                continue;
            }

            if (is_array($value)) {
                $masked[(string) $key] = self::maskSensitiveData($value);
                continue;
            }

            $masked[(string) $key] = $value;
        }

        return $masked;
    }
}