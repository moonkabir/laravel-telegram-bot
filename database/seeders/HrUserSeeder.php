<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class HrUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('HR_SEED_EMAIL', 'hr@example.com')],
            [
                'name' => 'HR',
                'password' => Hash::make(env('HR_SEED_PASSWORD')),
            ]
        );
    }
}
