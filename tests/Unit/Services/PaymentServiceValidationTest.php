<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\GatewayPaymentInterface;
use App\DataTransferObjects\GatewayChargeResult;
use App\DataTransferObjects\GatewayRefundResult;
use App\DataTransferObjects\PaymentChargeData;
use App\Enums\GatewayCodeEnum;
use App\Models\Product;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class PaymentServiceValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_validates_and_normalizes_a_valid_payload(): void
    {
        $service = $this->makeValidationService();

        $product = Product::factory()->create([
            'name' => 'Produto Válido',
            'amount' => 1990,
            'is_active' => true,
        ]);

        $normalized = $service->validatePayload([
            'client' => [
                'name' => '  Matheus Diamantino  ',
                'email' => '  MATHEUS@EXAMPLE.COM  ',
                'document' => '123.456.789-09',
            ],
            'products' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
            'card' => [
                'number' => '4111 1111 1111 4242',
                'cvv' => '123',
            ],
        ]);

        $this->assertSame('Matheus Diamantino', $normalized['client']['name']);
        $this->assertSame('matheus@example.com', $normalized['client']['email']);
        $this->assertSame('12345678909', $normalized['client']['document']);
        $this->assertSame($product->id, $normalized['products'][0]['product_id']);
        $this->assertSame(2, $normalized['products'][0]['quantity']);
        $this->assertSame('4111111111114242', $normalized['card']['number']);
        $this->assertSame('123', $normalized['card']['cvv']);
    }

    public function test_it_rejects_payload_when_client_name_is_missing(): void
    {
        $service = $this->makeValidationService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Client name is required.');

        $service->validatePayload([
            'client' => [
                'name' => '   ',
                'email' => 'cliente@example.com',
                'document' => '12345678909',
            ],
            'products' => [
                [
                    'product_id' => 1,
                    'quantity' => 1,
                ],
            ],
            'card' => [
                'number' => '4111111111114242',
                'cvv' => '123',
            ],
        ]);
    }

    public function test_it_rejects_payload_when_email_is_invalid(): void
    {
        $service = $this->makeValidationService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Valid client email is required.');

        $service->validatePayload([
            'client' => [
                'name' => 'Cliente Teste',
                'email' => 'email-invalido',
                'document' => '12345678909',
            ],
            'products' => [
                [
                    'product_id' => 1,
                    'quantity' => 1,
                ],
            ],
            'card' => [
                'number' => '4111111111114242',
                'cvv' => '123',
            ],
        ]);
    }

    public function test_it_rejects_payload_when_products_are_missing(): void
    {
        $service = $this->makeValidationService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one product is required.');

        $service->validatePayload([
            'client' => [
                'name' => 'Cliente Teste',
                'email' => 'cliente@example.com',
                'document' => '12345678909',
            ],
            'products' => [],
            'card' => [
                'number' => '4111111111114242',
                'cvv' => '123',
            ],
        ]);
    }

    public function test_it_rejects_payload_when_a_product_id_is_invalid(): void
    {
        $service = $this->makeValidationService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Product id is invalid.');

        $service->validatePayload([
            'client' => [
                'name' => 'Cliente Teste',
                'email' => 'cliente@example.com',
            ],
            'products' => [
                [
                    'product_id' => 0,
                    'quantity' => 1,
                ],
            ],
            'card' => [
                'number' => '4111111111114242',
                'cvv' => '123',
            ],
        ]);
    }

    public function test_it_rejects_payload_when_product_quantity_is_zero(): void
    {
        $service = $this->makeValidationService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Product quantity must be greater than zero.');

        $service->validatePayload([
            'client' => [
                'name' => 'Cliente Teste',
                'email' => 'cliente@example.com',
            ],
            'products' => [
                [
                    'product_id' => 1,
                    'quantity' => 0,
                ],
            ],
            'card' => [
                'number' => '4111111111114242',
                'cvv' => '123',
            ],
        ]);
    }

    public function test_it_rejects_payload_when_product_quantity_is_negative(): void
    {
        $service = $this->makeValidationService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Product quantity must be greater than zero.');

        $service->validatePayload([
            'client' => [
                'name' => 'Cliente Teste',
                'email' => 'cliente@example.com',
            ],
            'products' => [
                [
                    'product_id' => 1,
                    'quantity' => -2,
                ],
            ],
            'card' => [
                'number' => '4111111111114242',
                'cvv' => '123',
            ],
        ]);
    }

    public function test_it_rejects_payload_when_products_are_duplicated(): void
    {
        $service = $this->makeValidationService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate products are not allowed.');

        $service->validatePayload([
            'client' => [
                'name' => 'Cliente Teste',
                'email' => 'cliente@example.com',
            ],
            'products' => [
                [
                    'product_id' => 10,
                    'quantity' => 1,
                ],
                [
                    'product_id' => 10,
                    'quantity' => 3,
                ],
            ],
            'card' => [
                'number' => '4111111111114242',
                'cvv' => '123',
            ],
        ]);
    }

    public function test_it_rejects_payload_when_card_number_is_invalid(): void
    {
        $service = $this->makeValidationService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Card number is invalid.');

        $service->validatePayload([
            'client' => [
                'name' => 'Cliente Teste',
                'email' => 'cliente@example.com',
            ],
            'products' => [
                [
                    'product_id' => 1,
                    'quantity' => 1,
                ],
            ],
            'card' => [
                'number' => '1234',
                'cvv' => '123',
            ],
        ]);
    }

    public function test_it_rejects_payload_when_cvv_is_invalid(): void
    {
        $service = $this->makeValidationService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CVV is invalid.');

        $service->validatePayload([
            'client' => [
                'name' => 'Cliente Teste',
                'email' => 'cliente@example.com',
            ],
            'products' => [
                [
                    'product_id' => 1,
                    'quantity' => 1,
                ],
            ],
            'card' => [
                'number' => '4111111111114242',
                'cvv' => '12',
            ],
        ]);
    }

    public function test_it_rejects_purchase_when_product_does_not_exist(): void
    {
        $service = $this->makeValidationService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('One or more products were not found or are inactive.');

        $service->purchase([
            'client' => [
                'name' => 'Cliente Teste',
                'email' => 'cliente@example.com',
                'document' => '12345678909',
            ],
            'products' => [
                [
                    'product_id' => 999999,
                    'quantity' => 1,
                ],
            ],
            'card' => [
                'number' => '4111111111114242',
                'cvv' => '123',
            ],
        ]);
    }

    public function test_it_rejects_purchase_when_product_is_inactive(): void
    {
        $service = $this->makeValidationService();

        $inactiveProduct = Product::factory()->create([
            'name' => 'Produto Inativo',
            'amount' => 2990,
            'is_active' => false,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('One or more products were not found or are inactive.');

        $service->purchase([
            'client' => [
                'name' => 'Cliente Teste',
                'email' => 'cliente@example.com',
                'document' => '12345678909',
            ],
            'products' => [
                [
                    'product_id' => $inactiveProduct->id,
                    'quantity' => 1,
                ],
            ],
            'card' => [
                'number' => '4111111111114242',
                'cvv' => '123',
            ],
        ]);
    }

    private function makeValidationService(): ExposedPaymentValidationService
    {
        $gatewayOne = new FakeValidationGatewayService(GatewayCodeEnum::GATEWAY_ONE, 'Fake Gateway One');
        $gatewayTwo = new FakeValidationGatewayService(GatewayCodeEnum::GATEWAY_TWO, 'Fake Gateway Two');

        return new ExposedPaymentValidationService(
            db: $this->app->make('db'),
            gatewayOneService: $gatewayOne,
            gatewayTwoService: $gatewayTwo,
        );
    }
}

final class ExposedPaymentValidationService extends PaymentService
{
    /**
     * @param array<string, mixed> $payload
     * @return array{
     *     client: array{name: string, email: string, document: ?string},
     *     products: array<int, array{product_id:int, quantity:int}>,
     *     card: array{number: string, cvv: string}
     * }
     */
    public function validatePayload(array $payload): array
    {
        return $this->validateAndNormalizePayload($payload);
    }
}

final class FakeValidationGatewayService implements GatewayPaymentInterface
{
    public function __construct(
        private readonly GatewayCodeEnum $gatewayCode,
        private readonly string $gatewayName,
    ) {
    }

    public function code(): GatewayCodeEnum
    {
        return $this->gatewayCode;
    }

    public function name(): string
    {
        return $this->gatewayName;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function charge(PaymentChargeData $data): GatewayChargeResult
    {
        return GatewayChargeResult::success(
            externalId: 'fake_charge_id',
            status: 'approved',
            message: 'Approved',
            responseCode: '200',
            requestPayload: $data->toSafeArray(),
            responsePayload: [
                'status' => 'approved',
            ],
        );
    }

    public function refund(string $externalId, ?int $amount = null): GatewayRefundResult
    {
        return GatewayRefundResult::success(
            externalRefundId: 'fake_refund_id',
            status: 'processed',
            message: 'Processed',
            responsePayload: [
                'external_id' => $externalId,
                'amount' => $amount,
            ],
        );
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, ['charge', 'refund', 'refund_partial'], true);
    }
}