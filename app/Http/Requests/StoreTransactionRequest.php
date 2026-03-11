<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTransactionRequest extends FormRequest
{
    /**
     * Determine whether this request is authorized.
     *
     * Public purchase route: guests are allowed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Returns the validation rules for storing a transaction.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'max:150'],
            'customer.email' => ['required', 'string', 'email', 'max:150'],
            'customer.document' => ['nullable', 'string', 'max:30'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                'min:1',
                'distinct',
                Rule::exists(Product::class, 'id'),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],

            'card' => ['required', 'array'],
            'card.number' => ['required', 'string', 'digits_between:12,19'],
            'card.holder_name' => ['nullable', 'string', 'max:150'],
            'card.brand' => ['nullable', 'string', 'max:50'],
            'card.cvv' => ['required', 'string', 'digits_between:3,4'],
        ];
    }

    /**
     * Returns custom validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer.required' => 'The customer field is required.',
            'customer.array' => 'The customer field must be an object.',
            'customer.name.required' => 'The customer name field is required.',
            'customer.name.string' => 'The customer name must be a valid string.',
            'customer.name.max' => 'The customer name may not be greater than 150 characters.',
            'customer.email.required' => 'The customer email field is required.',
            'customer.email.email' => 'The customer email must be a valid email address.',
            'customer.email.max' => 'The customer email may not be greater than 150 characters.',
            'customer.document.max' => 'The customer document may not be greater than 30 characters.',

            'items.required' => 'The items field is required.',
            'items.array' => 'The items field must be a valid array.',
            'items.min' => 'At least one item is required.',
            'items.*.product_id.required' => 'Each item must contain a product_id.',
            'items.*.product_id.integer' => 'Each product_id must be a valid integer.',
            'items.*.product_id.min' => 'Each product_id must be greater than zero.',
            'items.*.product_id.distinct' => 'Duplicate product ids are not allowed.',
            'items.*.product_id.exists' => 'One or more selected products are invalid.',
            'items.*.quantity.required' => 'Each item must contain a quantity.',
            'items.*.quantity.integer' => 'Each quantity must be a valid integer.',
            'items.*.quantity.min' => 'The quantity must be at least 1.',

            'card.required' => 'The card field is required.',
            'card.array' => 'The card field must be an object.',
            'card.number.required' => 'The card number field is required.',
            'card.number.digits_between' => 'The card number must contain between 12 and 19 digits.',
            'card.holder_name.max' => 'The card holder name may not be greater than 150 characters.',
            'card.brand.max' => 'The card brand may not be greater than 50 characters.',
            'card.cvv.required' => 'The card cvv field is required.',
            'card.cvv.digits_between' => 'The card cvv must contain between 3 and 4 digits.',
        ];
    }

    /**
     * Prepare and normalize request data before validation.
     */
    protected function prepareForValidation(): void
    {
        $customer = $this->normalizeCustomer((array) $this->input('customer', []));
        $items = $this->normalizeItems((array) $this->input('items', []));
        $card = $this->normalizeCard((array) $this->input('card', []));

        $this->merge([
            'customer' => $customer,
            'items' => $items,
            'card' => $card,
        ]);
    }

    /**
     * Normalizes customer payload fields.
     *
     * @param array<string, mixed> $customer
     * @return array<string, mixed>
     */
    private function normalizeCustomer(array $customer): array
    {
        $customer['name'] = isset($customer['name'])
            ? trim((string) $customer['name'])
            : null;

        $customer['email'] = isset($customer['email'])
            ? mb_strtolower(trim((string) $customer['email']))
            : null;

        $customer['document'] = isset($customer['document'])
            ? $this->digitsOnlyOrNull((string) $customer['document'])
            : null;

        return $customer;
    }

    /**
     * Normalizes item payloads.
     *
     * @param array<int, mixed> $items
     * @return array<int, array<string, int|null>>
     */
    private function normalizeItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $index => $item) {
            $item = (array) $item;

            $normalized[$index] = [
                'product_id' => isset($item['product_id']) ? (int) $item['product_id'] : null,
                'quantity' => isset($item['quantity']) ? (int) $item['quantity'] : null,
            ];
        }

        return $normalized;
    }

    /**
     * Normalizes card payload fields.
     *
     * @param array<string, mixed> $card
     * @return array<string, string|null>
     */
    private function normalizeCard(array $card): array
    {
        return [
            'number' => isset($card['number'])
                ? $this->digitsOnlyOrNull((string) $card['number'])
                : null,
            'holder_name' => isset($card['holder_name'])
                ? $this->normalizeNullableString((string) $card['holder_name'])
                : null,
            'brand' => isset($card['brand'])
                ? mb_strtolower((string) $this->normalizeNullableString((string) $card['brand']))
                : null,
            'cvv' => isset($card['cvv'])
                ? $this->digitsOnlyOrNull((string) $card['cvv'])
                : null,
        ];
    }

    /**
     * Normalize a nullable string.
     */
    private function normalizeNullableString(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Returns only numeric characters from a string.
     * Empty strings are converted to null.
     */
    private function digitsOnlyOrNull(string $value): ?string
    {
        $value = preg_replace('/\D/u', '', $value) ?? '';
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}