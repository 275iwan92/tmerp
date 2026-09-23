<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Keputusan user 2026-09-23: bank = vendor, jadi masuk Master Supplier dengan
// kategori BANK (kategori sudah ada di sup_category). Rekening pegawai memilih
// bank lewat lookup supplier BANK; di pegawai cuma No. Rekening + Atas Nama.
// Vendor pengiriman (truk angkut produk) juga supplier -> kategori EKSPEDISI.
return new class extends Migration
{
    // Nama bank yang SUDAH dipakai di emp_bank_account (data nyata), bukan karangan.
    // Kode diberi awalan BANK- karena "MANDIRI " sudah dipakai supplier lain
    // (MANDIRI SURABAYA CALON, kategori Jahit Kawat) dan MySQL menganggap sama.
    private const BANKS = ['BCA', 'MANDIRI', 'BRI', 'BNI', 'SEABANK', 'BTN'];

    public function up(): void
    {
        $mst = DB::connection('tm2mst');

        $mst->table('sup_category')->updateOrInsert(
            ['category_code' => 'EKSPEDISI'],
            ['description' => 'Vendor pengiriman / angkutan (truk)', 'activate' => 1, 'data_origin' => 'LIVE',
                'created_by' => 'migration', 'updated_by' => 'migration', 'created_at' => now(), 'updated_at' => now()]
        );

        foreach (self::BANKS as $bank) {
            if (! $mst->table('sup_supplier')->where('supplier_code', 'BANK-'.$bank)->exists()) {
                $mst->table('sup_supplier')->insert([
                    'supplier_code' => 'BANK-'.$bank, 'supplier_name' => $bank, 'category' => 'BANK',
                    'is_subcon' => 0, 'activate' => 1, 'data_origin' => 'LIVE',
                    'note' => 'Dibuat otomatis dari nama bank rekening pegawai (2026-09-23); lengkapi data di Master Supplier.',
                    'created_by' => 'migration', 'updated_by' => 'migration', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        Schema::connection('tm2mst')->table('emp_bank_account', function (Blueprint $t) {
            $t->unsignedBigInteger('bank_supplier_id')->nullable()->after('employee_id');
            $t->foreign('bank_supplier_id')->references('id_supplier')->on('sup_supplier');
        });

        DB::connection('tm2mst')->statement("
            UPDATE emp_bank_account e
            JOIN sup_supplier s ON s.category = 'BANK' AND s.supplier_name = TRIM(e.bank_name)
            SET e.bank_supplier_id = s.id_supplier
        ");

        $sys = DB::connection('tm2sys');
        if (! $sys->table('sys_app_menu')->where('code', 'MD_SUPCAT')->exists()) {
            $sys->table('sys_app_menu')->insert([
                'id' => (int) $sys->table('sys_app_menu')->max('id') + 1,
                'parent_id' => $sys->table('sys_app_menu')->where('code', 'MD_SUPP')->value('parent_id'),
                'code' => 'MD_SUPCAT', 'label' => 'Master Kategori Supplier',
                'menu_type' => 'item', 'object_type' => 'form', 'target_kind' => 'app', 'sort_order' => 25,
                'activate' => true, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude_code',
                'status_updated_at' => now(), 'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'MD_SUPCAT')->delete();
        Schema::connection('tm2mst')->table('emp_bank_account', function (Blueprint $t) {
            $t->dropForeign(['bank_supplier_id']);
            $t->dropColumn('bank_supplier_id');
        });
        DB::connection('tm2mst')->table('sup_supplier')->whereIn('supplier_code', array_map(fn ($b) => 'BANK-'.$b, self::BANKS))->delete();
        DB::connection('tm2mst')->table('sup_category')->where('category_code', 'EKSPEDISI')->delete();
    }
};
