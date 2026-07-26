<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the two administrator accounts defined in PROJECT.md section 5.
     */
    public function run(): void
    {
        $administrators = [
            ['name' => 'cheskib', 'email' => 'cheskib@gmail.com'],
            ['name' => 'srulymax007', 'email' => 'srulymax007@gmail.com'],
        ];

        foreach ($administrators as $administrator) {
            User::updateOrCreate(
                ['email' => $administrator['email']],
                [
                    'name' => $administrator['name'],
                    'password' => Hash::make('collection321$$'),
                ]
            );
        }
    }
}
