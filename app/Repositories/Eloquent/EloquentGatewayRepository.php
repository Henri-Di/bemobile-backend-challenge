<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\GatewayRepositoryInterface;
use App\Enums\GatewayCodeEnum;
use App\Models\Gateway;
use Illuminate\Support\Collection;

final class EloquentGatewayRepository implements GatewayRepositoryInterface
{
    public function getActiveOrderedByPriority(): Collection
    {
        return Gateway::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
    }

    public function findById(int $id): ?Gateway
    {
        return Gateway::query()->find($id);
    }

    public function findByCode(GatewayCodeEnum $code): ?Gateway
    {
        return Gateway::query()
            ->where('code', $code->value)
            ->first();
    }

    public function updatePriority(Gateway $gateway, int $priority): Gateway
    {
        $gateway->update([
            'priority' => $priority,
        ]);

        return $gateway->refresh();
    }

    public function setActive(Gateway $gateway, bool $isActive): Gateway
    {
        $gateway->update([
            'is_active' => $isActive,
        ]);

        return $gateway->refresh();
    }
}