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

final class GatewayTwoService extends AbstractGatewayService implements GatewayPaymentInterface
{
    public function code(): GatewayCodeEnum
    {
        return GatewayCodeEnum::GATEWAY_TWO;
    }

    public function name(): string
    {
        return 'Gateway Two';
    }

    public function isAvailable(): bool
    {
        try {
            $this->baseUrl();
            $this->transactionsEndpoint();
            $this->refundEndpoint();

            return true;
        } catch (GatewayIntegrationException) {
            return false;
        }
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, ['charge', 'refund'], true);
    }

    public function charge(PaymentChargeData $data): GatewayChargeResult
    {
        $internal = $data->toInternalArray();

        $requestPayload = [
            'valor' => $internal['amount'],
            'nome' => $internal['name'],
            'email' => $internal['email'],
            'numeroCartao' => $internal['card_number'],
            'cvv' => $internal['cvv'],
        ];

        $endpoint = $this->transactionsEndpoint();

        $response = $this->executeSafely(
            callback: fn () => $this->baseClient()->post($endpoint, $requestPayload),
            message: 'Gateway 2 charge request failed.',
            context: [
                'gateway' => $this->code()->value,
                'endpoint' => $endpoint,
                'request_payload' => $this->maskSensitive($requestPayload),
            ],
        );

        $json = $this->safeJson($response);

        if (! $response->successful()) {
            return GatewayChargeResult::failure(
                message: $this->normalizeMessage($json, 'Gateway 2 charge failed.'),
                responseCode: $this->normalizeResponseCode($json) ?? (string) $response->status(),
                requestPayload: $this->maskSensitive($requestPayload),
                responsePayload: $this->maskSensitive($json),
                status: $this->normalizeStatus($json),
            );
        }

        $externalId = $this->firstNonEmpty($json, [
            'id',
            'external_id',
            'transacao.id',
            'transacao_id',
            'data.id',
            'data.external_id',
        ]);

        if ($externalId === null) {
            return GatewayChargeResult::failure(
                message: 'Gateway 2 charge succeeded without external transaction id.',
                responseCode: $this->normalizeResponseCode($json) ?? (string) $response->status(),
                requestPayload: $this->maskSensitive($requestPayload),
                responsePayload: $this->maskSensitive($json),
                status: $this->normalizeStatus($json),
            );
        }

        return GatewayChargeResult::success(
            externalId: $externalId,
            status: $this->normalizeStatus($json),
            message: $this->normalizeMessage($json, 'Gateway 2 charge approved.'),
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
                message: 'Gateway 2 refund requires a valid external transaction id.',
                gatewayCode: $this->code()->value,
                context: [],
            );
        }

        if ($amount !== null && $amount <= 0) {
            throw new GatewayIntegrationException(
                message: 'Gateway 2 refund amount must be greater than zero when provided.',
                gatewayCode: $this->code()->value,
                context: [
                    'external_id' => $externalId,
                    'amount' => $amount,
                ],
            );
        }

        $endpoint = $this->refundEndpoint();

        $requestPayload = ['id' => $externalId];

        if ($amount !== null) {
            $requestPayload['amount'] = $amount;
        }

        $response = $this->executeSafely(
            callback: fn () => $this->baseClient()->post($endpoint, $requestPayload),
            message: 'Gateway 2 refund request failed.',
            context: [
                'gateway' => $this->code()->value,
                'endpoint' => $endpoint,
                'request_payload' => $this->maskSensitive($requestPayload),
            ],
        );

        $json = $this->safeJson($response);

        if (! $response->successful()) {
            return GatewayRefundResult::failure(
                message: $this->normalizeMessage($json, 'Gateway 2 refund failed.'),
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
            message: $this->normalizeMessage($json, 'Gateway 2 refund processed.'),
            responsePayload: $this->maskSensitive($json),
        );
    }

    protected function baseClient(): PendingRequest
    {
        return $this->httpClient(
            $this->baseUrl(),
            $this->defaultHeaders(),
        );
    }

    protected function baseUrl(): string
    {
        $value = trim((string) config('gateways.gateway2.base_url'));

        if ($value === '') {
            throw new GatewayIntegrationException(
                message: 'Gateway 2 base URL is not configured.',
                gatewayCode: $this->code()->value,
            );
        }

        return $value;
    }

    protected function transactionsEndpoint(): string
    {
        $value = trim((string) config('gateways.gateway2.endpoints.transactions'));

        if ($value === '') {
            throw new GatewayIntegrationException(
                message: 'Gateway 2 transactions endpoint is not configured.',
                gatewayCode: $this->code()->value,
            );
        }

        return $value;
    }

    protected function refundEndpoint(): string
    {
        $value = trim((string) config('gateways.gateway2.endpoints.refund'));

        if ($value === '') {
            throw new GatewayIntegrationException(
                message: 'Gateway 2 refund endpoint is not configured.',
                gatewayCode: $this->code()->value,
            );
        }

        return $value;
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        $headers = (array) config('gateways.gateway2.headers', []);
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
}