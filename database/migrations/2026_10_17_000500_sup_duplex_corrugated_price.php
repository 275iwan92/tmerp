<?php

/*
 * REMARK
 * Tujuan       : Tiga tabel konstanta harga beli: duplex (sup_duplex_price), aturan lembar gelombang per supplier (sup_corrugated_rule: lebar trim maksimum + diskon per tingkat running meter), dan index lembar gelombang (sup_corrugated_index); plus tiga item menu dan kamus Indonesia.
 * Sumber hulu  : Excel Jo 2026-10-17 (HITUNGAN DUPLEX.xlsx sheet INDEX; PERHITUNGAN BF, BCF, SINGLE WALL, DOUBLE WALL.xlsx sheet UJK, RCA); keputusan Jo: metode Excel, diskon = nilai tabel dan petugas boleh menimpa harga.
 * Input        : -
 * Output       : tm2mst.sup_duplex_price, sup_corrugated_rule, sup_corrugated_index (kosong); tiga baris sys_app_menu (MD_DUPLEX_PRICE, MD_CORR_RULE, MD_CORR_INDEX); baris sys_dictionary.
 * Dipakai oleh : DuplexPriceEngine, CorrugatedSheetPriceEngine, tiga layar lookup Master Data.
 * Status       : Jalankan di ddt* dan tm2* (paritas Jo 2026-10-16): php artisan migrate --path=database/migrations/2026_10_17_000500_sup_duplex_corrugated_price.php [--env=mockup].
 */

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENUS = [
        ['MD_DUPLEX_PRICE', 'Supplier Duplex Price', 'fa-solid fa-boxes-stacked', 'v2:md-supplier-duplex-price', 50],
        ['MD_CORR_RULE', 'Supplier Corrugated Rule', 'fa-solid fa-ruler-combined', 'v2:md-supplier-corrugated-rule', 51],
        ['MD_CORR_INDEX', 'Supplier Corrugated Index', 'fa-solid fa-table-list', 'v2:md-supplier-corrugated-index', 52],
    ];

    private const UI = [
        'Supplier Duplex Price' => 'Harga Duplex Supplier',
        'Duplex price per supplier by effective date. Reference sheet: P x L / (79 x 109) x index x APKey. Weight: gsm x P x L / 20000 x index x APKey. APKey in percent (500 = x5.00). One row per gsm range.' => 'Harga duplex per supplier menurut tanggal berlaku. Referensi sheet: P x L / (79 x 109) x index x APKey. Berat: gsm x P x L / 20000 x index x APKey. APKey dalam persen (500 = dikali 5,00). Satu baris per rentang gsm.',
        'Supplier Corrugated Rule' => 'Aturan Lembar Gelombang Supplier',
        'Corrugated sheet rules per supplier by effective date: maximum trim width and the discount per running-meter tier. A negative discount raises the price. Below 300 running metres there is no price.' => 'Aturan lembar gelombang per supplier menurut tanggal berlaku: lebar trim maksimum dan diskon per tingkat running meter. Diskon negatif menaikkan harga. Di bawah 300 running meter tidak ada harga.',
        'Supplier Corrugated Index' => 'Index Lembar Gelombang Supplier',
        'Corrugated sheet index per supplier and substance by effective date. Index = price per square metre of the trimmed sheet. Use SW or DW as a generic substance. The material name decides which substance is used.' => 'Index lembar gelombang per supplier dan substance menurut tanggal berlaku. Index = harga per meter persegi lembar trim. Pakai SW atau DW sebagai substance umum. Nama bahan menentukan substance yang dipakai.',
        'Grade' => 'Grade',
        'Gsm from' => 'Gsm dari',
        'Gsm to' => 'Gsm sampai',
        'Formula' => 'Rumus',
        'Reference sheet' => 'Referensi sheet',
        'Weight' => 'Berat',
        'Index' => 'Index',
        'APKey %' => 'APKey %',
        'Max trim width (cm)' => 'Lebar trim maks (cm)',
        'Discount from 300 RM %' => 'Diskon mulai 300 RM %',
        'Discount from 500 RM %' => 'Diskon mulai 500 RM %',
        'Discount from 1000 RM %' => 'Diskon mulai 1000 RM %',
        'Discount from 2000 RM %' => 'Diskon mulai 2000 RM %',
        'Discount from 5000 RM %' => 'Diskon mulai 5000 RM %',
        'A price already exists for this supplier, grade, gsm and date.' => 'Harga untuk supplier, grade, gsm, dan tanggal ini sudah ada.',
        'Gsm to must not be less than gsm from.' => 'Gsm sampai tidak boleh lebih kecil dari gsm dari.',
        'A rule already exists for this supplier and date.' => 'Aturan untuk supplier dan tanggal ini sudah ada.',
        'An index already exists for this supplier, substance and date.' => 'Index untuk supplier, substance, dan tanggal ini sudah ada.',
    ];

    public function up(): void
    {
        $mst = Schema::connection('tm2mst');

        $mst->create('sup_duplex_price', function (Blueprint $table) {
            $table->id('id_duplex_price');
            $table->unsignedBigInteger('supplier_id');
            $table->string('grade', 20)->nullable()->comment('DP, DA, MSE ...; kosong = semua grade');
            $table->decimal('gsm_from', 8, 2);
            $table->decimal('gsm_to', 8, 2);
            $table->string('formula', 12)->comment('REF_REAM | WEIGHT');
            $table->decimal('index_value', 18, 4);
            $table->decimal('apkey_pct', 10, 4)->comment('persen, 500 = dikali 5,00');
            $table->string('price_basis', 4)->default('EXCL')->comment('INCL | EXCL PPN');
            $table->date('effective_date');
            $table->boolean('activate')->default(true);
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            $table->foreign('supplier_id')->references('id_supplier')->on('sup_supplier');
            $table->index(['supplier_id', 'effective_date'], 'ix_duplex_price');
        });

        $mst->create('sup_corrugated_rule', function (Blueprint $table) {
            $table->id('id_corr_rule');
            $table->unsignedBigInteger('supplier_id');
            $table->decimal('max_trim_width_cm', 8, 2)->comment('UJK 180, RCA 220');
            $table->decimal('disc_300', 8, 4)->nullable()->comment('persen; negatif = harga naik');
            $table->decimal('disc_500', 8, 4)->nullable();
            $table->decimal('disc_1000', 8, 4)->nullable();
            $table->decimal('disc_2000', 8, 4)->nullable();
            $table->decimal('disc_5000', 8, 4)->nullable();
            $table->date('effective_date');
            $table->boolean('activate')->default(true);
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            $table->foreign('supplier_id')->references('id_supplier')->on('sup_supplier');
            $table->unique(['supplier_id', 'effective_date'], 'uq_corr_rule');
        });

        $mst->create('sup_corrugated_index', function (Blueprint $table) {
            $table->id('id_corr_index');
            $table->unsignedBigInteger('supplier_id');
            $table->string('substance_name', 120)->comment('mis. K150/M125/K125, SDW K125/M125/K125, atau umum SW / DW');
            $table->decimal('index_per_m2', 18, 4);
            $table->string('price_basis', 4)->default('INCL')->comment('INCL | EXCL PPN');
            $table->date('effective_date');
            $table->boolean('activate')->default(true);
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            $table->foreign('supplier_id')->references('id_supplier')->on('sup_supplier');
            $table->unique(['supplier_id', 'substance_name', 'effective_date'], 'uq_corr_index');
        });

        $sys = DB::connection('tm2sys');
        foreach (self::MENUS as [$code, $label, $icon, $target, $sort]) {
            if (! $sys->table('sys_app_menu')->where('code', $code)->exists()) {
                $sys->table('sys_app_menu')->insert(['parent_id' => 5, 'code' => $code, 'label' => $label, 'icon' => $icon, 'menu_type' => 'item', 'object_type' => 'grid',
                    'target' => $target, 'sort_order' => $sort, 'activate' => 1, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)',
                    'status_updated_at' => now(), 'created_at' => now()]);
            }
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
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', array_column(self::MENUS, 0))->delete();
        foreach (['sup_corrugated_index', 'sup_corrugated_rule', 'sup_duplex_price'] as $t) {
            Schema::connection('tm2mst')->dropIfExists($t);
        }
    }
};
