<?php

/*
 * REMARK
 * Tujuan       : Penyimpan dan alur Purchase Return (Nota Retur Barang, NRB) dari layar v2 pur-return-form: dua sumber (penerimaan dengan qty ditolak QC, atau stok per supplier), validasi header dan baris (qty retur dari penerimaan QC dibatasi sisa reject), simpan Draft, Submit (nomor NRB terbit, masuk flow approval PRT), keputusan Approve / Reject / Hold (dipakai Approval Center), Cancel Draft dengan alasan, Mark Complete, Duplicate. Tidak membuat Stock Out (rm_stock_doc_id tetap kosong seperti v1).
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::pur.prt.index dan pur.prt.entry (diarsipkan di tmerp_unused/pur_prt_index_v1_2026-10-19.blade.php dan pur_prt_entry_v1_2026-10-19.blade.php); penerimaan POSTED (pv_receipt + pv_line.c_qty_rejected, c_unit_price), master bahan (mat_raw), supplier (sup_supplier), flow_config modul PRT.
 * Input        : id dokumen, sumber (QC_REJECT / FROM_STOCK) + pv_id atau supplier, isian header (tanggal, disposisi, alasan, tindak lanjut, catatan), baris (QC: kunci id baris penerimaan + qty + lot; stok: item_id + qty + harga + lot), user, keputusan.
 * Output       : tm2pur.prt_return + prt_line (c_line_value = qty x harga, c_credit_value = jumlah nilai baris bila Credit Note), flow_log (tm2mst); ValidationException bila aturan dilanggar.
 * Dipakai oleh : routes/v2_purchase_return.php (v2.pur.return.*), layar pur-return-form, App\Support\V2\Data\ApprovalCenter (keputusan PRT), grid PurchaseReturnGrid, tes PurchaseReturnServiceTest.
 * Status       : Aktif (Master T6, 2026-10-19). Baru dibanding v1: harga baris stok wajib > 0 (kosong dan item tanpa harga terakhir ditolak), Notes / Chronology tersimpan (kolom i_notes), qty retur dari penerimaan tidak boleh melebihi sisa reject, Save eksplisit (v1 menyimpan tiap perubahan langsung), tidak ada baris tersimpan sebelum Save pertama, Duplicate untuk dokumen Rejected / Cancelled.
 */

namespace App\Services\Pur;

