<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Lengkapi akun (COA) bahan supaya Purchase Requisition dari BOM ProDev tidak
// tertahan (instruksi user 2026-09-18: "buatkan COA nya, beri COA yang jelas.
// Kalau harus di tambahkan sebagai sub ledger, lakukan saja").
//
// Temuan 2026-09-17: 3.958 item berkategori "Others", 34 "Lamination", dan
// 5.506 item TANPA kategori tidak punya akun persediaan/HPP, sehingga
// pembelian plastik laminasi, foil, tali, dan wrapping tidak bisa dibuat PR.
//
// Yang dikerjakan:
// 1. 3 akun baru (sub-ledger di bawah induk yang sudah ada):
//      113600 Persediaan Bahan Pembantu & Kemasan   (lem, film, plastik, tali, foil)
//      113700 Persediaan Suku Cadang & Perlengkapan Pabrik
//      517000 HPP - Bahan Pembantu & Kemasan
//      616000 Beban Perlengkapan, Suku Cadang & Bahan Bakar
//    Plate CTP memakai akun yang SUDAH ADA (113300 tooling, 613000 amortisasi
//    pelat) -- tidak dibuat akun kembar.
// 2. Kategori bahan level 1 yang dipakai data tapi belum ada: Lamination, Others.
// 3. Akun default tiap kategori level 1 dilengkapi.
// 4. Kolom akun di mat_raw diisi mengikuti kategorinya (item tanpa kategori
//    ikut "Others"), supaya transaksi lama pun punya akun yang jelas.
return new class extends Migration
{
    // code => [id, nama, tipe, saldo normal, induk]. account_id BUKAN auto-increment
    // dan tidak boleh diturunkan dari kode (113 sudah dipakai Bank Mandiri).
    private const ACCOUNTS = [
        '113600' => [136, 'Persediaan Bahan Pembantu & Kemasan', 'ASSET', 'DEBIT', '110000'],
        '113700' => [137, 'Persediaan Suku Cadang & Perlengkapan Pabrik', 'ASSET', 'DEBIT', '110000'],
        '517000' => [517, 'HPP - Bahan Pembantu & Kemasan', 'COGS', 'DEBIT', '500000'],
        '616000' => [616, 'Beban Perlengkapan, Suku Cadang & Bahan Bakar', 'EXPENSE', 'DEBIT', '600000'],
    ];

    // kategori level 1 => [akun HPP/beban, akun persediaan, stock_class]
    private const CATEGORY_ACCOUNTS = [
        'Paper' => ['511000', '113100', 'RAW'],
        'Sheet' => ['511000', '113100', 'RAW'],
        'Ink' => ['512000', '113200', 'RAW'],
        'Chemical Liquid' => ['512000', '113200', 'RAW'],
        'Chemical Powder' => ['512000', '113200', 'RAW'],
        'Glue' => ['517000', '113600', 'RAW'],
        'Lamination' => ['517000', '113600', 'RAW'],
        'Plastik' => ['517000', '113600', 'RAW'],
        'Pack Material' => ['517000', '113600', 'RAW'],
        'Solasi' => ['517000', '113600', 'RAW'],
        'Others' => ['517000', '113600', 'RAW'],
        'CTP Plate' => ['613000', '113300', 'RAW'],
        'Stationery / ATK' => ['616000', '113700', 'INDIRECT'],
        'Spare Part Vehicle' => ['616000', '113700', 'INDIRECT'],
        'Spare Part Machine' => ['616000', '113700', 'INDIRECT'],
        'Tools / Equipment' => ['616000', '113700', 'INDIRECT'],
        'Laptop/Desktop/Networking' => ['616000', '113700', 'INDIRECT'],
        'Peralatan Kebersihan' => ['616000', '113700', 'INDIRECT'],
        'Gasoline' => ['616000', '113700', 'INDIRECT'],
        'Jasa/ Service' => ['513000', null, 'SERVICE'],
    ];

    // Kategori level 1 baru: [nama, prefix]
    private const NEW_CATEGORIES = [
        ['Lamination', 'F'],
        ['Others', 'O'],
    ];

    public function up(): void
    {
        $acc = DB::connection('tm2acc');
        $mst = DB::connection('tm2mst');
        $now = now();

        foreach (self::ACCOUNTS as $code => [$id, $name, $type, $balance, $parentCode]) {
            if ($acc->table('fin_account')->where('account_code', $code)->exists()) {
                continue;
            }

            $acc->table('fin_account')->insert([
                'account_id' => $id,
                'account_code' => $code,
                'account_name' => $name,
                'account_type' => $type,
                'normal_balance' => $balance,
                'parent_id' => $acc->table('fin_account')->where('account_code', $parentCode)->value('account_id'),
                'is_recon' => 0,
                'is_postable' => 1,
                'activate' => 1,
                'created_at' => $now, 'updated_at' => $now,
                'created_by' => 'claude_code', 'updated_by' => 'claude_code',
            ]);
        }

        $ids = $acc->table('fin_account')->pluck('account_id', 'account_code');
        $grIr = $ids['211900'] ?? null;

        foreach (self::NEW_CATEGORIES as [$name, $prefix]) {
            if ($mst->table('mat_category')->where('level', 1)->where('category_name', $name)->exists()) {
                continue;
            }

            $mst->table('mat_category')->insert([
                'parent_id' => null, 'level' => 1, 'category_name' => $name, 'prefix_char' => $prefix,
                'note' => 'Ditambahkan 2026-09-18: kategori ini sudah dipakai data tapi belum ada di master',
                'stock_class' => 'RAW', 'activate' => 1,
                'created_at' => $now, 'updated_at' => $now,
                'created_by' => 'claude_code', 'updated_by' => 'claude_code',
            ]);
        }

        foreach (self::CATEGORY_ACCOUNTS as $name => [$coa, $stock, $class]) {
            $mst->table('mat_category')->where('level', 1)->where('category_name', $name)->update([
                'default_coa_account_id' => $ids[$coa] ?? null,
                'default_stock_asset_account_id' => $stock ? ($ids[$stock] ?? null) : null,
                'default_gr_ir_account_id' => $grIr,
                'stock_class' => $class,
                'updated_at' => $now, 'updated_by' => 'claude_code',
            ]);
        }

        // Item bahan: isi akun yang masih kosong dari kategorinya; item tanpa
        // kategori mengikuti "Others" (bahan pembantu) supaya tetap bisa dibeli.
        $fallback = self::CATEGORY_ACCOUNTS['Others'];

        foreach (self::CATEGORY_ACCOUNTS as $name => [$coa, $stock, $class]) {
            $this->fillAccounts($mst, $name, $ids[$coa] ?? null, $stock ? ($ids[$stock] ?? null) : null, $grIr);
        }

        $this->fillAccounts($mst, null, $ids[$fallback[0]] ?? null, $ids[$fallback[1]] ?? null, $grIr);
    }

    private function fillAccounts($mst, ?string $category, ?int $coa, ?int $stock, ?int $grIr): void
    {
        $base = fn () => $category === null
            ? $mst->table('mat_raw')->where(fn ($q) => $q->whereNull('category1')->orWhere('category1', ''))
            : $mst->table('mat_raw')->where('category1', $category);

        $base()->whereNull('cogs_account_id')->update(['cogs_account_id' => $coa, 'update_by' => 'claude_code']);

        if ($stock) {
            $base()->whereNull('stock_asset_account_id')->update(['stock_asset_account_id' => $stock, 'update_by' => 'claude_code']);
        }

        $base()->whereNull('gr_ir_account_id')->update(['gr_ir_account_id' => $grIr, 'update_by' => 'claude_code']);
    }

    public function down(): void
    {
        $acc = DB::connection('tm2acc');
        $mst = DB::connection('tm2mst');
        $ids = $acc->table('fin_account')->whereIn('account_code', array_keys(self::ACCOUNTS))->pluck('account_id');

        // Akun yang sudah dipakai transaksi tidak dihapus -- cukup kosongkan
        // rujukannya di master bahan & kategori.
        $mst->table('mat_raw')->whereIn('cogs_account_id', $ids)->update(['cogs_account_id' => null]);
        $mst->table('mat_raw')->whereIn('stock_asset_account_id', $ids)->update(['stock_asset_account_id' => null]);
        $mst->table('mat_category')->whereIn('default_coa_account_id', $ids)->update(['default_coa_account_id' => null]);
        $mst->table('mat_category')->whereIn('default_stock_asset_account_id', $ids)->update(['default_stock_asset_account_id' => null]);
        $mst->table('mat_category')->where('created_by', 'claude_code')->where('level', 1)
            ->whereIn('category_name', array_column(self::NEW_CATEGORIES, 0))->delete();
        $acc->table('fin_account')->whereIn('account_code', array_keys(self::ACCOUNTS))->delete();
    }
};
