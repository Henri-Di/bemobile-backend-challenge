<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\GatewayPaymentInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRefundRequest;
use App\Models\Gateway;
use App\Models\Refund;
use App\Models\Transaction;
use App\Services\Gateways\GatewayOneService;
use App\Services\Gateways\GatewayTwoService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class RefundController extends Controller
{
    private const MESSAGE_ONLY_PAID_TRANSACTIONS = 'Only paid transactions can be refunded.';
    private const MESSAGE_INVALID_GATEWAY_REFERENCE = 'Transaction does not have a valid gateway reference for refund.';
    private const MESSAGE_GATEWAY_NOT_FOUND = 'Gateway not found for this transaction.';
    private const MESSAGE_UNSUPPORTED_GATEWAY = 'Unsupported gateway code.';
    private const MESSAGE_REFUND_PROCESSED = 'Refund processed successfully.';
    private const MESSAGE_REFUND_FAILED = 'Refund failed.';
    private const MESSAGE_REFUND_AMOUNT_EXCEEDS_TRANSACTION = 'Refund amount cannot exceed the original transaction amount.';
    private const MESSAGE_TRANSACTION_ALREADY_REFUNDED = 'Transaction has already been refunded.';
    private const MESSAGE_REFUND_PROCESSING_ERROR = 'Refund processing failed.';
    private const MESSAGE_INVALID_TRANSACTION_AMOUNT = 'Transaction amount is invalid for refund processing.';

    private const GATEWAY_ONE_BINDING = 'gateway.refund.service.gateway_1';
    private const GATEWAY_TWO_BINDING = 'gateway.refund.service.gateway_2';

    public function store(StoreRefundRequest $request, Transaction $transaction): JsonResponse
    {
        $validated = $request->validated();
        $refundAmount = $this->normalizeNullablePositiveInt($validated['amount'] ?? null);

        $transaction->loadMissing('gateway');

        if ($this->isRefundedTransaction($transaction)) {
            return $this->errorResponse(
                self::MESSAGE_TRANSACTION_ALREADY_REFUNDED,
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (! $this->isPaidTransaction($transaction)) {
            return $this->errorResponse(
                self::MESSAGE_ONLY_PAID_TRANSACTIONS,
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (! $this->hasValidGatewayReference($transaction)) {
            return $this->errorResponse(
                self::MESSAGE_INVALID_GATEWAY_REFERENCE,
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $gatewayModel = $transaction->gateway;

        if (! $gatewayModel instanceof Gateway) {
            return $this->errorResponse(
                self::MESSAGE_GATEWAY_NOT_FOUND,
                Response::HTTP_NOT_FOUND
            );
        }

        $transactionAmount = $this->normalizeNullablePositiveInt($transaction->amount);

        if ($transactionAmount === null) {
            return $this->errorResponse(
                self::MESSAGE_INVALID_TRANSACTION_AMOUNT,
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if ($refundAmount !== null && $refundAmount > $transactionAmount) {
            return $this->errorResponse(
                self::MESSAGE_REFUND_AMOUNT_EXCEEDS_TRANSACTION,
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        try {
            $gatewayService = $this->resolveGatewayService((string) $gatewayModel->code);
        } catch (Throwable $exception) {
            report($exception);

            return $this->errorResponse(
                self::MESSAGE_UNSUPPORTED_GATEWAY,
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $amountToRefund = $refundAmount ?? $transactionAmount;

        try {
            $result = $gatewayService->refund(
                (string) $transaction->external_id,
                $amountToRefund
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->errorResponse(
                self::MESSAGE_REFUND_PROCESSING_ERROR,
                Response::HTTP_INTERNAL_SERVER_ERROR,
                [
                    'error' => [
                        'type' => 'gateway_exception',
                    ],
                ]
            );
        }

        if (! (bool) ($result->success ?? false)) {
            return $this->errorResponse(
                $this->truncateString(
                    $this->toNullableString($result->message ?? null),
                    300
                ) ?? self::MESSAGE_REFUND_FAILED,
                Response::HTTP_UNPROCESSABLE_ENTITY,
                [
                    'data' => [
                        'status' => $this->toNullableString($result->status ?? null),
                        'response_payload' => $this->sanitizePayload($result->responsePayload ?? null),
                    ],
                ]
            );
        }

        try {
            /** @var Refund $refund */
            $refund = DB::transaction(function () use (
                $transaction,
                $gatewayModel,
                $request,
                $amountToRefund,
                $result
            ): Refund {
                $now = now();

                /** @var Refund $createdRefund */
                $createdRefund = Refund::query()->create([
                    'transaction_id' => (int) $transaction->id,
                    'gateway_id' => (int) $gatewayModel->id,
                    'external_refund_id' => $this->truncateString(
                        $this->toNullableString($result->externalRefundId ?? null),
                        120
                    ),
                    'status' => $this->refundPersistenceStatus($result->status ?? null),
                    'amount' => $amountToRefund,
                    'requested_by_user_id' => $this->extractUserId($request->user()),
                    'response_payload_json' => $this->sanitizePayload($result->responsePayload ?? null),
                    'processed_at' => $now,
                ]);

                $transaction->forceFill([
                    'status' => 'refunded',
                    'refunded_at' => $now,
                ])->save();

                return $createdRefund;
            });
        } catch (Throwable $exception) {
            report($exception);

            return $this->errorResponse(
                self::MESSAGE_REFUND_PROCESSING_ERROR,
                Response::HTTP_INTERNAL_SERVER_ERROR,
                [
                    'error' => [
                        'type' => 'refund_persistence_exception',
                    ],
                ]
            );
        }

        $refund->loadMissing([
            'transaction',
            'gateway',
        ]);

        return $this->successResponse(
            message: self::MESSAGE_REFUND_PROCESSED,
            data: [
                'refund' => [
                    'id' => (int) $refund->id,
                    'transaction_id' => (int) $refund->transaction_id,
                    'gateway_id' => (int) $refund->gateway_id,
                    'external_refund_id' => $this->toNullableString($refund->external_refund_id),
                    'status' => $this->toNullableString($refund->status),
                    'amount' => $this->normalizeNullablePositiveInt($refund->amount),
                    'requested_by_user_id' => $this->extractNullableInt($refund->requested_by_user_id),
                    'processed_at' => $this->normalizeDateTimeOutput($refund->processed_at),
                ],
                'transaction_id' => (int) $transaction->id,
                'external_refund_id' => $this->toNullableString($result->externalRefundId ?? null),
                'status' => $this->toNullableString($result->status ?? null),
            ],
            status: Response::HTTP_OK
        );
    }

    private function resolveGatewayService(string $gatewayCode): GatewayPaymentInterface
    {
        return match ($gatewayCode) {
            'gateway_1' => $this->resolveBoundGatewayService(
                self::GATEWAY_ONE_BINDING,
                GatewayOneService::class
            ),
            'gateway_2' => $this->resolveBoundGatewayService(
                self::GATEWAY_TWO_BINDING,
                GatewayTwoService::class
            ),
            default => throw new \RuntimeException(self::MESSAGE_UNSUPPORTED_GATEWAY),
        };
    }

    /**
     * @param class-string $fallbackConcrete
     */
    private function resolveBoundGatewayService(string $bindingKey, string $fallbackConcrete): GatewayPaymentInterface
    {
        $service = app()->bound($bindingKey)
            ? app($bindingKey)
            : app($fallbackConcrete);

        if (! $service instanceof GatewayPaymentInterface) {
            throw new \RuntimeException(self::MESSAGE_UNSUPPORTED_GATEWAY);
        }

        return $service;
    }

    private function isPaidTransaction(Transaction $transaction): bool
    {
        return $this->extractTransactionStatus($transaction) === 'paid';
    }

    private function isRefundedTransaction(Transaction $transaction): bool
    {
        return $this->extractTransactionStatus($transaction) === 'refunded';
    }

    private function extractTransactionStatus(Transaction $transaction): ?string
    {
        $status = $transaction->status;

        if (is_object($status) && isset($status->value) && is_scalar($status->value)) {
            return $this->normalizeStatus($status->value);
        }

        return $this->normalizeStatus($status);
    }

    private function hasValidGatewayReference(Transaction $transaction): bool
    {
        $gatewayId = $this->normalizeNullablePositiveInt($transaction->gateway_id);
        $externalId = $this->toNullableString($transaction->external_id);

        return $gatewayId !== null && $externalId !== null;
    }

    private function refundPersistenceStatus(mixed $gatewayStatus): string
    {
        $normalized = $this->normalizeStatus($gatewayStatus);

        if ($normalized === null) {
            return 'processed';
        }

        return match ($normalized) {
            'processed', 'success', 'succeeded', 'approved', 'done', 'completed' => 'processed',
            'failed', 'error', 'denied', 'rejected' => 'failed',
            default => 'processed',
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function successResponse(string $message, array $data = [], int $status = Response::HTTP_OK): JsonResponse
    {
        $response = response()->json([
            'message' => $message,
            'data' => $data,
        ], $status);

        $this->applyDefaultSecurityHeaders($response);

        return $response;
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function errorResponse(string $message, int $status, array $extra = []): JsonResponse
    {
        $payload = array_merge([
            'message' => $message,
        ], $extra);

        $response = response()->json($payload, $status);

        $this->applyDefaultSecurityHeaders($response);

        return $response;
    }

    private function applyDefaultSecurityHeaders(JsonResponse $response): void
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
    }

    private function normalizeStatus(mixed $value): ?string
    {
        $status = $this->toNullableString($value);

        if ($status === null) {
            return null;
        }

        return mb_strtolower($status);
    }

    private function normalizeNullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '' || ! preg_match('/^\d+$/', $value)) {
                return null;
            }

            $normalized = (int) $value;

            return $normalized > 0 ? $normalized : null;
        }

        if (is_float($value)) {
            $normalized = (int) $value;

            return $normalized > 0 ? $normalized : null;
        }

        return null;
    }

    private function extractNullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function normalizeDateTimeOutput(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return null;
    }

    private function toNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        if (is_scalar($value)) {
            $string = trim((string) $value);

            return $string === '' ? null : $string;
        }

        return null;
    }

    private function truncateString(?string $value, int $limit = 255): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_strlen($value) > $limit
            ? mb_substr($value, 0, $limit) . '...'
            : $value;
    }

    private function sanitizePayload(mixed $payload): mixed
    {
        if (is_array($payload)) {
            $sanitized = [];

            foreach ($payload as $key => $value) {
                $sanitized[$this->sanitizeArrayKey($key)] = $this->sanitizePayload($value);
            }

            return $sanitized;
        }

        if (is_object($payload)) {
            return $this->sanitizePayload((array) $payload);
        }

        if (is_string($payload)) {
            return $this->truncateString($payload, 5000);
        }

        if (is_int($payload) || is_float($payload) || is_bool($payload) || $payload === null) {
            return $payload;
        }

        return $this->truncateString((string) json_encode($payload), 5000);
    }

    private function sanitizeArrayKey(mixed $key): string
    {
        if (is_string($key) || is_int($key)) {
            $normalized = trim((string) $key);

            return $normalized !== '' ? $normalized : 'key';
        }

        return 'key';
    }

    private function extractUserId(?Authenticatable $user): ?int
    {
        $id = $user?->getAuthIdentifier();

        return is_numeric($id) ? (int) $id : null;
    }
}