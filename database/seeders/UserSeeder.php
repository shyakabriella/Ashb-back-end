<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
           
            Role::MD => [
                'email' => 'md@africansafarihub.com',
                'first_name' => 'Managing',
                'last_name' => 'Director',
                'phone' => '0782667889',
            ],
           
        ];

        foreach ($roles as $slug => $userData) {
            $role = Role::where('slug', $slug)->first();

            if (!$role) {
                $this->command?->warn("Role with slug '{$slug}' was not found. Skipping user creation.");
                continue;
            }

            User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'first_name' => $userData['first_name'],
                    'last_name' => $userData['last_name'],
                    'phone' => $userData['phone'],
                    'password' => Hash::make('password123'),
                    'role_id' => $role->id,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}