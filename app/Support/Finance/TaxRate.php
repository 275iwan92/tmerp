<?php

/*
 * REMARK
 * Tujuan       : Tarif pajak dokumen: PPN menurut tanggal berlaku (tabel fin_tax_rate, layar Tax Rate), PPh 23 dari konstanta.
 * Sumber hulu  : Master Data > Tax Rate (fin_tax_rate); Settings > Constants (PPN cadangan, PPH23_JASA).
 * Input        : Tanggal dokumen (kosong = hari ini).
 * Output       : Tarif dalam persen; tanggal sebelum baris pertama memakai baris pertama.
 * Dipakai oleh : QuoQuotation, CoOrder, CustomerInvoiceService, PoOrder, DeliveryOrderPrint, PaperPriceEngine.
 * Status       : Aktif; konstanta PPN hanya dipakai bila Tax Rate kosong.
 */

namespace App\Support\Finance;

use App\Models\Master\FinTaxRate;
use App\Models\Sys\SysConstant;
use Carbon\Carbon;
use Carbon\CarbonInterface;

final class TaxRate
{
    /** @var array<string, float> tarif per tanggal; dibaca sekali per request karena totals() dipanggil per baris grid */
    private static array $cache = [];

    // Tarif PPN (%) yang berlaku pada tanggal dokumen: baris aktif dengan tanggal berlaku terakhir <= tanggal.
    public static function ppn(CarbonInterface|string|null $on = null): float
    {
        $date = $on === null || $on === '' ? now()->toDateString() : Carbon::parse($on)->toDateString();

        return self::$cache[$date] ??= self::lookup('PPN', $date) ?? SysConstant::number('PPN', 11);
    }

    public static function pph23(): float
    {
        return once(fn () => SysConstant::number('PPH23_JASA', 2));
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    private static function lookup(string $code, string $date): ?float
    {
        $rows = FinTaxRate::query()->where('tax_code', $code)->where('activate', true);
        $rate = (clone $rows)->whereDate('effective_date', '<=', $date)->orderByDesc('effective_date')->value('rate')
            ?? (clone $rows)->orderBy('effective_date')->value('rate');

        return $rate === null ? null : (float) $rate;
    }
}
