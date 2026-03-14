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
        $ceoRole = Role::where('slug', Role::CEO)->first();
        $mdRole = Role::where('slug', Role::MD)->first();
        $chiefMarketRole = Role::where('slug', Role::CHIEF_MARKET)->first();
        $employeeRole = Role::where('slug', Role::EMPLOYEE)->first();

        if ($ceoRole) {
            User::updateOrCreate(
                ['email' => 'admin@africansafarihub.com'],
                [
                    'first_name' => 'Company',
                    'last_name' => 'admin',
                    'phone' => '0782667888',
                    'password' => Hash::make('password123'),
                    'role_id' => $ceoRole->id,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
        }

    }
}