<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\GatewayPaymentInterface;
use App\DataTransferObjects\PaymentChargeData;
use App\Enums\TransactionAttemptStatusEnum;
use App\Enums\TransactionStatusEnum;
use App\Exceptions\GatewayIntegrationException;
use App\Models\Client;
use App\Models\Gateway;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionAttempt;
use App\Models\TransactionProduct;
use App\Models\User;
use App\Services\Gateways\GatewayOneService;
use App\Services\Gateways\GatewayTwoService;
use Carbon\CarbonImmutable;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Throwable;

class PaymentService
{
    public function __construct(
        protected readonly DatabaseManager $db,
        protected readonly GatewayOneService $gatewayOneService,
        protected readonly GatewayTwoService $gatewayTwoService,
    ) {
    }

    /**
     * Creates and processes a purchase transaction.
     *
     * @param array<string, mixed> $payload
     */
    public function purchase(array $payload, ?User $createdByUser = null): Transaction
    {
        $normalized = $this->validateAndNormalizePayload($payload);

        return $this->db->transaction(function () use ($normalized, $createdByUser): Transaction {
            $client = $this->findOrCreateClient($normalized['client']);

            /** @var Collection<int, Product> $products */
            $products = Product::query()
                ->active()
                ->whereIn('id', array_column($normalized['products'], 'product_id'))
                ->get()
                ->keyBy('id');

            $this->assertAllProductsFound($normalized['products'], $products);

            $totalAmount = $this->calculateTotalAmount($normalized['products'], $products);

            $paymentData = $this->buildPaymentData(
                amount: $totalAmount,
                clientName: $normalized['client']['name'],
                clientEmail: $normalized['client']['email'],
                cardNumber: $normalized['card']['number'],
                cvv: $normalized['card']['cvv'],
            );

            $transaction = $this->createTransaction(
                client: $client,
                paymentData: $paymentData,
                totalAmount: $totalAmount,
                createdByUser: $createdByUser,
            );

            $this->createTransactionItems(
                transaction: $transaction,
                requestedProducts: $normalized['products'],
                products: $products,
            );

            $transaction->forceFill([
                'status' => TransactionStatusEnum::PROCESSING,
            ])->save();

            /** @var Collection<int, Gateway> $gateways */
            $gateways = Gateway::query()
                ->active()
                ->orderedByPriority()
                ->get();

            if ($gateways->isEmpty()) {
                $this->markTransactionAsFailed(
                    $transaction,
                    'No active gateway available.'
                );

                return $this->freshTransaction($transaction);
            }

            foreach ($gateways as $index => $gateway) {
                $attemptNumber = $index + 1;
                $gatewayService = $this->resolveGatewayService((string) $gateway->code);

                if ($this->gatewayIsUnavailable($gatewayService)) {
                    $this->registerUnavailableGatewayAttempt($transaction, $gateway, $attemptNumber);
                    continue;
                }

                $result = $this->attemptCharge(
                    gateway: $gateway,
                    gatewayService: $gatewayService,
                    paymentData: $paymentData,
                    transaction: $transaction,
                    attemptNumber: $attemptNumber,
                );

                if ($result['success'] === true) {
                    $this->markTransactionAsPaid(
                        transaction: $transaction,
                        gateway: $gateway,
                        externalId: $result['external_id'],
                        message: $result['message'],
                        responseCode: $result['response_code'],
                    );

                    return $this->freshTransaction($transaction);
                }
            }

            $this->markTransactionAsFailed(
                $transaction,
                'All gateways failed to process the transaction.'
            );

            return $this->freshTransaction($transaction);
        });
    }

    /**
     * Validates and normalizes raw purchase payload data.
     *
     * @param array<string, mixed> $payload
     * @return array{
     *     client: array{name: string, email: string, document: ?string},
     *     products: array<int, array{product_id:int, quantity:int}>,
     *     card: array{number: string, cvv: string}
     * }
     */
    protected function validateAndNormalizePayload(array $payload): array
    {
        $clientName = trim((string) data_get($payload, 'client.name', data_get($payload, 'name', '')));
        $clientEmail = mb_strtolower(trim((string) data_get($payload, 'client.email', data_get($payload, 'email', ''))));
        $clientDocument = $this->normalizeNullableDigits(data_get($payload, 'client.document'));

        $products = data_get($payload, 'products', []);
        $cardNumber = $this->digitsOnly((string) data_get($payload, 'card.number', data_get($payload, 'cardNumber', '')));
        $cvv = $this->digitsOnly((string) data_get($payload, 'card.cvv', data_get($payload, 'cvv', '')));

        if ($clientName === '') {
            throw new InvalidArgumentException('Client name is required.');
        }

        if ($clientEmail === '' || ! filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Valid client email is required.');
        }

        if (! is_array($products) || $products === []) {
            throw new InvalidArgumentException('At least one product is required.');
        }

        if (strlen($cardNumber) < 12 || strlen($cardNumber) > 19) {
            throw new InvalidArgumentException('Card number is invalid.');
        }

        if (strlen($cvv) < 3 || strlen($cvv) > 4) {
            throw new InvalidArgumentException('CVV is invalid.');
        }

        $normalizedProducts = [];
        $seenProductIds = [];

        foreach ($products as $item) {
            $productId = (int) data_get($item, 'product_id');
            $quantity = (int) data_get($item, 'quantity');

            if ($productId < 1) {
                throw new InvalidArgumentException('Product id is invalid.');
            }

            if ($quantity < 1) {
                throw new InvalidArgumentException('Product quantity must be greater than zero.');
            }

            if (in_array($productId, $seenProductIds, true)) {
                throw new InvalidArgumentException('Duplicate products are not allowed.');
            }

            $seenProductIds[] = $productId;

            $normalizedProducts[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
            ];
        }

        return [
            'client' => [
                'name' => $clientName,
                'email' => $clientEmail,
                'document' => $clientDocument,
            ],
            'products' => $normalizedProducts,
            'card' => [
                'number' => $cardNumber,
                'cvv' => $cvv,
            ],
        ];
    }

