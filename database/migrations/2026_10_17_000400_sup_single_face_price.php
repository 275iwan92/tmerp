<?php

/*
 * REMARK
 * Tujuan       : Tabel harga Single Face per supplier (sup_single_face_price) + item menu Master Data > Supplier Single Face Price + kamus Indonesia (Jo 2026-10-17).
 * Sumber hulu  : Keputusan Jo 2026-10-17: harga per lembar = index x panjang (m) x lebar (m) / (1 + PPN/100); index per supplier + tanggal berlaku, diisi di layar.
 * Input        : -
 * Output       : tm2mst.sup_single_face_price (kosong); satu baris sys_app_menu (MD_SF_PRICE); baris sys_dictionary.
 * Dipakai oleh : PaperPriceEngine (cabang Single Face), SupplierSingleFacePriceLookup (v2:md-supplier-single-face-price).
 * Status       : Jalankan di ddt* dan tm2* (paritas Jo 2026-10-16): php artisan migrate --path=database/migrations/2026_10_17_000400_sup_single_face_price.php [--env=mockup].
 */

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CODE = 'MD_SF_PRICE';

    private const UI = [
        'Supplier Single Face Price' => 'Harga Single Face Supplier',
        'Single Face price per supplier, versioned by effective date like Exchange Rate. Index = price per square metre. Price per sheet = index x length (m) x width (m), divided by (1 + PPN/100) when the price includes PPN.' => 'Harga Single Face per supplier, berversi menurut tanggal berlaku seperti Kurs. Index = harga per meter persegi. Harga per lembar = index x panjang (m) x lebar (m), dibagi (1 + PPN/100) bila harga sudah termasuk PPN.',
        'Substance' => 'Substance',
        'Flute' => 'Flute',
        'Index / m2' => 'Index / m2',
        'A price already exists for this supplier, substance, flute and date.' => 'Harga untuk supplier, substance, flute, dan tanggal ini sudah ada.',
    ];

    public function up(): void
    {
        Schema::connection('tm2mst')->create('sup_single_face_price', function (Blueprint $table) {
            $table->id('id_sf_price');
            $table->unsignedBigInteger('supplier_id');
            $table->string('substance_name', 100)->comment('mis. SF M 125 / K 125, dari mat_sheet_rate.substance_name');
            $table->string('flute_type', 10)->comment('E/F, B/F, C/F, BC/F');
            $table->decimal('index_per_m2', 18, 4);
            $table->string('price_basis', 4)->default('INCL')->comment('INCL | EXCL PPN');
            $table->date('effective_date');
            $table->boolean('activate')->default(true);
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            $table->foreign('supplier_id')->references('id_supplier')->on('sup_supplier');
            $table->unique(['supplier_id', 'substance_name', 'flute_type', 'effective_date'], 'uq_sf_price');
        });

        $sys = DB::connection('tm2sys');
        if (! $sys->table('sys_app_menu')->where('code', self::CODE)->exists()) {
            $sys->table('sys_app_menu')->insert(['parent_id' => 5, 'code' => self::CODE, 'label' => 'Supplier Single Face Price', 'icon' => 'fa-solid fa-layer-group',
                'menu_type' => 'item', 'object_type' => 'grid', 'target' => 'v2:md-supplier-single-face-price', 'sort_order' => 49, 'activate' => 1,
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
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', self::CODE)->delete();
        Schema::connection('tm2mst')->dropIfExists('sup_single_face_price');
    }
};
