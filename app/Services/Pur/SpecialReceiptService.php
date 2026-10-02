<?php

namespace App\Services\Pur;

use App\Models\Acc\FinVoucher;
use App\Models\Pur\PoLine;
use App\Models\Pur\PvLine;
use App\Models\Pur\PvReceipt;
use App\Services\Acc\JournalPostingService;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

// Penerimaan non-PO-biasa (user 2026-10-01), satu dokumen SPB dgn i_receipt_type:
// CUSTOMER -- bahan dari customer untuk CO tertentu (Inquiry "bahan dari customer"). Wajib No. CO, baris menunjuk
//             co_order_item. Bukan stok titipan umum & bukan aset kita: tidak ke kartu stok, tidak dijurnal.
// SUBCON   -- barang kembali dari subkon atas PO Subkon (jasa). Kebanyakan langsung diteruskan ke proses berikutnya
//             (tidak masuk gudang). Confirm: PO dihitung ulang + Dr HPP Jasa Subkon (513000 / akun baris PO) / Cr GR/IR.
//             Kekurangan yang baru ketahuan di proses berikutnya -> reportShortage(): qty diterima turun, PO dihitung
//             ulang, jurnal dikoreksi (PV_SHORT). Cancel memakai PvPostingService::cancel (ikut membalik PV_SHORT).
final class SpecialReceiptService
{
    public const SUBCON_EXPENSE = '513000';

    public const GR_IR = '211900';

    public static function openSubconLines(?int $supplierId = null)
    {
        return PoLine::with('order')->whereHas('order', fn ($q) => $q->where('i_po_type', 'SUBCONTRACT')->whereIn('status', ['SENT', 'PARTIALLY_RECEIVED'])
            ->where('stat_cancel', false)->when($supplierId, fn ($w) => $w->where('i_supplier_id', $supplierId)))
            ->where('is_receivable', true)->whereColumn('c_qty_received', '<', 'i_qty')->orderBy('po_id')->orderBy('line_no')->get();
    }

    public static function createCustomer(int $coId, ?string $warehouse, string $login): PvReceipt
    {
        $co = DB::connection('tm2sdi')->table('co_order')->where('id_order', $coId)->first() ?? throw new InvalidArgumentException(t('CO not found.'));
        if ($co->stat_cancel) {
            throw new InvalidArgumentException(tf('CO :no is already cancelled.', ['no' => $co->co_no]));
        }
        $items = DB::connection('tm2sdi')->table('co_order_item as i')->leftJoin('iqp_inquiry as q', 'q.id_inquiry', '=', 'i.inquiry_id')
            ->where('i.order_id', $coId)->orderBy('i.line_seq')->get(['i.id_item', 'i.description', 'q.material_source', 'q.cust_material_spec']);

        return DB::connection('tm2pur')->transaction(function () use ($co, $items, $warehouse, $login) {
            $pv = PvReceipt::create(['doc_no' => null, 'i_receipt_type' => 'CUSTOMER', 'i_doc_date' => now()->toDateString(),
                'i_customer_id' => $co->customer_id, 'i_co_id' => $co->id_order, 'i_warehouse_code' => $warehouse ?: null, 'c_po_doc_list' => $co->co_no,
                'status' => 'DRAFT', 'created_by' => $login, 'updated_by' => $login]);
            // Baris awal per item CO; spesifikasi bahan dari Inquiry bila Inquiry menyebut "bahan dari customer".
            foreach ($items->values() as $i => $it) {
                PvLine::create(['pv_id' => $pv->pv_id, 'line_no' => $i + 1, 'i_co_line_id' => $it->id_item, 'is_stock' => false,
                    'i_description' => mb_substr($it->material_source === 'CUSTOMER' && $it->cust_material_spec ? $it->cust_material_spec : 'Customer material — '.$it->description, 0, 255),
                    'i_qty_purchase' => 0, 'i_uom_purchase' => 'LBR', 'qc_qty_status' => 'DONE', 'qc_quality_status' => 'PASS', 'created_by' => $login, 'updated_by' => $login]);
            }

            return $pv;
        });
    }

