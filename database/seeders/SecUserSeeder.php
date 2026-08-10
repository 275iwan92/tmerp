<?php

namespace Database\Seeders;

use App\Models\Sys\SecGroup;
use App\Models\Sys\SecUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SecUserSeeder extends Seeder
{
    /**
     * sec_user kosong sejak CLEANUP_tm2sys.sql (tabel users lama dibuang) —
     * tanpa ini tidak ada akun yang bisa dipakai login sama sekali.
     * Password default 'password' — WAJIB diganti sebelum produksi.
     */
    public function run(): void
    {
        $admin = SecUser::query()->updateOrCreate(
            ['login' => 'admin'],
            [
                'pswd' => Hash::make('password'),
                'name' => 'Budi Santoso',
                'email' => 'budi.santoso@telasih.co.id',
                'active' => true,
                'is_admin' => true,
                'is_active' => true,
            ]
        );

        $sales = SecUser::query()->updateOrCreate(
            ['login' => 'rina'],
            [
                'pswd' => Hash::make('password'),
                'name' => 'Rina Wijaya',
                'email' => 'rina.wijaya@telasih.co.id',
                'active' => true,
                'is_admin' => false,
                'is_active' => true,
            ]
        );

        $salesStaff = SecGroup::query()->where('group_code', 'SALES_STAFF')->first();

        if ($salesStaff) {
            $sales->groups()->syncWithoutDetaching([$salesStaff->id_group]);
        }

        // Admin tidak wajib punya grup (is_admin sudah bypass semua izin),
        // tapi diikutkan ke DIREKSI supaya nama grup/role tampil di dropdown profil.
        $direksi = SecGroup::query()->where('group_code', 'DIREKSI')->first();

        if ($direksi) {
            $admin->groups()->syncWithoutDetaching([$direksi->id_group]);
        }
    }
}
