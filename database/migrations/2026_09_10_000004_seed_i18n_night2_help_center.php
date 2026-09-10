<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// i18n Malam 2/7 (docs/BACKLOG.md §13 baris #2): Help Center index
// (resources/views/pages/help/index.blade.php) -- HANYA chrome (header,
// tab bar, form/list Tiket Bantuan). Tab "Cara Pakai Aplikasi" (konten
// edukasi panjang) & Tab "Modul" (deskripsi tiap modul, array $modules di
// dalam @php) SENGAJA TIDAK disentuh -- itu ratusan baris prosa, bukan
// chrome, di luar budget ~1 jam malam ini. Dicatat sbg gap terbuka di
// BACKLOG.md §13.
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['shell_help_header_title', 'Bantuan', 'Help'],
            ['shell_help_search_placeholder', 'Cari topik bantuan…', 'Search help topics…'],
            ['shell_help_tab_tickets', 'Tiket Bantuan', 'Support Tickets'],
            ['shell_help_tab_ui', 'Cara Pakai Aplikasi', 'How to Use the App'],
            ['shell_help_tab_modules', 'Modul', 'Modules'],
            ['shell_help_back_to_ticket_list', 'Kembali ke daftar tiket saya', 'Back to my ticket list'],
            ['shell_help_you_suffix', 'Anda', 'You'],
            ['shell_help_no_reply_yet', 'Belum ada balasan dari tim support.', 'No reply from the support team yet.'],
            ['shell_help_reply_placeholder', 'Balas / tambah info…', 'Reply / add info…'],
            ['shell_help_btn_send', 'Kirim', 'Send'],
            ['shell_help_ticket_closed_prefix', 'Tiket ini sudah', 'This ticket is already'],
            ['shell_help_ticket_closed_suffix', ', tidak bisa dibalas lagi.', ', it can no longer be replied to.'],
            ['shell_help_new_ticket_title', 'Kirim Tiket Baru', 'Submit a New Ticket'],
            ['shell_help_new_ticket_desc', 'Laporkan bug, minta bantuan, atau ajukan pertanyaan ke tim developer/system analyst.', 'Report a bug, ask for help, or send a question to the developer/system analyst team.'],
            ['shell_help_ticket_sent', 'Tiket terkirim', 'Ticket sent'],
            ['shell_help_ticket_sent_prefix', 'Nomor tiket', 'Ticket number'],
            ['shell_help_ticket_sent_suffix', '— tim kami akan segera menindaklanjuti. Lihat statusnya di daftar "Tiket Saya" di sebelah kanan.', '— our team will follow up shortly. Check its status in the "My Tickets" list on the right.'],
            ['shell_help_send_another', 'Kirim tiket lain', 'Send another ticket'],
            ['shell_help_context_label', 'Konteks layar (opsional)', 'Screen context (optional)'],
            ['shell_help_context_placeholder', 'mis. Master Customer, Customer Order #123', 'e.g. Customer Master, Customer Order #123'],
            ['shell_help_description_label', 'Deskripsi *', 'Description *'],
            ['shell_help_desc_required', 'Deskripsi wajib diisi.', 'Description is required.'],
            ['shell_help_description_placeholder', 'Ceritakan apa yang terjadi — makin detail, makin cepat ditangani.', 'Tell us what happened — the more detail, the faster it gets handled.'],
            ['shell_help_attachment_label', 'Lampiran screenshot (opsional)', 'Screenshot attachment (optional)'],
            ['shell_help_uploading', 'Mengunggah…', 'Uploading…'],
            ['shell_help_remove_attachment', 'Hapus', 'Remove'],
            ['shell_help_btn_send_ticket', 'Kirim Tiket', 'Send Ticket'],
            ['shell_help_my_tickets', 'Tiket Saya', 'My Tickets'],
            ['shell_help_replies_suffix', 'balasan', 'replies'],
            ['shell_help_no_tickets', 'Belum ada tiket.', 'No tickets yet.'],
            ['shell_help_not_found_prompt', 'Tidak menemukan yang dicari?', 'Can’t find what you’re looking for?'],
            ['shell_help_send_to_support', 'Kirim tiket ke tim support →', 'Send a ticket to the support team →'],
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
            'shell_help_header_title', 'shell_help_search_placeholder', 'shell_help_tab_tickets', 'shell_help_tab_ui',
            'shell_help_tab_modules', 'shell_help_back_to_ticket_list', 'shell_help_you_suffix', 'shell_help_no_reply_yet',
            'shell_help_reply_placeholder', 'shell_help_btn_send', 'shell_help_ticket_closed_prefix',
            'shell_help_ticket_closed_suffix', 'shell_help_new_ticket_title', 'shell_help_new_ticket_desc',
            'shell_help_ticket_sent', 'shell_help_ticket_sent_prefix', 'shell_help_ticket_sent_suffix',
            'shell_help_send_another', 'shell_help_context_label', 'shell_help_context_placeholder',
            'shell_help_description_label', 'shell_help_desc_required', 'shell_help_description_placeholder',
            'shell_help_attachment_label', 'shell_help_uploading', 'shell_help_remove_attachment',
            'shell_help_btn_send_ticket', 'shell_help_my_tickets', 'shell_help_replies_suffix', 'shell_help_no_tickets',
            'shell_help_not_found_prompt', 'shell_help_send_to_support',
        ];
        $sys->table('sys_translations')->whereIn('trans_key', $keys)->delete();
    }
};
