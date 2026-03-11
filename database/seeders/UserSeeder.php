<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Default password used for seeded users.
     */
    private const DEFAULT_PASSWORD = 'password';

    /**
     * Run the user seeders.
     */
    public function run(): void
    {
        foreach ($this->users() as $user) {
            $model = User::query()->firstOrNew([
                'email' => $user['email'],
            ]);

            $model->name = $user['name'];
            $model->role = $user['role'];
            $model->is_active = $user['is_active'];

            // Define password only when creating the user
            if (! $model->exists) {
                $model->password = Hash::make(self::DEFAULT_PASSWORD);
            }

            $model->save();
        }
    }

    /**
     * Returns the default seeded users.
     *
     * @return array<int, array{
     *     name: string,
     *     email: string,
     *     role: UserRoleEnum,
     *     is_active: bool
     * }>
     */
    private function users(): array
    {
        return [
            [
                'name' => 'Admin User',
                'email' => 'admin@bemobile.local',
                'role' => UserRoleEnum::ADMIN,
                'is_active' => true,
            ],
            [
                'name' => 'Manager User',
                'email' => 'manager@bemobile.local',
                'role' => UserRoleEnum::MANAGER,
                'is_active' => true,
            ],
            [
                'name' => 'Finance User',
                'email' => 'finance@bemobile.local',
                'role' => UserRoleEnum::FINANCE,
                'is_active' => true,
            ],
            [
                'name' => 'Default User',
                'email' => 'user@bemobile.local',
                'role' => UserRoleEnum::USER,
                'is_active' => true,
            ],
        ];
    }
}