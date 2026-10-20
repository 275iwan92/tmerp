<?php

declare(strict_types=1);

/* REMARK
 * Tujuan       : Management Reports > Sales Details (v2): nilai periode, daftar dokumen (CO / Surat Jalan), peringkat customer dan per salesperson.
 * Sumber hulu  : co_order_legacy, co_delivery_legacy (tm2sdi), cust_customer (tm2mst), ManagementReportService (peringkat); aturan sama dengan layar v1 pages::report.sales.
 * Input        : th (tahun), bln (bulan), basis (order | shipment), q (cari nomor / customer), tab (docs | customer | sales), page.
 * Output       : angka KPI, baris tabel halaman 25 baris, deret grafik batang.
 * Dipakai oleh : layar v2 mgmt-sales.
 * Status       : Aktif (T9 Management Reports, 2026-10-20).
 */

namespace App\Support\V2\Data\Mgmt;

use Devlib\Locale;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class SalesDetail extends MgmtReport
{
    public const BASES = ['order' => 'Incoming PO (Customer Order)', 'shipment' => 'Shipment (Delivery Note)'];

    public const TABS = ['docs' => 'Document List', 'customer' => 'Customer Ranking', 'sales' => 'Per Salesperson'];

    public const PER_PAGE = 25;

    public readonly int $year;

    public readonly ?int $month;

    public readonly string $basis;

    public readonly string $tab;

    public readonly string $search;

    public readonly int $page;

    public function __construct(array $q)
    {
        $this->year = self::pickYear(isset($q['th']) ? (int) $q['th'] : null);
        $this->month = ! empty($q['bln']) ? (int) $q['bln'] : null;
        $this->basis = array_key_exists((string) ($q['basis'] ?? ''), self::BASES) ? (string) $q['basis'] : 'order';
        $tab = ($q['tab'] ?? '') === 'dokumen' ? 'docs' : (string) ($q['tab'] ?? '');  // v1 memakai 'dokumen'
        $this->tab = array_key_exists($tab, self::TABS) ? $tab : 'docs';
        $this->search = trim((string) ($q['q'] ?? ''));
        $this->page = max(1, (int) ($q['page'] ?? 1));
    }

    /** @return array<int, string> */
    public static function monthOptions(): array
    {
        return array_combine(range(1, 12), array_map('t', ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']));
    }

    /** Tabel, kolom tanggal, kolom nomor sesuai dasar omset. */
    private function source(): array
    {
        return $this->basis === 'shipment' ? ['co_delivery_legacy', 'dnote_date', 'legacy_dnote_no'] : ['co_order_legacy', 'co_date', 'legacy_co_no'];
    }

    /** @return array{docs: int, value: float} */
    public function periodTotal(): array
    {
        [$table, $dateCol] = $this->source();
        $r = DB::connection('tm2sdi')->table($table)->whereYear($dateCol, $this->year)
            ->when($this->month, fn ($q) => $q->whereMonth($dateCol, $this->month))
            ->selectRaw('COUNT(*) docs, COALESCE(SUM(grand_total),0) v')->first();

        return ['docs' => (int) $r->docs, 'value' => (float) $r->v];
    }

    public function documents(): LengthAwarePaginator
    {
        [$table, $dateCol, $noCol] = $this->source();

        $q = DB::connection('tm2sdi')->table($table.' as d')
            ->whereYear("d.$dateCol", $this->year)
            ->when($this->month, fn ($qq) => $qq->whereMonth("d.$dateCol", $this->month))
            ->when($this->search !== '', function ($qq) use ($noCol) {
                $term = '%'.$this->search.'%';
                $ids = DB::connection('tm2mst')->table('cust_customer')->where('customer_name', 'like', $term)->pluck('id_customer');

                return $qq->where(fn ($w) => $w->where("d.$noCol", 'like', $term)->orWhereIn('d.customer_id', $ids));
            })
            ->orderByDesc("d.$dateCol")
            ->select('d.*', DB::raw("d.$noCol as doc_no"), DB::raw("d.$dateCol as doc_date"));

        return $q->paginate(self::PER_PAGE, ['*'], 'page', $this->page);
    }

    /** Baris tabel dokumen untuk x-dl::table (kolom customer / telusuri raw). */
    public function documentRows(LengthAwarePaginator $docs): array
    {
        $ids = collect($docs->items())->pluck('customer_id')->filter()->unique();
        $names = $ids->isEmpty() ? [] : DB::connection('tm2mst')->table('cust_customer')->whereIn('id_customer', $ids)->pluck('customer_name', 'id_customer')->all();

        return array_map(function ($d) use ($names) {
            $url = $d->customer_id ? route('v2.screen', 'mgmt-customer').'?customer='.$d->customer_id.'&th='.$this->year : null;

            return ['no' => raw($d->doc_no), 'date' => Locale::date($d->doc_date), 'customer' => $url ? self::link($url, $names[$d->customer_id] ?? t('(unknown)')) : '—',
                'status' => raw($d->doc_status_normalized ?? '—'), 'value' => Locale::money($d->grand_total), 'trace' => $url ? self::link($url, t('trace').' →') : ''];
        }, $docs->items());
    }

    /** @return list<array<string, mixed>> */
    public function customerRanking(): array
    {
        return self::svc()->topCustomers($this->year, $this->basis, 30);
    }

    /** Baris tabel peringkat customer; nama customer menembus ke Customer 360. */
    public function customerRankRows(array $rank): array
    {
        return array_map(fn ($c, $i) => ['rank' => $i + 1, 'name' => self::link(route('v2.screen', 'mgmt-customer').'?customer='.$c['id'].'&th='.$this->year, (string) $c['name']),
            'docs' => Locale::number($c['docs']), 'value' => Locale::money($c['value'])], $rank, array_keys($rank));
    }

    /** @return list<array<string, mixed>> */
    public function salesRanking(): array
    {
        return self::svc()->salesPersonPerformance($this->year);
    }

    /** Alamat layar ini dengan query disesuaikan (untuk tab dan halaman). */
    public function url(array $over = []): string
    {
        $q = array_filter(['th' => $this->year, 'bln' => $this->month, 'basis' => $this->basis, 'tab' => $this->tab, 'q' => $this->search ?: null, 'page' => $this->page > 1 ? $this->page : null], fn ($v) => $v !== null && $v !== '');

        return route('v2.screen', 'mgmt-sales').'?'.http_build_query(array_filter($over + $q, fn ($v) => $v !== null && $v !== ''));
    }
}
