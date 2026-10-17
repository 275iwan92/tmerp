<?php

/*
 * REMARK
 * Tujuan       : Tabel harga kertas per supplier (sup_paper_price) + item menu Master Data > Supplier Paper Price + kamus Indonesia; isi awal harga ivory CV CAHAYA MAJU (Jo 2026-10-17).
 * Sumber hulu  : Keputusan Jo 2026-10-17 (rumus harga rim ivory); sup_supplier, mat_grammage.
 * Input        : -
 * Output       : tm2mst.sup_paper_price; satu baris sys_app_menu (MD_PAPER_PRICE); baris sys_dictionary; satu baris harga CAHAYA MAJU bila suppliernya ada.
 * Dipakai oleh : PaperPriceEngine, SupplierPaperPriceLookup (v2:md-supplier-paper-price).
 * Status       : Jalankan di ddt* dan tm2* (paritas Jo 2026-10-16): php artisan migrate --path=database/migrations/2026_10_17_000100_sup_paper_price.php [--env=mockup].
 */

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CODE = 'MD_PAPER_PRICE';

    private const UI = [
        'Supplier Paper Price' => 'Harga Kertas Supplier',
        'Paper price per supplier, versioned by effective date like Exchange Rate. Roll price per kg or ton; ream price for standard sheet sizes. An empty grammage applies to all grammages. Price basis: whether the price already includes PPN.' => 'Harga kertas per supplier, berversi menurut tanggal berlaku seperti Kurs. Harga roll per kg atau ton; harga ream untuk ukuran standar. Gramasi kosong berlaku untuk semua gramasi. Dasar harga: apakah harga sudah termasuk PPN.',
        'Paper kind' => 'Jenis kertas',
        'Roll price' => 'Harga roll',
        'Cut cost / kg' => 'Biaya potong / kg',
        'Ream price / kg' => 'Harga ream / kg',
        'Price basis' => 'Dasar harga',
        'Incl PPN' => 'Termasuk PPN',
        'Excl PPN' => 'Belum termasuk PPN',
        'Fill the roll price or the ream price.' => 'Isi harga roll atau harga ream.',
    ];

    public function up(): void
    {
        Schema::connection('tm2mst')->create('sup_paper_price', function (Blueprint $table) {
            $table->id('id_paper_price');
            $table->unsignedBigInteger('supplier_id');
            $table->string('paper_kind', 30)->comment('IVORY, DUPLEX ...: dicocokkan dengan nama / kategori bahan');
            $table->unsignedBigInteger('grammage_id')->nullable()->comment('NULL = berlaku untuk semua gramasi');
            $table->decimal('roll_price', 18, 4)->nullable()->comment('harga roll per roll_price_unit');
            $table->string('roll_price_unit', 3)->default('KG')->comment('KG | TON');
            $table->decimal('cut_cost_per_kg', 18, 4)->default(0);
            $table->decimal('sheet_price_per_kg', 18, 4)->nullable()->comment('harga ream ukuran standar per kg');
            $table->string('price_basis', 4)->default('INCL')->comment('INCL | EXCL PPN');
            $table->date('effective_date');
            $table->boolean('activate')->default(true);
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            $table->foreign('supplier_id')->references('id_supplier')->on('sup_supplier');
            $table->foreign('grammage_id')->references('id_grammage')->on('mat_grammage');
            $table->index(['supplier_id', 'paper_kind', 'effective_date'], 'ix_paper_price_lookup');
        });

        $sys = DB::connection('tm2sys');
        if (! $sys->table('sys_app_menu')->where('code', self::CODE)->exists()) {
            $sys->table('sys_app_menu')->insert(['parent_id' => 5, 'code' => self::CODE, 'label' => 'Supplier Paper Price', 'icon' => 'fa-solid fa-scroll',
                'menu_type' => 'item', 'object_type' => 'grid', 'target' => 'v2:md-supplier-paper-price', 'sort_order' => 48, 'activate' => 1,
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

        // Harga ivory CAHAYA MAJU per 17-Oct-2026 (Jo): roll 13.000/kg + potong 250/kg, ream ukuran standar 12.500/kg, semua incl PPN.
        $cm = DB::connection('tm2mst')->table('sup_supplier')->where('supplier_name', 'CV CAHAYA MAJU')->value('id_supplier');
        if ($cm) {
            DB::connection('tm2mst')->table('sup_paper_price')->insert(['supplier_id' => $cm, 'paper_kind' => 'IVORY', 'grammage_id' => null,
                'roll_price' => 13000, 'roll_price_unit' => 'KG', 'cut_cost_per_kg' => 250, 'sheet_price_per_kg' => 12500, 'price_basis' => 'INCL',
                'effective_date' => '2026-10-17', 'activate' => 1, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'migration', 'updated_by' => 'migration']);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', self::CODE)->delete();
        Schema::connection('tm2mst')->dropIfExists('sup_paper_price');
    }
};
