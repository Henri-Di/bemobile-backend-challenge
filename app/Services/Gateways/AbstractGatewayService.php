<?php

declare(strict_types=1);

namespace App\Services\Gateways;

use App\Enums\GatewayCodeEnum;
use App\Exceptions\GatewayIntegrationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Throwable;

abstract class AbstractGatewayService
{
    public function __construct(
        protected readonly HttpFactory $http,
    ) {
    }

    /**
     * Returns the unique internal gateway code.
     */
    abstract public function code(): GatewayCodeEnum;

    /**
     * Builds a configured HTTP client for gateway communication.
     *
     * @param array<string, string> $headers
     */
    protected function httpClient(string $baseUrl, array $headers = []): PendingRequest
    {
        return $this->http
            ->baseUrl($this->normalizeBaseUrl($baseUrl))
            ->acceptJson()
            ->asJson()
            ->timeout($this->httpTimeout())
            ->connectTimeout($this->httpConnectTimeout())
            ->retry(
                $this->httpRetryTimes(),
                $this->httpRetrySleep(),
                fn (Throwable $exception): bool => $this->shouldRetry($exception)
            )
            ->withHeaders($this->maskEmptyHeaders($headers));
    }

    /**
     * @return array<string, mixed>
     */
    protected function safeJson(Response $response): array
    {
        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @throws GatewayIntegrationException
     */
    protected function ensureSuccess(Response $response, string $message, array $context = []): void
    {
        if ($response->successful()) {
            return;
        }

        throw new GatewayIntegrationException(
            message: $message,
            gatewayCode: $this->code()->value,
            context: $this->maskSensitive(array_merge($context, [
                'http_status' => $response->status(),
                'response_body' => $this->truncateString($response->body(), 5000),
                'response_json' => $this->safeJson($response),
            ])),
        );
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     * @param array<string, mixed> $context
     * @return T
     *
     * @throws GatewayIntegrationException
     */
    protected function executeSafely(callable $callback, string $message, array $context = []): mixed
    {
        try {
            return $callback();
        } catch (GatewayIntegrationException $exception) {
            throw $exception;
        } catch (ConnectionException|RequestException $exception) {
            throw new GatewayIntegrationException(
                message: $message,
                gatewayCode: $this->code()->value,
                context: $this->maskSensitive(array_merge($context, [
                    'exception' => $exception::class,
                    'error' => $exception->getMessage(),
                ])),
                previous: $exception,
            );
        } catch (Throwable $exception) {
            throw new GatewayIntegrationException(
                message: $message,
                gatewayCode: $this->code()->value,
                context: $this->maskSensitive(array_merge($context, [
                    'exception' => $exception::class,
                    'error' => $exception->getMessage(),
                ])),
                previous: $exception,
            );
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $keys
     */
    protected function firstNonEmpty(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = Arr::get($payload, $key);

            if (is_string($value)) {
                $value = trim($value);

                if ($value !== '') {
                    return $value;
                }

                continue;
            }

            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function normalizeMessage(array $payload, ?string $fallback = null): ?string
    {
        return $this->firstNonEmpty($payload, [
            'message',
            'mensagem',
            'error',
            'erro',
            'detail',
            'details',
            'description',
        ]) ?? $fallback;
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function normalizeStatus(array $payload): ?string
    {
        return $this->firstNonEmpty($payload, [
            'status',
            'situacao',
            'state',
            'payment_status',
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function normalizeResponseCode(array $payload): ?string
    {
        return $this->firstNonEmpty($payload, [
            'code',
            'codigo',
            'response_code',
            'status_code',
            'error_code',
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function maskSensitive(array $payload): array
    {
        $sensitiveKeys = array_map('strtolower', [
            'token',
            'secret',
            'password',
            'cvv',
            'cvc',
            'security_code',
            'cardNumber',
            'card_number',
            'numeroCartao',
            'number',
            'authorization',
            'api_key',
            'apikey',
            'client_secret',
            'access_token',
            'refresh_token',
            'Gateway-Auth-Token',
            'Gateway-Auth-Secret',
        ]);

        $masked = [];

        foreach ($payload as $key => $value) {
            $stringKey = (string) $key;
            $normalizedKey = strtolower($stringKey);

            if (in_array($normalizedKey, $sensitiveKeys, true)) {
                $masked[$stringKey] = '***';
                continue;
            }

            if (is_array($value)) {
                $masked[$stringKey] = $this->maskSensitive($value);
                continue;
            }

            $masked[$stringKey] = is_string($value)
                ? $this->maskSensitiveStringByKey($normalizedKey, $value)
                : $value;
        }

        return $masked;
    }

    protected function shouldRetry(Throwable $exception): bool
    {
        return $exception instanceof ConnectionException
            || $exception instanceof RequestException;
    }

    protected function httpTimeout(): int
    {
        return max(1, (int) config('gateways.http.timeout', 10));
    }

    protected function httpConnectTimeout(): int
    {
        return max(1, (int) config('gateways.http.connect_timeout', 5));
    }

    protected function httpRetryTimes(): int
    {
        return max(0, (int) config('gateways.http.retry_times', 2));
    }

    protected function httpRetrySleep(): int
    {
        return max(0, (int) config('gateways.http.retry_sleep', 200));
    }

    protected function normalizeBaseUrl(string $baseUrl): string
    {
        return rtrim(trim($baseUrl), '/');
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    protected function maskEmptyHeaders(array $headers): array
    {
        return array_filter(
            $headers,
            static fn (mixed $value): bool => is_string($value) && trim($value) !== ''
        );
    }

    protected function truncateString(?string $value, int $limit = 5000): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_strlen($value) > $limit
            ? mb_substr($value, 0, $limit) . '...'
            : $value;
    }

    protected function maskSensitiveStringByKey(string $key, string $value): string
    {
        if (
            str_contains($key, 'token')
            || str_contains($key, 'secret')
            || str_contains($key, 'password')
            || str_contains($key, 'authorization')
        ) {
            return '***';
        }

        return $value;
    }
}