    /**
     * Finds an existing client by email or creates a new one.
     *
     * @param array{name: string, email: string, document: ?string} $clientData
     */
    protected function findOrCreateClient(array $clientData): Client
    {
        /** @var Client $client */
        $client = Client::query()->firstOrCreate(
            ['email' => $clientData['email']],
            [
                'name' => $clientData['name'],
                'document' => $clientData['document'],
            ]
        );

        $dirty = false;

        if ($client->name !== $clientData['name']) {
            $client->name = $clientData['name'];
            $dirty = true;
        }

        if ($client->document !== $clientData['document']) {
            $client->document = $clientData['document'];
            $dirty = true;
        }

        if ($dirty) {
            $client->save();
        }

        return $client;
    }

    /**
     * Ensures all requested products exist and are active.
     *
     * @param array<int, array{product_id:int, quantity:int}> $requestedProducts
     * @param Collection<int, Product> $products
     */
    protected function assertAllProductsFound(array $requestedProducts, Collection $products): void
    {
        $requestedIds = collect($requestedProducts)->pluck('product_id')->unique()->sort()->values()->all();
        $foundIds = $products->keys()->sort()->values()->all();

        if ($requestedIds !== $foundIds) {
            throw new InvalidArgumentException('One or more products were not found or are inactive.');
        }
    }

    /**
     * Calculates the total amount for the transaction.
     *
     * @param array<int, array{product_id:int, quantity:int}> $requestedProducts
     * @param Collection<int, Product> $products
     */
    protected function calculateTotalAmount(array $requestedProducts, Collection $products): int
    {
        $total = 0;

        foreach ($requestedProducts as $item) {
            /** @var Product $product */
            $product = $products->get($item['product_id']);
            $total += ((int) $product->amount) * $item['quantity'];
        }

        return $total;
    }

    /**
     * Creates the transaction record.
     */
    protected function createTransaction(
        Client $client,
        PaymentChargeData $paymentData,
        int $totalAmount,
        ?User $createdByUser = null,
    ): Transaction {
        /** @var Transaction $transaction */
        $transaction = Transaction::query()->create([
            'client_id' => $client->id,
            'status' => TransactionStatusEnum::PENDING,
            'amount' => $totalAmount,
            'card_last_numbers' => $paymentData->cardLastNumbers(),
            'created_by_user_id' => $createdByUser?->id,
        ]);

        return $transaction;
    }

    /**
     * Creates transaction item records.
     *
     * @param array<int, array{product_id:int, quantity:int}> $requestedProducts
     * @param Collection<int, Product> $products
     */
    protected function createTransactionItems(
        Transaction $transaction,
        array $requestedProducts,
        Collection $products,
    ): void {
        foreach ($requestedProducts as $item) {
            /** @var Product $product */
            $product = $products->get($item['product_id']);

            TransactionProduct::query()->create([
                'transaction_id' => $transaction->id,
                'product_id' => $product->id,
                'quantity' => $item['quantity'],
                'unit_amount' => (int) $product->amount,
                'total_amount' => ((int) $product->amount) * $item['quantity'],
            ]);
        }
    }

