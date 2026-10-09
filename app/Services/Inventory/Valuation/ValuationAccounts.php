<?php

declare(strict_types=1);

namespace App\Services\Inventory\Valuation;

use App\Models\Master\MatCategory;
use App\Models\Master\MatRaw;
use App\Models\Sys\SysConstant;
use App\Services\Acc\JournalPostingService;
use Illuminate\Support\Facades\DB;

// Akun jurnal persediaan. Akun khusus valuasi dibaca dari konstanta (Settings > Constants, nilainya KODE akun), akun persediaan / HPP
// per bahan memakai aturan yang sama dengan PvPostingService dan StockPostingService (item, lalu default kategori level 1).
final class ValuationAccounts
{
    public static function id(string $constantKey): int
    {
        $code = trim((string) SysConstant::text($constantKey, ''));
        $id = $code === '' ? null : DB::connection('tm2acc')->table('fin_account')->where('account_code', $code)->where('activate', 1)->value('account_id');
        if (! $id) {
            throw new ValuationRefused(tf('The account for :key is not set or not found (Settings > Constants).', ['key' => $constantKey]));
        }

        return (int) $id;
    }

    /** @return list<string> kode akun dari konstanta berdaftar koma */
    public static function codes(string $constantKey): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) SysConstant::text($constantKey, '')))));
    }

    /** @return list<int> */
    public static function ids(string $constantKey): array
    {
        $codes = self::codes($constantKey);

        return $codes ? DB::connection('tm2acc')->table('fin_account')->whereIn('account_code', $codes)->pluck('account_id')->map(fn ($v) => (int) $v)->all() : [];
    }

    public static function stockFor(int $materialId): ?int
    {
        [$material, $category] = self::material($materialId);

        return app(JournalPostingService::class)->resolveAccount($material?->stock_asset_account_id, $category?->default_stock_asset_account_id);
    }

    public static function cogsFor(int $materialId): ?int
    {
        [$material, $category] = self::material($materialId);

        return app(JournalPostingService::class)->resolveAccount($material?->cogs_account_id, $category?->default_coa_account_id);
    }

    /** @return array{0: ?MatRaw, 1: ?MatCategory} */
    private static function material(int $materialId): array
    {
        $material = MatRaw::find($materialId);

        return [$material, $material ? MatCategory::level(1)->where('category_name', $material->category1)->first() : null];
    }
}
