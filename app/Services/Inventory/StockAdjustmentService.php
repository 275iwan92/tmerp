<?php

/*
 * REMARK
 * Tujuan       : Penyimpan dan alur persetujuan dokumen Stock Adjustment (AD) dan Stock Opname (OP) untuk RM, FG, SFG dari layar v2 inv-stock-adj-form: validasi header dan baris, saldo sistem + harga rata-rata dibaca dari Kartu Stok saat Save, selisih = fisik - sistem, Description per baris (wajib bila selisih tidak nol), Submit ke flow approval (nilai absolut selisih menentukan level), Approve (level terakhir = posting) dan Reject (kembali ke pembuat untuk revisi), tarik semua item bersaldo (Opname cakupan penuh), Cancel Draft, Duplicate. Tidak menulis tabel stok sendiri.
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::inventory.stock-adjustment (diarsipkan di tmerp_unused/inventory_stock_adjustment_v1_2026-10-19.blade.php); flow_config modul AD dan OP (Setup > Setting Approval), Kartu Stok rm / fg / sfg_stockcard (baris terakhir per item + bin), ItemAverageCost. Penulisan dokumen lewat StockDocumentService, posting lewat StockPostingService, keputusan lewat FlowEngineService.
 * Input        : Kelas stok, jenis (AD / OP), id dokumen, isian header (tanggal, gudang, alasan, catatan; Opname: petugas, mulai, selesai, saksi, cakupan penuh), baris (item_id, location_id, qty_physical, line_note, uom_code, lot_no), user, keputusan (approve / reject / hold + catatan).
 * Output       : Dokumen di tm2inv.rm_stock / fg_stock / sfg_stock (doc_cat AD atau OP) + baris (qty = fisik - sistem bertanda, qty_system, qty_physical, unit_price, line_note); flow_log (tm2mst); posting ke Kartu Stok oleh level terakhir; ValidationException bila aturan dilanggar.
 * Dipakai oleh : routes/v2_stockdocs.php (v2.inv.stockadj.*), layar inv-stock-adj-form, App\Support\V2\Data\ApprovalCenter (daftar dan keputusan), grid StockDocGrid (adj), tes StockAdjustmentServiceTest.
 * Status       : Aktif (Master T5d, 2026-10-19). Baris wajib Description bila selisih tidak nol dan satu item + bin tidak boleh dua kali dalam satu dokumen (v1 tidak memeriksa keduanya). Dokumen buatan Count Task (ref COUNT_TASK) hanya-baca.
 */

namespace App\Services\Inventory;

