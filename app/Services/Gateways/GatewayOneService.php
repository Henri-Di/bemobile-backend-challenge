<?php

declare(strict_types=1);

namespace App\Services\Gateways;

use App\Contracts\GatewayPaymentInterface;
use App\DataTransferObjects\GatewayChargeResult;
use App\DataTransferObjects\GatewayRefundResult;
use App\DataTransferObjects\PaymentChargeData;
use App\Enums\GatewayCodeEnum;
use App\Exceptions\GatewayIntegrationException;
use Illuminate\Http\Client\PendingRequest;

final class GatewayOneService extends AbstractGatewayService implements GatewayPaymentInterface
{
    public function code(): GatewayCodeEnum
    {
        return GatewayCodeEnum::GATEWAY_ONE;
    }

    public function name(): string
    {
        return 'Gateway One';
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, ['charge', 'refund'], true);
    }

    public function isAvailable(): bool
    {
        try {
            $this->baseUrl();
            $this->loginEndpoint();
            $this->transactionsEndpoint();
            $this->gatewayAuthEmail();
            $this->gatewayAuthToken();

            return true;
        } catch (GatewayIntegrationException) {
            return false;
        }
    }

    public function charge(PaymentChargeData $data): GatewayChargeResult
    {
        $requestPayload = $data->toInternalArray();
        $token = $this->authenticate();

        $response = $this->executeSafely(
            callback: fn () => $this->authorizedClient($token)->post(
                $this->transactionsEndpoint(),
                $requestPayload
            ),
            message: 'Gateway 1 charge request failed.',
            context: [
                'gateway' => $this->code()->value,
                'endpoint' => $this->transactionsEndpoint(),
                'request_payload' => $this->maskSensitive($requestPayload),
            ],
        );

        $json = $this->safeJson($response);

        if (! $response->successful()) {
            return GatewayChargeResult::failure(
                message: $this->normalizeMessage($json, 'Gateway 1 charge failed.'),
                responseCode: $this->normalizeResponseCode($json) ?? (string) $response->status(),
                requestPayload: $this->maskSensitive($requestPayload),
                responsePayload: $this->maskSensitive($json),
                status: $this->normalizeStatus($json),
            );
        }

        $externalId = $this->firstNonEmpty($json, [
            'id',
            'external_id',
            'transaction_id',
            'data.id',
            'data.external_id',
            'data.transaction_id',
        ]);

        if ($externalId === null) {
            return GatewayChargeResult::failure(
                message: 'Gateway 1 charge succeeded without external transaction id.',
                responseCode: $this->normalizeResponseCode($json) ?? (string) $response->status(),
                requestPayload: $this->maskSensitive($requestPayload),
                responsePayload: $this->maskSensitive($json),
                status: $this->normalizeStatus($json),
            );
        }

        return GatewayChargeResult::success(
            externalId: $externalId,
            status: $this->normalizeStatus($json),
            message: $this->normalizeMessage($json, 'Gateway 1 charge approved.'),
            responseCode: $this->normalizeResponseCode($json) ?? (string) $response->status(),
            requestPayload: $this->maskSensitive($requestPayload),
            responsePayload: $this->maskSensitive($json),
        );
    }

    public function refund(string $externalId, ?int $amount = null): GatewayRefundResult
    {
        $externalId = trim($externalId);

        if ($externalId === '') {
            throw new GatewayIntegrationException(
                message: 'Gateway 1 refund requires a valid external transaction id.',
                gatewayCode: $this->code()->value,
                context: [],
            );
        }

        if ($amount !== null && $amount <= 0) {
            throw new GatewayIntegrationException(
                message: 'Gateway 1 refund amount must be greater than zero when provided.',
                gatewayCode: $this->code()->value,
                context: [
                    'external_id' => $externalId,
                    'amount' => $amount,
                ],
            );
        }

        $token = $this->authenticate();
        $endpoint = $this->chargebackEndpoint($externalId);

        $payload = $amount !== null ? ['amount' => $amount] : [];

        $response = $this->executeSafely(
            callback: fn () => $this->authorizedClient($token)->post($endpoint, $payload),
            message: 'Gateway 1 refund request failed.',
            context: [
                'gateway' => $this->code()->value,
                'endpoint' => $endpoint,
                'external_id' => $externalId,
                'request_payload' => $this->maskSensitive($payload),
            ],
        );

        $json = $this->safeJson($response);

        if (! $response->successful()) {
            return GatewayRefundResult::failure(
                message: $this->normalizeMessage($json, 'Gateway 1 refund failed.'),
                responsePayload: $this->maskSensitive($json),
                status: $this->normalizeStatus($json),
            );
        }

        return GatewayRefundResult::success(
            externalRefundId: $this->firstNonEmpty($json, [
                'id',
                'refund_id',
                'external_refund_id',
                'data.id',
                'data.refund_id',
                'data.external_refund_id',
            ]),
            status: $this->normalizeStatus($json),
            message: $this->normalizeMessage($json, 'Gateway 1 refund processed.'),
            responsePayload: $this->maskSensitive($json),
        );
    }

    protected function authenticate(): string
    {
        $payload = [
            'email' => $this->gatewayAuthEmail(),
            'token' => $this->gatewayAuthToken(),
        ];

        $response = $this->executeSafely(
            callback: fn () => $this->baseClient()->post($this->loginEndpoint(), $payload),
            message: 'Gateway 1 authentication request failed.',
            context: [
                'gateway' => $this->code()->value,
                'endpoint' => $this->loginEndpoint(),
                'request_payload' => $this->maskSensitive($payload),
            ],
        );

        $this->ensureSuccess(
            response: $response,
            message: 'Gateway 1 authentication failed.',
            context: [
                'endpoint' => $this->loginEndpoint(),
            ],
        );

        $json = $this->safeJson($response);

        $token = $this->firstNonEmpty($json, [
            'token',
            'access_token',
            'data.token',
            'data.access_token',
        ]);

        if ($token === null) {
            throw new GatewayIntegrationException(
                message: 'Gateway 1 authentication token was not returned.',
                gatewayCode: $this->code()->value,
                context: [
                    'endpoint' => $this->loginEndpoint(),
                    'response_json' => $this->maskSensitive($json),
                ],
            );
        }

        return $token;
    }

    protected function baseClient(): PendingRequest
    {
        return $this->httpClient(
            $this->baseUrl(),
            $this->defaultHeaders(),
        );
    }

    protected function authorizedClient(string $token): PendingRequest
    {
        return $this->httpClient(
            $this->baseUrl(),
            array_merge($this->defaultHeaders(), [
                'Authorization' => 'Bearer ' . trim($token),
            ]),
        );
    }

    protected function baseUrl(): string
    {
        $value = trim((string) config('gateways.gateway1.base_url'));

        if ($value === '') {
            throw new GatewayIntegrationException(
                message: 'Gateway 1 base URL is not configured.',
                gatewayCode: $this->code()->value,
            );
        }

        return $value;
    }

    protected function loginEndpoint(): string
    {
        $value = trim((string) config('gateways.gateway1.endpoints.login'));

        if ($value === '') {
            throw new GatewayIntegrationException(
                message: 'Gateway 1 login endpoint is not configured.',
                gatewayCode: $this->code()->value,
            );
        }

        return $value;
    }

    protected function transactionsEndpoint(): string
    {
        $value = trim((string) config('gateways.gateway1.endpoints.transactions'));

        if ($value === '') {
            throw new GatewayIntegrationException(
                message: 'Gateway 1 transactions endpoint is not configured.',
                gatewayCode: $this->code()->value,
            );
        }

        return $value;
    }

    protected function chargebackEndpoint(string $externalId): string
    {
        $template = trim((string) config('gateways.gateway1.endpoints.chargeback'));

        if ($template === '') {
            throw new GatewayIntegrationException(
                message: 'Gateway 1 chargeback endpoint is not configured.',
                gatewayCode: $this->code()->value,
            );
        }

        return str_replace('{id}', $externalId, $template);
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        $headers = (array) config('gateways.gateway1.headers', []);
        $normalized = [];

        foreach ($headers as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (is_scalar($value)) {
                $normalized[$key] = (string) $value;
            }
        }

        return $normalized;
    }

    protected function gatewayAuthEmail(): string
    {
        $value = trim((string) config('gateways.gateway1.auth.email'));

        if ($value === '') {
            throw new GatewayIntegrationException(
                message: 'Gateway 1 authentication email is not configured.',
                gatewayCode: $this->code()->value,
            );
        }

        return $value;
    }

    protected function gatewayAuthToken(): string
    {
        $value = trim((string) config('gateways.gateway1.auth.token'));

        if ($value === '') {
            throw new GatewayIntegrationException(
                message: 'Gateway 1 authentication token is not configured.',
                gatewayCode: $this->code()->value,
            );
        }

        return $value;
    }
}