<?php

declare(strict_types=1);

namespace App\Http\Resources;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class TransactionResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'external_id' => $this->external_id,
            'status' => $this->normalizeEnum($this->status),
            'amount' => (int) $this->amount,
            'card_last_numbers' => $this->card_last_numbers,
            'gateway_response_code' => $this->gateway_response_code,
            'gateway_message' => $this->gateway_message,

            'paid_at' => $this->formatLocalDateTime($this->paid_at),
            'refunded_at' => $this->formatLocalDateTime($this->refunded_at),

            'client' => $this->relationLoaded('client') && $this->client
                ? [
                    'id' => (int) $this->client->id,
                    'name' => (string) $this->client->name,
                    'email' => (string) $this->client->email,
                    'document' => $this->client->document,
                ]
                : null,

            'gateway' => $this->relationLoaded('gateway') && $this->gateway
                ? [
                    'id' => (int) $this->gateway->id,
                    'code' => (string) $this->gateway->code,
                    'name' => (string) $this->gateway->name,
                ]
                : null,

            'items' => $this->transformItems(),
            'attempts' => $this->transformAttempts(),
            'refunds' => $this->transformRefunds(),

            'created_at' => $this->formatUtcToSaoPaulo($this->created_at),
            'updated_at' => $this->formatUtcToSaoPaulo($this->updated_at),
        ];
    }

    private function normalizeEnum(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function transformItems(): array
    {
        if (! $this->relationLoaded('items')) {
            return [];
        }

        return $this->items->map(function ($item) {
            return [
                'id' => (int) $item->id,
                'quantity' => (int) $item->quantity,
                'unit_amount' => (int) $item->unit_amount,
                'total_amount' => (int) $item->total_amount,
                'product' => $item->relationLoaded('product') && $item->product
                    ? [
                        'id' => (int) $item->product->id,
                        'name' => (string) $item->product->name,
                    ]
                    : null,
            ];
        })->values()->all();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function transformAttempts(): array
    {
        if (! $this->relationLoaded('attempts')) {
            return [];
        }

        return $this->attempts->map(function ($attempt) {
            return [
                'id' => (int) $attempt->id,
                'attempt_number' => (int) $attempt->attempt_number,
                'status' => $this->normalizeEnum($attempt->status),
                'external_id' => $attempt->external_id,
                'error_message' => $attempt->error_message,
                'processed_at' => $this->formatLocalDateTime($attempt->processed_at),
                'gateway' => $attempt->relationLoaded('gateway') && $attempt->gateway
                    ? [
                        'id' => (int) $attempt->gateway->id,
                        'code' => (string) $attempt->gateway->code,
                        'name' => (string) $attempt->gateway->name,
                    ]
                    : null,
            ];
        })->values()->all();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function transformRefunds(): array
    {
        if (! $this->relationLoaded('refunds')) {
            return [];
        }

        return $this->refunds->map(function ($refund) {
            return [
                'id' => (int) $refund->id,
                'amount' => (int) $refund->amount,
                'status' => $this->normalizeEnum($refund->status),
                'external_refund_id' => $refund->external_refund_id,
                'processed_at' => $this->formatLocalDateTime($refund->processed_at),
                'gateway' => $refund->relationLoaded('gateway') && $refund->gateway
                    ? [
                        'id' => (int) $refund->gateway->id,
                        'code' => (string) $refund->gateway->code,
                        'name' => (string) $refund->gateway->name,
                    ]
                    : null,
            ];
        })->values()->all();
    }

    private function formatLocalDateTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatUtcToSaoPaulo(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value, 'UTC')
                ->setTimezone('America/Sao_Paulo')
                ->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}