<?php

/*
 * REMARK
 * Tujuan       : Penyimpan dokumen stok manual dari layar v2 inv-stock-doc-form: Stock In (RM, FG, SFG), Stock Out (RM, SFG) dan Return from Production (RM, SFG); validasi header dan baris, batch (baru untuk Stock In, pilih yang ada untuk Stock In dan Stock Out), simpan Draft, Confirm = posting ke Kartu Stok, batalkan Draft. Tidak menulis tabel stok sendiri.
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::inventory.stock-in dan stock-out (diarsipkan di tmerp_unused/inventory_stock_in_v1_2026-10-19.blade.php, inventory_stock_out_v1_2026-10-19.blade.php dan inventory_stock_return_v1_2026-10-19.blade.php). Stock In: kategori STOCK atau BB, gudang wajib, minimal satu baris, qty > 0, bin wajib, batch baru wajib kedaluwarsa bila item punya umur simpan. Stock Out: gudang sumber, departemen penerima wajib, proses tujuan teks bebas, tanpa harga (nilai = rata-rata berjalan saat posting), FG tidak boleh keluar (hanya lewat Delivery Order). Return: baris diambil dari Stock Out POSTED yang belum dibatalkan dan masih punya sisa (qty - qty_returned), qty retur <= sisa, GOOD ke bin usable gudang tujuan, SCRAP ke bin non-usable dengan Description wajib, harga = rata-rata kartu baris sumber. Penulisan dokumen lewat StockDocumentService, posting lewat StockPostingService, batch lewat BatchService.
 * Input        : Jenis dokumen (in / out / return), kelas stok, id dokumen (bila Draft tersimpan), isian header, baris form (item_id, batch_id, new_lot / new_production / new_expiry (hanya in), location_id, qty, uom_code, unit_price (hanya in), lot_no); Return: source_doc_id dan baris berkunci id baris Stock Out sumber (qty, return_condition, location_id, line_note), user.
 * Output       : Dokumen Draft di tm2inv.rm_stock / fg_stock / sfg_stock + baris (Return: doc_cat RETURN, ref_doc_id = Stock Out sumber); batch baru di inv_batch (hanya in); hasil posting dari StockPostingService; ValidationException bila aturan dilanggar.
 * Dipakai oleh : routes/v2_stockdocs.php (v2.inv.stockdoc.*), layar inv-stock-doc-form, grid StockDocGrid (kelas dan jenis yang boleh), tes StockDocEntryServiceTest.
 * Status       : Aktif. Confirm menyimpan isian form lebih dulu lalu memposting (v1 hanya memposting Draft yang sudah tersimpan dan membuang isian yang belum disimpan). Return: qty_returned baris sumber bertambah di dalam transaksi posting dan dipulihkan saat Return dibatalkan (StockPostingService, StockReversalService); Stock Out yang sudah punya Return tidak boleh dibatalkan. Jenis Adjustment menyusul.
 */

namespace App\Services\Inventory;

