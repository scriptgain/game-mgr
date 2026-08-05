<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $pass = Hash::make('gamemgr-dev-pass');

        // Four accounts, because the permission model only proves itself when
        // you can log in as somebody who should NOT see a screen.
        $people = [
            ['admin@gamemgr.local', 'Allen Jenkins', 'admin', true],
            ['staff@gamemgr.local', 'Sam Ortiz', 'admin', false],
            ['client@gamemgr.local', 'Dana Whitfield', 'client', false],
            ['friend@gamemgr.local', 'Priya Raman', 'client', false],
        ];

        foreach ($people as [$email, $name, $role, $root]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => $pass,
                'role' => $role,
                'root_admin' => $root,
                'timezone' => 'America/Phoenix',
                'password_changed_at' => now(),
                'email_verified_at' => now(),
            ]);
        }
    }
}