use App\Models\Master\MatRaw;
use App\Models\Pur\PrtLine;
use App\Models\Pur\PrtReturn;
use App\Models\Pur\PvLine;
use App\Models\Pur\PvReceipt;
use App\Models\Sys\SecUser;
use App\Services\Flow\FlowEngineService;
use App\Support\V2\Data\StockEntry;
use Carbon\Carbon;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PurchaseReturnService
{
    public const SOURCES = ['QC_REJECT' => 'From receipt (QC reject)', 'FROM_STOCK' => 'From stock'];

    public const DISPOSITIONS = ['CREDIT_NOTE' => 'Credit note', 'REPLACEMENT' => 'Replace goods'];

    public function __construct(private FlowEngineService $flow) {}

    public function editable(PrtReturn $h): bool
    {
        return $h->status === 'DRAFT';
    }

    /** [state form, stage] dari status dokumen dan keputusan terakhir. */
    public function formState(PrtReturn $h): array
    {
        $held = $h->status === 'SUBMITTED' && $this->flow->history('PRT', (int) $h->prt_id)->last()?->action === 'HOLD';

        return match (true) {
            $held => ['hold', 'Info requested'],
            $h->status === 'SUBMITTED' => ['approval', 'Waiting approval'],
            $h->status === 'CONFIRMED' => ['confirmed', 'Confirmed'],
            $h->status === 'COMPLETED' => ['closed', 'Completed'],
            $h->status === 'REJECTED' => ['rejected', 'Rejected'],
            $h->status === 'CANCELLED' => ['cancelled', null],
            default => ['draft', null],
        };
    }

    /** @return array<int, string> supplier aktif (kartu Start dari stok) */
    public function supplierOptions(): array
    {
        return DB::connection('tm2mst')->table('sup_supplier')->where('activate', true)->orderBy('supplier_name')->pluck('supplier_name', 'id_supplier')->all();
    }

    /** @return list<array<string, mixed>> penerimaan POSTED yang masih punya reject QC, dengan sisa yang boleh diretur */
    public function returnableReceipts(): array
    {
        $names = $this->supplierOptions();

        return PvReceipt::with('lines')->where('status', 'POSTED')->orderByDesc('pv_id')->get()
            ->map(fn ($pv) => ['id' => (int) $pv->pv_id, 'doc_no' => (string) $pv->doc_no, 'supplier' => $names[$pv->i_supplier_id] ?? '—', 'balance' => (float) $pv->lines->sum(fn ($l) => $this->returnable($l, null))])
            ->filter(fn ($r) => $r['balance'] > 0.0001)->values()->all();
    }

    /** Sisa reject QC baris penerimaan yang masih boleh diretur (dikurangi dokumen retur lain yang tidak dibatalkan). */
    public function returnable(PvLine $l, ?int $exceptPrtId): float
    {
        $used = (float) PrtLine::where('pv_line_id', $l->pv_line_id)
            ->whereHas('return', fn ($q) => $q->whereNotIn('status', ['CANCELLED', 'REJECTED'])->when($exceptPrtId, fn ($qq) => $qq->where('prt_id', '!=', $exceptPrtId)))->sum('i_qty');

        return max(0.0, (float) $l->c_qty_rejected - $used);
    }

    /**
     * Baris x-dl::lines. QC: satu baris per baris penerimaan yang ditolak QC (kunci pv_line_id); stok: baris tersimpan atau isian form.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(string $source, ?PrtReturn $doc, ?int $pvId = null, ?array $input = null): array
    {
        if ($source === 'QC_REJECT') {
            $pv = $doc?->pv_id ?? $pvId;
            $mine = $doc ? $doc->lines->keyBy('pv_line_id') : collect();
            $inp = collect($input ?? []);
            $rows = [];
            foreach (PvLine::where('pv_id', $pv)->where('c_qty_rejected', '>', 0)->orderBy('line_no')->get() as $l) {
                $ret = $this->returnable($l, $doc?->prt_id);
                $own = $mine->get($l->pv_line_id);
                if ($ret <= 0.0001 && ! $own) {
                    continue;
                }
                $o = $inp->get($l->pv_line_id);
                $rows[] = ['pv_line_id' => $l->pv_line_id, 'item' => trim($l->c_item_code.' · '.$l->i_description, ' ·'), 'rejected' => Locale::number((float) $l->c_qty_rejected, 3), 'returnable' => Locale::number($ret, 3),
                    'uom' => (string) $l->i_uom_stock, 'qty' => $o['qty'] ?? ($own ? (float) $own->i_qty : ''), 'price' => (float) $l->c_unit_price, 'i_lot_no' => $o['i_lot_no'] ?? (string) ($own->i_lot_no ?? '')];
            }

            return $rows;
        }
        $raw = $input ?? ($doc ? $doc->lines->map(fn ($l) => ['item_id' => $l->item_id, 'qty' => (float) $l->i_qty, 'uom_code' => $l->i_uom, 'price' => (float) $l->c_unit_price, 'i_lot_no' => $l->i_lot_no])->all() : []);

        return array_map(fn ($l) => ['item_id' => $l['item_id'] ?? '', 'item_id_label' => ! empty($l['item_id']) ? (StockEntry::itemFind('RM', $l['item_id'])['label'] ?? '#'.$l['item_id']) : '',
            'qty' => $l['qty'] ?? '', 'uom_code' => (string) ($l['uom_code'] ?? ''), 'price' => $l['price'] ?? '', 'i_lot_no' => (string) ($l['i_lot_no'] ?? '')], $raw);
    }

    /** Simpan Draft (dokumen baru belum bernomor). Return id dokumen. */
    public function save(?int $id, array $in, array $lines, SecUser $user): int
    {
        if ($id) {
            $h = PrtReturn::findOrFail($id);
            if (! $this->editable($h)) {
                throw ValidationException::withMessages(['document' => 'This document is already submitted, confirmed or cancelled and cannot be changed.']);
            }
            $source = $h->i_source;
            $pvId = $h->pv_id;
            $supplierId = (int) $h->i_supplier_id;
        } else {
            $source = (string) ($in['source'] ?? '');
            if (! isset(self::SOURCES[$source])) {
                throw ValidationException::withMessages(['source' => 'Choose where the return comes from.']);
            }
            $pvId = $source === 'QC_REJECT' ? (int) ($in['pv_id'] ?? 0) : null;
            $pv = $pvId ? PvReceipt::where('status', 'POSTED')->find($pvId) : null;
            if ($source === 'QC_REJECT' && ! $pv) {
                throw ValidationException::withMessages(['pv_id' => 'Choose a posted receipt with QC-rejected goods.']);
            }
            $supplierId = $pv ? (int) $pv->i_supplier_id : (int) ($in['supplier_id'] ?? 0);
            if (! DB::connection('tm2mst')->table('sup_supplier')->where('id_supplier', $supplierId)->where('activate', true)->exists()) {
                throw ValidationException::withMessages(['supplier_id' => 'Choose an active supplier.']);
            }
        }
        $d = validator($in, ['i_doc_date' => ['required', 'date'], 'i_disposition' => ['required', 'in:'.implode(',', array_keys(self::DISPOSITIONS))], 'i_reason' => ['nullable', 'string', 'max:500'],
            'i_notes' => ['nullable', 'string'], 'replacement_due_date' => ['nullable', 'date'], 'qc_inspector' => ['nullable', 'string', 'max:100'], 'comm_method' => ['nullable', 'string', 'max:100'], 'supplier_pic' => ['nullable', 'string', 'max:100'],
            'physical_return_date' => ['nullable', 'date'], 'expedition' => ['nullable', 'string', 'max:100'], 'cn_supplier_no' => ['nullable', 'string', 'max:100'], 'cn_received_date' => ['nullable', 'date']],
            [], ['i_doc_date' => 'Return date', 'i_disposition' => 'Disposition'])->validate();
        $rows = $source === 'QC_REJECT' ? $this->cleanQcLines((int) $pvId, $id, $lines) : $this->cleanStockLines($lines);
        $credit = $d['i_disposition'] === 'CREDIT_NOTE';

        return DB::connection('tm2pur')->transaction(function () use ($id, $d, $rows, $source, $pvId, $supplierId, $credit, $user) {
            $strip = fn ($v) => ($v ?? '') === '' ? null : $v;
            $header = ['i_doc_date' => $d['i_doc_date'], 'i_disposition' => $d['i_disposition'], 'i_reason' => (string) ($d['i_reason'] ?? ''), 'i_notes' => $strip($d['i_notes'] ?? null),
                'replacement_due_date' => $d['i_disposition'] === 'REPLACEMENT' ? $strip($d['replacement_due_date'] ?? null) : null, 'qc_inspector' => $source === 'QC_REJECT' ? $strip($d['qc_inspector'] ?? null) : null,
                'comm_method' => $strip($d['comm_method'] ?? null), 'supplier_pic' => $strip($d['supplier_pic'] ?? null), 'physical_return_date' => $strip($d['physical_return_date'] ?? null),
                'expedition' => $strip($d['expedition'] ?? null), 'cn_supplier_no' => $credit ? $strip($d['cn_supplier_no'] ?? null) : null, 'cn_received_date' => $credit ? $strip($d['cn_received_date'] ?? null) : null,
                'updated_by' => $user->login];
            if ($id) {
                $h = PrtReturn::findOrFail($id);
                $h->update($header);
                $h->lines()->delete();
            } else {
                $po = $pvId ? PvLine::where('pv_id', $pvId)->value('c_po_id') : null;
                $h = PrtReturn::create($header + ['i_supplier_id' => $supplierId, 'pv_id' => $pvId, 'po_id' => $po, 'i_source' => $source, 'status' => 'DRAFT', 'created_by' => $user->login]);
            }
            $n = 0;
            foreach ($rows as $r) {
                PrtLine::create(['prt_id' => $h->prt_id, 'pv_line_id' => $r['pv_line_id'], 'line_no' => ++$n, 'item_id' => $r['item_id'], 'c_item_code' => $r['code'], 'i_description' => $r['name'], 'i_qty' => $r['qty'], 'i_uom' => $r['uom'],
                    'c_unit_price' => $r['price'], 'c_line_value' => $r['qty'] * $r['price'], 'is_stock_moved' => $source === 'FROM_STOCK', 'i_lot_no' => $r['lot'], 'created_by' => $user->login, 'updated_by' => $user->login]);
            }
            $h->update(['c_credit_value' => $credit ? $h->lines()->sum('c_line_value') : 0]);

            return (int) $h->prt_id;
        });
    }

    /** Baris dari penerimaan QC (kunci id baris penerimaan): qty kosong dilewati, sisanya 0 < qty <= sisa reject, harga mengikuti penerimaan. */
    private function cleanQcLines(int $pvId, ?int $prtId, array $lines): array
    {
        $out = [];
        $errors = [];
        foreach (PvLine::where('pv_id', $pvId)->where('c_qty_rejected', '>', 0)->orderBy('line_no')->get() as $l) {
            $in = $lines[$l->pv_line_id] ?? null;
            $qty = $in ? Locale::input($in['qty'] ?? '') : null;
            if ($qty === null || (float) $qty == 0.0) {
                continue;
            }
            $max = $this->returnable($l, $prtId);
            if ((float) $qty < 0 || (float) $qty > $max + 0.0001) {
                $errors['lines'] = "Return qty of {$l->i_description} must be between zero and the returnable balance (".Locale::number($max, 3).').';

                continue;
            }
            $out[] = ['pv_line_id' => $l->pv_line_id, 'item_id' => $l->item_id, 'code' => $l->c_item_code, 'name' => $l->i_description, 'qty' => (float) $qty, 'uom' => $l->i_uom_stock, 'price' => (float) $l->c_unit_price,
                'lot' => trim((string) ($in['i_lot_no'] ?? '')) ?: null];
        }

        return $this->finish($out, $errors);
    }

    /** Baris dari stok: item bahan aktif dan terkonfirmasi, qty > 0, harga >= 0 (kosong = harga terakhir item; harga akhir wajib > 0). */
    private function cleanStockLines(array $lines): array
    {
        $out = [];
        $errors = [];
        foreach (array_values($lines) as $i => $in) {
            $n = $i + 1;
            $itemId = (int) ($in['item_id'] ?? 0);
            $qty = Locale::input($in['qty'] ?? '');
            if (! $itemId && ($qty === null || (float) $qty == 0.0)) {
                continue;
            }
            $m = $itemId ? MatRaw::where('activate', true)->where('stat_confirm', true)->find($itemId) : null;
            if (! $m) {
                $errors['lines'] = "Line {$n}: choose an item.";

                continue;
            }
            if ($qty === null || (float) $qty <= 0) {
                $errors['lines'] = "Line {$n}: return qty must be greater than zero.";

                continue;
            }
            $price = Locale::input($in['price'] ?? '');
            if ($price !== null && (float) $price < 0) {
                $errors['lines'] = "Line {$n}: the price cannot be negative.";

                continue;
            }
            $final = $price === null || (float) $price == 0.0 ? (float) ($m->last_unitprice ?: 0) : (float) $price;
            if ($final <= 0) {
                $errors['lines'] = "Line {$n}: enter a price, {$m->material_name} has no last price.";

                continue;
            }
            $out[] = ['pv_line_id' => null, 'item_id' => $m->material_id, 'code' => $m->material_code, 'name' => $m->material_name, 'qty' => (float) $qty, 'uom' => $m->uom_stock ?: 'PCS',
                'price' => $final, 'lot' => trim((string) ($in['i_lot_no'] ?? '')) ?: null];
        }

        return $this->finish($out, $errors);
    }

    private function finish(array $out, array $errors): array
    {
        if (! $out && ! $errors) {
            $errors['lines'] = 'Enter a return quantity on at least one line.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    /** Ajukan Draft: nomor NRB terbit, masuk flow PRT. Return pesan peringatan (kosong = masuk antrian). */
    public function submit(int $id, SecUser $user): string
    {
        $h = PrtReturn::with('lines')->findOrFail($id);
        if (! $this->editable($h)) {
            throw ValidationException::withMessages(['document' => 'Only a draft can be submitted.']);
        }
        if ($h->lines->isEmpty() || trim((string) $h->i_reason) === '' || ! $h->i_disposition) {
            throw ValidationException::withMessages(['document' => 'Fill in the Main reason and at least one returned line before submitting.']);
        }
        DB::connection('tm2pur')->transaction(fn () => $h->update(['doc_no' => PrtReturn::nextDocNo(), 'status' => 'SUBMITTED', 'updated_by' => $user->login]));
        $result = $this->flow->submit('PRT', $id, 'SUBMITTED', (float) $h->lines->sum('c_line_value'));

        return $result === null ? "No active approval setting for PRT: the document is Submitted but in nobody's queue. Contact the Admin." : '';
    }

    /** Pesan error keputusan (kosong = sukses): approve | reject | hold. */
    public function decide(int $id, string $action, string $note, SecUser $user): string
    {
        $h = PrtReturn::findOrFail($id);
        $pending = $this->flow->pendingForUser('PRT', $user->getAuthIdentifier())->firstWhere('doc_id', $id);
        if (! $pending || $h->status !== 'SUBMITTED') {
            return 'This document is not waiting for your approval.';
        }
        $amount = (float) $pending->amount_snapshot;
        if ($action === 'hold') {
            if (trim($note) === '') {
                return 'A note/question is required to Request Info.';
            }
            $this->flow->hold('PRT', $id, $pending->flow_step_id, $note, $amount);

            return '';
        }
        if ($action === 'reject') {
            if (mb_strlen(trim($note)) < 10) {
                return 'Rejection reason is required, minimum 10 characters.';
            }
            $r = $this->flow->reject('PRT', $id, $pending->flow_step_id, $note, $amount);
        } else {
            $r = $this->flow->approve('PRT', $id, $pending->flow_step_id, $amount);
        }
        $h->update(['status' => $r['status'], 'updated_by' => $user->login]);

        return '';
    }

    /** Batalkan Draft dengan alasan (min. 10 karakter). */
    public function cancel(int $id, string $reason, SecUser $user): void
    {
        $h = PrtReturn::findOrFail($id);
        if (! $this->editable($h)) {
            throw ValidationException::withMessages(['document' => 'Only a draft can be cancelled.']);
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['cancel_reason' => 'Cancellation reason is required, minimum 10 characters.']);
        }
        $h->update(['status' => 'CANCELLED', 'cancel_reason' => trim($reason), 'updated_by' => $user->login]);
    }

    /** Selesai: barang pengganti / nota kredit sudah diterima. */
    public function complete(int $id, SecUser $user): void
    {
        $h = PrtReturn::findOrFail($id);
        if ($h->status !== 'CONFIRMED') {
            throw ValidationException::withMessages(['document' => 'Only a confirmed return can be completed.']);
        }
        $h->update(['status' => 'COMPLETED', 'updated_by' => $user->login]);
    }

    /** Salinan Draft baru (sumber, supplier, disposisi, alasan dan baris yang sama). Return id baru. */
    public function duplicate(int $id, SecUser $user): int
    {
        $h = PrtReturn::with('lines')->findOrFail($id);
        $in = ['source' => $h->i_source, 'pv_id' => $h->pv_id, 'supplier_id' => $h->i_supplier_id, 'i_doc_date' => now()->toDateString(), 'i_disposition' => $h->i_disposition, 'i_reason' => $h->i_reason, 'i_notes' => $h->i_notes,
            'replacement_due_date' => $h->replacement_due_date?->toDateString(), 'qc_inspector' => $h->qc_inspector, 'comm_method' => $h->comm_method, 'supplier_pic' => $h->supplier_pic, 'expedition' => $h->expedition];
        $lines = $h->i_source === 'QC_REJECT'
            ? $h->lines->mapWithKeys(fn ($l) => [$l->pv_line_id => ['qty' => (float) $l->i_qty, 'i_lot_no' => $l->i_lot_no]])->all()
            : $h->lines->map(fn ($l) => ['item_id' => $l->item_id, 'qty' => (float) $l->i_qty, 'price' => (float) $l->c_unit_price, 'i_lot_no' => $l->i_lot_no])->all();

        return $this->save(null, $in, $lines, $user);
    }

    /** Riwayat tab History: dokumen + keputusan approval, terbaru di atas. */
    public function history(PrtReturn $h): array
    {
        $rows = [['at' => $h->created_at?->toDateTimeString(), 'by' => $h->created_by, 'action' => 'Created', 'note' => '']];
        if ($h->updated_at && $h->updated_at != $h->created_at) {
            $rows[] = ['at' => $h->updated_at->toDateTimeString(), 'by' => $h->updated_by, 'action' => 'Last modified', 'note' => ''];
        }
        foreach ($this->flow->history('PRT', (int) $h->prt_id) as $f) {
            $rows[] = ['at' => $f->acted_at?->toDateTimeString(), 'by' => $f->created_by ?? 'system', 'action' => ucfirst(strtolower((string) $f->action)), 'note' => (string) $f->notes];
        }
        if ($h->status === 'CANCELLED') {
            $rows[] = ['at' => $h->updated_at ? Carbon::parse($h->updated_at)->toDateTimeString() : null, 'by' => $h->updated_by, 'action' => 'Cancelled', 'note' => (string) $h->cancel_reason];
        }
        usort($rows, fn ($a, $b) => strcmp((string) $b['at'], (string) $a['at']));

        return $rows;
    }
}
