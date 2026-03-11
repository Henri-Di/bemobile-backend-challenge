<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Transaction;
use App\Models\TransactionAttempt;
use Illuminate\Support\Collection;

interface TransactionRepositoryInterface
{
    public function createPending(array $data): Transaction;

    /**
     * @param Collection<int, array<string, mixed>>|array<int, array<string, mixed>> $items
     */
    public function attachProducts(Transaction $transaction, Collection|array $items): void;

    public function createAttempt(Transaction $transaction, array $data): TransactionAttempt;

    public function markAsPaid(
        Transaction $transaction,
        int $gatewayId,
        string $externalId,
        ?string $responseCode = null,
        ?string $message = null
    ): Transaction;

    public function markAsFailed(
        Transaction $transaction,
        ?string $message = null,
        ?string $responseCode = null
    ): Transaction;

    public function markAsRefunded(Transaction $transaction): Transaction;

    public function findById(int $id): ?Transaction;
}