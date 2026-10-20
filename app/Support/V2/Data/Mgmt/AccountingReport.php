<?php

declare(strict_types=1);

/* REMARK
 * Tujuan       : Management Reports > Accounting & Finance (v2): akun COA aktif, voucher terposting, nilai posting bulanan dan supplier dengan utang terbesar.
 * Sumber hulu  : fin_account, fin_voucher, fin_voucher_line (tm2acc, disaring Visible::apply), ManagementReportService (utang usaha); aturan sama dengan layar v1 pages::report.accounting.
 * Input        : th (tahun; kosong = tahun berjalan).
 * Output       : angka KPI, deret grafik, baris tabel.
 * Dipakai oleh : layar v2 mgmt-accounting.
 * Status       : Aktif (T9 Management Reports, 2026-10-20).
 */

namespace App\Support\V2\Data\Mgmt;

use App\Support\DataOrigin\Visible;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;

final class AccountingReport extends MgmtReport
{
    public readonly int $year;

    public function __construct(array $q)
    {
        $this->year = ! empty($q['th']) ? (int) $q['th'] : (int) now()->year;
    }

    /** @return array<int, int> lima tahun terakhir untuk pilihan */
    public static function recentYears(): array
    {
        $y = range((int) now()->year, (int) now()->year - 4);

        return array_combine($y, $y);
    }

    public function coaActive(): int
    {
        return DB::connection('tm2acc')->table('fin_account')->where('activate', true)->count();
    }

    public function voucherStats(): array
    {
        $rows = Visible::apply(DB::connection('tm2acc')->table('fin_voucher'), 'tm2acc', 'fin_voucher')
            ->whereYear('posting_date', $this->year)->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        return ['posted' => (int) ($rows['POSTED'] ?? 0), 'draft' => (int) ($rows['DRAFT'] ?? 0), 'cancelled' => (int) ($rows['CANCELLED'] ?? 0)];
    }

    public function postedValue(): float
    {
        return (float) Visible::apply(DB::connection('tm2acc')->table('fin_voucher_line as l')->join('fin_voucher as v', 'v.voucher_id', '=', 'l.voucher_id'), 'tm2acc', 'fin_voucher', 'v')
            ->where('v.status', 'POSTED')->whereYear('v.posting_date', $this->year)->sum('l.debit');
    }

    public function payable(): array
    {
        return self::svc()->accountsPayable();
    }

    /** @return list<array<string, string>> baris tabel supplier */
    public function payableRows(): array
    {
        return array_map(fn ($s) => ['name' => raw($s['name']), 'outstanding' => Locale::money($s['outstanding'])], self::svc()->topPayableSuppliers(5));
    }

    /** @return list<float> nilai posting debit per bulan */
    public function monthlyPostedValue(): array
    {
        $rows = Visible::apply(DB::connection('tm2acc')->table('fin_voucher_line as l')->join('fin_voucher as v', 'v.voucher_id', '=', 'l.voucher_id'), 'tm2acc', 'fin_voucher', 'v')
            ->where('v.status', 'POSTED')->whereYear('v.posting_date', $this->year)->selectRaw('MONTH(v.posting_date) as m, SUM(l.debit) as total')->groupBy('m')->pluck('total', 'm');

        return collect(range(1, 12))->map(fn ($m) => (float) ($rows[$m] ?? 0))->all();
    }
}
