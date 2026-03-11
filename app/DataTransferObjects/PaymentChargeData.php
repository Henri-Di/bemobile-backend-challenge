<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

final class PaymentChargeData
{
    public function __construct(
        public readonly int $amount,
        public readonly string $name,
        public readonly string $email,
        public readonly string $cardNumber,
        public readonly string $cvv,
    ) {
        $this->validate();
    }

    /**
     * Validates basic payment input data.
     */
    private function validate(): void
    {
        if ($this->amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }

        if (! filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address.');
        }

        if (strlen($this->sanitizeCardNumber()) < 12) {
            throw new \InvalidArgumentException('Invalid card number.');
        }

        if (strlen($this->cvv) < 3) {
            throw new \InvalidArgumentException('Invalid CVV.');
        }
    }

    /**
     * Returns a normalized payload used internally by the gateway service.
     *
     * @return array<string, mixed>
     */
    public function toInternalArray(): array
    {
        return [
            'amount' => $this->amount,
            'name' => trim($this->name),
            'email' => strtolower(trim($this->email)),
            'card_number' => $this->sanitizeCardNumber(),
            'cvv' => $this->cvv,
        ];
    }

    /**
     * Returns a safe payload for logs and debugging.
     * Sensitive card data is masked.
     *
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'amount' => $this->amount,
            'name' => $this->name,
            'email' => $this->email,
            'card_last_digits' => $this->cardLastNumbers(),
        ];
    }

    /**
     * Returns the last four digits of the credit card.
     */
    public function cardLastNumbers(): string
    {
        return substr($this->sanitizeCardNumber(), -4);
    }

    /**
     * Returns the sanitized card number (digits only).
     */
    public function sanitizeCardNumber(): string
    {
        return preg_replace('/\D/', '', $this->cardNumber) ?? '';
    }

    /**
     * Returns the masked card number.
     *
     * Example:
     * **** **** **** 1234
     */
    public function maskedCard(): string
    {
        $last4 = $this->cardLastNumbers();

        return '**** **** **** ' . $last4;
    }
}