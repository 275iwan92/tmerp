<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Angka setelan chat = konstanta yang bisa diubah user di Settings > Constants (kategori Chat), bukan angka mati di kode.
// Paket chat membacanya lewat Chat\Chat::settingsUsing() (AppServiceProvider). Tabel chat sendiri = migrasi milik paket
// (packages/chat/database/migrations).
return new class extends Migration
{
    private const CONSTANTS = [
        'CHAT_ATTACHMENT_MAX_MB' => [20, 'Largest file that can be attached to a chat message, in MB.'],
        'CHAT_POLL_SECONDS' => [5, 'How often an open screen asks the server for new chat messages, in seconds.'],
        'CHAT_ONLINE_TIMEOUT_SECONDS' => [90, 'A signed-in user is shown as online while the chat heard from their screen within this many seconds.'],
    ];

    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        if (! $sys->table('sys_constant_category')->where('code', 'CHAT')->exists()) {
            $sys->table('sys_constant_category')->insert(['code' => 'CHAT', 'label' => 'Chat', 'description' => 'Chat between users: attachment size, refresh interval, online window',
                'sort_order' => 90, 'activate' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach (self::CONSTANTS as $key => [$number, $description]) {
            if (! $sys->table('sys_constant')->where('constant_key', $key)->exists()) {
                $sys->table('sys_constant')->insert(['constant_key' => $key, 'category_code' => 'CHAT', 'constant_group' => 'CHAT', 'constant_number' => $number,
                    'description' => $description, 'activate' => 1, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'migration', 'updated_by' => 'migration']);
            }
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');
        $sys->table('sys_constant')->whereIn('constant_key', array_keys(self::CONSTANTS))->delete();
        $sys->table('sys_constant_category')->where('code', 'CHAT')->delete();
    }
};
