<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        User::query()->updateOrCreate(
            ['email' => 'admin@example.test'],
            [
                'name' => 'Demo Administrator',
                'password' => Hash::make('AdminDemo123!'),
                'role' => Role::Admin,
                'position_title' => 'Administrator',
                'institution' => 'Local Development',
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'signer@example.test'],
            [
                'name' => 'Demo Signer',
                'password' => Hash::make('SignerDemo123!'),
                'role' => Role::Signer,
                'position_title' => 'Document Signer',
                'institution' => 'Local Development',
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'signer2@example.test'],
            [
                'name' => 'Demo Signer 2',
                'password' => Hash::make('SignerDemo123!'),
                'role' => Role::Signer,
                'position_title' => 'Document Signer 2',
                'institution' => 'Local Development',
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'signer3@example.test'],
            [
                'name' => 'Demo Signer 3',
                'password' => Hash::make('SignerDemo123!'),
                'role' => Role::Signer,
                'position_title' => 'Document Signer 3',
                'institution' => 'Local Development',
            ],
        );
    }
}