    public static function createSubcon(int $supplierId, array $poLineIds, ?int $doId, string $login): PvReceipt
    {
        $lines = self::openSubconLines($supplierId)->whereIn('po_line_id', array_map('intval', $poLineIds));
        if ($lines->isEmpty()) {
            throw new InvalidArgumentException(t('Choose at least 1 Subcon PO line that is still open.'));
        }

        return DB::connection('tm2pur')->transaction(function () use ($supplierId, $lines, $doId, $login) {
            $pv = PvReceipt::create(['doc_no' => null, 'i_receipt_type' => 'SUBCON', 'i_doc_date' => now()->toDateString(),
                'i_supplier_id' => $supplierId, 'i_do_id' => $doId, 'c_po_doc_list' => $lines->pluck('order.doc_no')->unique()->implode(', '),
                'c_po_count' => $lines->pluck('po_id')->unique()->count(), 'status' => 'DRAFT', 'created_by' => $login, 'updated_by' => $login]);
            foreach ($lines->values() as $i => $l) {
                PvLine::create(['pv_id' => $pv->pv_id, 'line_no' => $i + 1, 'po_line_id' => $l->po_line_id, 'c_po_id' => $l->po_id, 'c_po_doc_no' => $l->order->doc_no,
                    'c_po_line_no' => $l->line_no, 'item_id' => $l->item_id, 'c_item_code' => $l->c_item_code, 'i_description' => $l->i_description, 'is_stock' => false,
                    'i_qty_purchase' => (float) $l->i_qty - (float) $l->c_qty_received, 'i_uom_purchase' => $l->i_uom, 'c_unit_price' => (float) $l->i_price,
                    'qc_qty_status' => 'DONE', 'qc_quality_status' => 'PASS', 'created_by' => $login, 'updated_by' => $login]);
            }

            return $pv;
        });
    }

    public static function save(PvReceipt $pv, array $in, string $login): void
    {
        abort_unless($pv->status === 'DRAFT', 422, t('The receipt is already Confirmed, it cannot be changed.'));
        foreach (['i_doc_date', 'i_supplier_do_no', 'i_vehicle_no', 'i_notes', 'i_warehouse_code'] as $f) {
            if (array_key_exists($f, $in)) {
                $pv->{$f} = trim((string) $in[$f]) === '' ? null : trim((string) $in[$f]);
            }
        }
        $pv->i_doc_date ??= now()->toDateString();
        $pv->updated_by = $login;
        $pv->save();

        $lines = $pv->lines()->get()->keyBy('pv_line_id');
        foreach (array_keys((array) ($in['remove'] ?? [])) as $id) {
            if ($pv->i_receipt_type === 'CUSTOMER') {
                $lines->get((int) $id)?->delete();
            }
        }
        $next = (int) $lines->max('line_no');
        foreach ((array) ($in['lines'] ?? []) as $id => $row) {
            $row = (array) $row;
            $line = is_numeric($id) ? $lines->get((int) $id) : null;
            if (! $line) {
                if ($pv->i_receipt_type !== 'CUSTOMER' || trim((string) ($row['i_description'] ?? '')) === '' || isset($in['remove'][$id])) {
                    continue;
                }
                $line = new PvLine(['pv_id' => $pv->pv_id, 'line_no' => ++$next, 'is_stock' => false, 'qc_qty_status' => 'DONE', 'qc_quality_status' => 'PASS', 'created_by' => $login]);
            }
            $qty = (float) (Locale::input($row['i_qty_purchase'] ?? $line->i_qty_purchase) ?? 0);
            $line->fill(['i_qty_purchase' => $qty, 'c_qty_accepted' => $qty, 'i_next_process' => trim((string) ($row['i_next_process'] ?? $line->i_next_process)) ?: null,
                'i_lot_no' => trim((string) ($row['i_lot_no'] ?? $line->i_lot_no)) ?: null, 'updated_by' => $login]);
            if ($pv->i_receipt_type === 'CUSTOMER') {
                $line->fill(['i_description' => mb_substr(trim((string) ($row['i_description'] ?? $line->i_description)), 0, 255),
                    'i_uom_purchase' => trim((string) ($row['i_uom_purchase'] ?? $line->i_uom_purchase)) ?: '-',
                    'item_id' => filled($row['item_id'] ?? null) ? (int) $row['item_id'] : $line->item_id,
                    'i_co_line_id' => filled($row['i_co_line_id'] ?? null) ? (int) $row['i_co_line_id'] : $line->i_co_line_id]);
            }
            $line->c_line_value = round($qty * (float) $line->c_unit_price, 2);
            $line->save();
        }
    }

