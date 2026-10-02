<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// User 2026-10-02: "yang lama dihapus saja, gantikan dengan yang baru. yang lama disudahi saja". Layar Order to Cash dan
// Down Payment lama (v1 + grid v2-nya) diarsipkan; Customer Invoice mengambil alih: invoice dari Surat Jalan, tagihan uang
// muka + Record Receipt, dan penerimaan pelunasan (Record Payment). Migrasi ini menghapus baris menu dan topik Help kedua
// layar lama, dan menambah terjemahan teks dialog Record Payment. Hanya berjalan bila menu Customer Invoice sudah ada.
return new class extends Migration
{
    private const OLD_MENUS = ['FIN_AR', 'FIN_DP'];

    private const OLD_HELP = ['acc-receivables', 'acc-dp-invoice'];

    private const UI = [
        'Record Payment' => 'Record Payment', 'Open Invoices of This Customer' => 'Invoice Terbuka Customer Ini', 'Paid Now' => 'Dibayar Sekarang', 'Note' => 'Catatan',
        'One receipt may pay several invoices of this customer, in part or in full. Confirm Payment makes the bank receipt voucher and the journal: Bank to Receivable.' => 'Satu penerimaan boleh melunasi beberapa invoice customer ini, sebagian atau penuh. Confirm Payment membuat bukti bank masuk dan jurnal: Bank pada Piutang.',
        'Payment received and allocated to the invoice.' => 'Pembayaran diterima dan dialokasikan ke invoice.',
        'This invoice was made before delivery note lines existed: it has one amount and no lines.' => 'Invoice ini dibuat sebelum ada baris Surat Jalan: hanya satu nilai, tanpa baris.',
    ];

    public function up(): void
    {
        $db = DB::connection('tm2sys');
        if (! $db->table('sys_app_menu')->where('code', 'FIN_AR_INVOICE')->exists()) {
            return;
        }
        $db->table('sys_app_menu')->whereIn('code', self::OLD_MENUS)->delete();
        // Customer Invoice menempati tempat Order to Cash di menu.
        $db->table('sys_app_menu')->where('code', 'FIN_AR_INVOICE')->update(['sort_order' => 20]);

        if (Schema::connection('tm2sys')->hasTable('sys_help_topic')) {
            $topics = $db->table('sys_help_topic')->whereIn('topic_key', self::OLD_HELP)->pluck('id_help_topic');
            if (Schema::connection('tm2sys')->hasTable('sys_help_line')) {
                $db->table('sys_help_line')->whereIn('help_topic_id', $topics)->delete();
            }
            $db->table('sys_help_topic')->whereIn('id_help_topic', $topics)->delete();
        }
        if (Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            foreach (self::UI as $en => $id) {
                $db->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => $en, 'created_at' => now(), 'created_by' => 'migration']);
                $key = $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary');
                if ($key && ! $db->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
                    $db->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => $id]);
                }
            }
            Dictionary::flush();
        }
    }

    // Layar lamanya sudah diarsipkan (C:\Users\iwanp\tmerp_unused), jadi baris menunya tidak dikembalikan.
    public function down(): void {}
};
