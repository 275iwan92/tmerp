<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Auto-jurnal Purchase Receive + Stock Out (2026-08-22, riset ERP besar
// Odoo/SAP/D365/Oracle -- semua pakai akun clearing "GR/IR" antara barang
// diterima & invoice supplier resmi masuk, krn Purchase Invoice belum
// dibangun di sini). COA yang sudah diseed (FinanceMasterSeeder) TIDAK punya
// akun GR/IR sama sekali -- ini akun teknis (bukan keputusan bisnis), aman
// ditambah tanpa perlu review kategori spt akun HPP per-item.
// Jurnal "Pemakaian" (Stock Out) juga belum ada -- 5 jurnal lama semua utk
// kas/bank/jual/beli/umum, tak ada utk konsumsi internal bahan baku.
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2acc');
        $now = now();

        if (! $db->table('fin_account')->where('account_code', '211900')->exists()) {
            $maxId = (int) $db->table('fin_account')->max('account_id');
            $db->table('fin_account')->insert([
                'account_id' => $maxId + 1,
                'account_code' => '211900',
                'account_name' => 'Hutang Barang Belum Ditagih (GR/IR Clearing)',
                'account_type' => 'LIABILITY',
                'normal_balance' => 'CREDIT',
                'parent_id' => null,
                'is_recon' => 0,
                'is_postable' => 1,
                'is_active' => 1,
                'created_by' => '_system',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! $db->table('fin_journal')->where('journal_code', 'USG')->exists()) {
            $db->table('fin_journal')->insert([
                'journal_code' => 'USG',
                'journal_name' => 'Jurnal Pemakaian Bahan Baku',
                'journal_type' => 'GENERAL',
                'is_active' => 1,
                'created_by' => '_system',
                'created_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $db = DB::connection('tm2acc');
        $db->table('fin_journal')->where('journal_code', 'USG')->delete();
        $db->table('fin_account')->where('account_code', '211900')->delete();
    }
};
