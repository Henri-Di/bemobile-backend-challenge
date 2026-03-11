<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DataTransferObjects\GatewayChargeResult;
use App\DataTransferObjects\GatewayRefundResult;
use App\DataTransferObjects\PaymentChargeData;
use App\Enums\GatewayCodeEnum;

interface GatewayPaymentInterface
{
    /**
     * Returns the unique internal gateway code.
     */
    public function code(): GatewayCodeEnum;

    /**
     * Returns the human-readable gateway name.
     */
    public function name(): string;

    /**
     * Indicates whether the gateway is available for processing.
     */
    public function isAvailable(): bool;

    /**
     * Creates a payment charge in the external gateway.
     */
    public function charge(PaymentChargeData $data): GatewayChargeResult;

    /**
     * Refunds a previously created transaction.
     *
     * When amount is null, the implementation should attempt a full refund.
     * Amount must be informed in minor units, such as cents.
     */
    public function refund(string $externalId, ?int $amount = null): GatewayRefundResult;

    /**
     * Indicates whether the gateway supports a given feature.
     *
     * Examples:
     * - charge
     * - refund
     * - refund_partial
     * - webhook
     * - async_status
     */
    public function supports(string $feature): bool;
}