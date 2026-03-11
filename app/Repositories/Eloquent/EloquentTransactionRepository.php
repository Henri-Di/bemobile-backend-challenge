<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\TransactionRepositoryInterface;
use App\Enums\TransactionStatusEnum;
use App\Models\Transaction;
use App\Models\TransactionAttempt;
use Illuminate\Support\Collection;

final class EloquentTransactionRepository implements TransactionRepositoryInterface
{
    public function createPending(array $data): Transaction
    {
        $data['status'] = TransactionStatusEnum::PENDING->value;

        return Transaction::query()->create($data);
    }

    public function attachProducts(Transaction $transaction, Collection|array $items): void
    {
        foreach ($items as $item) {
            $transaction->products()->attach($item['product_id'], [
                'quantity' => $item['quantity'],
                'unit_amount' => $item['unit_amount'],
                'total_amount' => $item['total_amount'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function createAttempt(Transaction $transaction, array $data): TransactionAttempt
    {
        return $transaction->attempts()->create([
            'gateway_id' => $data['gateway_id'],
            'attempt_number' => $data['attempt_number'],
            'status' => $data['status'],
            'external_id' => $data['external_id'] ?? null,
            'request_payload_json' => $data['request_payload_json'] ?? null,
            'response_payload_json' => $data['response_payload_json'] ?? null,
            'error_message' => $data['error_message'] ?? null,
            'processed_at' => $data['processed_at'] ?? now(),
        ]);
    }

    public function markAsPaid(
        Transaction $transaction,
        int $gatewayId,
        string $externalId,
        ?string $responseCode = null,
        ?string $message = null
    ): Transaction {
        $transaction->update([
            'gateway_id' => $gatewayId,
            'external_id' => $externalId,
            'status' => TransactionStatusEnum::PAID->value,
            'gateway_response_code' => $responseCode,
            'gateway_message' => $message,
            'paid_at' => now(),
        ]);

        return $transaction->refresh();
    }

    public function markAsFailed(
        Transaction $transaction,
        ?string $message = null,
        ?string $responseCode = null
    ): Transaction {
        $transaction->update([
            'status' => TransactionStatusEnum::FAILED->value,
            'gateway_message' => $message,
            'gateway_response_code' => $responseCode,
        ]);

        return $transaction->refresh();
    }

    public function markAsRefunded(Transaction $transaction): Transaction
    {
        $transaction->update([
            'status' => TransactionStatusEnum::REFUNDED->value,
            'refunded_at' => now(),
        ]);

        return $transaction->refresh();
    }

    public function findById(int $id): ?Transaction
    {
        return Transaction::query()
            ->with(['client', 'gateway', 'products', 'attempts', 'refunds'])
            ->find($id);
    }
}