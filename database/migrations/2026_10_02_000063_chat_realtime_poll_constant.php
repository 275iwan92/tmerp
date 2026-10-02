<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Chat seketika (WebSocket): selama tersambung, layar cukup bertanya ke server sejarang ini (jaring pengaman + denyut
// online). Diubah di Settings > Constants kategori Chat; wajib di bawah CHAT_ONLINE_TIMEOUT_SECONDS.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        if (! $sys->table('sys_constant')->where('constant_key', 'CHAT_REALTIME_POLL_SECONDS')->exists()) {
            $sys->table('sys_constant')->insert(['constant_key' => 'CHAT_REALTIME_POLL_SECONDS', 'category_code' => 'CHAT', 'constant_group' => 'CHAT', 'constant_number' => 30,
                'description' => 'While the instant (WebSocket) connection is up, how often a screen still asks the server, in seconds. Keep it below CHAT_ONLINE_TIMEOUT_SECONDS.',
                'activate' => 1, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'migration', 'updated_by' => 'migration']);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_constant')->where('constant_key', 'CHAT_REALTIME_POLL_SECONDS')->delete();
    }
};