use App\Models\Inventory\FgStock;
use App\Models\Inventory\FgStockcard;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\RmStockcard;
use App\Models\Inventory\SfgStock;
use App\Models\Inventory\SfgStockcard;
use App\Models\Master\WhLoc;
use App\Models\Sys\SecUser;
use App\Services\Flow\FlowEngineService;
use App\Support\V2\Data\StockEntry;
use Carbon\Carbon;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StockAdjustmentService
{
    /** jenis => judul, awalan nomor */
    public const KINDS = ['AD' => ['title' => 'Stock Adjustment', 'prefix' => 'ADJ'], 'OP' => ['title' => 'Stock Opname', 'prefix' => 'OPN']];

    public const REASONS = ['COUNT_ERROR' => 'Count / input error', 'DAMAGED' => 'Damaged', 'LOST' => 'Lost', 'FOUND' => 'Found (more than recorded)', 'EXPIRED' => 'Expired', 'OTHER' => 'Other'];

    public const EDITABLE = ['DRAFT', 'REVISION_NEEDED'];

    public const WAITING = ['SUBMITTED', 'APPROVED_L1', 'APPROVED_L2'];

    private const HEADERS = ['RM' => RmStock::class, 'FG' => FgStock::class, 'SFG' => SfgStock::class];

    private const CARDS = ['RM' => RmStockcard::class, 'FG' => FgStockcard::class, 'SFG' => SfgStockcard::class];

    public function __construct(private StockDocumentService $docs, private StockPostingService $posting, private FlowEngineService $flow) {}

    public static function supports(string $class): bool
    {
        return isset(self::HEADERS[$class]);
    }

    /** Dokumen milik layar ini: Adjustment atau Opname (termasuk buatan Count Task, hanya-baca). */
    public static function ownsDocument(object $h): bool
    {
        return in_array($h->doc_cat, ['AD', 'OP'], true);
    }

    /** Draft atau dikembalikan untuk revisi, bukan dokumen proses lain. */
    public function editable(object $h): bool
    {
        return in_array($h->flow_status, self::EDITABLE, true) && ! $h->stat_confirm && ! $h->stat_cancel && ($h->ref_doc_type ?: 'MANUAL') === 'MANUAL';
    }

    /** [state form, stage] dari status dokumen. */
    public function formState(object $h): array
    {
        return match (true) {
            (bool) $h->stat_cancel || $h->flow_status === 'CANCELLED' => ['cancelled', null],
            $h->flow_status === 'POSTED' => ['confirmed', 'Posted'],
            in_array($h->flow_status, self::WAITING, true) => ['approval', 'Waiting approval'],
            $h->flow_status === 'REJECTED' => ['rejected', 'Rejected'],
            $h->flow_status === 'REVISION_NEEDED' => ['draft', 'Revision needed'],
            default => ['draft', null],
        };
    }

    /** @return array<int, string> alasan; nilai lama di luar daftar tetap tampil */
    public function reasonOptions(?string $current = null): array
    {
        return self::REASONS + ($current && ! isset(self::REASONS[$current]) ? [$current => $current] : []);
    }

    /** Simpan Draft (dokumen baru bernomor saat ini). Return id dokumen. */
    public function save(string $class, ?int $docId, array $in, array $lines, SecUser $user): int
    {
        abort_unless(self::supports($class), 404);
        if ($docId) {
            $h = $this->header($class, $docId);
            if (! $this->editable($h)) {
                throw ValidationException::withMessages(['document' => 'This document is in approval, posted or cancelled and cannot be changed.']);
            }
            $kind = $h->doc_cat;
        } else {
            $kind = (string) ($in['doc_kind'] ?? '');
            if (! isset(self::KINDS[$kind])) {
                throw ValidationException::withMessages(['doc_kind' => 'Choose Adjustment or Stock Opname.']);
            }
        }

        $rules = ['doc_date' => ['required', 'date'], 'warehouse_id' => ['required', 'integer', 'exists:tm2mst.wh_warehouse,id_warehouse'], 'reason_code' => ['required', 'string', 'max:50'], 'notes' => ['nullable', 'string']];
        $names = ['doc_date' => 'Date', 'warehouse_id' => 'Warehouse', 'reason_code' => 'Reason'];
        if ($kind === 'OP') {
            $rules += ['opn_officer' => ['required', 'string', 'max:100'], 'opn_start' => ['required', 'date'], 'opn_stop' => ['required', 'date'], 'opn_witness' => ['required', 'string', 'max:100'], 'full_scope' => ['nullable']];
            $names += ['opn_officer' => 'Count officer', 'opn_start' => 'Count start', 'opn_stop' => 'Count stop', 'opn_witness' => 'Witness'];
        }
        $d = validator($in, $rules, [], $names)->validate();
        $rows = $this->cleanLines($class, (int) $d['warehouse_id'], $lines);

        return DB::connection('tm2inv')->transaction(function () use ($class, $docId, $kind, $d, $rows, $user) {
            $docNo = $docId ? (string) $this->header($class, $docId)->doc_no : $this->docs->nextDocNo($class, self::KINDS[$kind]['prefix']);
            $itemCol = StockEntry::LINE_ITEM[$class];
            $out = array_map(fn ($r) => [$itemCol => $r['item_id'], 'qty' => $r['physical'] - $r['system'], 'qty_system' => $r['system'], 'qty_physical' => $r['physical'], 'uom_code' => $r['uom_code'], 'lot_no' => $r['lot_no'],
                'location_id' => $r['location_id'], 'unit_price' => $r['price'], 'action_type' => 'ADJUST', 'line_note' => $r['note']], $rows);
            $header = ['doc_no' => $docNo, 'doc_date' => $d['doc_date'], 'doc_type' => -1, 'doc_cat' => $kind, 'ref_doc_type' => 'MANUAL', 'warehouse_id' => $d['warehouse_id'], 'reason_code' => $d['reason_code'],
                'notes' => ($d['notes'] ?? '') !== '' ? $d['notes'] : null, 'opn_officer' => null, 'opn_start' => null, 'opn_stop' => null, 'opn_witness' => null, 'full_scope' => false];
            if ($kind === 'OP') {
                $header = array_merge($header, ['opn_officer' => $d['opn_officer'], 'opn_start' => Carbon::parse($d['opn_start'])->format('Y-m-d H:i:s'), 'opn_stop' => Carbon::parse($d['opn_stop'])->format('Y-m-d H:i:s'),
                    'opn_witness' => $d['opn_witness'], 'full_scope' => ! empty($d['full_scope'])]);
            }

            return $this->docs->saveDraft($class, $docId, $header, $out, $user->login);
        });
    }

    /**
     * Baris form -> baris bersih. Baris kosong dibuang; sisanya wajib item, bin gudang dokumen, fisik >= 0; saldo sistem dan harga dibaca dari Kartu Stok.
     *
     * @return list<array<string, mixed>>
     */
    private function cleanLines(string $class, int $warehouseId, array $lines): array
    {
        $num = fn ($v) => Locale::input($v);
        $out = [];
        $seen = [];
        $errors = [];
        foreach (array_values($lines) as $i => $l) {
            $n = $i + 1;
            $itemId = (int) ($l['item_id'] ?? 0);
            $physical = $num($l['physical'] ?? '');
            if (! $itemId && ! ($l['location_id'] ?? '') && $physical === null) {
                continue;
            }
            $item = $itemId ? StockEntry::itemFind($class, $itemId) : null;
            if (! $item) {
                $errors['lines'] = "Line {$n}: choose an item.";

                continue;
            }
            $bin = ! empty($l['location_id']) ? WhLoc::query()->find((int) $l['location_id']) : null;
            if (! $bin || ! $bin->activate || (int) $bin->warehouse_id !== $warehouseId) {
                $errors['lines'] = "Line {$n}: choose a bin of the document warehouse.";

                continue;
            }
            if ($physical === null || (float) $physical < 0) {
                $errors['lines'] = "Line {$n}: enter the counted quantity (zero or more).";

                continue;
            }
            if (isset($seen[$itemId.':'.$bin->location_id])) {
                $errors['lines'] = "Line {$n}: this item and bin already appear on another line.";

                continue;
            }
            $seen[$itemId.':'.$bin->location_id] = true;
            $bal = $this->systemBalance($class, $itemId, (int) $bin->location_id);
            $note = trim((string) ($l['line_note'] ?? ''));
            if (abs((float) $physical - $bal['qty']) > 0.0001 && $note === '') {
                $errors['lines'] = "Line {$n}: Description is required when the count differs from the system balance.";

                continue;
            }
            $out[] = ['item_id' => $itemId, 'location_id' => (int) $bin->location_id, 'physical' => (float) $physical, 'system' => $bal['qty'], 'price' => $bal['price'],
                'uom_code' => trim((string) ($l['uom_code'] ?? '')) ?: $item['uom'], 'lot_no' => trim((string) ($l['lot_no'] ?? '')) ?: null, 'note' => $note !== '' ? mb_substr($note, 0, 255) : null];
        }
        if (! $out && ! $errors) {
            $errors['lines'] = 'Add at least one line.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    /** Saldo sistem (qty_eb baris kartu terakhir item + bin) dan harga rata-rata; keduanya 0 bila posisi belum pernah bergerak. */
    public function systemBalance(string $class, int $itemId, int $locationId): array
    {
        $row = self::CARDS[$class]::where(StockEntry::LINE_ITEM[$class], $itemId)->where('location_id', $locationId)->orderByDesc('doc_date')->orderByDesc('doc_id')->first();
        $price = ItemAverageCost::readerAvg($class, $itemId, null, $row?->avg_unitprice === null ? null : (float) $row->avg_unitprice);

        return ['qty' => (float) ($row->qty_eb ?? 0), 'price' => (float) ($price ?? 0)];
    }

    /**
     * Opname cakupan penuh: semua posisi (item + bin) bersaldo di gudang, baris kartu terakhir per posisi; fisik dikosongkan untuk diisi petugas.
     *
     * @return list<array<string, mixed>>
     */
    public function fullScopeRows(string $class, int $warehouseId): array
    {
        $itemKey = StockEntry::LINE_ITEM[$class];
        $card = self::CARDS[$class];
        // Baris TERAKHIR tiap posisi = urut (doc_date, doc_id), sama dengan view v_*_onhand: dokumen bertanggal mundur disisipkan di tengah kartu.
        $latest = $card::where('warehouse_id', $warehouseId)
            ->selectRaw("{$itemKey}, location_id, MAX(CONCAT(DATE_FORMAT(doc_date, '%Y%m%d%H%i%s'), LPAD(doc_id, 12, '0'))) as mx")
            ->groupBy($itemKey, 'location_id')->pluck('mx')->map(fn ($mx) => (int) substr((string) $mx, 14));
        $rows = $card::whereIn('doc_id', $latest)->where('qty_eb', '!=', 0)->get();
        $avg = ItemAverageCost::enabled() ? ItemAverageCost::currentAvgMany($class, $rows->pluck($itemKey)->unique()->all()) : [];

        return $rows->map(fn ($r) => ['item_id' => $r->{$itemKey}, 'location_id' => $r->location_id, 'physical' => '', 'line_note' => '', 'uom_code' => (string) $r->uom_code, 'lot_no' => (string) $r->lot_no,
            'qty_system' => (float) $r->qty_eb, 'unit_price' => (float) ($avg[$r->{$itemKey}.':'.($class === 'FG' ? (int) $r->parts_id : 0)] ?? $r->avg_unitprice)])->values()->all();
    }

    /**
     * Baris x-dl::lines dari dokumen tersimpan, atau dari isian form (old input / hasil tarik) dengan saldo sistem dibaca ulang.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(string $class, ?object $doc, ?array $input = null): array
    {
        $col = StockEntry::LINE_ITEM[$class];
        $raw = $input ?? ($doc ? $doc->lines->map(fn ($l) => ['item_id' => $l->{$col}, 'location_id' => $l->location_id, 'physical' => $l->qty_physical, 'line_note' => $l->line_note, 'uom_code' => $l->uom_code,
            'lot_no' => $l->lot_no, 'qty_system' => (float) $l->qty_system, 'unit_price' => (float) $l->unit_price])->all() : []);

        return array_map(function ($l) use ($class) {
            $itemId = (int) ($l['item_id'] ?? 0);
            $locId = (int) ($l['location_id'] ?? 0);
            $bin = $locId ? StockEntry::binFind($locId) : null;
            $sys = isset($l['qty_system']) ? ['qty' => (float) $l['qty_system'], 'price' => (float) ($l['unit_price'] ?? 0)] : ($itemId && $locId ? $this->systemBalance($class, $itemId, $locId) : ['qty' => 0.0, 'price' => 0.0]);

            return ['item_id' => $itemId ?: '', 'item_id_label' => $itemId ? (StockEntry::itemFind($class, $itemId)['label'] ?? '#'.$itemId) : '', 'location_id' => $locId ?: '', 'location_id_label' => $bin['label'] ?? '',
                'qty_system' => $sys['qty'], 'unit_price' => $sys['price'], 'physical' => ($l['physical'] ?? '') === null ? '' : ($l['physical'] ?? ''), 'line_note' => (string) ($l['line_note'] ?? ''),
                'uom_code' => (string) ($l['uom_code'] ?? ''), 'lot_no' => (string) ($l['lot_no'] ?? '')];
        }, $raw);
    }

    /** SUM(ABS(selisih x harga)): nilai yang menentukan level approval. */
    public function absoluteValue(object $doc): float
    {
        return (float) $doc->lines->sum(fn ($l) => abs(((float) $l->qty_physical - (float) $l->qty_system) * (float) $l->unit_price));
    }

    /** Ajukan Draft untuk approval; return pesan peringatan (kosong = masuk antrian). */
    public function submit(string $class, int $docId, SecUser $user): string
    {
        $h = $this->header($class, $docId)->load('lines');
        if (! $this->editable($h)) {
            throw ValidationException::withMessages(['document' => 'This document is already in approval, posted or cancelled.']);
        }
        $h->update(['flow_status' => 'SUBMITTED', 'update_by' => $user->login]);
        $result = $this->flow->submit($h->doc_cat, $docId, 'SUBMITTED', $this->absoluteValue($h));

        return $result === null ? "No active approval setting for {$h->doc_cat}: the document is Submitted but in nobody's queue. Contact the Admin." : '';
    }

    /** Pesan error keputusan (kosong = sukses). Approve di level terakhir memposting ke Kartu Stok. */
    public function decide(string $class, int $docId, string $action, string $note, SecUser $user): string
    {
        $h = $this->header($class, $docId);
        $kind = $h->doc_cat;
        $pending = $this->flow->pendingForUser($kind, $user->getAuthIdentifier())->firstWhere('doc_id', $docId);
        if (! $pending) {
            return 'This document is not waiting for your approval.';
        }
        if ($action === 'hold') {
            $this->flow->hold($kind, $docId, $pending->flow_step_id, $note, (float) $pending->amount_snapshot);

            return '';
        }
        if ($action === 'reject') {
            if (mb_strlen(trim($note)) < 10) {
                return 'Rejection reason is required, minimum 10 characters.';
            }
            $r = $this->flow->reject($kind, $docId, $pending->flow_step_id, $note, (float) $pending->amount_snapshot);
            $h->update(['flow_status' => $r['status'], 'update_by' => $user->login]);

            return '';
        }
        $r = $this->flow->approve($kind, $docId, $pending->flow_step_id, (float) $pending->amount_snapshot);
        if (! $r['final']) {
            $h->update(['flow_status' => $r['status'], 'update_by' => $user->login]);

            return '';
        }
        $post = $this->posting->postDocument($class, $docId, $user->login);

        return $post['posted'] ? '' : (string) $post['error'];
    }

    /** Batalkan Draft (dokumen Posted dikoreksi dengan Adjustment baru). */
    public function cancelDraft(string $class, int $docId, SecUser $user): void
    {
        $h = $this->header($class, $docId);
        if (! $this->editable($h)) {
            throw ValidationException::withMessages(['document' => 'Only a draft can be cancelled.']);
        }
        $this->docs->cancel($class, $docId, $user->login);
    }

    /** Salinan Draft baru (header, baris, hitungan fisik); saldo sistem dibaca ulang saat Save berikutnya. Return id dokumen baru. */
    public function duplicate(string $class, int $docId, SecUser $user): int
    {
        $h = $this->header($class, $docId)->load('lines');
        $in = ['doc_kind' => $h->doc_cat, 'doc_date' => now()->toDateString(), 'warehouse_id' => $h->warehouse_id, 'reason_code' => $h->reason_code ?: 'OTHER', 'notes' => $h->notes,
            'opn_officer' => $h->opn_officer ?: $user->login, 'opn_start' => $h->opn_start ?? now(), 'opn_stop' => $h->opn_stop ?? now(), 'opn_witness' => $h->opn_witness ?: '-', 'full_scope' => $h->full_scope];

        return $this->save($class, null, $in, $this->rows($class, $h->setRelation('lines', $h->lines), null), $user);
    }

    /** Riwayat tab History: dokumen + keputusan approval, terbaru di atas. */
    public function history(object $h): array
    {
        $rows = [['at' => $h->create_log?->toDateTimeString(), 'by' => $h->create_by, 'action' => 'Created', 'note' => '']];
        if ($h->update_log && $h->update_log != $h->create_log) {
            $rows[] = ['at' => $h->update_log->toDateTimeString(), 'by' => $h->update_by, 'action' => 'Last modified', 'note' => ''];
        }
        foreach ($this->flow->history((string) $h->doc_cat, (int) $h->doc_id) as $f) {
            $rows[] = ['at' => $f->acted_at?->toDateTimeString(), 'by' => $f->created_by ?? 'system', 'action' => $f->action.($f->status_to && $f->status_to !== $f->status_from ? ' → '.$f->status_to : ''), 'note' => (string) $f->notes];
        }
        if ($h->posted_at) {
            $rows[] = ['at' => Carbon::parse($h->posted_at)->toDateTimeString(), 'by' => $h->posted_by, 'action' => 'Posted', 'note' => 'Stock Card updated'];
        }
        if ($h->cancelled_at) {
            $rows[] = ['at' => Carbon::parse($h->cancelled_at)->toDateTimeString(), 'by' => $h->cancelled_by, 'action' => 'Cancelled', 'note' => (string) $h->cancel_reason];
        }
        usort($rows, fn ($a, $b) => strcmp((string) $b['at'], (string) $a['at']));

        return $rows;
    }

    private function header(string $class, int $docId): object
    {
        $h = self::HEADERS[$class]::findOrFail($docId);
        abort_unless(self::ownsDocument($h), 404);

        return $h;
    }
}
