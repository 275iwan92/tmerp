<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Paritas ke 5 Excel HPP asli (KalkuHPP, dicocokkan 2026-09-18) -- lihat
// procost_formula_library.md §VIII.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sdi')->table('iqp_costing_price_tier', function (Blueprint $t) {
            $t->decimal('c_ppn_input_credit_pc', 18, 4)->nullable()->after('c_hpp_pc')
                ->comment('PPN masukan bahan/pc yg dikeluarkan dari DPP (kertas/sheet/laminasi sheet/SF EF ÷ PPN_INPUT_MATERIAL_DIVISOR). 0 utk NON_PPN');
        });
        // Excel & data legacy tidak pernah membulatkan -> default baru NONE. Baris lama tidak diubah.
        DB::connection('tm2sdi')->statement("ALTER TABLE iqp_costing_price_tier MODIFY rounding_mode VARCHAR(10) NOT NULL DEFAULT 'NONE'");

        $now = now();
        foreach ([
            ['PPN_INPUT_MATERIAL_DIVISOR', 1.1, 'Pembagi harga bahan yg dibeli sudah termasuk PPN (Excel HPP: kertas/sheet/laminasi sheet/SF EF ÷1,1) utk jalur INCLUDE PPN'],
            ['SHEET_MACHINE_WIDTH_CM_RCA', 220, 'Lebar mesin supplier RCA utk Info Harga Supplier (Excel OB COCO TOKYO blok HARGA RCA). Supplier lain pakai SHEET_MACHINE_WIDTH_CM'],
        ] as [$key, $num, $desc]) {
            DB::connection('tm2sys')->table('sys_constant')->updateOrInsert(['constant_key' => $key], [
                'category_code' => 'PRODUCTION', 'constant_group' => 'COSTING',
                'constant_number' => $num, 'constant_text' => null, 'description' => $desc,
                'activate' => true, 'created_by' => 'excel_hpp', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_constant')->whereIn('constant_key', ['PPN_INPUT_MATERIAL_DIVISOR', 'SHEET_MACHINE_WIDTH_CM_RCA'])->delete();
        DB::connection('tm2sdi')->statement("ALTER TABLE iqp_costing_price_tier MODIFY rounding_mode VARCHAR(10) NOT NULL DEFAULT 'NEAREST'");
        Schema::connection('tm2sdi')->table('iqp_costing_price_tier', fn (Blueprint $t) => $t->dropColumn('c_ppn_input_credit_pc'));
    }
};
