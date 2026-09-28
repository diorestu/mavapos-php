<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $name = config('superadmin.name');
        $email = config('superadmin.email');
        $password = config('superadmin.password');

        if (! $name || ! $email || ! $password) {
            throw new RuntimeException('SUPERADMIN_NAME, SUPERADMIN_EMAIL, dan SUPERADMIN_PASSWORD wajib diisi.');
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $password, 'role' => 'superadmin', 'tenant_owner_id' => null, 'trial_ends_at' => null],
        );
    }
}
