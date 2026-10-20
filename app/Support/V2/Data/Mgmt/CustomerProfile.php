<?php

declare(strict_types=1);

/* REMARK
 * Tujuan       : Management Reports > Customer 360 (v2): seluruh riwayat satu customer (nilai order, kirim, tagih, tren tahunan, produk terbanyak) dan rantai dokumen satu CO: baris - Surat Jalan - Invoice.
 * Sumber hulu  : cust_customer (tm2mst), co_order_legacy / co_order_legacy_detail / co_delivery_legacy(_detail) / co_invoice_legacy(_detail) (tm2sdi); aturan sama dengan layar v1 pages::report.customer.
 * Input        : customer (id), th (tahun, opsional), co (id CO yang dibuka, opsional).
 * Output       : angka KPI, deret grafik, baris tabel, rantai dokumen.
 * Dipakai oleh : layar v2 mgmt-customer; tautan dari mgmt-sales.
 * Status       : Aktif (T9 Management Reports, 2026-10-20).
 */

namespace App\Support\V2\Data\Mgmt;

use Devlib\Locale;
use Illuminate\Support\Facades\DB;

final class CustomerProfile extends MgmtReport
{
    public readonly int $customer;

    public readonly ?int $year;

    public readonly ?int $openOrder;

    public function __construct(array $q)
    {
        $this->customer = (int) ($q['customer'] ?? 0);
        $this->year = ! empty($q['th']) ? (int) $q['th'] : null;
        $this->openOrder = ! empty($q['co']) ? (int) $q['co'] : null;
    }

    public function profile(): ?object
    {
        return DB::connection('tm2mst')->table('cust_customer')->where('id_customer', $this->customer)->first();
    }

    /** @return array<int, int> tahun yang punya order, untuk pilihan tahun */
    public function customerYearOptions(): array
    {
        $rows = DB::connection('tm2sdi')->select('SELECT DISTINCT YEAR(co_date) y FROM co_order_legacy WHERE customer_id = ? AND co_date IS NOT NULL ORDER BY y DESC', [$this->customer]);
        $years = array_map(fn ($r) => (int) $r->y, $rows);

        return array_combine($years, $years);
    }

    public function lifetime(): array
    {
        $co = DB::connection('tm2sdi')->selectOne('SELECT COUNT(*) docs, COALESCE(SUM(grand_total),0) v, MIN(co_date) first_date, MAX(co_date) last_date FROM co_order_legacy WHERE customer_id = ?', [$this->customer]);
        $sj = DB::connection('tm2sdi')->selectOne('SELECT COUNT(*) docs, COALESCE(SUM(grand_total),0) v FROM co_delivery_legacy WHERE customer_id = ?', [$this->customer]);
        $inv = DB::connection('tm2sdi')->selectOne('SELECT COUNT(*) docs, COALESCE(SUM(grand_total),0) v FROM co_invoice_legacy WHERE customer_id = ?', [$this->customer]);

        return ['co_docs' => (int) $co->docs, 'co_value' => (float) $co->v, 'first_date' => $co->first_date, 'last_date' => $co->last_date,
            'sj_docs' => (int) $sj->docs, 'sj_value' => (float) $sj->v, 'inv_docs' => (int) $inv->docs, 'inv_value' => (float) $inv->v];
    }

    /** @return array{labels: list<string>, series: list<array<string, mixed>>} tren omset tahunan customer ini */
    public function yearlyTrend(): array
    {
        $rows = DB::connection('tm2sdi')->select('SELECT YEAR(co_date) y, COALESCE(SUM(grand_total),0) v FROM co_order_legacy WHERE customer_id = ? AND co_date IS NOT NULL GROUP BY y ORDER BY y', [$this->customer]);

        return ['labels' => array_map(fn ($r) => (string) $r->y, $rows), 'series' => [['name' => t('Order value'), 'data' => array_map(fn ($r) => (float) $r->v, $rows)]]];
    }

