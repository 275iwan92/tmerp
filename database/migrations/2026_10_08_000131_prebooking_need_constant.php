<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Pre-booking sejak ProCost terbit (B-01): inquiry belum punya tanggal butuh, jadi dipakai jumlah hari kerja sejak hari ini
// (aturan proyek: due date = 14 hari kerja dari approval prove fisik). Pengaturan di Settings > Constants.
return new class extends Migration
{
    private const KEY = 'INV_PRE_BOOKING_NEED_WORKDAYS';

    public function up(): void
    {
        $sys = DB::connection('tm2sys')->table('sys_constant');
        if (! (clone $sys)->where('constant_key', self::KEY)->exists()) {
            $sys->insert(['constant_key' => self::KEY, 'category_code' => 'INVENTORY', 'constant_group' => 'INV', 'constant_number' => 14, 'constant_text' => null,
                'description' => 'Working days from today used as the material need date of a pre-booking when the inquiry has no due date yet.',
                'activate' => 1, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'claude (booking & pick list)', 'updated_by' => 'claude (booking & pick list)']);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_constant')->where('constant_key', self::KEY)->delete();
    }
};
