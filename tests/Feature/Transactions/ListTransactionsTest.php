<?php

declare(strict_types=1);

namespace Tests\Feature\Transactions;

use App\Enums\TransactionStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Client;
use App\Models\Gateway;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\GatewaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class ListTransactionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GatewaySeeder::class);
    }

    public function test_it_lists_transactions_for_an_authenticated_user(): void
    {
        $user = $this->authorizedUser();

        $clientOne = Client::factory()->create([
            'name' => 'Cliente Um',
            'email' => 'cliente1@example.com',
        ]);

        $clientTwo = Client::factory()->create([
            'name' => 'Cliente Dois',
            'email' => 'cliente2@example.com',
        ]);

        $gateway = $this->gatewayByCode('gateway_1');

        $transactionOne = Transaction::factory()->create([
            'client_id' => $clientOne->id,
            'gateway_id' => $gateway->id,
            'status' => TransactionStatusEnum::PAID,
            'amount' => 1990,
            'external_id' => 'txn_list_001',
        ]);

        $transactionTwo = Transaction::factory()->create([
            'client_id' => $clientTwo->id,
            'gateway_id' => $gateway->id,
            'status' => TransactionStatusEnum::FAILED,
            'amount' => 2990,
            'external_id' => 'txn_list_002',
        ]);

        $response = $this->authenticate($user)
            ->getJson($this->transactionsIndexUrl());

        $response
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ]);

        $responseData = $response->json('data');

        $this->assertIsArray($responseData);
        $this->assertCount(2, $responseData);

        $transactionIds = array_column($responseData, 'id');

        $this->assertContains($transactionOne->id, $transactionIds);
        $this->assertContains($transactionTwo->id, $transactionIds);
    }

    public function test_it_requires_authentication_to_list_transactions(): void
    {
        $response = $this->getJson($this->transactionsIndexUrl());

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_it_filters_transactions_by_status(): void
    {
        $user = $this->authorizedUser();
        $client = Client::factory()->create();
        $gateway = $this->gatewayByCode('gateway_1');

        $paidTransaction = Transaction::factory()->create([
            'client_id' => $client->id,
            'gateway_id' => $gateway->id,
            'status' => TransactionStatusEnum::PAID,
            'amount' => 1990,
            'external_id' => 'txn_status_paid',
        ]);

        Transaction::factory()->create([
            'client_id' => $client->id,
            'gateway_id' => $gateway->id,
            'status' => TransactionStatusEnum::FAILED,
            'amount' => 2990,
            'external_id' => 'txn_status_failed',
        ]);

        $response = $this->authenticate($user)
            ->getJson($this->transactionsIndexUrl([
                'status' => 'paid',
            ]));

        $response->assertStatus(Response::HTTP_OK);

        $responseData = $response->json('data');

        $this->assertIsArray($responseData);
        $this->assertCount(1, $responseData);
        $this->assertSame($paidTransaction->id, $responseData[0]['id']);
        $this->assertSame('paid', $responseData[0]['status']);
    }

    public function test_it_filters_transactions_by_client_id(): void
    {
        $user = $this->authorizedUser();
        $gateway = $this->gatewayByCode('gateway_1');

        $targetClient = Client::factory()->create([
            'name' => 'Cliente Alvo',
            'email' => 'alvo@example.com',
        ]);

        $otherClient = Client::factory()->create([
            'name' => 'Cliente Outro',
            'email' => 'outro@example.com',
        ]);

        $targetTransaction = Transaction::factory()->create([
            'client_id' => $targetClient->id,
            'gateway_id' => $gateway->id,
            'status' => TransactionStatusEnum::PAID,
            'amount' => 1990,
            'external_id' => 'txn_client_target',
        ]);

        Transaction::factory()->create([
            'client_id' => $otherClient->id,
            'gateway_id' => $gateway->id,
            'status' => TransactionStatusEnum::PAID,
            'amount' => 2990,
            'external_id' => 'txn_client_other',
        ]);

        $response = $this->authenticate($user)
            ->getJson($this->transactionsIndexUrl([
                'client_id' => $targetClient->id,
            ]));

        $response->assertStatus(Response::HTTP_OK);

        $responseData = $response->json('data');

        $this->assertIsArray($responseData);
        $this->assertCount(1, $responseData);
        $this->assertSame($targetTransaction->id, $responseData[0]['id']);
        $this->assertSame($targetClient->id, $responseData[0]['client']['id']);
    }

    public function test_it_respects_per_page_parameter(): void
    {
        $user = $this->authorizedUser();
        $client = Client::factory()->create();
        $gateway = $this->gatewayByCode('gateway_1');

        Transaction::factory()->count(3)->create([
            'client_id' => $client->id,
            'gateway_id' => $gateway->id,
            'status' => TransactionStatusEnum::PAID,
            'amount' => 1990,
        ]);

        $response = $this->authenticate($user)
            ->getJson($this->transactionsIndexUrl([
                'per_page' => 2,
            ]));

        $response->assertStatus(Response::HTTP_OK);

        $responseData = $response->json('data');
        $meta = $response->json('meta');

        $this->assertIsArray($responseData);
        $this->assertIsArray($meta);
        $this->assertCount(2, $responseData);
        $this->assertSame(2, $meta['per_page']);
        $this->assertSame(3, $meta['total']);
    }

    public function test_it_returns_client_and_gateway_data_in_the_list_response(): void
    {
        $user = $this->authorizedUser();

        $client = Client::factory()->create([
            'name' => 'Cliente Estruturado',
            'email' => 'cliente.estruturado@example.com',
        ]);

        $gateway = $this->gatewayByCode('gateway_1');

        $transaction = Transaction::factory()->create([
            'client_id' => $client->id,
            'gateway_id' => $gateway->id,
            'status' => TransactionStatusEnum::PAID,
            'amount' => 4590,
            'external_id' => 'txn_structured_001',
        ]);

        $response = $this->authenticate($user)
            ->getJson($this->transactionsIndexUrl());

        $response->assertStatus(Response::HTTP_OK);

        $responseData = $response->json('data');

        $this->assertIsArray($responseData);

        $listedTransaction = $this->findTransactionInResponse($responseData, $transaction->id);

        $this->assertNotNull($listedTransaction);
        $this->assertArrayHasKey('client', $listedTransaction);
        $this->assertArrayHasKey('gateway', $listedTransaction);

        $this->assertSame($transaction->id, $listedTransaction['id']);
        $this->assertSame($client->id, $listedTransaction['client']['id']);
        $this->assertSame($client->name, $listedTransaction['client']['name']);
        $this->assertSame($client->email, $listedTransaction['client']['email']);
        $this->assertSame($gateway->id, $listedTransaction['gateway']['id']);
    }

    private function transactionsIndexUrl(array $query = []): string
    {
        $url = route('api.v1.transactions.index');

        if ($query === []) {
            return $url;
        }

        return $url . '?' . http_build_query($query);
    }

    private function authenticate(User $user): self
    {
        return $this->actingAs($user, 'sanctum');
    }

    private function authorizedUser(): User
    {
        return User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'is_active' => true,
        ]);
    }

    private function gatewayByCode(string $code): Gateway
    {
        /** @var Gateway $gateway */
        $gateway = Gateway::query()
            ->where('code', $code)
            ->firstOrFail();

        return $gateway;
    }

    /**
     * @param array<int, array<string, mixed>> $responseData
     * @return array<string, mixed>|null
     */
    private function findTransactionInResponse(array $responseData, int $transactionId): ?array
    {
        foreach ($responseData as $transaction) {
            if ((int) ($transaction['id'] ?? 0) === $transactionId) {
                return $transaction;
            }
        }

        return null;
    }
}