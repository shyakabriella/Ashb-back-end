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
                ['email' => 'ceo@africansafarihub.com'],
                [
                    'first_name' => 'Company',
                    'last_name' => 'CEO',
                    'phone' => '0780000001',
                    'password' => Hash::make('password123'),
                    'role_id' => $ceoRole->id,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
        }

        if ($mdRole) {
            User::updateOrCreate(
                ['email' => 'md@africansafarihub.com'],
                [
                    'first_name' => 'Managing',
                    'last_name' => 'Director',
                    'phone' => '0780000002',
                    'password' => Hash::make('password123'),
                    'role_id' => $mdRole->id,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
        }

        if ($chiefMarketRole) {
            User::updateOrCreate(
                ['email' => 'market@africansafarihub.com'],
                [
                    'first_name' => 'Chief',
                    'last_name' => 'of Market',
                    'phone' => '0780000003',
                    'password' => Hash::make('password123'),
                    'role_id' => $chiefMarketRole->id,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
        }

        if ($employeeRole) {
            User::updateOrCreate(
                ['email' => 'employee@africansafarihub.com'],
                [
                    'first_name' => 'Employee',
                    'last_name' => 'User',
                    'phone' => '0780000004',
                    'password' => Hash::make('password123'),
                    'role_id' => $employeeRole->id,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}