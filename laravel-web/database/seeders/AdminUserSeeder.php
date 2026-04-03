<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = (string) env('ADMIN_SEED_NAME', 'UNAIR Admin');
        $email = (string) env('ADMIN_SEED_EMAIL', 'admin@unair.ac.id');
        $password = (string) env('ADMIN_SEED_PASSWORD', 'admin12345');

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => UserRole::Admin->value,
                'email_verified_at' => now(),
            ],
        );
    }
}
