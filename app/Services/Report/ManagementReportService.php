<?php

namespace App\Services\Report;

use Illuminate\Support\Facades\DB;

// Sumber tunggal perhitungan metrik Laporan Manajemen, meniru struktur
// "TM OM DAILY REPORT" yang sudah dipakai Direksi (docs/attachments).
//
// DUA SUDUT PANDANG OMSET yang WAJIB dibedakan -- ini inti laporan lama:
//   - by SHIPMENT (Surat Jalan / co_delivery_legacy) = barang sudah keluar
//   - by PO MASUK (Customer Order / co_order_legacy) = order diterima
// Keduanya TIDAK pernah sama dan manajemen memantau dua-duanya.
//
// BATAS DATA YANG HARUS DIKETAHUI PEMBACA (diverifikasi ke tm2old):
//   - Harga di Surat Jalan baru terisi mulai H2-2023. Sebelum itu nilainya
//     Rp 0 -- BUKAN karena tidak ada pengiriman, tapi sistem lama tidak
//     mencatat harga di dokumen itu. Omset by Shipment sebelum 2023 karena
//     itu tidak bisa dipakai; pakai by PO Masuk untuk periode tersebut.
//   - sales_person hanya terisi ~42% baris CO.
class ManagementReportService
{
    // Tahun pertama yang nilainya bisa dipercaya untuk omset by Shipment.
    public const SHIPMENT_VALUE_FROM_YEAR = 2023;

