<?php

namespace App\Support\V2\Data;

use App\Models\Master\FinCurrency;
use App\Models\Master\MatRaw;
use App\Models\Pur\PoLine;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PqLine;
use App\Models\Pur\PqQuotation;
use App\Models\Pur\PrLine;
use App\Models\Pur\PrLineAlloc;
use App\Models\Pur\PrRequisition;
use App\Services\Pur\PoBuilderService;
use App\Services\Pur\PqFileReader;
use App\Services\Pur\PqMatcher;
use App\Services\Pur\PrAllocationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

// Purchase Quotation v2 (user 2026-10-01): officer TIDAK mengetik penawaran -- upload file dari email supplier (PDF / Excel /
// foto), sistem membaca & mencocokkan ke supplier, baris PR acuan, dan Master Bahan; officer cukup cek baris kuning, Confirm,
// lalu pilih pemenang per baris PR di layar Bandingkan -> Buat PO. PO dari PQ tetap lewat pr_line_alloc (PR ikut ter-update,
// tidak bisa di-PO dobel) dan mencatat po_line.pq_line_id. PQ Closed saat semua barisnya sudah jadi PO; PQ yang kalah dibiarkan.
final class PurchaseQuotation
{
    public const HEADER = ['i_supplier_id', 'i_doc_date', 'i_quotation_valid_until', 'i_currency_id', 'i_fx_rate', 'i_payment_term', 'i_lead_time_day', 'i_notes'];

    public const STATUS_TONES = ['DRAFT' => 'neutral', 'CONFIRMED' => 'success', 'CLOSED' => 'info', 'CANCELLED' => 'danger'];

    public const READ_LABELS = ['READ' => 'Siap dipilih', 'CHECK' => 'Perlu cek', 'MANUAL' => 'Isi manual', 'FAILED' => 'Gagal dibaca'];

    public const READ_TONES = ['Siap dipilih' => 'success', 'Perlu cek' => 'warn', 'Isi manual' => 'warn', 'Gagal dibaca' => 'danger'];

    public static function rows(): array
    {
        $pqs = PqQuotation::with('lines')->orderByDesc('pq_id')->limit(500)->get();
        $sup = PurchaseOrder::suppliers($pqs->pluck('i_supplier_id')->filter()->unique()->all());
        $prs = PrRequisition::whereIn('pr_id', $pqs->pluck('i_pr_id')->filter()->unique())->pluck('doc_no', 'pr_id');

        return $pqs->map(fn ($q) => ['id' => $q->pq_id, 'no' => $q->doc_no ?: '(draft #'.$q->pq_id.')',
            'supplier' => $sup[$q->i_supplier_id] ?? (($q->i_supplier_text ?: '—').' '.t('(not matched)')), 'pr' => $prs[$q->i_pr_id] ?? '—',
            'date' => $q->i_doc_date?->toDateString(), 'valid' => $q->i_quotation_valid_until?->toDateString(), 'file' => $q->source_name ?? '—',
            'read' => self::READ_LABELS[$q->extract_status] ?? '—', 'lines' => $q->lines->count(), 'total' => (float) $q->c_total_idr, 'status' => $q->status,
            // Baris anak grid (Expand): nama item selalu tampil, tanpa harga.
            'items' => $q->lines->map(fn ($l) => ['name' => $l->i_description ?: $l->i_source_text, 'qty' => (float) $l->i_qty, 'unit' => $l->i_uom,
                'lead' => $l->i_lead_time_day, 'note' => $l->i_notes])->all()])->all();
    }

    // PR yang boleh ditawar: APPROVED / sebagian di-PO, belum batal (sama dgn openRequisitions v1).
    public static function prOptions(): array
    {
        return PrRequisition::whereIn('status', ['APPROVED', 'PARTIALLY_ORDERED'])->where('stat_cancel', false)->orderByDesc('pr_id')->limit(100)
            ->get()->mapWithKeys(fn ($p) => [$p->pr_id => $p->doc_no.' · '.str_replace(':n', (string) $p->lines()->count(), t(':n lines'))])->all();
    }

    public static function currencies(): array
    {
        return FinCurrency::where('activate', true)->orderBy('currency_code')->pluck('currency_code', 'id_currency')->all();
    }

