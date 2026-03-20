<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate([
            'email' => 'admin@unair.ac.id',
        ], [
            'name' => 'UNAIR Admin',
            'password' => Hash::make('admin12345'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        User::query()->updateOrCreate([
            'email' => 'mahasiswa@unair.ac.id',
        ], [
            'name' => 'Mahasiswa UNAIR',
            'password' => Hash::make('mahasiswa12345'),
            'role' => 'mahasiswa',
            'email_verified_at' => now(),
        ]);
    }
}
