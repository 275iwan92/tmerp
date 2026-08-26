<?php

namespace Database\Seeders;

use App\Models\Sys\SecUser;
use App\Models\Sys\SysSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

// security_defaultpassword_spec.md §7 — 6 user key person UAT. Login = nama
// depan (lowercase). Password tetap (mudah diingat tester, atas permintaan
// Jo — bukan random lagi), must_change_password=1 tetap dipaksa ganti di
// login pertama.
// Group Warehouse/HR/PPIC/Purchasing/Costing/Op-Manager BELUM ada di
// sec_group — seeder ini TIDAK menyentuh sec_user_group (lihat §7 TODO).
class UatKeyPersonSeeder extends Seeder
{
    public function run(): void
    {
        SysSetting::query()->updateOrCreate(
            ['setting_key' => 'notify_channel'],
            ['setting_value' => 'manual']
        );

        $people = [
            ['login' => 'nisa', 'name' => 'Nisa', 'role' => 'Operational Manager', 'password' => 'nisaopm12'],
            ['login' => 'arinda', 'name' => 'Arinda', 'role' => 'Warehouse', 'password' => 'arindawh12'],
            ['login' => 'sania', 'name' => 'Sania', 'role' => 'HR', 'password' => 'saniahr12'],
            ['login' => 'tya', 'name' => 'Tya', 'role' => 'PPIC', 'password' => 'tyappic12'],
            ['login' => 'eka', 'name' => 'Eka', 'role' => 'Purchasing', 'password' => 'ekapur12'],
            ['login' => 'wiwik', 'name' => 'Wiwik', 'role' => 'Costing', 'password' => 'wikhpp12'],
        ];

        $this->command->info('=== UAT KEY PERSON — CATAT PASSWORD INI ===');

        foreach ($people as $p) {
            $plain = $p['password'];

            SecUser::query()->updateOrCreate(
                ['login' => $p['login']],
                [
                    'name' => $p['name'],
                    'email' => $p['login'].'@telasih.co.id',
                    'pswd' => Hash::make($plain),
                    'default_password' => $plain, // accessor -> encrypt
                    'must_change_password' => true,
                    'active' => 1,
                    'is_active' => 1,
                    'is_admin' => 0,
                    'approval_status' => 'approved',
                    'theme_code' => 'navy',
                    'pswd_last_updated' => now(),
                ]
            );

            $this->command->line(sprintf('%-8s | %-20s | %s', $p['login'], $p['role'], $plain));
        }

        $this->command->info('===========================================');
    }
}
