<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $role = DB::table('roles')->where('role_name', 'SuperAdmin')->first();
        if ($role) {
            User::updateOrCreate(
                ['email' => 'admin@sabapost.com'],
                [
                    'full_name' => 'Super Admin',
                    'password_hash' => 'password',
                    'role_id' => $role->role_id,
                    'phone' => '777777777',
                    'account_status' => 'Active'
                ]
            );
        }
    }
}
