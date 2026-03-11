<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Factory responsible for generating User entities for tests.
 *
 * This factory ensures:
 * - Distinct and normalized user names
 * - Unique email addresses
 * - Valid default role assignment
 * - Active users by default
 * - Reusable states for authorization and authentication scenarios
 *
 * Intended for:
 * - Feature tests
 * - Integration tests
 * - Development seed generation
 *
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    /**
     * The model associated with the factory.
     *
     * @var class-string<User>
     */
    protected $model = User::class;

    /**
     * The current hashed password reused by the factory.
     */
    protected static ?string $password = null;

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
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRoleEnum::USER,
            'is_active' => true,
            'remember_token' => Str::random(10),
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

        return fake()->unique()->safeEmail(
            $slug !== '' ? sprintf('%s@example.test', $slug) : null
        );
    }

    /**
     * Indicate that the user's email address is unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (): array => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Assign the ADMIN role.
     */
    public function admin(): static
    {
        return $this->state(fn (): array => [
            'role' => UserRoleEnum::ADMIN,
        ]);
    }

    /**
     * Assign the MANAGER role.
     */
    public function manager(): static
    {
        return $this->state(fn (): array => [
            'role' => UserRoleEnum::MANAGER,
        ]);
    }

    /**
     * Assign the FINANCE role.
     */
    public function finance(): static
    {
        return $this->state(fn (): array => [
            'role' => UserRoleEnum::FINANCE,
        ]);
    }

    /**
     * Assign the USER role.
     */
    public function regular(): static
    {
        return $this->state(fn (): array => [
            'role' => UserRoleEnum::USER,
        ]);
    }

    /**
     * Mark the user as inactive and unverified.
     */
    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
            'email_verified_at' => null,
        ]);
    }

    /**
     * Generate a deterministic user useful for repeatable tests.
     */
    public function deterministic(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Test User',
            'email' => 'user@example.test',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => UserRoleEnum::USER,
            'is_active' => true,
            'remember_token' => 'testtoken1',
        ]);
    }
}