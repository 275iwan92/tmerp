<?php

declare(strict_types=1);

namespace App\Services\Inventory\Valuation;

use App\Models\Master\SupSupplier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

// Umur GR/IR (V-03): barang sudah diterima tetapi belum ditagih, dan sebaliknya tagihan sudah datang tetapi barang belum diterima.
//   Diterima  : baris stok penerimaan PO berstatus POSTED (jurnal Dr Persediaan / Cr GR/IR dibuat PvPostingService), nilai = qty diterima x harga PO.
//   Ditagih   : (a) pv_line.c_qty_invoiced x harga (bila diisi), (b) baris Tanda Terima Tagihan yang Confirmed dan menunjuk PO (fin_invoice_receipt_line.po_id).
//   Pencocokan per PO, penerimaan TERTUA lebih dulu: nilai tertagih menutup penerimaan paling lama; sisa penerimaan = "Received not invoiced"
//   (umur = hari sejak tanggal terima); kelebihan tagihan = "Invoiced not received" (umur = hari sejak tanggal tagihan tertua).
// Catatan: nilai tagihan dianggap sebanding dengan nilai terima (PPN dan diskon faktur belum dipisah); selama tidak ada tautan dokumen
// per baris, pencocokan ini hanya perkiraan per PO.
final class GrIrAgingService
{
    public const RECEIVED = 'Received not invoiced';

    public const INVOICED = 'Invoiced not received';

    public const BUCKETS = ['b1' => '0-30', 'b2' => '31-60', 'b3' => '61-90', 'b4' => '90+'];

    /** @return list<array<string, mixed>> */
    public function rows(CarbonImmutable $asOf): array
    {
        $asOf = $asOf->startOfDay();
        $lines = DB::connection('tm2pur')->table('pv_line as l')->join('pv_receipt as r', 'r.pv_id', '=', 'l.pv_id')
            ->where('r.status', 'POSTED')->where('r.i_receipt_type', 'PO')->where('l.is_stock', 1)->where('l.c_qty_accepted', '>', 0)->where('r.i_doc_date', '<=', $asOf->toDateString())
            ->orderBy('r.i_doc_date')->orderBy('l.pv_line_id')
            ->get(['l.pv_line_id', 'l.pv_id', 'l.c_po_id', 'l.c_po_doc_no', 'l.i_description', 'l.c_qty_accepted', 'l.c_unit_price', 'l.c_qty_invoiced', 'r.doc_no', 'r.i_doc_date', 'r.i_supplier_id']);
        $billed = $this->billedByPo($asOf);
        $names = SupSupplier::query()->whereKey($lines->pluck('i_supplier_id')->filter()->unique()->all())->pluck('supplier_name', 'id_supplier');

        $rows = [];
        foreach ($lines->groupBy(fn ($l) => $l->c_po_id ?: 'pv'.$l->pv_id) as $po => $set) {
            $remaining = (float) ($billed[$po]['amount'] ?? 0.0);
            foreach ($set as $l) {
                $value = round((float) $l->c_qty_accepted * (float) $l->c_unit_price, 2);
                $open = round($value - (float) $l->c_qty_invoiced * (float) $l->c_unit_price, 2);
                $take = min($open, $remaining);
                $remaining = round($remaining - $take, 2);
                if ($open - $take >= 0.005) {
                    $rows[] = $this->row(self::RECEIVED, 'R'.$l->pv_line_id, $names[$l->i_supplier_id] ?? '', (string) $l->c_po_doc_no, (string) $l->doc_no, (string) $l->i_doc_date, $open - $take, $asOf);
                }
            }
            if ($remaining >= 0.005) {
                $first = $set->first();
                $rows[] = $this->row(self::INVOICED, 'I'.$po, $names[$first->i_supplier_id] ?? '', (string) $first->c_po_doc_no, '', (string) ($billed[$po]['first'] ?? $first->i_doc_date), $remaining, $asOf);
            }
        }
        // PO yang HANYA punya tagihan (belum ada penerimaan sama sekali).
        foreach (array_diff_key($billed, $lines->groupBy(fn ($l) => $l->c_po_id ?: 'pv'.$l->pv_id)->all()) as $po => $b) {
            $rows[] = $this->row(self::INVOICED, 'I'.$po, (string) ($b['supplier'] ?? ''), (string) $b['po_no'], '', (string) $b['first'], (float) $b['amount'], $asOf);
        }

        return $rows;
    }

    /** @return array<string, float> total per jenis (dan per umur) untuk ringkasan */
    public function totals(array $rows): array
    {
        $t = [self::RECEIVED => 0.0, self::INVOICED => 0.0, 'over_90' => 0.0];
        foreach ($rows as $r) {
            $t[$r['kind']] += $r['amount'];
            $t['over_90'] += $r['b4'];
        }

        return array_map(fn ($v) => round($v, 2), $t);
    }

    public static function bucket(int $days): string
    {
        return match (true) {
            $days <= 30 => 'b1',
            $days <= 60 => 'b2',
            $days <= 90 => 'b3',
            default => 'b4',
        };
    }

    /** @return array<string|int, array{amount: float, first: string, po_no: string, supplier: ?string}> tagihan Confirmed per PO */
    private function billedByPo(CarbonImmutable $asOf): array
    {
        $rows = DB::connection('tm2acc')->table('fin_invoice_receipt_line as l')->join('fin_invoice_receipt as r', 'r.id_invoice_receipt', '=', 'l.invoice_receipt_id')
            ->where('r.stat_confirm', 1)->where('r.stat_cancel', 0)->whereNotNull('l.po_id')->where('r.receipt_date', '<=', $asOf->toDateString())
            ->groupBy('l.po_id')->selectRaw('l.po_id, SUM(l.amount) AS amount, MIN(COALESCE(l.invoice_date, r.receipt_date)) AS first_date, MAX(l.po_no) AS po_no, MAX(r.supplier_id) AS supplier_id')->get();
        $names = SupSupplier::query()->whereKey($rows->pluck('supplier_id')->filter()->unique()->all())->pluck('supplier_name', 'id_supplier');

        return $rows->mapWithKeys(fn ($r) => [(int) $r->po_id => ['amount' => (float) $r->amount, 'first' => (string) $r->first_date, 'po_no' => (string) $r->po_no,
            'supplier' => $names[$r->supplier_id] ?? null]])->all();
    }

    private function row(string $kind, string $id, string $supplier, string $po, string $doc, string $date, float $amount, CarbonImmutable $asOf): array
    {
        $date = substr($date, 0, 10);
        $days = max(0, (int) CarbonImmutable::parse($date)->startOfDay()->diffInDays($asOf, false));
        $bucket = self::bucket($days);
        $amount = round($amount, 2);

        $row = ['id' => $id, 'kind' => $kind, 'supplier' => $supplier, 'po_no' => $po, 'doc_no' => $doc, 'doc_date' => $date, 'amount' => $amount, 'days' => $days,
            'bucket' => self::BUCKETS[$bucket]] + array_map(fn () => 0.0, self::BUCKETS);
        $row[$bucket] = $amount;

        return $row;
    }
}
