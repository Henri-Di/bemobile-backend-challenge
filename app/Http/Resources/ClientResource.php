<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Client
 */
final class ClientResource extends JsonResource
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
            'name' => (string) $this->name,
            'email' => $this->email !== null ? (string) $this->email : null,
            'document' => $this->document !== null ? (string) $this->document : null,
            'document_masked' => method_exists($this->resource, 'maskedDocument')
                ? $this->resource->maskedDocument()
                : null,
            'transactions_count' => isset($this->transactions_count)
                ? (int) $this->transactions_count
                : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}