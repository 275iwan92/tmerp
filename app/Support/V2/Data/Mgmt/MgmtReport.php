<?php

declare(strict_types=1);

/* REMARK
 * Tujuan       : Dasar bersama laporan Management Reports v2 (Penjualan, Pembelian, Customer 360, Akuntansi, Produksi, SDM, Gudang):
 *                tahun yang dipilih, daftar tahun, nama bulan, dan bentuk baris badge / tautan yang dibaca objek DevLib.
 * Sumber hulu  : App\Services\Report\ManagementReportService (angka dan aturan sama dengan layar v1 pages::report.*).
 * Input        : query GET layar (th, dan filter tiap laporan).
 * Output       : array siap pakai x-dl::kpi, x-dl::chart, x-dl::table, x-dl::progress.
 * Dipakai oleh : turunan di App\Support\V2\Data\Mgmt, layar v2 mgmt-sales, mgmt-purchase, mgmt-customer, mgmt-accounting, mgmt-production, mgmt-hr, mgmt-warehouse.
 * Status       : Aktif (T9 Management Reports, 2026-10-20).
 */

namespace App\Support\V2\Data\Mgmt;

use App\Services\Report\ManagementReportService;
use App\Support\Localization\Dictionary;

abstract class MgmtReport
{
    /** Nama bulan 3 huruf mengikuti bahasa tampilan. */
    public static function months(): array
    {
        return array_map(fn (string $m) => Dictionary::t($m, 'date.month_short'), ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']);
    }

    protected static function svc(): ManagementReportService
    {
        return app(ManagementReportService::class);
    }

    /** @return list<int> */
    public static function years(): array
    {
        return self::svc()->availableYears();
    }

    /** Tahun dari query; kosong = tahun terbaru yang punya data (sama dengan mount() v1). */
    public static function pickYear(?int $year): int
    {
        return $year ?: (int) (self::years()[0] ?? now()->year);
    }

    /** Tautan di sel x-dl::table: sel berisi ['href', 'text']; teks dibungkus raw() karena nilai data. */
    protected static function link(string $url, string $text): array
    {
        return ['href' => $url, 'text' => raw($text)];
    }

    /** Daftar tahun untuk pilihan: [2025 => 2025]. */
    public static function yearOptions(): array
    {
        return array_combine(self::years(), self::years());
    }
}
