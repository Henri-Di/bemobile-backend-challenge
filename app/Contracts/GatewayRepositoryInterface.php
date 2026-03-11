<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\GatewayCodeEnum;
use App\Models\Gateway;
use Illuminate\Support\Collection;

interface GatewayRepositoryInterface
{
    /**
     * Returns all active gateways ordered by priority.
     *
     * @return Collection<int, Gateway>
     */
    public function getActiveOrderedByPriority(): Collection;

    /**
     * Finds a gateway by its id.
     */
    public function findById(int $id): ?Gateway;

    /**
     * Finds a gateway by its enum code.
     */
    public function findByCode(GatewayCodeEnum $code): ?Gateway;

    /**
     * Updates gateway priority.
     */
    public function updatePriority(Gateway $gateway, int $priority): Gateway;

    /**
     * Activates or deactivates a gateway.
     */
    public function setActive(Gateway $gateway, bool $isActive): Gateway;
}