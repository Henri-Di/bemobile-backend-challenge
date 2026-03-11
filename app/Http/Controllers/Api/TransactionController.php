<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\TransactionIndexRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\PaymentService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class TransactionController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;
    private const MIN_PER_PAGE = 1;
    private const MAX_PER_PAGE = 100;

    private const ERROR_MESSAGE_GENERIC = 'Transaction processing failed.';
    private const LOG_MESSAGE_FAILURE = 'Transaction processing failed';

    /**
     * List transactions using optional filters.
     */
    public function index(TransactionIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $perPage = $this->normalizePerPage($filters['per_page'] ?? self::DEFAULT_PER_PAGE);
        $status = $this->normalizeStatus($filters['status'] ?? null);
        $clientId = $this->normalizePositiveInt($filters['client_id'] ?? null);
        $gatewayId = $this->normalizePositiveInt($filters['gateway_id'] ?? null);

        $transactions = Transaction::query()
            ->with($this->defaultIndexRelations())
            ->when(
                $status !== null,
                fn (Builder $query): Builder => $query->where('status', $status)
            )
            ->when(
                $clientId !== null,
                fn (Builder $query): Builder => $query->where('client_id', $clientId)
            )
            ->when(
                $gatewayId !== null,
                fn (Builder $query): Builder => $query->where('gateway_id', $gatewayId)
            )
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());

        return TransactionResource::collection($transactions);
    }

    /**
     * Display a single transaction with its required relationships.
     */
    public function show(Transaction $transaction): TransactionResource
    {
        $this->loadShowRelations($transaction);

        return new TransactionResource($transaction);
    }

    /**
     * Process a purchase transaction.
     */
    public function store(
        StoreTransactionRequest $request,
        PaymentService $paymentService
    ): JsonResponse {
        $user = $request->user();
        $validated = $request->validated();
        $requestContext = $this->buildRequestContext($validated);
        $paymentPayload = $this->mapStorePayloadToPaymentPayload($validated);

        try {
            $transaction = $paymentService->purchase($paymentPayload, $user);

            $this->loadShowRelations($transaction);

            return $this->jsonResourceResponse(
                resource: new TransactionResource($transaction),
                status: Response::HTTP_CREATED
            );
        } catch (Throwable $exception) {
            $this->logTransactionFailure($exception, [
                'user_id' => $this->extractUserId($user),
                'request_context' => $requestContext,
            ]);

            report($exception);

            return $this->errorResponse(
                message: $this->resolvePublicErrorMessage($exception),
                status: $this->resolveHttpStatusCode($exception)
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function defaultIndexRelations(): array
    {
        return [
            'client',
            'gateway',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function defaultShowRelations(): array
    {
        return [
            'client',
            'gateway',
            'items.product',
            'attempts.gateway',
            'refunds.gateway',
        ];
    }

    private function loadShowRelations(Transaction $transaction): void
    {
        $transaction->loadMissing($this->defaultShowRelations());
    }

    /**
     * @param array<string, mixed> $validated
     * @return array{
     *     client: array{name: ?string, email: ?string, document: ?string},
     *     products: array<int, array{product_id:int, quantity:int}>,
     *     card: array{number: ?string, cvv: ?string}
     * }
     */
    private function mapStorePayloadToPaymentPayload(array $validated): array
    {
        $customer = is_array($validated['customer'] ?? null) ? $validated['customer'] : [];
        $card = is_array($validated['card'] ?? null) ? $validated['card'] : [];
        $items = is_array($validated['items'] ?? null) ? $validated['items'] : [];

        $products = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $productId = $this->normalizePositiveInt($item['product_id'] ?? null);
            $quantity = $this->normalizePositiveInt($item['quantity'] ?? null);

            if ($productId === null || $quantity === null) {
                continue;
            }

            $products[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
            ];
        }

        return [
            'client' => [
                'name' => $this->toNullableString($customer['name'] ?? null),
                'email' => $this->sanitizeEmail($customer['email'] ?? null),
                'document' => $this->digitsOnlyNullable($customer['document'] ?? null),
            ],
            'products' => $products,
            'card' => [
                'number' => $this->digitsOnlyNullable($card['number'] ?? null),
                'cvv' => $this->digitsOnlyNullable($card['cvv'] ?? null),
            ],
        ];
    }

    private function jsonResourceResponse(TransactionResource $resource, int $status): JsonResponse
    {
        $response = $resource->response();
        $response->setStatusCode($status);

        $this->applyDefaultSecurityHeaders($response);

        return $response;
    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        $response = response()->json([
            'message' => $message,
        ], $status);

        $this->applyDefaultSecurityHeaders($response);

        return $response;
    }

    private function applyDefaultSecurityHeaders(JsonResponse $response): void
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function buildRequestContext(array $payload): array
    {
        $customer = is_array($payload['customer'] ?? null) ? $payload['customer'] : [];
        $card = is_array($payload['card'] ?? null) ? $payload['card'] : [];
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];

        return [
            'customer' => [
                'name' => $this->truncateString(
                    $this->toNullableString($customer['name'] ?? null),
                    120
                ),
                'email' => $this->truncateString(
                    $this->sanitizeEmail($customer['email'] ?? null),
                    180
                ),
                'document' => $this->maskDocument(
                    $this->toNullableString($customer['document'] ?? null)
                ),
            ],
            'card' => [
                'last4' => $this->extractLastFourDigits($card['number'] ?? null),
                'holder_name' => $this->truncateString(
                    $this->toNullableString($card['holder_name'] ?? null),
                    120
                ),
                'brand' => $this->truncateString(
                    $this->normalizeCardBrand($card['brand'] ?? null),
                    40
                ),
            ],
            'items_summary' => [
                'count' => count($items),
                'product_ids' => $this->extractProductIds($items),
                'total_quantity' => $this->sumItemQuantities($items),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logTransactionFailure(Throwable $exception, array $context = []): void
    {
        Log::error(self::LOG_MESSAGE_FAILURE, array_merge($context, [
            'exception_class' => $exception::class,
            'error_message' => $this->truncateString(
                $this->toNullableString($exception->getMessage()),
                1000
            ),
            'file' => $this->truncateString($exception->getFile(), 500),
            'line' => $exception->getLine(),
        ]));
    }

    private function resolvePublicErrorMessage(Throwable $exception): string
    {
        if ((bool) config('app.debug')) {
            return $this->truncateString(
                $this->toNullableString($exception->getMessage()),
                300
            ) ?? self::ERROR_MESSAGE_GENERIC;
        }

        return self::ERROR_MESSAGE_GENERIC;
    }

    private function resolveHttpStatusCode(Throwable $exception): int
    {
        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();

            return $this->isValidHttpErrorStatus($status)
                ? $status
                : Response::HTTP_INTERNAL_SERVER_ERROR;
        }

        $status = (int) $exception->getCode();

        return $this->isValidHttpErrorStatus($status)
            ? $status
            : Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    private function isValidHttpErrorStatus(int $status): bool
    {
        return $status >= 400 && $status <= 599;
    }

    private function normalizePerPage(mixed $value): int
    {
        $perPage = $this->normalizePositiveInt($value) ?? self::DEFAULT_PER_PAGE;

        return max(self::MIN_PER_PAGE, min($perPage, self::MAX_PER_PAGE));
    }

    private function normalizeStatus(mixed $value): ?string
    {
        $status = $this->toNullableString($value);

        if ($status === null) {
            return null;
        }

        return mb_strtolower($status);
    }

    private function normalizeCardBrand(mixed $value): ?string
    {
        $brand = $this->toNullableString($value);

        if ($brand === null) {
            return null;
        }

        return mb_strtolower($brand);
    }

    /**
     * @param array<int, mixed> $items
     * @return array<int, int>
     */
    private function extractProductIds(array $items): array
    {
        $ids = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $productId = $this->normalizePositiveInt($item['product_id'] ?? null);

            if ($productId !== null) {
                $ids[] = $productId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<int, mixed> $items
     */
    private function sumItemQuantities(array $items): int
    {
        $total = 0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $quantity = $this->normalizePositiveInt($item['quantity'] ?? null);

            if ($quantity !== null) {
                $total += $quantity;
            }
        }

        return $total;
    }

    private function extractLastFourDigits(mixed $value): ?string
    {
        $number = $this->toNullableString($value);

        if ($number === null) {
            return null;
        }

        $digits = preg_replace('/\D+/u', '', $number);

        if (! is_string($digits) || $digits === '') {
            return null;
        }

        return substr($digits, -4);
    }

    private function maskDocument(?string $document): ?string
    {
        if ($document === null) {
            return null;
        }

        $digits = preg_replace('/\D+/u', '', $document);

        if (! is_string($digits) || $digits === '') {
            return null;
        }

        $length = strlen($digits);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4) . substr($digits, -4);
    }

    private function normalizePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '' || preg_match('/^\d+$/', $value) !== 1) {
                return null;
            }

            $intValue = (int) $value;

            return $intValue > 0 ? $intValue : null;
        }

        if (is_float($value)) {
            $intValue = (int) $value;

            return $intValue > 0 ? $intValue : null;
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

            return $value !== '' ? $value : null;
        }

        if (is_scalar($value)) {
            $string = trim((string) $value);

            return $string !== '' ? $string : null;
        }

        return null;
    }

    private function sanitizeEmail(mixed $value): ?string
    {
        $email = $this->toNullableString($value);

        if ($email === null) {
            return null;
        }

        $sanitized = filter_var($email, FILTER_SANITIZE_EMAIL);

        if (! is_string($sanitized) || $sanitized === '') {
            return null;
        }

        return filter_var($sanitized, FILTER_VALIDATE_EMAIL)
            ? mb_strtolower($sanitized)
            : null;
    }

    private function digitsOnlyNullable(mixed $value): ?string
    {
        $string = $this->toNullableString($value);

        if ($string === null) {
            return null;
        }

        $digits = preg_replace('/\D+/u', '', $string);

        return is_string($digits) && $digits !== ''
            ? $digits
            : null;
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

    private function extractUserId(?Authenticatable $user): ?int
    {
        $id = $user?->getAuthIdentifier();

        return is_numeric($id) ? (int) $id : null;
    }
}