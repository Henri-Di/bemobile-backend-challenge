<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Refund
 */
final class RefundResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'transaction_id' => (int) $this->transaction_id,
            'gateway_id' => (int) $this->gateway_id,
            'external_refund_id' => $this->external_refund_id,
            'status' => $this->status,
            'amount' => (int) $this->amount,
            'requested_by_user_id' => $this->requested_by_user_id !== null
                ? (int) $this->requested_by_user_id
                : null,
            'processed_at' => $this->processed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'transaction' => $this->whenLoaded('transaction', function (): array {
                return [
                    'id' => (int) $this->transaction->id,
                    'status' => is_object($this->transaction->status) && isset($this->transaction->status->value)
                        ? (string) $this->transaction->status->value
                        : (string) $this->transaction->status,
                    'amount' => (int) $this->transaction->amount,
                ];
            }),
            'gateway' => $this->whenLoaded('gateway', function (): array {
                return [
                    'id' => (int) $this->gateway->id,
                    'code' => (string) $this->gateway->code,
                    'name' => (string) $this->gateway->name,
                ];
            }),
        ];
    }
}