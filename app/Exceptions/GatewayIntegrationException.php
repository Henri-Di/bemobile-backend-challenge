<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class GatewayIntegrationException extends RuntimeException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message,
        protected readonly string $gatewayCode,
        protected readonly array $context = [],
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Returns the gateway identifier that triggered the exception.
     */
    public function gatewayCode(): string
    {
        return $this->gatewayCode;
    }

    /**
     * Returns additional contextual data related to the failure.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * Creates a standardized exception for gateway communication errors.
     *
     * @param array<string, mixed> $context
     */
    public static function communicationError(
        string $gatewayCode,
        array $context = [],
        ?Throwable $previous = null
    ): self {
        return new self(
            message: 'Failed to communicate with the payment gateway.',
            gatewayCode: $gatewayCode,
            context: $context,
            previous: $previous,
        );
    }

    /**
     * Creates a standardized exception for invalid gateway responses.
     *
     * @param array<string, mixed> $context
     */
    public static function invalidResponse(
        string $gatewayCode,
        array $context = [],
        ?Throwable $previous = null
    ): self {
        return new self(
            message: 'Invalid response received from the payment gateway.',
            gatewayCode: $gatewayCode,
            context: $context,
            previous: $previous,
        );
    }

    /**
     * Creates a standardized exception for gateway configuration issues.
     *
     * @param array<string, mixed> $context
     */
    public static function configurationError(
        string $gatewayCode,
        array $context = [],
        ?Throwable $previous = null
    ): self {
        return new self(
            message: 'Payment gateway configuration error.',
            gatewayCode: $gatewayCode,
            context: $context,
            previous: $previous,
        );
    }

    /**
     * Converts the exception to a structured array useful for logs.
     *
     * @return array{
     *     message: string,
     *     gateway: string,
     *     code: int,
     *     context: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'gateway' => $this->gatewayCode,
            'code' => $this->getCode(),
            'context' => $this->context,
        ];
    }
}