use App\Models\Inventory\FgStock;
use App\Models\Inventory\InvBatch;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\RmStockcard;
use App\Models\Inventory\RmStockD;
use App\Models\Inventory\SfgStock;
use App\Models\Inventory\SfgStockcard;
use App\Models\Inventory\SfgStockD;
use App\Models\Master\OrgDepartment;
use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Models\Sys\SecUser;
use App\Support\V2\Data\StockEntry;
use Carbon\Carbon;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StockDocEntryService
{
    public const CLASSES = ['RM' => 'Raw Material', 'FG' => 'Finished Goods', 'SFG' => 'Semi Finished Goods'];

    /** jenis => judul, awalan nomor, arah (doc_type), kelas yang boleh, kategori dokumen */
    public const KINDS = [
        'in' => ['title' => 'Stock In', 'prefix' => 'IN', 'doc_type' => 1, 'classes' => ['RM', 'FG', 'SFG'], 'cats' => ['STOCK' => 'STOCK (normal stock in)', 'BB' => 'BB (opening balance)']],
        'out' => ['title' => 'Stock Out', 'prefix' => 'OUT', 'doc_type' => -1, 'classes' => ['RM', 'SFG'], 'cats' => ['STOCK' => 'STOCK']],
        'return' => ['title' => 'Return from Production', 'prefix' => 'RTN', 'doc_type' => 1, 'classes' => ['RM', 'SFG'], 'cats' => ['RETURN' => 'RETURN']],
    ];

    private const HEADERS = ['RM' => RmStock::class, 'FG' => FgStock::class, 'SFG' => SfgStock::class];

    private const DETAILS = ['RM' => RmStockD::class, 'SFG' => SfgStockD::class];

    private const CARDS = ['RM' => RmStockcard::class, 'SFG' => SfgStockcard::class];

    public function __construct(private StockDocumentService $docs, private StockPostingService $posting) {}

    /** Jenis dan kelas yang punya form v2 (FG tidak punya Stock Out). */
    public static function supports(string $kind, string $class): bool
    {
        return isset(self::KINDS[$kind]) && in_array($class, self::KINDS[$kind]['classes'], true);
    }

    /** Dokumen milik jenis ini? (sama dengan daftar StockDocGrid: Stock In juga memuat dokumen otomatis dan pembalik; Return dan adjustment bukan.) */
    public static function ownsDocument(string $kind, object $h): bool
    {
        return match ($kind) {
            'in' => (int) $h->doc_type === 1 && $h->doc_cat !== 'RETURN',
            'out' => (int) $h->doc_type === -1 && ! in_array($h->doc_cat, ['AD', 'OP'], true),
            'return' => $h->doc_cat === 'RETURN',
            default => false,
        };
    }

    /** Simpan Draft (dokumen baru bernomor saat ini). Return id dokumen. */
    public function save(string $kind, string $class, ?int $docId, array $in, array $lines, SecUser $user): int
    {
        abort_unless(self::supports($kind, $class), 404);
        if ($kind === 'return') {
            return $this->saveReturn($class, $docId, $in, $lines, $user);
        }
        $cfg = self::KINDS[$kind];
        if ($docId) {
            $h = $this->header($class, $docId);
            if ($h->stat_confirm || $h->stat_cancel || $h->flow_status !== 'DRAFT') {
                throw ValidationException::withMessages(['document' => 'This document is already posted or cancelled and cannot be changed.']);
            }
        }

        $rules = ['doc_date' => ['required', 'date'], 'warehouse_id' => ['required', 'integer', 'exists:tm2mst.wh_warehouse,id_warehouse'], 'notes' => ['nullable', 'string']];
        $names = ['doc_date' => 'Date', 'warehouse_id' => $kind === 'out' ? 'Source warehouse' : 'Warehouse'];
        if ($kind === 'in') {
            $rules['doc_cat'] = ['required', 'in:'.implode(',', array_keys($cfg['cats']))];
            $names['doc_cat'] = 'Category';
        } else {
            $rules['issued_to_dept'] = ['required', 'integer', 'exists:tm2mst.org_department,department_id'];
            $rules['process_code'] = ['nullable', 'string', 'max:100'];
            $names['issued_to_dept'] = 'Issued to (department)';
        }
        $d = validator($in, $rules, [], $names)->validate();

        $rows = $this->cleanLines($kind, $class, (int) $d['warehouse_id'], $lines);

        return DB::connection('tm2inv')->transaction(function () use ($kind, $cfg, $class, $docId, $d, $rows, $user) {
            $docNo = $docId ? (string) $this->header($class, $docId)->doc_no : $this->docs->nextDocNo($class, $cfg['prefix']);
            $itemCol = StockEntry::LINE_ITEM[$class];
            $out = [];
            foreach ($rows as $r) {
                $batchId = $r['batch_id'];
                $lot = $r['lot'];
                if ($r['new_batch']) {
                    $batch = BatchService::ensure($class, $r['item_id'], $r['new_lot'] ?: null, ['item_code' => $r['item_code'], 'item_name' => $r['item_name'],
                        'production_date' => $r['new_production'], 'expiry_date' => $r['new_expiry']], $user->login);
                    $batchId = $batch->id_batch;
                    $lot = (string) $batch->supplier_lot;
                } elseif ($batchId) {
                    $lot = (string) (InvBatch::query()->whereKey($batchId)->value('supplier_lot') ?? '');
                }
                $line = [$itemCol => $r['item_id'], 'qty' => $r['qty'], 'uom_code' => $r['uom_code'], 'lot_no' => $lot ?: null, 'batch_id' => $batchId ?: null, 'location_id' => $r['location_id']];
                $out[] = $kind === 'out' ? $line + ['action_type' => 'ISSUE'] : $line + ['unit_price' => $r['unit_price']];
            }
            $header = ['doc_no' => $docNo, 'doc_date' => $d['doc_date'], 'doc_type' => $cfg['doc_type'], 'ref_doc_type' => 'MANUAL', 'warehouse_id' => $d['warehouse_id'],
                'notes' => ($d['notes'] ?? '') !== '' ? $d['notes'] : null];
            $header += $kind === 'in' ? ['doc_cat' => $d['doc_cat']] : ['doc_cat' => 'STOCK', 'issued_to_dept' => $d['issued_to_dept'], 'process_code' => ($d['process_code'] ?? '') !== '' ? $d['process_code'] : null];

            return $this->docs->saveDraft($class, $docId, $header, $out, $user->login);
        });
    }

    /** Confirm = posting ke Kartu Stok. @return array{posted: bool, error: ?string, stock_warning?: ?string, journal_warning?: ?string} */
    public function post(string $class, int $docId, SecUser $user): array
    {
        // Return: sisa dicek ulang saat Confirm (Return lain atau pembatalan Stock Out bisa terjadi sejak Draft disimpan).
        if ($error = $this->returnRefusal($class, $docId)) {
            return ['posted' => false, 'error' => $error];
        }

        return $this->posting->postDocument($class, $docId, $user->login);
    }

    /** Batalkan Draft (dokumen Posted dikoreksi lewat pembalik, bukan di sini). */
    public function cancelDraft(string $class, int $docId, SecUser $user): void
    {
        $this->docs->cancel($class, $docId, $user->login);
    }

    /** [state form, stage] dari status dokumen. */
    public function formState(object $h): array
    {
        return match (true) {
            (bool) $h->reversed_by_doc_id => ['cancelled', 'Reversed'],
            (bool) $h->stat_cancel || $h->flow_status === 'CANCELLED' => ['cancelled', null],
            $h->flow_status === 'POSTED' => ['confirmed', 'Posted'],
            (bool) $h->stat_confirm => ['draft', 'Locked (not posted)'],
            default => ['draft', null],
        };
    }

    /** Dokumen Draft yang masih boleh diedit. */
    public function editable(object $h): bool
    {
        return $h->flow_status === 'DRAFT' && ! $h->stat_confirm && ! $h->stat_cancel;
    }

    /** @return array<int, string> gudang aktif */
    public function warehouseOptions(): array
    {
        return WhWarehouse::where('activate', true)->orderBy('warehouse_code')->get()->mapWithKeys(fn ($w) => [$w->id_warehouse => $w->warehouse_code.' — '.$w->warehouse_name])->all();
    }

    /** @return array<int, string> departemen aktif (Stock Out: diserahkan kepada) */
    public function departmentOptions(): array
    {
        return OrgDepartment::where('activate', true)->orderBy('department_name')->pluck('department_name', 'department_id')->all();
    }

    /** Riwayat tab History: terbaru di atas. */
    public function history(object $h): array
    {
        $rows = [['at' => $h->create_log?->toDateTimeString(), 'by' => $h->create_by, 'action' => 'Created', 'note' => '']];
        if ($h->update_log && $h->update_log != $h->create_log) {
            $rows[] = ['at' => $h->update_log->toDateTimeString(), 'by' => $h->update_by, 'action' => 'Last modified', 'note' => ''];
        }
        if ($h->posted_at) {
            $rows[] = ['at' => Carbon::parse($h->posted_at)->toDateTimeString(), 'by' => $h->posted_by, 'action' => 'Posted', 'note' => 'Stock Card updated'];
        }
        if ($h->cancelled_at) {
            $rows[] = ['at' => Carbon::parse($h->cancelled_at)->toDateTimeString(), 'by' => $h->cancelled_by, 'action' => 'Cancelled', 'note' => (string) $h->cancel_reason];
        }

        return array_reverse($rows);
    }

    private function header(string $class, int $docId): object
    {
        return self::HEADERS[$class]::findOrFail($docId);
    }

    /**
     * Baris form -> baris bersih. Baris kosong (tanpa item dan qty) dibuang; sisanya wajib item, qty > 0, bin milik gudang dokumen.
     *
     * @return list<array<string, mixed>>
     */
    private function cleanLines(string $kind, string $class, int $warehouseId, array $lines): array
    {
        $num = fn ($v) => (float) (Locale::input($v) ?? 0);
        $out = [];
        $errors = [];
        foreach (array_values($lines) as $i => $l) {
            $n = $i + 1;
            $itemId = (int) ($l['item_id'] ?? 0);
            $qty = $num($l['qty'] ?? 0);
            if (! $itemId && $qty == 0.0) {
                continue;
            }
            $item = $itemId ? StockEntry::itemFind($class, $itemId) : null;
            if (! $item) {
                $errors['lines'] = "Line {$n}: choose an item.";

                continue;
            }
            if ($qty <= 0) {
                $errors['lines'] = "Line {$n}: Qty must be greater than zero.";

                continue;
            }
            $bin = ! empty($l['location_id']) ? WhLoc::query()->find((int) $l['location_id']) : null;
            if (! $bin || ! $bin->activate || ! $bin->is_usable || (int) $bin->warehouse_id !== $warehouseId) {
                $errors['lines'] = "Line {$n}: choose a usable bin of the document warehouse.";

                continue;
            }
            $batchId = (int) ($l['batch_id'] ?? 0);
            if ($batchId) {
                $b = InvBatch::query()->find($batchId);
                if (! $b || $b->stock_class !== $class || (int) $b->item_id !== $itemId) {
                    $errors['lines'] = "Line {$n}: the batch belongs to another item.";

                    continue;
                }
            }
            $newLot = $newProd = $newExp = null;
            $newBatch = false;
            if ($kind === 'in') {
                $newLot = trim((string) ($l['new_lot'] ?? ''));
                $newProd = $this->date($l['new_production'] ?? null);
                $newExp = $this->date($l['new_expiry'] ?? null);
                $newBatch = ! $batchId && ($newLot !== '' || $newProd || $newExp);
                if ($newBatch && ! $newExp && BatchService::needsExpiry($class, $itemId)) {
                    $errors['lines'] = "Line {$n}: this item has a shelf life, fill in the expiry date of the new batch.";

                    continue;
                }
            }
            $out[] = ['item_id' => $itemId, 'item_code' => $item['code'], 'item_name' => $item['name'], 'qty' => $qty, 'uom_code' => trim((string) ($l['uom_code'] ?? '')) ?: $item['uom'],
                'unit_price' => $kind === 'in' ? max(0.0, $num($l['unit_price'] ?? 0)) : 0.0, 'location_id' => (int) $bin->location_id, 'batch_id' => $batchId, 'lot' => trim((string) ($l['lot_no'] ?? '')),
                'new_batch' => $newBatch, 'new_lot' => $newLot, 'new_production' => $newProd, 'new_expiry' => $newExp];
        }
        if (! $out && ! $errors) {
            $errors['lines'] = 'Add at least one line.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    // ---- Return from Production ----

    /** Stock Out POSTED yang belum dibatalkan (sumber Return), dengan baris; null bila bukan. $liveOnly = false: sumber apa pun statusnya (menampilkan Return yang sudah tersimpan). */
    public function returnSource(string $class, int $sourceId, bool $liveOnly = true): ?object
    {
        $q = self::HEADERS[$class]::with('lines')->where('doc_cat', 'STOCK')->where('doc_type', -1);

        return ($liveOnly ? $q->where('flow_status', 'POSTED')->where('stat_cancel', false)->whereNull('reversed_by_doc_id') : $q)->find($sourceId);
    }

    private function returnable(object $sourceLine): float
    {
        return max(0.0, (float) $sourceLine->qty - (float) ($sourceLine->qty_returned ?? 0));
    }

    /** Sumber yang masih bisa diretur: Stock Out POSTED dengan sisa di minimal satu baris (kartu awal Return baru). */
    public function returnableSource(string $class, int $sourceId): ?object
    {
        $h = $this->returnSource($class, $sourceId);

        return $h && $h->lines->contains(fn ($l) => $this->returnable($l) > 0.0001) ? $h : null;
    }

    /** @return list<array<string, mixed>> Stock Out yang bisa diretur, terbaru dulu */
    public function returnableSources(string $class): array
    {
        return self::HEADERS[$class]::with('lines')->where('doc_cat', 'STOCK')->where('doc_type', -1)->where('flow_status', 'POSTED')->where('stat_cancel', false)->whereNull('reversed_by_doc_id')
            ->orderByDesc('doc_id')->get()->map(fn ($d) => ['id' => (int) $d->doc_id, 'doc_no' => (string) $d->doc_no, 'date' => $d->doc_date?->toDateString(), 'process' => (string) $d->process_code,
                'lines' => $d->lines->filter(fn ($l) => $this->returnable($l) > 0.0001)->count(), 'balance' => (float) $d->lines->sum(fn ($l) => $this->returnable($l))])
            ->filter(fn ($r) => $r['balance'] > 0.0001)->values()->all();
    }

    /**
     * Baris form Return: satu baris per baris Stock Out sumber (kunci = id baris sumber). Dokumen tersimpan menampilkan qty, kondisi, bin dan Description-nya.
     *
     * @return list<array<string, mixed>>
     */
    public function returnRows(string $class, object $source, ?object $doc = null): array
    {
        $col = StockEntry::LINE_ITEM[$class];
        $mine = $doc ? $doc->lines->keyBy('source_detail_id') : collect();
        $rows = [];
        foreach ($source->lines as $l) {
            $own = $mine->get($l->getKey());
            if ($this->returnable($l) <= 0.0001 && ! $own) {
                continue;
            }
            $bin = $own?->location_id ? StockEntry::binFind($own->location_id) : null;
            $rows[] = ['source_detail_id' => $l->getKey(), 'item' => StockEntry::itemFind($class, $l->{$col})['label'] ?? '#'.$l->{$col}, 'issued' => (float) $l->qty, 'returnable' => $this->returnable($l),
                'uom_code' => (string) $l->uom_code, 'qty' => $own ? (float) $own->qty : '', 'return_condition' => $own->return_condition ?? 'GOOD', 'location_id' => $own->location_id ?? '',
                'location_id_label' => $bin['label'] ?? '', 'line_note' => (string) ($own->line_note ?? '')];
        }

        return $rows;
    }

    private function saveReturn(string $class, ?int $docId, array $in, array $lines, SecUser $user): int
    {
        $cfg = self::KINDS['return'];
        if ($docId) {
            $h = $this->header($class, $docId);
            if ($h->stat_confirm || $h->stat_cancel || $h->flow_status !== 'DRAFT') {
                throw ValidationException::withMessages(['document' => 'This document is already posted or cancelled and cannot be changed.']);
            }
            $sourceId = (int) $h->ref_doc_id;
        } else {
            $sourceId = (int) ($in['source_doc_id'] ?? 0);
        }
        $d = validator($in, ['doc_date' => ['required', 'date'], 'warehouse_id' => ['required', 'integer', 'exists:tm2mst.wh_warehouse,id_warehouse'], 'notes' => ['nullable', 'string']],
            [], ['doc_date' => 'Date', 'warehouse_id' => 'Destination warehouse'])->validate();
        $source = $this->returnableSource($class, $sourceId);
        if (! $source) {
            throw ValidationException::withMessages(['source_doc_id' => 'Choose a posted Stock Out that still has a returnable balance.']);
        }
        $rows = $this->cleanReturnLines($class, $source, (int) $d['warehouse_id'], $lines);

        return DB::connection('tm2inv')->transaction(function () use ($cfg, $class, $docId, $d, $rows, $source, $user) {
            $docNo = $docId ? (string) $this->header($class, $docId)->doc_no : $this->docs->nextDocNo($class, $cfg['prefix']);
            $itemCol = StockEntry::LINE_ITEM[$class];
            $out = array_map(fn ($r) => [$itemCol => $r['item_id'], 'qty' => $r['qty'], 'uom_code' => $r['uom_code'], 'lot_no' => $r['lot_no'], 'batch_id' => $r['batch_id'], 'location_id' => $r['location_id'],
                'action_type' => 'RETURN', 'return_condition' => $r['condition'], 'line_note' => $r['note'], 'source_detail_id' => $r['source_detail_id'], 'unit_price' => $r['unit_price']], $rows);

            return $this->docs->saveDraft($class, $docId, ['doc_no' => $docNo, 'doc_date' => $d['doc_date'], 'doc_type' => 1, 'doc_cat' => 'RETURN', 'ref_doc_type' => 'OUT', 'ref_doc_id' => $source->doc_id,
                'ref_doc_no' => $source->doc_no, 'warehouse_id' => $d['warehouse_id'], 'process_code' => $source->process_code ?: null, 'notes' => ($d['notes'] ?? '') !== '' ? $d['notes'] : null], $out, $user->login);
        });
    }

    /**
     * Baris Return (kunci = id baris Stock Out sumber). Baris tanpa qty dilewati; sisanya qty <= sisa, GOOD ke bin usable gudang tujuan, SCRAP ke bin non-usable dengan Description.
     *
     * @return list<array<string, mixed>>
     */
    private function cleanReturnLines(string $class, object $source, int $warehouseId, array $lines): array
    {
        $num = fn ($v) => (float) (Locale::input($v) ?? 0);
        $refName = $this->posting->referenceName($class);
        $col = StockEntry::LINE_ITEM[$class];
        $out = [];
        $errors = [];
        foreach ($source->lines as $src) {
            $l = $lines[$src->getKey()] ?? null;
            $qty = $l ? $num($l['qty'] ?? 0) : 0.0;
            if (! $l || $qty == 0.0) {
                continue;
            }
            $name = StockEntry::itemFind($class, $src->{$col})['name'] ?? '#'.$src->{$col};
            if ($qty < 0 || $qty > $this->returnable($src) + 0.0001) {
                $errors['lines'] = "Return qty of {$name} must be between zero and the returnable balance (".Locale::number($this->returnable($src), 3).').';

                continue;
            }
            $condition = ($l['return_condition'] ?? 'GOOD') === 'SCRAP' ? 'SCRAP' : 'GOOD';
            $note = trim((string) ($l['line_note'] ?? ''));
            if ($condition === 'SCRAP' && $note === '') {
                $errors['lines'] = "Description is required for scrap lines ({$name}).";

                continue;
            }
            $bin = ! empty($l['location_id']) ? WhLoc::query()->find((int) $l['location_id']) : null;
            $binOk = $bin && $bin->activate && ($condition === 'GOOD' ? $bin->is_usable && (int) $bin->warehouse_id === $warehouseId : ! $bin->is_usable);
            if (! $binOk) {
                $errors['lines'] = $condition === 'GOOD' ? "Choose a usable bin of the destination warehouse for {$name}." : "Choose a non-usable (scrap) bin for {$name}.";

                continue;
            }
            $out[] = ['source_detail_id' => $src->getKey(), 'item_id' => (int) $src->{$col}, 'qty' => $qty, 'uom_code' => (string) $src->uom_code, 'lot_no' => $src->lot_no, 'batch_id' => $src->batch_id,
                'location_id' => (int) $bin->location_id, 'condition' => $condition, 'note' => $note !== '' ? mb_substr($note, 0, 255) : null,
                'unit_price' => (float) (self::CARDS[$class]::where('reference_name', $refName)->where('reference_detail_id', $src->getKey())->value('avg_unitprice') ?? 0)];
        }
        if (! $out && ! $errors) {
            $errors['lines'] = 'Enter a return quantity on at least one line.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    /** Pesan bila Return Draft ini tidak boleh diposting sekarang (sumber dibatalkan atau sisa sudah dipakai Return lain); null = aman atau bukan Return. */
    private function returnRefusal(string $class, int $docId): ?string
    {
        $h = self::HEADERS[$class]::with('lines')->find($docId);
        if (! $h || $h->doc_cat !== 'RETURN') {
            return null;
        }
        if (! $this->returnSource($class, (int) $h->ref_doc_id)) {
            return 'The Stock Out of this Return is cancelled, so it can no longer be returned.';
        }
        foreach ($h->lines as $l) {
            $src = $l->source_detail_id ? self::DETAILS[$class]::find($l->source_detail_id) : null;
            if (! $src || (float) $l->qty > $this->returnable($src) + 0.0001) {
                return 'The Stock Out no longer has enough returnable balance for a line of this Return (another Return took it). Reduce the quantity and save again.';
            }
        }

        return null;
    }

    private function date(mixed $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        try {
            return Carbon::parse($v)->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['lines' => "Date '{$v}' is not valid."]);
        }
    }
}