    /**
     * Attempts to charge the transaction using the given gateway.
     *
     * @return array{
     *     success: bool,
     *     external_id: ?string,
     *     message: ?string,
     *     response_code: ?string
     * }
     */
    protected function attemptCharge(
        Gateway $gateway,
        GatewayPaymentInterface $gatewayService,
        PaymentChargeData $paymentData,
        Transaction $transaction,
        int $attemptNumber,
    ): array {
        $safeRequestPayload = $this->maskPaymentPayload($paymentData->toInternalArray());

        try {
            $result = $gatewayService->charge($paymentData);

            TransactionAttempt::query()->create([
                'transaction_id' => $transaction->id,
                'gateway_id' => $gateway->id,
                'attempt_number' => $attemptNumber,
                'status' => $result->success
                    ? TransactionAttemptStatusEnum::SUCCESS
                    : TransactionAttemptStatusEnum::FAILED,
                'external_id' => $result->externalId,
                'request_payload_json' => $result->requestPayload ?: $safeRequestPayload,
                'response_payload_json' => $result->responsePayload,
                'error_message' => $result->success ? null : $result->message,
                'processed_at' => $this->now(),
            ]);

            return [
                'success' => $result->success,
                'external_id' => $result->externalId,
                'message' => $result->message,
                'response_code' => $result->responseCode,
            ];
        } catch (GatewayIntegrationException $exception) {
            TransactionAttempt::query()->create([
                'transaction_id' => $transaction->id,
                'gateway_id' => $gateway->id,
                'attempt_number' => $attemptNumber,
                'status' => TransactionAttemptStatusEnum::FAILED,
                'request_payload_json' => $safeRequestPayload,
                'response_payload_json' => $exception->context(),
                'error_message' => $exception->getMessage(),
                'processed_at' => $this->now(),
            ]);

            return [
                'success' => false,
                'external_id' => null,
                'message' => $exception->getMessage(),
                'response_code' => null,
            ];
        } catch (Throwable $exception) {
            TransactionAttempt::query()->create([
                'transaction_id' => $transaction->id,
                'gateway_id' => $gateway->id,
                'attempt_number' => $attemptNumber,
                'status' => TransactionAttemptStatusEnum::FAILED,
                'request_payload_json' => $safeRequestPayload,
                'response_payload_json' => [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ],
                'error_message' => 'Unexpected gateway processing error.',
                'processed_at' => $this->now(),
            ]);

            return [
                'success' => false,
                'external_id' => null,
                'message' => 'Unexpected gateway processing error.',
                'response_code' => null,
            ];
        }
    }

    /**
     * Registers a failed attempt for an unavailable gateway.
     */
    protected function registerUnavailableGatewayAttempt(
        Transaction $transaction,
        Gateway $gateway,
        int $attemptNumber,
    ): void {
        TransactionAttempt::query()->create([
            'transaction_id' => $transaction->id,
            'gateway_id' => $gateway->id,
            'attempt_number' => $attemptNumber,
            'status' => TransactionAttemptStatusEnum::FAILED,
            'request_payload_json' => [],
            'response_payload_json' => [],
            'error_message' => 'Gateway is unavailable.',
            'processed_at' => $this->now(),
        ]);
    }

    /**
     * Marks the transaction as paid.
     */
    protected function markTransactionAsPaid(
        Transaction $transaction,
        Gateway $gateway,
        ?string $externalId,
        ?string $message,
        ?string $responseCode,
    ): void {
        $transaction->forceFill([
            'gateway_id' => $gateway->id,
            'external_id' => $externalId,
            'status' => TransactionStatusEnum::PAID,
            'gateway_response_code' => $responseCode,
            'gateway_message' => $message,
            'paid_at' => $this->now(),
        ])->save();
    }

    /**
     * Marks the transaction as failed.
     */
    protected function markTransactionAsFailed(Transaction $transaction, string $message): void
    {
        $transaction->forceFill([
            'status' => TransactionStatusEnum::FAILED,
            'gateway_message' => $message,
            'gateway_id' => null,
            'external_id' => null,
        ])->save();
    }

    /**
     * Returns a fully loaded transaction.
     */
    protected function freshTransaction(Transaction $transaction): Transaction
    {
        /** @var Transaction $fresh */
        $fresh = $transaction->fresh([
            'client',
            'items.product',
            'attempts.gateway',
            'gateway',
        ]);

        return $fresh;
    }

    /**
     * Resolves the gateway service implementation by gateway code.
     */
    protected function resolveGatewayService(string $gatewayCode): GatewayPaymentInterface
    {
        return match ($gatewayCode) {
            'gateway_1' => $this->gatewayOneService,
            'gateway_2' => $this->gatewayTwoService,
            default => throw new InvalidArgumentException("Unsupported gateway code [{$gatewayCode}]."),
        };
    }

    /**
     * Creates the payment DTO.
     */
    protected function buildPaymentData(
        int $amount,
        string $clientName,
        string $clientEmail,
        string $cardNumber,
        string $cvv,
    ): PaymentChargeData {
        return new PaymentChargeData(
            amount: $amount,
            name: $clientName,
            email: $clientEmail,
            cardNumber: $cardNumber,
            cvv: $cvv,
        );
    }

    /**
     * Returns the current São Paulo time.
     */
    protected function now(): CarbonImmutable
    {
        return CarbonImmutable::now('America/Sao_Paulo');
    }

    /**
     * Returns digits only from a string.
     */
    protected function digitsOnly(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }

    /**
     * Normalizes a nullable document value to digits only.
     */
    protected function normalizeNullableDigits(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = $this->digitsOnly((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Masks sensitive card payload fields before persistence.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function maskPaymentPayload(array $payload): array
    {
        $masked = $payload;

        if (array_key_exists('card_number', $masked)) {
            $masked['card_number'] = '***';
        }

        if (array_key_exists('cvv', $masked)) {
            $masked['cvv'] = '***';
        }

        return $masked;
    }

    protected function gatewayIsUnavailable(GatewayPaymentInterface $gatewayService): bool
    {
        return method_exists($gatewayService, 'isAvailable') && ! $gatewayService->isAvailable();
    }
}