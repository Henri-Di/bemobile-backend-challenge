<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TransactionIndexRequest extends FormRequest
{
    private const DEFAULT_PER_PAGE = 15;
    private const MIN_PER_PAGE = 1;
    private const MAX_PER_PAGE = 100;

    /**
     * @var array<int, string>
     */
    private const ALLOWED_STATUSES = [
        'pending',
        'processing',
        'authorized',
        'paid',
        'failed',
        'refused',
        'cancelled',
        'refunded',
        'partially_refunded',
    ];

    /**
     * Authentication is already enforced by route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->normalizeStatusInput($this->input('status')),
            'client_id' => $this->normalizeIntegerInput($this->input('client_id')),
            'gateway_id' => $this->normalizeIntegerInput($this->input('gateway_id')),
            'per_page' => $this->normalizeIntegerInput(
                $this->input('per_page', self::DEFAULT_PER_PAGE)
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'nullable',
                'string',
                Rule::in(self::ALLOWED_STATUSES),
            ],
            'client_id' => [
                'nullable',
                'integer',
                'min:' . self::MIN_PER_PAGE,
            ],
            'gateway_id' => [
                'nullable',
                'integer',
                'min:' . self::MIN_PER_PAGE,
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:' . self::MIN_PER_PAGE,
                'max:' . self::MAX_PER_PAGE,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.string' => 'The status filter must be a string.',
            'status.in' => 'The selected status filter is invalid.',

            'client_id.integer' => 'The client_id filter must be an integer.',
            'client_id.min' => 'The client_id filter must be greater than zero.',

            'gateway_id.integer' => 'The gateway_id filter must be an integer.',
            'gateway_id.min' => 'The gateway_id filter must be greater than zero.',

            'per_page.integer' => 'The per_page value must be an integer.',
            'per_page.min' => 'The per_page value must be at least 1.',
            'per_page.max' => 'The per_page value may not be greater than 100.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return [
            'status' => $validated['status'] ?? null,
            'client_id' => $validated['client_id'] ?? null,
            'gateway_id' => $validated['gateway_id'] ?? null,
            'per_page' => $validated['per_page'] ?? self::DEFAULT_PER_PAGE,
        ];
    }

    private function normalizeStatusInput(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === ''
                ? null
                : mb_strtolower($trimmed);
        }

        if (is_scalar($value)) {
            $string = trim((string) $value);

            return $string === ''
                ? null
                : mb_strtolower($string);
        }

        return $value;
    }

    private function normalizeIntegerInput(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '') {
                return null;
            }

            return preg_match('/^\d+$/', $trimmed) === 1
                ? (int) $trimmed
                : $value;
        }

        if (is_float($value)) {
            return floor($value) === $value
                ? (int) $value
                : $value;
        }

        return $value;
    }
}