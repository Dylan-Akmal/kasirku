<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@kasirku.test'],
            [
                'name' => 'Admin Kasirku',
                'password' => 'password',
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'kasir@kasirku.test'],
            [
                'name' => 'Kasir Satu',
                'password' => 'password',
                'role' => 'kasir',
                'is_active' => true,
            ]
        );
    }
}