    public static function post(PvReceipt $pv, string $login): array
    {
        $pv->load('lines');
        if ($pv->status !== 'DRAFT') {
            return [t('The receipt does not have status Draft.')];
        }
        $err = [];
        if ($pv->lines->isEmpty()) {
            $err[] = t('No rows yet.');
        }
        foreach ($pv->lines as $l) {
            if ((float) $l->i_qty_purchase <= 0) {
                $err[] = tf('Line :line: fill in the received qty.', ['line' => $l->line_no]);
            }
            if ($pv->i_receipt_type === 'SUBCON' && $l->po_line_id) {
                $po = PoLine::find($l->po_line_id);
                if ($po && (float) $l->i_qty_purchase > (float) $po->i_qty - (float) $po->c_qty_received + 0.0001) {
                    $err[] = tf('Line :line: the qty exceeds what is left of the Subcon PO (:qty).', ['line' => $l->line_no, 'qty' => Locale::decimal((float) $po->i_qty - (float) $po->c_qty_received, 2)]);
                }
            }
        }
        if ($err) {
            return $err;
        }

        DB::connection('tm2pur')->transaction(function () use ($pv, $login) {
            // Nomor lahir di sini (seri PBC / PBS), di dalam transaksi yang sama dengan perubahan status.
            $pv->update(['doc_no' => $pv->doc_no ?: PvReceipt::nextDocNo((string) $pv->i_receipt_type), 'status' => 'POSTED', 'posted_at' => now(), 'posted_by' => $login, 'updated_by' => $login]);
            foreach ($pv->lines->pluck('c_po_id')->filter()->unique() as $poId) {
                PvPostingService::recalcPo((int) $poId);
            }
        });
        if ($pv->i_receipt_type === 'SUBCON') {
            self::journal($pv, 'PV', $pv->lines->mapWithKeys(fn ($l) => [$l->pv_line_id => (float) $l->i_qty_purchase])->all(), false, $login, "Penerimaan dari subkon {$pv->doc_no}");
        }

        return [];
    }

    public static function reportShortage(PvReceipt $pv, int $lineId, float $qty, string $note, string $login): void
    {
        if ($pv->i_receipt_type !== 'SUBCON' || $pv->status !== 'POSTED') {
            throw new InvalidArgumentException(t('A shortage correction is only for a subcon receipt that is already Confirmed.'));
        }
        $line = $pv->lines()->findOrFail($lineId);
        if ($qty <= 0 || $qty > (float) $line->i_qty_purchase) {
            throw new InvalidArgumentException(tf('The short qty must be > 0 and not more than the received qty (:qty).', ['qty' => Locale::decimal($line->i_qty_purchase, 2)]));
        }
        if (mb_strlen(trim($note)) < 5) {
            throw new InvalidArgumentException(t('Fill in the shortage note (in which process it was found).'));
        }
        DB::connection('tm2pur')->transaction(function () use ($line, $qty, $note, $login) {
            $line->update(['i_qty_purchase' => (float) $line->i_qty_purchase - $qty, 'c_qty_accepted' => (float) $line->i_qty_purchase - $qty,
                'c_qty_short' => (float) $line->c_qty_short + $qty, 'c_line_value' => round(((float) $line->i_qty_purchase - $qty) * (float) $line->c_unit_price, 2),
                'i_short_note' => mb_substr(trim(($line->i_short_note ? $line->i_short_note.' | ' : '').trim($note)), 0, 255), 'updated_by' => $login]);
            if ($line->c_po_id) {
                PvPostingService::recalcPo((int) $line->c_po_id);
            }
        });
        self::journal($pv, 'PV_SHORT', [$line->pv_line_id => $qty], true, $login, "Kekurangan subkon {$pv->doc_no} baris {$line->line_no}");
    }

    // Dr biaya jasa subkon / Cr GR/IR (atau kebalikannya utk koreksi kekurangan). Akun biaya = akun baris PO bila diisi.
    private static function journal(PvReceipt $pv, string $source, array $qtyByLine, bool $reverse, string $login, string $description): void
    {
        $acc = DB::connection('tm2acc')->table('fin_account');
        $grIr = (int) (clone $acc)->where('account_code', self::GR_IR)->value('account_id');
        $default = (int) (clone $acc)->where('account_code', self::SUBCON_EXPENSE)->value('account_id');
        $rows = [];
        foreach ($pv->lines()->whereIn('pv_line_id', array_keys($qtyByLine))->get() as $l) {
            $value = round($qtyByLine[$l->pv_line_id] * (float) $l->c_unit_price, 2);
            $expense = (int) (PoLine::whereKey($l->po_line_id)->value('i_coa_account_id') ?: $default);
            if ($value > 0 && $expense && $grIr) {
                $rows[$expense] = ($rows[$expense] ?? 0) + $value;
            }
        }
        if (! $rows) {
            return;
        }
        $lines = [];
        foreach ($rows as $expense => $value) {
            $lines[] = ['account_id' => $expense, $reverse ? 'credit' : 'debit' => $value, 'narration' => $description];
            $lines[] = ['account_id' => $grIr, $reverse ? 'debit' : 'credit' => $value, 'narration' => "GR/IR {$pv->doc_no}"];
        }
        app(JournalPostingService::class)->post('PUR', $source, $pv->pv_id, $description, $lines, $login, now()->toDateString());
    }

    public static function vouchers(PvReceipt $pv): array
    {
        return FinVoucher::withoutGlobalScopes()->whereIn('source_type', ['PV', 'PV_SHORT'])->where('source_id', $pv->pv_id)->orderBy('voucher_id')
            ->pluck('voucher_number')->all();
    }
}
