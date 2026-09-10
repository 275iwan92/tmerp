<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// i18n Malam 2/7 (docs/BACKLOG.md §13 baris #2): sisa string chrome shell
// (drawer Notifikasi/Chat/Asisten AI, command palette, tombol topbar) yang
// belum ditarik Malam 1 / sesi 2026-09-07. Lanjutan prefix shell_* (migration
// 2026_09_07_000009_seed_shell_chrome_translations.php).
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['shell_search_command_title', 'Cari / perintah (Ctrl K)', 'Search / command (Ctrl K)'],
            ['shell_hide_topbar_title', 'Sembunyikan top bar', 'Hide top bar'],
            ['shell_chat_title', 'Chat', 'Chat'],
            ['shell_notif_title', 'Notifikasi', 'Notifications'],
            ['shell_theme_switch_light', 'Ganti ke tema terang', 'Switch to light theme'],
            ['shell_theme_switch_dark', 'Ganti ke tema gelap', 'Switch to dark theme'],
            ['shell_notif_mark_all_read', 'Tandai semua terbaca', 'Mark all as read'],
            ['shell_notif_empty', 'Belum ada notifikasi.', 'No notifications yet.'],
            ['shell_notif_view_all', 'Lihat semua notifikasi →', 'View all notifications →'],
            ['shell_chat_empty', 'Belum ada percakapan.', 'No conversations yet.'],
            ['shell_chat_no_messages', 'Belum ada pesan.', 'No messages yet.'],
            ['shell_chat_select_conversation', 'Pilih percakapan di sebelah kiri.', 'Select a conversation on the left.'],
            ['shell_chat_input_placeholder', 'Tulis pesan…', 'Write a message…'],
            ['shell_ai_title', 'Asisten AI', 'AI Assistant'],
            ['shell_ai_preview_badge', 'pratinjau', 'preview'],
            ['shell_ai_intro', 'Tanya dengan bahasa biasa — mis. <b>“buka inquiry”</b> atau <b>“pengguna”</b>, saya bantu bukakan form/grid-nya di tab. Versi ini masih pratinjau (pencocokan kata kunci, bukan LLM sungguhan).', 'Ask in plain language — e.g. <b>“open inquiry”</b> or <b>“users”</b>, and I’ll open the form/grid for you in a tab. This version is still a preview (keyword matching, not a real LLM).'],
            ['shell_ai_chip_inquiry', 'buka inquiry', 'open inquiry'],
            ['shell_ai_chip_user', 'pengguna', 'users'],
            ['shell_ai_input_placeholder', 'Ketik permintaan…', 'Type a request…'],
            ['shell_palette_placeholder', 'Ketik nama form, grid, atau laporan…', 'Type a form, grid, or report name…'],
            ['shell_palette_no_match', 'Tak ada yang cocok.', 'No matches.'],
            ['shell_palette_hint_select', 'pilih', 'select'],
            ['shell_palette_hint_open', 'buka', 'open'],
            ['shell_palette_hint_close', 'tutup', 'close'],
        ];

        $sys = DB::connection('tm2sys');

        foreach ($rows as [$key, $id, $en]) {
            $sys->table('sys_translations')->updateOrInsert(['lang_code' => 'id', 'trans_key' => $key], ['trans_value' => $id]);
            $sys->table('sys_translations')->updateOrInsert(['lang_code' => 'en', 'trans_key' => $key], ['trans_value' => $en]);
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');
        $keys = [
            'shell_search_command_title', 'shell_hide_topbar_title', 'shell_chat_title', 'shell_notif_title',
            'shell_theme_switch_light', 'shell_theme_switch_dark', 'shell_notif_mark_all_read', 'shell_notif_empty',
            'shell_notif_view_all', 'shell_chat_empty', 'shell_chat_no_messages', 'shell_chat_select_conversation',
            'shell_chat_input_placeholder', 'shell_ai_title', 'shell_ai_preview_badge', 'shell_ai_intro',
            'shell_ai_chip_inquiry', 'shell_ai_chip_user', 'shell_ai_input_placeholder', 'shell_palette_placeholder',
            'shell_palette_no_match', 'shell_palette_hint_select', 'shell_palette_hint_open', 'shell_palette_hint_close',
        ];
        $sys->table('sys_translations')->whereIn('trans_key', $keys)->delete();
    }
};
