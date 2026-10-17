<?php

/*
 * REMARK
 * Tujuan       : Tarif PPN berversi menurut tanggal berlaku (fin_tax_rate) + item menu Master Data > Tax Rate + kamus Indonesia; isi awal PPN 10% sejak 01-Apr-1985 dan 11% sejak 01-Apr-2022 (Jo 2026-10-17).
 * Sumber hulu  : Keputusan Jo 2026-10-17 (PPN 11% sejak 1 April 2022, sebelumnya 10%); konstanta PPN di sys_constant (dipertahankan sebagai cadangan).
 * Input        : -
 * Output       : tm2mst.fin_tax_rate (2 baris PPN); satu baris sys_app_menu (MD_TAX_RATE); baris sys_dictionary; deskripsi konstanta PPN diperjelas.
 * Dipakai oleh : TaxRate::ppn(tanggal), PaperPriceEngine, PoOrder::recalcTax, TaxRateLookup (v2:md-tax-rate).
 * Status       : Jalankan di ddt* dan tm2* (paritas Jo 2026-10-16): php artisan migrate --path=database/migrations/2026_10_17_000200_fin_tax_rate.php [--env=mockup].
 */

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CODE = 'MD_TAX_RATE';

    private const UI = [
        'Tax Rate' => 'Tarif Pajak',
        'Tax rate by effective date, like Exchange Rate. A document uses the rate in force on its own date, so an old document keeps its old rate. To change the rate, add a new row with the new date.' => 'Tarif pajak menurut tanggal berlaku, seperti Kurs. Dokumen memakai tarif yang berlaku pada tanggalnya sendiri, jadi dokumen lama tetap bertarif lama. Untuk mengubah tarif, tambah baris baru dengan tanggal baru.',
        'Rate %' => 'Tarif %',
        'Tax' => 'Pajak',
        'A rate already exists for this tax and date.' => 'Tarif untuk pajak dan tanggal ini sudah ada.',
    ];

    public function up(): void
    {
        Schema::connection('tm2mst')->create('fin_tax_rate', function (Blueprint $table) {
            $table->id('id_tax_rate');
            $table->string('tax_code', 20)->comment('PPN');
            $table->decimal('rate', 8, 4)->comment('persen, mis. 11');
            $table->date('effective_date');
            $table->boolean('activate')->default(true);
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            $table->unique(['tax_code', 'effective_date'], 'uq_tax_rate_date');
        });

        $now = now();
        foreach ([['PPN', 10, '1985-04-01'], ['PPN', 11, '2022-04-01']] as [$code, $rate, $date]) {
            DB::connection('tm2mst')->table('fin_tax_rate')->insert(['tax_code' => $code, 'rate' => $rate, 'effective_date' => $date, 'activate' => 1,
                'created_at' => $now, 'updated_at' => $now, 'created_by' => 'migration', 'updated_by' => 'migration']);
        }

        $sys = DB::connection('tm2sys');
        if (! $sys->table('sys_app_menu')->where('code', self::CODE)->exists()) {
            $sys->table('sys_app_menu')->insert(['parent_id' => 5, 'code' => self::CODE, 'label' => 'Tax Rate', 'icon' => 'fa-solid fa-percent',
                'menu_type' => 'item', 'object_type' => 'grid', 'target' => 'v2:md-tax-rate', 'sort_order' => 94, 'activate' => 1,
                'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now()]);
        }

        foreach (self::UI as $en => $id) {
            $sys->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => $en, 'group_code' => 'general', 'origin' => 'app',
                'activate' => 1, 'created_at' => now(), 'created_by' => 'migration']);
            $key = $sys->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary');
            if ($key && ! $sys->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
                $sys->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => $id]);
            }
        }
        Dictionary::flush();

        $sys->table('sys_constant')->where('constant_key', 'PPN')->update(['description' => 'Tarif PPN (%) cadangan, dipakai hanya bila Master Data > Tax Rate kosong; tarif per tanggal diisi di layar Tax Rate']);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', self::CODE)->delete();
        DB::connection('tm2sys')->table('sys_constant')->where('constant_key', 'PPN')->update(['description' => 'Tarif PPN (%)']);
        Schema::connection('tm2mst')->dropIfExists('fin_tax_rate');
    }
};
