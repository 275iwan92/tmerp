<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Papan widget, keputusan user 2026-10-02 sesudah review: pilihan periode "dari daily sampai yearly" dan order value yang
// merinci Draft di samping Confirmed.
//   sys_user_board.period_code   periode papan pilihan user (database yang sudah menjalankan 000160 versi awal)
//   widget revenue_month -> order_value (judul "Order Value": tidak lagi terikat bulan)
//   baris katalog yang belum pernah diubah user dibuat ulang dari kelasnya, supaya uraian baru (per periode) terpakai
// Database yang baru menjalankan 000160 sekarang sudah benar dari awal; di sana hanya terjemahannya yang ditambahkan.
return new class extends Migration
{
    private const UI = [
        'Daily' => 'Harian', 'Weekly' => 'Mingguan', 'Monthly' => 'Bulanan', 'Quarterly' => 'Kuartalan', 'Half-yearly' => 'Semesteran', 'Yearly' => 'Tahunan',
        'Order Value' => 'Nilai Order',
        'Confirmed Customer Orders of the period against the target and the period before, with the Draft amount beside it.' => 'Customer Order Confirmed pada periode itu terhadap target dan periode sebelumnya, dengan nilai Draft di sampingnya.',
        'Number of Customer Orders entered in the period, without the cancelled ones: Confirmed and Draft.' => 'Jumlah Customer Order yang masuk pada periode itu, tanpa yang Cancelled: Confirmed dan Draft.',
        'Share of customer deliveries that left on or before the promised date in the period.' => 'Bagian pengiriman ke customer yang berangkat tepat atau sebelum tanggal janji pada periode itu.',
        'Confirmed order value per period as bars and its target as a line: days, weeks, months, quarters or years.' => 'Nilai order Confirmed per periode sebagai batang dan targetnya sebagai garis: hari, minggu, bulan, kuartal, atau tahun.',
        'Company targets of the period and what has been reached so far.' => 'Target perusahaan pada periode itu dan pencapaiannya sampai hari ini.',
        'Documents of the period at each sales step: Inquiry, price published, Quotation, Customer Order.' => 'Dokumen pada periode itu di tiap tahap penjualan: Inquiry, harga terbit, Quotation, Customer Order.',
        'Confirmed order value of each sales person in the period against the personal target.' => 'Nilai order Confirmed tiap sales person pada periode itu terhadap target pribadinya.',
        'Customer Orders of the period split by status: Draft, Waiting for approval, Confirmed, Closed.' => 'Customer Order pada periode itu per status: Draft, Waiting for approval, Confirmed, Closed.',
        'The five customers with the highest confirmed order value in the period.' => 'Lima customer dengan nilai order Confirmed tertinggi pada periode itu.',
        'No delivery has left in this period' => 'Belum ada pengiriman yang berangkat pada periode ini', 'Bars = actual, line = target' => 'Batang = pencapaian, garis = target',
        'as of today, lower is better' => 'per hari ini, makin kecil makin baik', 'Documents dated' => 'Dokumen bertanggal', 'highest first' => 'tertinggi di atas',
        'documents' => 'dokumen', 'confirmed order value' => 'nilai order Confirmed', 'Waiting for approval' => 'Menunggu approval',
        'Targets are set per month' => 'Target diisi per bulan', 'No target set for this period' => 'Target periode ini belum diisi',
        'Confirmed orders this month' => 'Order Confirmed bulan ini', 'Draft orders this month' => 'Order Draft bulan ini', 'Unknown period.' => 'Periode tidak dikenal.',
    ];

    public function up(): void
    {
        $sys = Schema::connection('tm2sys');
        $db = DB::connection('tm2sys');
        if ($sys->hasTable('sys_user_board') && ! $sys->hasColumn('sys_user_board', 'period_code')) {
            $sys->table('sys_user_board', fn (Blueprint $t) => $t->string('period_code', 12)->default('monthly')->after('profile_code'));
        }
        if ($sys->hasTable('sys_widget')) {
            foreach (['sys_board_template', 'sys_user_widget'] as $table) {
                // Yang sudah punya order_value di profil / papan yang sama: baris lamanya dibuang, bukan diganti (kunci unik).
                foreach ($db->table($table)->where('widget_key', 'revenue_month')->get() as $row) {
                    $owner = $table === 'sys_board_template' ? ['profile_code' => $row->profile_code] : ['user_id' => $row->user_id];
                    $pk = $table === 'sys_board_template' ? 'id_board_template' : 'id_user_widget';
                    $db->table($table)->where($owner)->where('widget_key', 'order_value')->exists()
                        ? $db->table($table)->where($pk, $row->{$pk})->delete()
                        : $db->table($table)->where($pk, $row->{$pk})->update(['widget_key' => 'order_value']);
                }
            }
            // Dibuat ulang dari kelas widget saat katalog dibaca berikutnya (App\Support\V2\Board\WidgetCatalog).
            $db->table('sys_widget')->where('updated_by', 'board')->delete();
            $db->table('sys_widget')->where('widget_key', 'revenue_month')->delete();
        }

        if (! $sys->hasTable('sys_dictionary')) {
            return;
        }
        foreach (self::UI as $en => $id) {
            $db->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => $en, 'created_at' => now(), 'created_by' => 'migration']);
            $key = $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary');
            if ($key && ! $db->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
                $db->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => $id]);
            }
        }
        Dictionary::flush();
    }

    public function down(): void {}
};
