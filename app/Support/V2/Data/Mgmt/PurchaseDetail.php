<?php

declare(strict_types=1);

/* REMARK
 * Tujuan       : Management Reports > Purchase Details (v2): KPI pembelian, tren bulanan, supplier terbesar, utang usaha (AP) dan daftar dokumen rantai PR - PO - Penerimaan - Invoice - Pembayaran.
 * Sumber hulu  : pr_requisition_legacy, po_order_legacy, po_receive_legacy, po_invoice_legacy, po_payment_legacy (tm2pur), sup_supplier (tm2mst), ManagementReportService; aturan sama dengan layar v1 pages::report.purchase.
 * Input        : th (tahun), q (cari nomor), tab (pr | po | receive | invoice | payment), page.
 * Output       : angka KPI, deret grafik, baris tabel halaman 25 baris.
 * Dipakai oleh : layar v2 mgmt-purchase.
 * Status       : Aktif (T9 Management Reports, 2026-10-20).
 */

namespace App\Support\V2\Data\Mgmt;

use Devlib\Locale;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class PurchaseDetail extends MgmtReport
{
    public const TABS = ['pr' => 'Requisitions (PR)', 'po' => 'Purchase Order', 'receive' => 'Goods Receipt', 'invoice' => 'Supplier Invoice', 'payment' => 'Payment'];

    public const PER_PAGE = 25;

    public readonly int $year;

    public readonly string $tab;

    public readonly string $search;

    public readonly int $page;

    public function __construct(array $q)
    {
        $this->year = self::pickYear(isset($q['th']) ? (int) $q['th'] : null);
        $this->tab = array_key_exists((string) ($q['tab'] ?? ''), self::TABS) ? (string) $q['tab'] : 'po';
        $this->search = trim((string) ($q['q'] ?? ''));
        $this->page = max(1, (int) ($q['page'] ?? 1));
    }

    public function summary(): array
    {
        return self::svc()->purchaseSummary($this->year);
    }

    /** @return list<float> nilai pembelian per bulan */
    public function monthlyValues(): array
    {
        return array_column(self::svc()->monthlyPurchase($this->year), 'value');
    }

    /** @return list<array<string, mixed>> */
    public function topSuppliers(): array
    {
        return self::svc()->topSuppliers($this->year, 10);
    }

    public function payable(): array
    {
        return self::svc()->accountsPayable();
    }

    /** @return list<array<string, mixed>> */
    public function payableSuppliers(): array
    {
        return self::svc()->topPayableSuppliers(12);
    }

    /** Invoice yang supplier-nya tak terpetakan: angka per supplier jadi tak utuh. */
    public function unmappedInvoices(): int
    {
        return DB::connection('tm2pur')->table('po_invoice_legacy')->whereNull('supplier_id')->count();
    }

    /** Tabel, kolom tanggal, kolom nomor, ada nilai? per tab. */
    private function tabSource(): array
    {
        return match ($this->tab) {
            'pr' => ['pr_requisition_legacy', 'pr_date', 'legacy_pr_no', false],
            'receive' => ['po_receive_legacy', 'prcv_date', 'legacy_prcv_no', false],
            'invoice' => ['po_invoice_legacy', 'pinv_date', 'legacy_pinv_no', true],
            'payment' => ['po_payment_legacy', 'ppym_date', 'legacy_ppym_no', true],
            default => ['po_order_legacy', 'po_date', 'legacy_po_no', true],
        };
    }

    public function documents(): LengthAwarePaginator
    {
        [$table, $dateCol, $noCol, $hasValue] = $this->tabSource();

        return DB::connection('tm2pur')->table($table)->whereYear($dateCol, $this->year)
            ->when($this->search !== '', fn ($q) => $q->where($noCol, 'like', '%'.$this->search.'%'))
            ->orderByDesc($dateCol)
            ->select('*', DB::raw("$noCol as doc_no"), DB::raw("$dateCol as doc_date"), DB::raw($hasValue ? 'grand_total as doc_value' : 'NULL as doc_value'))
            ->paginate(self::PER_PAGE, ['*'], 'page', $this->page);
    }

    public function documentRows(LengthAwarePaginator $docs): array
    {
        $ids = collect($docs->items())->pluck('supplier_id')->filter()->unique();
        $names = $ids->isEmpty() ? [] : DB::connection('tm2mst')->table('sup_supplier')->whereIn('id_supplier', $ids)->pluck('supplier_name', 'id_supplier')->all();

        return array_map(fn ($d) => ['no' => raw($d->doc_no), 'date' => Locale::date($d->doc_date),
            'supplier' => raw(isset($d->supplier_id) ? ($names[$d->supplier_id] ?? t('(unknown)')) : '—'),
            'status' => raw($d->doc_status_normalized ?? '—'), 'value' => $d->doc_value !== null ? Locale::money($d->doc_value) : '—'], $docs->items());
    }

    public function url(array $over = []): string
    {
        $q = array_filter(['th' => $this->year, 'tab' => $this->tab, 'q' => $this->search ?: null, 'page' => $this->page > 1 ? $this->page : null], fn ($v) => $v !== null && $v !== '');

        return route('v2.screen', 'mgmt-purchase').'?'.http_build_query(array_filter($over + $q, fn ($v) => $v !== null && $v !== ''));
    }
}