    /** Produk yang paling sering dipesan. Alias `lines` tidak boleh dipakai (kata tercadang MySQL 8). */
    public function topProducts(): array
    {
        $rows = DB::connection('tm2sdi')->select('
            SELECT COALESCE(d.legacy_item_code, "(no code)") item, COUNT(*) line_count,
                   COALESCE(SUM(d.qty),0) qty, COALESCE(SUM(d.qty_unitprice),0) v
            FROM co_order_legacy_detail d
            JOIN co_order_legacy h ON h.id_co_legacy = d.co_legacy_id
            WHERE h.customer_id = ?
            GROUP BY item ORDER BY v DESC LIMIT 10', [$this->customer]);

        return array_map(fn ($p) => ['item' => raw($p->item), 'lines' => Locale::number($p->line_count), 'qty' => Locale::number((float) $p->qty), 'value' => Locale::money($p->v)], $rows);
    }

    public function orderRows(): array
    {
        $orders = DB::connection('tm2sdi')->table('co_order_legacy')->where('customer_id', $this->customer)
            ->when($this->year, fn ($q) => $q->whereYear('co_date', $this->year))->orderByDesc('co_date')->limit(100)->get();

        return array_map(fn ($o) => ['no' => self::link($this->url(['co' => $this->openOrder === (int) $o->id_co_legacy ? null : $o->id_co_legacy]), (string) $o->legacy_co_no),
            'date' => Locale::date($o->co_date), 'po' => raw($o->customer_po_no ?: '—'), 'sales' => raw($o->sales_person ?: '—'),
            'status' => raw($o->doc_status_normalized), 'value' => Locale::money($o->grand_total)], $orders->all());
    }

    /** Rantai dokumen untuk satu CO yang dibuka: baris, Surat Jalan, Invoice. */
    public function orderChain(): array
    {
        if (! $this->openOrder) {
            return [];
        }
        $out = [];
        foreach (DB::connection('tm2sdi')->table('co_order_legacy_detail')->where('co_legacy_id', $this->openOrder)->get() as $line) {
            $key = $line->legacy_co_detail_id;
            $deliveries = $key ? DB::connection('tm2sdi')->select('
                SELECT h.legacy_dnote_no no_dok, h.dnote_date tgl, d.qty_ship qty
                FROM co_delivery_legacy_detail d
                JOIN co_delivery_legacy h ON h.id_delivery_legacy = d.delivery_legacy_id
                WHERE d.legacy_co_detail_id = ? ORDER BY h.dnote_date', [$key]) : [];
            $invoices = $key ? DB::connection('tm2sdi')->select('
                SELECT h.legacy_cinv_no no_dok, h.cinv_date tgl, d.qty_inv qty, d.qty_unitprice nilai
                FROM co_invoice_legacy_detail d
                JOIN co_invoice_legacy h ON h.id_invoice_legacy = d.invoice_legacy_id
                WHERE d.legacy_co_detail_id = ? ORDER BY h.cinv_date', [$key]) : [];

            $out[] = ['title' => ($line->legacy_item_code ?: t('(no item code)')).' — '.t('qty').' '.Locale::number((float) $line->qty).' '.$line->uom_code.', '.Locale::money($line->qty_unitprice),
                'deliveries' => array_map(fn ($d) => ['no' => raw($d->no_dok), 'date' => Locale::date($d->tgl), 'qty' => Locale::number((float) $d->qty)], $deliveries),
                'invoices' => array_map(fn ($i) => ['no' => raw($i->no_dok), 'date' => Locale::date($i->tgl), 'value' => Locale::money($i->nilai)], $invoices)];
        }

        return $out;
    }

    public function url(array $over = []): string
    {
        $q = array_filter(['customer' => $this->customer, 'th' => $this->year, 'co' => $this->openOrder], fn ($v) => $v !== null && $v !== '');
        $merged = array_filter(array_replace($q, $over), fn ($v) => $v !== null && $v !== '');

        return route('v2.screen', 'mgmt-customer').'?'.http_build_query($merged);
    }
}
