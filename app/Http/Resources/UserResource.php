<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = is_object($this->role) && property_exists($this->role, 'value')
            ? (string) $this->role->value
            : (string) $this->role;

        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'email' => (string) $this->email,
            'role' => $role,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->timezone('America/Sao_Paulo')?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->timezone('America/Sao_Paulo')?->format('Y-m-d H:i:s'),
        ];
    }
}