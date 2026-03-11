<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TransactionIndexRequest extends FormRequest
{
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
            'status' => $this->normalizeNullableString($this->input('status')),
            'client_id' => $this->normalizeNullableInteger($this->input('client_id')),
            'gateway_id' => $this->normalizeNullableInteger($this->input('gateway_id')),
            'per_page' => $this->normalizeNullableInteger($this->input('per_page', 15)),
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
                'min:1',
            ],
            'gateway_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
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

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : mb_strtolower($value);
        }

        if (is_scalar($value)) {
            $string = trim((string) $value);

            return $string === '' ? null : mb_strtolower($string);
        }

        return null;
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '' || ! preg_match('/^\d+$/', $value)) {
                return null;
            }

            $intValue = (int) $value;

            return $intValue > 0 ? $intValue : null;
        }

        if (is_float($value)) {
            $intValue = (int) $value;

            return $intValue > 0 ? $intValue : null;
        }

        return null;
    }
}