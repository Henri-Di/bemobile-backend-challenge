<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

final class StoreUserRequest extends FormRequest
{
    /**
     * Determine whether the current user is authorized to perform this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Prepare input data before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->normalizePersonName($this->input('name')),
            'email' => $this->sanitizeEmail($this->input('email')),
            'role' => $this->normalizeRole($this->input('role')),
            'is_active' => $this->normalizeNullableBoolean($this->input('is_active', true)) ?? true,
        ]);
    }

    /**
     * Get the validation rules for the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc,dns', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)],
            'role' => ['required', 'string', Rule::in(User::roles())],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => 'The selected role is invalid.',
        ];
    }

    /**
     * Normalize a person name.
     */
    private function normalizePersonName(mixed $value): ?string
    {
        $name = $this->normalizeNullableString($value);

        if ($name === null) {
            return null;
        }

        $name = preg_replace('/\s+/u', ' ', $name);

        return is_string($name) ? mb_substr($name, 0, 255) : null;
    }

    /**
     * Normalize a role value.
     */
    private function normalizeRole(mixed $value): ?string
    {
        $role = $this->normalizeNullableString($value);

        return $role !== null ? mb_strtoupper($role) : null;
    }

    /**
     * Convert an input into a nullable trimmed string.
     */
    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        if (is_scalar($value)) {
            $string = trim((string) $value);

            return $string === '' ? null : $string;
        }

        return null;
    }

    /**
     * Sanitize and validate an email address.
     */
    private function sanitizeEmail(mixed $value): ?string
    {
        $email = $this->normalizeNullableString($value);

        if ($email === null) {
            return null;
        }

        $sanitized = filter_var($email, FILTER_SANITIZE_EMAIL);

        if (! is_string($sanitized) || $sanitized === '') {
            return null;
        }

        return filter_var($sanitized, FILTER_VALIDATE_EMAIL)
            ? mb_strtolower($sanitized)
            : null;
    }

    /**
     * Normalize an input into a nullable boolean.
     */
    private function normalizeNullableBoolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return match ($value) {
                1 => true,
                0 => false,
                default => null,
            };
        }

        if (is_string($value)) {
            $value = mb_strtolower(trim($value));

            return match ($value) {
                '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off' => false,
                default => null,
            };
        }

        return null;
    }
}