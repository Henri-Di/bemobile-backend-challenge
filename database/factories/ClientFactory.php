<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory responsible for generating Client entities for tests.
 *
 * This factory ensures:
 * - Distinct and normalized client names
 * - Unique and normalized email addresses
 * - Distinct synthetic document numbers
 * - Reusable states for common test scenarios
 *
 * Intended for:
 * - Feature tests
 * - Integration tests
 * - Development seed generation
 *
 * @extends Factory<Client>
 */
final class ClientFactory extends Factory
{
    /**
     * The model associated with the factory.
     *
     * @var class-string<Client>
     */
    protected $model = Client::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->generateSafeName();

        return [
            'name' => $name,
            'email' => $this->generateUniqueEmail($name),
            'document' => $this->generatePersonalDocumentNumber(),
        ];
    }

    /**
     * Generate a safe and normalized full name.
     */
    protected function generateSafeName(): string
    {
        $name = fake()->name();
        $normalized = preg_replace('/\s+/u', ' ', trim($name));

        return Str::title((string) $normalized);
    }

    /**
     * Generate a unique normalized email address.
     */
    protected function generateUniqueEmail(string $name): string
    {
        $slug = Str::slug($name, '.');

        if ($slug === '') {
            $slug = 'client';
        }

        return fake()->unique()->safeEmail(
            sprintf('%s@example.test', $slug)
        );
    }

    /**
     * Generate a synthetic CPF-like document number.
     */
    protected function generatePersonalDocumentNumber(): string
    {
        return fake()->unique()->numerify('###########');
    }

    /**
     * Generate a synthetic CNPJ-like document number.
     */
    protected function generateCorporateDocumentNumber(): string
    {
        return fake()->unique()->numerify('##############');
    }

    /**
     * State: create a client without a document.
     */
    public function withoutDocument(): self
    {
        return $this->state(fn (): array => [
            'document' => null,
        ]);
    }

    /**
     * State: create a client without an email address.
     */
    public function withoutEmail(): self
    {
        return $this->state(fn (): array => [
            'email' => null,
        ]);
    }

    /**
     * State: generate a corporate client with a CNPJ-like document.
     */
    public function corporate(): self
    {
        return $this->state(fn (): array => [
            'document' => $this->generateCorporateDocumentNumber(),
        ]);
    }

    /**
     * State: generate a client with only mandatory attributes.
     */
    public function minimal(): self
    {
        return $this->state(fn (): array => [
            'email' => null,
            'document' => null,
        ]);
    }

    /**
     * Deterministic client useful for repeatable tests.
     */
    public function deterministic(): self
    {
        return $this->state(fn (): array => [
            'name' => 'Test Client',
            'email' => 'client@example.test',
            'document' => '12345678901',
        ]);
    }
}