    public static function prLineOptions(PqQuotation $pq): array
    {
        return $pq->i_pr_id ? PrLine::where('pr_id', $pq->i_pr_id)->orderBy('line_no')->get()
            ->mapWithKeys(fn ($l) => [$l->pr_line_id => '#'.$l->line_no.' '.$l->i_description.' · '.self::qty($l->i_qty).' '.$l->i_uom])->all() : [];
    }

    // Satu file = satu PQ Draft. File tak terbaca tetap jadi Draft (status Isi manual / Gagal dibaca) supaya tidak hilang.
    public static function upload(?int $prId, UploadedFile $file): PqQuotation
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, PqFileReader::ACCEPT, true)) {
            throw new InvalidArgumentException(str_replace([':file', ':ext'], [$file->getClientOriginalName(), $ext], t('File :file: type .:ext is not supported yet (PDF, Excel, CSV, JPG, PNG).')));
        }
        $login = Auth::user()->login;
        $path = $file->store('pur/pq/'.now()->format('Ym'), 'public');

        $status = 'MANUAL';
        $note = PqFileReader::aiEnabled() ? null : 'Pembaca AI belum aktif (ANTHROPIC_API_KEY kosong): hanya Excel/CSV yang dibaca otomatis.';
        $data = null;
        try {
            $data = PqFileReader::read(Storage::disk('public')->path($path), $ext);
        } catch (Throwable $e) {
            [$status, $note] = ['FAILED', mb_substr($e->getMessage(), 0, 250)];
        }

        return DB::connection('tm2pur')->transaction(function () use ($prId, $file, $path, $data, $status, $note, $login) {
            [$supplierId] = PqMatcher::supplier($data['supplier_name'] ?? null);
            $currency = ($data['currency'] ?? null) ? FinCurrency::where('currency_code', strtoupper($data['currency']))->value('id_currency') : null;
            $docDate = self::date($data['quote_date'] ?? null) ?? now()->toDateString();
            $valid = self::date($data['valid_until'] ?? null);

            $pq = PqQuotation::create([
                'i_doc_date' => $docDate, 'i_supplier_id' => $supplierId, 'i_supplier_text' => mb_substr((string) ($data['supplier_name'] ?? ''), 0, 150) ?: null,
                'i_pr_id' => $prId, 'i_quotation_valid_until' => $valid ?? Carbon::parse($docDate)->addDays(14)->toDateString(),
                'i_currency_id' => $currency ?? FinCurrency::where('currency_code', 'IDR')->value('id_currency'), 'i_fx_rate' => 1,
                'i_payment_term' => mb_substr((string) ($data['payment_term'] ?? ''), 0, 50) ?: null, 'i_lead_time_day' => $data['lead_time_day'] ?? null,
                'source_path' => $path, 'source_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'extract_status' => $status, 'extract_note' => $note, 'status' => 'DRAFT', 'created_by' => $login, 'updated_by' => $login,
            ]);

            $prLines = $prId ? PrLine::where('pr_id', $prId)->get() : collect();
            $sure = $supplierId !== null && $valid !== null;
            foreach (array_values($data['lines'] ?? []) as $i => $l) {
                $text = trim((string) ($l['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $m = PqMatcher::line($text, $prLines);
                $line = new PqLine([
                    'pq_id' => $pq->pq_id, 'line_no' => $i + 1, 'pr_line_id' => $m['pr_line_id'], 'item_id' => $m['item_id'],
                    'i_description' => mb_substr($text, 0, 255), 'i_source_text' => mb_substr($text, 0, 255), 'c_match_pct' => $m['pct'],
                    'i_qty' => (float) ($l['qty'] ?? 0), 'i_uom' => mb_substr((string) ($l['uom'] ?? ''), 0, 50) ?: '-', 'i_price_fx' => (float) ($l['price'] ?? 0),
                    'i_lead_time_day' => $l['lead_time_day'] ?? null, 'created_by' => $login, 'updated_by' => $login,
                ]);
                $line->recalc(1);
                $line->save();
                $sure = $sure && $m['pct'] >= PqMatcher::SURE && (float) $line->i_qty > 0 && (float) $line->i_price_fx > 0;
            }
            if ($data !== null) {
                $pq->extract_status = $pq->lines()->exists() ? ($sure ? 'READ' : 'CHECK') : 'FAILED';
                $pq->extract_note ??= $pq->extract_status === 'FAILED' ? 'Tidak ada baris harga yang terbaca dari file ini.' : null;
            }
            $pq->recalcTotal();
            $pq->save();

            return $pq;
        });
    }

    public static function save(PqQuotation $pq, array $in): void
    {
        abort_unless($pq->status === 'DRAFT', 422, t('The PQ is already Confirmed and cannot be changed.'));
        $login = Auth::user()->login;
        foreach (self::HEADER as $f) {
            if (array_key_exists($f, $in)) {
                $v = is_string($in[$f]) ? trim($in[$f]) : $in[$f];
                $pq->{$f} = $v === '' || $v === null ? ($f === 'i_fx_rate' ? 1 : null) : $v;
            }
        }
        $pq->i_quotation_valid_until ??= Carbon::parse($pq->i_doc_date ?? now())->addDays(14);
        $pq->updated_by = $login;
        $pq->save();

        $fx = (float) $pq->i_fx_rate ?: 1;
        $lines = $pq->lines()->get()->keyBy('pq_line_id');
        foreach (array_keys((array) ($in['remove'] ?? [])) as $id) {
            $lines->get((int) $id)?->delete();
        }
        $next = (int) $lines->max('line_no');
        foreach ((array) ($in['lines'] ?? []) as $id => $row) {
            $row = (array) $row;
            if (isset($in['remove'][$id])) {
                continue;
            }
            $line = is_numeric($id) ? $lines->get((int) $id) : null;
            if (! $line) {
                if (trim((string) ($row['i_description'] ?? '')) === '') {
                    continue;
                }
                $line = new PqLine(['pq_id' => $pq->pq_id, 'line_no' => ++$next, 'created_by' => $login]);
            }
            $prLine = self::int($row['pr_line_id'] ?? null);
            $item = self::int($row['item_id'] ?? null);
            if ($prLine !== $line->pr_line_id || $item !== $line->item_id) {
                $line->c_match_pct = ($prLine || $item) ? 100 : null;  // dipilih officer = pasti
            }
            if ($prLine && ! $item) {
                $item = PrLine::whereKey($prLine)->value('item_id');
            }
            $line->fill([
                'pr_line_id' => $prLine, 'item_id' => $item,
                'i_description' => mb_substr(trim((string) ($row['i_description'] ?? $line->i_description)), 0, 255),
                'i_qty' => (float) ($row['i_qty'] ?? 0), 'i_uom' => mb_substr(trim((string) ($row['i_uom'] ?? '')), 0, 50) ?: '-',
                'i_price_fx' => (float) ($row['i_price_fx'] ?? 0), 'i_lead_time_day' => self::int($row['i_lead_time_day'] ?? null), 'updated_by' => $login,
            ]);
            $line->recalc($fx);
            $line->save();
        }
        $pq->recalcTotal();
        $pq->save();
    }

    // Syarat Confirm: supplier cocok, minimal 1 baris, tiap baris qty & harga > 0 dan sudah menunjuk baris PR atau Master Bahan.
    public static function confirmErrors(PqQuotation $pq): array
    {
        $pq->load('lines');
        $err = [];
        if (! $pq->i_supplier_id) {
            $err[] = str_replace(':name', $pq->i_supplier_text ?: '—', t('Choose the supplier from the master (written in the file: ":name").'));
        }
        if ($pq->lines->isEmpty()) {
            $err[] = t('There are no price lines yet.');
        }
        foreach ($pq->lines as $l) {
            if ((float) $l->i_qty <= 0 || (float) $l->i_price_fx <= 0) {
                $err[] = str_replace(':n', (string) $l->line_no, t('Line :n: qty and price must be > 0.'));
            }
            if (! $l->pr_line_id && ! $l->item_id) {
                $err[] = str_replace(':n', (string) $l->line_no, t('Line :n: choose a PR line or a Material Master item.'));
            }
        }

        return $err;
    }

    public static function confirm(PqQuotation $pq): array
    {
        if ($err = self::confirmErrors($pq)) {
            return $err;
        }
        DB::connection('tm2pur')->transaction(fn () => $pq->update(['doc_no' => PqQuotation::nextDocNo(), 'status' => 'CONFIRMED', 'updated_by' => Auth::user()->login]));

        return [];
    }

    public static function cancel(PqQuotation $pq, string $reason): ?string
    {
        if ($pq->status !== 'CONFIRMED') {
            return t('Only a Confirmed PQ can be Cancelled.');
        }
        if (mb_strlen(trim($reason)) < 10) {
            return t('A cancellation reason is required, at least 10 characters.');
        }
        if (self::orderedLineIds($pq)) {
            return t('Some lines of this PQ are already on a PO — cancel the PO first.');
        }
        $pq->update(['status' => 'CANCELLED', 'cancel_reason' => trim($reason), 'updated_by' => Auth::user()->login]);

        return null;
    }

    public static function delete(PqQuotation $pq): void
    {
        abort_unless($pq->status === 'DRAFT', 422, t('Only a Draft PQ can be deleted.'));
        DB::connection('tm2pur')->transaction(function () use ($pq) {
            $pq->lines()->delete();
            $pq->delete();
        });
        if ($pq->source_path) {
            Storage::disk('public')->delete($pq->source_path);
        }
    }

    /** @return array<int, string> pq_line_id => no. PO */
    public static function orderedLineIds(PqQuotation $pq): array
    {
        return PoLine::whereIn('pq_line_id', $pq->lines()->pluck('pq_line_id'))->join('po_order', 'po_order.po_id', '=', 'po_line.po_id')
            ->selectRaw("po_line.pq_line_id, COALESCE(po_order.doc_no, CONCAT('Draft #', po_order.po_id)) as po_no")->pluck('po_no', 'pq_line_id')->all();
    }

    // Layar Bandingkan: baris PR x supplier (PQ Confirmed yang menunjuk PR ini). Harga dalam IDR per satuan penawaran.
    public static function compare(int $prId): array
    {
        $pr = PrRequisition::findOrFail($prId);
        $prLines = PrLine::where('pr_id', $prId)->orderBy('line_no')->get();
        $pqs = PqQuotation::with('lines')->where('i_pr_id', $prId)->where('status', 'CONFIRMED')->orderBy('pq_id')->get();
        $sup = PurchaseOrder::suppliers($pqs->pluck('i_supplier_id')->unique()->all());
        $ordered = PoLine::whereIn('pq_line_id', $pqs->flatMap->lines->pluck('pq_line_id'))->pluck('pq_line_id')->flip();
        $openAlloc = PrLineAlloc::whereIn('pr_line_id', $prLines->pluck('pr_line_id'))->where('c_is_converted', false)->pluck('pr_line_id')->flip();

        $rows = $prLines->map(function ($l) use ($pqs, $ordered, $openAlloc) {
            $cells = [];
            $cheapest = null;
            foreach ($pqs as $q) {
                $pl = $q->lines->firstWhere('pr_line_id', $l->pr_line_id);
                if (! $pl) {
                    continue;
                }
                $sameUom = strcasecmp(trim($pl->i_uom), trim((string) $l->i_uom)) === 0;
                $cells[$q->pq_id] = ['pq_line_id' => $pl->pq_line_id, 'price' => (float) $pl->c_price_idr, 'uom' => $pl->i_uom, 'lead' => $pl->i_lead_time_day ?? $q->i_lead_time_day,
                    'same_uom' => $sameUom, 'ordered' => isset($ordered[$pl->pq_line_id])];
                if ($sameUom && ($cheapest === null || $pl->c_price_idr < $cells[$cheapest]['price'])) {
                    $cheapest = $q->pq_id;
                }
            }

            return ['pr_line_id' => $l->pr_line_id, 'label' => '#'.$l->line_no.' '.$l->i_description, 'qty' => self::qty($l->i_qty).' '.$l->i_uom,
                'done' => in_array($l->c_line_status, ['FULLY_ORDERED'], true) || (! isset($openAlloc[$l->pr_line_id]) && (float) $l->c_qty_ordered > 0),
                'cells' => $cells, 'cheapest' => $cheapest];
        })->all();

        return ['pr' => $pr, 'rows' => $rows, 'suppliers' => $pqs->map(fn ($q) => ['pq_id' => $q->pq_id, 'no' => $q->doc_no,
            'name' => $sup[$q->i_supplier_id] ?? '#'.$q->i_supplier_id, 'term' => $q->i_payment_term, 'valid' => \Devlib\Locale::d($q->i_quotation_valid_until)])->all()];
    }

    // Buat PO dari baris PQ terpilih: 1 draft PO per supplier. Baris ber-PR -> lewat alokasi PR (PoBuilderService), harga
    // diganti harga PQ; baris tanpa PR -> po_line langsung. Balasan: [po_id => no/draft], atau exception berisi alasan.
    public static function createPo(array $pqLineIds): array
    {
        $lines = PqLine::with('quotation')->whereIn('pq_line_id', array_map('intval', $pqLineIds))->get();
        abort_if($lines->isEmpty(), 422, t('Choose at least 1 quotation line.'));
        $done = PoLine::whereIn('pq_line_id', $lines->pluck('pq_line_id'))->pluck('pq_line_id')->flip();
        $login = Auth::user()->login;

        return DB::connection('tm2pur')->transaction(function () use ($lines, $done, $login) {
            $pos = [];
            foreach ($lines as $pl) {
                $q = $pl->quotation;
                if ($q->status !== 'CONFIRMED') {
                    throw new InvalidArgumentException(str_replace([':no', ':n'], [(string) $q->doc_no, (string) $pl->line_no], t('Line :n :no: the PQ is not Confirmed yet.')));
                }
                if (isset($done[$pl->pq_line_id])) {
                    throw new InvalidArgumentException(str_replace([':no', ':n'], [(string) $q->doc_no, (string) $pl->line_no], t('Line :n :no is already on a PO.')));
                }
                $po = $pos[$q->i_supplier_id] ??= PoBuilderService::findOrCreateDraft($q->i_supplier_id);

                if ($pl->pr_line_id) {
                    $prLine = PrLine::findOrFail($pl->pr_line_id);
                    if (strcasecmp(trim($pl->i_uom), trim((string) $prLine->i_uom)) !== 0) {
                        throw new InvalidArgumentException(str_replace([':no', ':n', ':quoted', ':pr'], [(string) $q->doc_no, (string) $pl->line_no, (string) $pl->i_uom, (string) $prLine->i_uom], t('Line :n :no: the quoted unit (:quoted) differs from the PR (:pr) — make them the same first.')));
                    }
                    if (! $prLine->allocations()->exists()) {
                        PrAllocationService::ensureDefaultAllocation($prLine);
                    }
                    $alloc = PrLineAlloc::where('pr_line_id', $prLine->pr_line_id)->where('c_is_converted', false)->orderBy('alloc_no')->first();
                    $poLine = $alloc ? PoBuilderService::addAllocation($po, $alloc) : null;
                    if (! $poLine) {
                        throw new InvalidArgumentException(str_replace(':item', (string) $prLine->i_description, t('PR line :item is already on a PO.')));
                    }
                    $poLine->fill(['pq_line_id' => $pl->pq_line_id, 'i_price' => $pl->c_price_idr]);
                } else {
                    $mat = $pl->item_id ? MatRaw::find($pl->item_id) : null;
                    $poLine = new PoLine([
                        'po_id' => $po->po_id, 'line_no' => ((int) $po->lines()->max('line_no')) + 1, 'pq_line_id' => $pl->pq_line_id,
                        'item_id' => $pl->item_id, 'c_item_code' => $mat?->material_code, 'i_description' => $pl->i_description, 'i_line_class' => 'RAW',
                        'i_qty' => $pl->i_qty, 'i_uom' => $pl->i_uom, 'c_uom_stock' => $mat?->uom_stock, 'c_conv_to_stock' => $mat?->conversion_to_stock,
                        'i_price' => $pl->c_price_idr, 'i_disc_pct' => 0, 'is_stock' => true, 'c_tol_pct' => $mat?->tol_purchase,
                        'created_by' => $login, 'updated_by' => $login,
                    ]);
                }
                $poLine->recalcNet();
                $poLine->save();
            }

            foreach ($pos as $po) {
                $po->refresh()->recalcTax();
                $po->save();
            }
            foreach ($lines->pluck('quotation')->unique('pq_id') as $q) {
                if (! $q->lines()->whereNotIn('pq_line_id', PoLine::whereNotNull('pq_line_id')->select('pq_line_id'))->exists()) {
                    $q->update(['status' => 'CLOSED', 'updated_by' => $login]);
                }
            }

            return collect($pos)->mapWithKeys(fn (PoOrder $p) => [$p->po_id => $p->doc_no ?: 'Draft #'.$p->po_id])->all();
        });
    }

    public static function formStatus(PqQuotation $pq): string
    {
        return ['DRAFT' => 'draft', 'CONFIRMED' => 'confirmed', 'CLOSED' => 'closed', 'CANCELLED' => 'cancelled'][$pq->status] ?? 'draft';
    }

    private static function date(?string $v): ?string
    {
        try {
            return $v ? Carbon::parse($v)->toDateString() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function int(mixed $v): ?int
    {
        return $v === null || $v === '' ? null : (int) $v;
    }

    private static function qty(mixed $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') ?: '0';
    }
}