    /** Omset per bulan untuk 1 tahun, dua sudut pandang sekaligus. */
    public function monthlyRevenue(int $year, bool $confirmedOnly = false): array
    {
        $coFilter = $confirmedOnly ? "AND doc_status_normalized = 'CONFIRMED'" : '';

        $shipment = DB::connection('tm2sdi')->select("
            SELECT MONTH(dnote_date) m, COUNT(*) docs, COALESCE(SUM(grand_total),0) v
            FROM co_delivery_legacy WHERE YEAR(dnote_date) = ? GROUP BY m", [$year]);

        $order = DB::connection('tm2sdi')->select("
            SELECT MONTH(co_date) m, COUNT(*) docs, COALESCE(SUM(grand_total),0) v
            FROM co_order_legacy WHERE YEAR(co_date) = ? {$coFilter} GROUP BY m", [$year]);

        $rows = [];
        for ($m = 1; $m <= 12; $m++) {
            $rows[$m] = ['month' => $m, 'shipment' => 0.0, 'shipment_docs' => 0, 'order' => 0.0, 'order_docs' => 0];
        }
        foreach ($shipment as $r) {
            $rows[(int) $r->m]['shipment'] = (float) $r->v;
            $rows[(int) $r->m]['shipment_docs'] = (int) $r->docs;
        }
        foreach ($order as $r) {
            $rows[(int) $r->m]['order'] = (float) $r->v;
            $rows[(int) $r->m]['order_docs'] = (int) $r->docs;
        }

        return array_values($rows);
    }

    /** Perbandingan bulanan tahun berjalan vs tahun sebelumnya + growth. */
    public function yearOverYear(int $year, string $basis = 'shipment', bool $confirmedOnly = false): array
    {
        $now = $this->monthlyRevenue($year, $confirmedOnly);
        $prev = $this->monthlyRevenue($year - 1, $confirmedOnly);

        $out = [];
        foreach ($now as $i => $row) {
            $cur = $row[$basis];
            $old = $prev[$i][$basis];
            $out[] = [
                'month' => $row['month'],
                'current' => $cur,
                'previous' => $old,
                // Growth tidak bermakna kalau pembanding 0 -- dikembalikan null,
                // bukan 0/100%, supaya UI bisa menandainya "n/a" dgn jujur.
                'growth' => $old > 0 ? ($cur - $old) / $old * 100 : null,
            ];
        }

        return $out;
    }

    /** Ringkasan total 1 tahun (dipakai kartu KPI). */
    public function yearSummary(int $year, bool $confirmedOnly = false): array
    {
        $m = $this->monthlyRevenue($year, $confirmedOnly);
        $p = $this->monthlyRevenue($year - 1, $confirmedOnly);

        $sum = fn (array $rows, string $k) => array_sum(array_column($rows, $k));

        $shipNow = $sum($m, 'shipment');
        $shipPrev = $sum($p, 'shipment');
        $ordNow = $sum($m, 'order');
        $ordPrev = $sum($p, 'order');

        return [
            'shipment' => $shipNow,
            'shipment_growth' => $shipPrev > 0 ? ($shipNow - $shipPrev) / $shipPrev * 100 : null,
            'order' => $ordNow,
            'order_growth' => $ordPrev > 0 ? ($ordNow - $ordPrev) / $ordPrev * 100 : null,
            'order_docs' => $sum($m, 'order_docs'),
            'shipment_docs' => $sum($m, 'shipment_docs'),
        ];
    }

    /** Top customer berdasar salah satu sudut pandang. */
    public function topCustomers(int $year, string $basis = 'order', int $limit = 10): array
    {
        [$table, $dateCol] = $basis === 'shipment'
            ? ['co_delivery_legacy', 'dnote_date']
            : ['co_order_legacy', 'co_date'];

        $rows = DB::connection('tm2sdi')->select("
            SELECT customer_id, COUNT(*) docs, COALESCE(SUM(grand_total),0) v
            FROM `$table` WHERE YEAR(`$dateCol`) = ? AND customer_id IS NOT NULL
            GROUP BY customer_id ORDER BY v DESC LIMIT ?", [$year, $limit]);

        return $this->attachCustomerNames($rows);
    }

    /** Omset per salesperson (basis PO masuk, sesuai laporan lama bagian 5). */
    public function salesPersonPerformance(int $year): array
    {
        $rows = DB::connection('tm2sdi')->select("
            SELECT COALESCE(NULLIF(TRIM(sales_person),''),'(tidak tercatat)') sp,
                   COUNT(*) docs, COALESCE(SUM(grand_total),0) v
            FROM co_order_legacy WHERE YEAR(co_date) = ?
            GROUP BY sp ORDER BY v DESC", [$year]);

        $total = array_sum(array_map(fn ($r) => (float) $r->v, $rows));

        return array_map(fn ($r) => [
            'name' => $r->sp,
            'docs' => (int) $r->docs,
            'value' => (float) $r->v,
            'pct' => $total > 0 ? (float) $r->v / $total * 100 : 0,
        ], $rows);
    }

    /** Pencapaian per kuartal, dua sudut pandang. */
    public function quarterly(int $year): array
    {
        $ship = DB::connection('tm2sdi')->select('
            SELECT QUARTER(dnote_date) q, COALESCE(SUM(grand_total),0) v
            FROM co_delivery_legacy WHERE YEAR(dnote_date) = ? GROUP BY q', [$year]);
        $ord = DB::connection('tm2sdi')->select('
            SELECT QUARTER(co_date) q, COALESCE(SUM(grand_total),0) v
            FROM co_order_legacy WHERE YEAR(co_date) = ? GROUP BY q', [$year]);

        $out = [];
        for ($q = 1; $q <= 4; $q++) {
            $out[$q] = ['quarter' => $q, 'shipment' => 0.0, 'order' => 0.0];
        }
        foreach ($ship as $r) {
            $out[(int) $r->q]['shipment'] = (float) $r->v;
        }
        foreach ($ord as $r) {
            $out[(int) $r->q]['order'] = (float) $r->v;
        }

        return array_values($out);
    }

    /** Ringkasan sisi pembelian: PO, penerimaan, pembayaran. */
    public function purchaseSummary(int $year): array
    {
        $po = DB::connection('tm2pur')->selectOne('
            SELECT COUNT(*) docs, COALESCE(SUM(grand_total),0) v
            FROM po_order_legacy WHERE YEAR(po_date) = ?', [$year]);
        $pr = DB::connection('tm2pur')->selectOne('
            SELECT COUNT(*) docs FROM pr_requisition_legacy WHERE YEAR(pr_date) = ?', [$year]);
        $rcv = DB::connection('tm2pur')->selectOne('
            SELECT COUNT(*) docs FROM po_receive_legacy WHERE YEAR(prcv_date) = ?', [$year]);
        $pay = DB::connection('tm2pur')->selectOne('
            SELECT COUNT(*) docs, COALESCE(SUM(grand_total),0) v
            FROM po_payment_legacy WHERE YEAR(ppym_date) = ?', [$year]);

        return [
            'pr_docs' => (int) $pr->docs,
            'po_docs' => (int) $po->docs,
            'po_value' => (float) $po->v,
            'receive_docs' => (int) $rcv->docs,
            'payment_docs' => (int) $pay->docs,
            'payment_value' => (float) $pay->v,
        ];
    }

    /** Pembelian per bulan (untuk grafik tren). */
    public function monthlyPurchase(int $year): array
    {
        $rows = DB::connection('tm2pur')->select('
            SELECT MONTH(po_date) m, COUNT(*) docs, COALESCE(SUM(grand_total),0) v
            FROM po_order_legacy WHERE YEAR(po_date) = ? GROUP BY m', [$year]);

        $out = [];
        for ($m = 1; $m <= 12; $m++) {
            $out[$m] = ['month' => $m, 'value' => 0.0, 'docs' => 0];
        }
        foreach ($rows as $r) {
            $out[(int) $r->m]['value'] = (float) $r->v;
            $out[(int) $r->m]['docs'] = (int) $r->docs;
        }

        return array_values($out);
    }

    /** Supplier terbesar berdasar nilai PO. */
    public function topSuppliers(int $year, int $limit = 10): array
    {
        $rows = DB::connection('tm2pur')->select('
            SELECT supplier_id, COUNT(*) docs, COALESCE(SUM(grand_total),0) v
            FROM po_order_legacy WHERE YEAR(po_date) = ? AND supplier_id IS NOT NULL
            GROUP BY supplier_id ORDER BY v DESC LIMIT ?', [$year, $limit]);

        $ids = array_filter(array_map(fn ($r) => $r->supplier_id, $rows));
        $names = $ids
            ? DB::connection('tm2mst')->table('sup_supplier')->whereIn('id_supplier', $ids)->pluck('supplier_name', 'id_supplier')->all()
            : [];

        return array_map(fn ($r) => [
            'id' => (int) $r->supplier_id,
            'name' => $names[$r->supplier_id] ?? '(tidak dikenal)',
            'docs' => (int) $r->docs,
            'value' => (float) $r->v,
        ], $rows);
    }

    /**
     * Utang usaha (AP) -- satu-satunya saldo hutang/piutang yang benar-benar
     * bisa dihitung dari data lama.
     *
     * Dihitung grand_total invoice DIKURANGI realisasi pembayaran, BUKAN dari
     * pur_pinv.total_payment (kolom itu selalu 0 di sumber). Sambungan
     * pembayaran->invoice terisi 100%, jadi angka ini bisa dipertanggungjawabkan.
     *
     * Pasangannya di sisi penjualan (AR) TIDAK tersedia: sdi_cpym dan
     * sdi_cpym_detail dua-duanya kosong -- sistem lama tidak pernah mencatat
     * penerimaan pembayaran customer.
     */
    public function accountsPayable(?int $year = null): array
    {
        $invoice = DB::connection('tm2pur')->table('po_invoice_legacy')
            ->when($year, fn ($q) => $q->whereYear('pinv_date', $year))
            ->selectRaw('COUNT(*) docs, COALESCE(SUM(grand_total),0) v')->first();

        $paid = DB::connection('tm2pur')->table('po_payment_legacy_detail as d')
            ->join('po_invoice_legacy as i', 'i.id_pinv_legacy', '=', 'd.pinv_legacy_id')
            ->when($year, fn ($q) => $q->whereYear('i.pinv_date', $year))
            ->selectRaw('COALESCE(SUM(d.paid_amount),0) v')->first();

        $invoiceValue = (float) $invoice->v;
        $paidValue = (float) $paid->v;

        return [
            'invoice_docs' => (int) $invoice->docs,
            'invoice_value' => $invoiceValue,
            'paid_value' => $paidValue,
            'outstanding' => $invoiceValue - $paidValue,
        ];
    }

    /** Sisa utang terbesar per supplier -- prioritas pembayaran. */
    public function topPayableSuppliers(int $limit = 10): array
    {
        $rows = DB::connection('tm2pur')->select('
            SELECT i.supplier_id,
                   COUNT(DISTINCT i.id_pinv_legacy) docs,
                   COALESCE(SUM(i.grand_total),0) invoiced,
                   COALESCE((SELECT SUM(d.paid_amount) FROM po_payment_legacy_detail d
                             JOIN po_invoice_legacy i2 ON i2.id_pinv_legacy = d.pinv_legacy_id
                             WHERE i2.supplier_id = i.supplier_id),0) paid
            FROM po_invoice_legacy i
            WHERE i.supplier_id IS NOT NULL
            GROUP BY i.supplier_id
            ORDER BY invoiced DESC LIMIT ?', [$limit]);

        $ids = array_filter(array_map(fn ($r) => $r->supplier_id, $rows));
        $names = $ids
            ? DB::connection('tm2mst')->table('sup_supplier')->whereIn('id_supplier', $ids)->pluck('supplier_name', 'id_supplier')->all()
            : [];

        return array_map(fn ($r) => [
            'id' => (int) $r->supplier_id,
            'name' => $names[$r->supplier_id] ?? '(tidak dikenal)',
            'docs' => (int) $r->docs,
            'invoiced' => (float) $r->invoiced,
            'paid' => (float) $r->paid,
            'outstanding' => (float) $r->invoiced - (float) $r->paid,
        ], $rows);
    }

    /** Nilai invoice terbit per tahun -- dasar AR (bukan saldo terutang). */
    public function invoiceSummary(int $year): array
    {
        $r = DB::connection('tm2sdi')->selectOne('
            SELECT COUNT(*) docs, COALESCE(SUM(grand_total),0) v
            FROM co_invoice_legacy WHERE YEAR(cinv_date) = ?', [$year]);

        return ['docs' => (int) $r->docs, 'value' => (float) $r->v];
    }

    /**
     * Tanggal PO terakhir yang tercatat. Modul pembelian sistem lama berhenti
     * dipakai di awal 2025, jadi tahun setelahnya wajar bernilai Rp 0 --
     * layar memakai ini untuk menjelaskan, bukan membiarkan pembaca mengira
     * angkanya rusak.
     */
    public function purchaseDataLastDate(): ?string
    {
        return DB::connection('tm2pur')->table('po_order_legacy')->max('po_date');
    }

    /**
     * Kepadatan rantai dokumen Purchasing -- angka NYATA hasil audit migrasi
     * (bukan target/SLA operasional spt "on-time delivery" di mockup, karena
     * data itu tidak ada di sumber). Dipakai panel "Kesehatan Data" supaya
     * Direksi lihat seberapa bisa dipercaya rantai telusur PO->Terima->
     * Invoice->Bayar, bukan cuma angka rupiah.
     */
    public function purchasingDataHealth(): array
    {
        $poMaterial = DB::connection('tm2pur')->selectOne(
            'SELECT COUNT(*) n, SUM(material_id IS NOT NULL) f FROM po_order_legacy_detail'
        );
        $receivePo = DB::connection('tm2pur')->selectOne(
            'SELECT COUNT(*) n, SUM(legacy_po_detail_id IS NOT NULL) f FROM po_receive_legacy_detail'
        );
        $invoicePo = DB::connection('tm2pur')->selectOne(
            'SELECT COUNT(*) n, SUM(legacy_po_detail_id IS NOT NULL) f FROM po_invoice_legacy_detail'
        );
        $paymentInvoice = DB::connection('tm2pur')->selectOne(
            "SELECT COUNT(*) n, SUM(pinv_legacy_id IS NOT NULL) f FROM po_payment_legacy_detail WHERE legacy_pinv_no IS NOT NULL"
        );
        $invoiceSupplier = DB::connection('tm2pur')->selectOne(
            'SELECT COUNT(*) n, SUM(supplier_id IS NOT NULL) f FROM po_invoice_legacy'
        );

        $pct = fn ($r) => $r->n > 0 ? round((int) $r->f / (int) $r->n * 100, 1) : 0.0;

        return [
            ['label' => 'Baris PO teridentifikasi bahan bakunya', 'pct' => $pct($poMaterial)],
            ['label' => 'Penerimaan barang tersambung ke PO', 'pct' => $pct($receivePo)],
            ['label' => 'Invoice supplier tersambung ke PO', 'pct' => $pct($invoicePo)],
            ['label' => 'Pembayaran tersambung ke invoice', 'pct' => $pct($paymentInvoice)],
            ['label' => 'Invoice teridentifikasi suppliernya', 'pct' => $pct($invoiceSupplier)],
        ];
    }

    /**
     * "Management Attention" -- anomali NYATA hasil audit sepanjang migrasi
     * (bukan contoh/dummy). Tiap item bisa ditelusuri balik ke angka yang
     * sudah diverifikasi di sesi migrasi -- lihat
     * memory project_tm2old_co_po_migration.md kalau perlu jejak lengkapnya.
     */
    public function managementAttention(): array
    {
        $attention = [];

        $ap = $this->accountsPayable();
        if ($ap['outstanding'] > 0) {
            $attention[] = [
                'tone' => 'warn',
                'title' => 'Sisa utang usaha (AP)',
                'text' => 'Rp ' . number_format($ap['outstanding'], 0, ',', '.')
                    . ' dari total invoice supplier Rp ' . number_format($ap['invoice_value'], 0, ',', '.')
                    . ' — dihitung invoice dikurangi realisasi pembayaran (kolom saldo di sumber tidak pernah diisi).',
            ];
        }

        $unmappedInv = DB::connection('tm2pur')->selectOne(
            'SELECT COUNT(*) n, SUM(supplier_id IS NULL) f FROM po_invoice_legacy'
        );
        if ((int) $unmappedInv->f > 0) {
            $pct = round((int) $unmappedInv->f / (int) $unmappedInv->n * 100, 1);
            $attention[] = [
                'tone' => 'danger',
                'title' => 'Invoice tanpa padanan supplier',
                'text' => number_format((int) $unmappedInv->f) . ' dari ' . number_format((int) $unmappedInv->n)
                    . " invoice ({$pct}%) — sistem lama menyimpan nama perusahaan di kolom kode (mis. MADJU/TANINDO/KEISHA), bukan kode resmi.",
            ];
        }

        $overpaid = DB::connection('tm2pur')->select('
            SELECT i.supplier_id, SUM(i.grand_total) tagih,
                   COALESCE((SELECT SUM(d.paid_amount) FROM po_payment_legacy_detail d
                             JOIN po_invoice_legacy i2 ON i2.id_pinv_legacy = d.pinv_legacy_id
                             WHERE i2.supplier_id = i.supplier_id),0) bayar
            FROM po_invoice_legacy i WHERE i.supplier_id IS NOT NULL
            GROUP BY i.supplier_id HAVING bayar > tagih');
        if (count($overpaid) > 0) {
            $attention[] = [
                'tone' => 'warn',
                'title' => 'Supplier tercatat dibayar melebihi tagihan',
                'text' => count($overpaid) . ' supplier — kemungkinan ada invoice yang tidak ikut terekam di sistem lama, bukan kesalahan hitung.',
            ];
        }

        $unmatchedMaterial = DB::connection('tm2pur')->selectOne(
            'SELECT COUNT(*) n, SUM(material_id IS NULL) f FROM po_order_legacy_detail'
        );
        if ((int) $unmatchedMaterial->f > 0) {
            $pct = round((int) $unmatchedMaterial->f / (int) $unmatchedMaterial->n * 100, 1);
            $attention[] = [
                'tone' => 'info',
                'title' => 'Baris PO tanpa padanan Master Raw Material',
                'text' => number_format((int) $unmatchedMaterial->f) . " baris ({$pct}%) — item ada di sumber tapi belum terklasifikasi sbg bahan baku saat migrasi Master, perlu ditinjau manual.",
            ];
        }

        return $attention;
    }

    /** Tahun-tahun yang punya data, untuk isian dropdown periode. */
    public function availableYears(): array
    {
        $rows = DB::connection('tm2sdi')->select('
            SELECT DISTINCT YEAR(co_date) y FROM co_order_legacy
            WHERE co_date IS NOT NULL ORDER BY y DESC');

        return array_map(fn ($r) => (int) $r->y, $rows);
    }

    private function attachCustomerNames(array $rows): array
    {
        $ids = array_filter(array_map(fn ($r) => $r->customer_id, $rows));
        $names = $ids
            ? DB::connection('tm2mst')->table('cust_customer')->whereIn('id_customer', $ids)->pluck('customer_name', 'id_customer')->all()
            : [];

        return array_map(fn ($r) => [
            'id' => (int) $r->customer_id,
            'name' => $names[$r->customer_id] ?? '(tidak dikenal)',
            'docs' => (int) $r->docs,
            'value' => (float) $r->v,
        ], $rows);
    }
}
