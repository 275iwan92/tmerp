<?php

use App\Models\Master\FinCurrency;
use App\Models\Master\MRawMaterial;
use App\Models\Pur\PqLine;
use App\Models\Pur\PqQuotation;
use App\Models\Pur\PrLine;
use App\Models\Pur\PrRequisition;
use App\Services\Pur\PoBuilderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// PROC_TIER_3 §3 + mockup_purchase_quotation.html. PQ "kondisional, boleh
// dilewati" -- konfirmasi diri sendiri (bukan approval berjenjang), jadi TIDAK
// ada blok Approve/Reject spt PR/PO/PRT (lihat pur/pq/index.blade.php).
new #[Title('Purchase Quotation Entry — TELASIH MAKMUR ERP')] class extends Component
{
    public int $pqId;

    public string $itemSearch = '';

    public string $cancelReason = '';

    public string $cancelError = '';

    public function mount(int $pq): void
    {
        $this->pqId = $pq;
    }

    #[Computed]
    public function pq(): PqQuotation
    {
        return PqQuotation::with(['lines' => fn ($q) => $q->orderBy('line_no')])->findOrFail($this->pqId);
    }

    #[Computed]
    public function supplier()
    {
        return DB::connection('tm2mst')->table('sup_supplier')->where('id_supplier', $this->pq->i_supplier_id)->first();
    }

    #[Computed]
    public function currencies()
    {
        return FinCurrency::where('is_active', true)->orderBy('currency_code')->get();
    }

    #[Computed]
    public function openRequisitions()
    {
        return PrRequisition::whereIn('status', ['APPROVED', 'PARTIALLY_ORDERED'])->where('is_cancelled', false)->orderByDesc('pr_id')->limit(50)->get();
    }

    #[Computed]
    public function itemResults()
    {
        if (mb_strlen(trim($this->itemSearch)) < 2) {
            return collect();
        }
        $term = $this->itemSearch;

        return MRawMaterial::where('is_active', true)
            ->where(fn ($q) => $q->where('material_code', 'like', "%{$term}%")->orWhere('material_name', 'like', "%{$term}%"))
            ->limit(15)->get();
    }

    // Nilai dokumen sedang di-BANDINGKAN antar-PQ utk PR yang sama -- hanya
    // relevan bila i_pr_id diisi (PROC_TIER_1 "Perbandingan Penawaran Harga").
    #[Computed]
    public function comparisonQuotations()
    {
        if (! $this->pq->i_pr_id) {
            return collect();
        }

        return PqQuotation::where('i_pr_id', $this->pq->i_pr_id)
            ->where('status', '!=', 'CANCELLED')
            ->with('lines')
            ->get();
    }

    #[Computed]
    public function comparisonSupplierNames(): array
    {
        $ids = $this->comparisonQuotations->pluck('i_supplier_id')->unique();

        return DB::connection('tm2mst')->table('sup_supplier')->whereIn('id_supplier', $ids)->pluck('supplier_name', 'id_supplier')->all();
    }

    public function updateHeader(string $field, $value): void
    {
        if ($this->pq->status !== 'DRAFT') {
            return;
        }

        $allowed = ['i_doc_date', 'i_quotation_valid_until', 'i_pr_id', 'i_currency_id', 'i_fx_rate', 'i_payment_term', 'i_lead_time_day', 'i_notes'];
        if (! in_array($field, $allowed, true)) {
            return;
        }

        $this->pq->update([$field => $value === '' ? null : $value, 'updated_by' => Auth::user()->login]);
        unset($this->pq);
    }

    public function addLine(int $materialId): void
    {
        if ($this->pq->status !== 'DRAFT') {
            return;
        }

        $material = MRawMaterial::findOrFail($materialId);
        $login = Auth::user()->login;
        $nextLineNo = ((int) $this->pq->lines()->max('line_no')) + 1;

        $line = PqLine::create([
            'pq_id' => $this->pqId,
            'line_no' => $nextLineNo,
            'item_id' => $material->material_id,
            'i_description' => $material->material_name,
            'i_qty' => 1,
            'i_uom' => $material->uom_stock ?: 'PCS',
            'i_price_fx' => 0,
            'created_by' => $login,
            'updated_by' => $login,
        ]);
        $line->recalc((float) $this->pq->i_fx_rate);
        $line->save();

        $this->itemSearch = '';
        $this->recalcTotal();
        unset($this->pq);
    }

    // Tarik semua baris PR acuan sekaligus -- cepat utk officer, harga awal 0
    // (belum ditawar), tinggal diisi per baris.
    public function pullFromPr(): void
    {
        if ($this->pq->status !== 'DRAFT' || ! $this->pq->i_pr_id) {
            return;
        }

        $login = Auth::user()->login;
        $existingItemIds = $this->pq->lines->pluck('item_id')->filter()->all();
        $nextLineNo = ((int) $this->pq->lines()->max('line_no')) + 1;

        foreach (PrLine::where('pr_id', $this->pq->i_pr_id)->where('i_item_source', 'MASTER_ITEM')->get() as $prLine) {
            if (in_array($prLine->item_id, $existingItemIds, true)) {
                continue;
            }

            $line = PqLine::create([
                'pq_id' => $this->pqId,
                'line_no' => $nextLineNo++,
                'pr_line_id' => $prLine->pr_line_id,
                'item_id' => $prLine->item_id,
                'i_description' => $prLine->i_description,
                'i_qty' => $prLine->i_qty,
                'i_uom' => $prLine->i_uom,
                'i_price_fx' => 0,
                'created_by' => $login,
                'updated_by' => $login,
            ]);
            $line->recalc((float) $this->pq->i_fx_rate);
            $line->save();
        }

        $this->recalcTotal();
        unset($this->pq);
    }

    public function updateLine(int $lineId, string $field, $value): void
    {
        if ($this->pq->status !== 'DRAFT') {
            return;
        }

        $allowed = ['i_qty', 'i_price_fx', 'i_lead_time_day'];
        if (! in_array($field, $allowed, true)) {
            return;
        }

        $line = PqLine::where('pq_id', $this->pqId)->findOrFail($lineId);
        $line->{$field} = $value === '' ? 0 : $value;
        $line->recalc((float) $this->pq->i_fx_rate);
        $line->updated_by = Auth::user()->login;
        $line->save();

        $this->recalcTotal();
        unset($this->pq, $this->comparisonQuotations);
    }

    public function removeLine(int $lineId): void
    {
        if ($this->pq->status !== 'DRAFT') {
            return;
        }

        PqLine::where('pq_id', $this->pqId)->where('pq_line_id', $lineId)->delete();
        $this->recalcTotal();
        unset($this->pq);
    }

    protected function recalcTotal(): void
    {
        $pq = PqQuotation::findOrFail($this->pqId);
        $pq->recalcTotal();
        $pq->save();
    }

    public function confirmPq(): void
    {
        if ($this->pq->status !== 'DRAFT' || $this->pq->lines->isEmpty()) {
            return;
        }

        $this->pq->update([
            'doc_no' => PqQuotation::nextDocNo(),
            'status' => 'CONFIRMED',
            'updated_by' => Auth::user()->login,
        ]);
        unset($this->pq);
    }

    public function cancelPq(): void
    {
        if (in_array($this->pq->status, ['CANCELLED'], true)) {
            return;
        }

        if (mb_strlen(trim($this->cancelReason)) < 10) {
            $this->cancelError = 'Alasan pembatalan wajib diisi, minimal 10 karakter.';

            return;
        }

        $this->pq->update(['status' => 'CANCELLED', 'cancel_reason' => $this->cancelReason, 'updated_by' => Auth::user()->login]);
        $this->cancelReason = '';
        $this->cancelError = '';
        unset($this->pq);
    }

    // "Buat PO dari Pilihan Terbaik" -- PQ CONFIRMED jadi sumber langsung
    // po_line (alloc_id NULL, bukan lewat pr_line_alloc -- PQ opsional/berdiri
    // sendiri, tak selalu berasal dari PR yang diapprove; simplifikasi v1
    // yang disengaja, dicatat di memory).
    public function createPoFromPq(): void
    {
        if ($this->pq->status !== 'CONFIRMED' || $this->pq->lines->isEmpty()) {
            return;
        }

        $po = PoBuilderService::findOrCreateDraft($this->pq->i_supplier_id);
        $login = Auth::user()->login;
        $nextSeq = ((int) $po->lines()->max('line_no')) + 1;

        foreach ($this->pq->lines as $pqLine) {
            // i_department_id nullable (migration 2026_08_22_000018) -- PQ boleh
            // berdiri sendiri tanpa PR acuan. Kalau baris ini memang menawar
            // baris PR tertentu, ikut departemen baris itu; kalau tidak, NULL.
            $po->lines()->create([
                'line_no' => $nextSeq++,
                'alloc_id' => null,
                'item_id' => $pqLine->item_id,
                'i_description' => $pqLine->i_description,
                'i_line_class' => 'RAW',
                'i_qty' => $pqLine->i_qty,
                'i_uom' => $pqLine->i_uom,
                'i_price' => $pqLine->c_price_idr,
                'i_disc_pct' => 0,
                'i_department_id' => $pqLine->prLine?->i_department_id,
                'i_coa_account_id' => $pqLine->prLine?->i_coa_account_id,
                'is_stock' => true,
                'created_by' => $login,
                'updated_by' => $login,
            ]);
        }

        $po->lines->each(function ($l) {
            $l->recalcNet();
            $l->save();
        });
        $po->recalcTax();
        $po->save();

        $this->redirect(route('pur.po.entry', ['po' => $po->po_id]));
    }
};
?>

<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">📋 Purchase Quotation Entry</b>
        <span class="text-xs text-white/75">{{ $this->pq->doc_no ?: '(belum ada nomor)' }} · {{ $this->supplier?->supplier_name }}</span>
        <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full
            {{ match ($this->pq->status) {
                'DRAFT' => 'bg-[#eef1f5] text-[#4b5568]',
                'CONFIRMED' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                'CANCELLED' => 'bg-[#fbeaea] text-[#a3241f]',
                default => 'bg-[#eef1f5] text-[#4b5568]',
            } }}">{{ $this->pq->status }}</span>
        <a href="{{ route('pur.pq.list') }}" title="Kembali ke Daftar PQ"
            class="ml-auto inline-flex items-center gap-2 pl-3.5 pr-1 py-1 bg-[#43508a] rounded-full no-underline hover:brightness-110">
            <span class="text-[11px] font-bold uppercase tracking-wide text-white">Back</span>
            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#43508a] text-xs font-bold">←</span>
        </a>
    </header>

    <div class="max-w-[1400px] mx-auto my-5 px-4">
        @php $readOnly = $this->pq->status !== 'DRAFT'; @endphp

        {{-- HEADER --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4">
            <h3 class="text-xs text-navy uppercase font-bold mb-3">Informasi Umum</h3>
            <div class="grid">
                <div>
                    <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Supplier</label>
                    <input readonly value="{{ $this->supplier?->supplier_name }} ({{ $this->supplier?->supplier_code }})" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs bg-[#f8fafc] text-muted">
                </div>
                <div>
                    <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Tanggal PQ</label>
                    <input type="date" value="{{ $this->pq->i_doc_date?->format('Y-m-d') }}" @disabled($readOnly)
                        wire:change="updateHeader('i_doc_date', $event.target.value)" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                </div>
                <div>
                    <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Berlaku s/d</label>
                    <input type="date" value="{{ $this->pq->i_quotation_valid_until?->format('Y-m-d') }}" @disabled($readOnly)
                        wire:change="updateHeader('i_quotation_valid_until', $event.target.value)" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                </div>
                <div>
                    <label class="text-[10px] text-muted uppercase font-semibold block mb-1">PR Acuan (opsional)</label>
                    <select @disabled($readOnly) wire:change="updateHeader('i_pr_id', $event.target.value)" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                        <option value="">— tidak ada —</option>
                        @foreach ($this->openRequisitions as $pr)
                            <option value="{{ $pr->pr_id }}" @selected($this->pq->i_pr_id === $pr->pr_id)>{{ $pr->doc_no }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Mata Uang</label>
                    <select @disabled($readOnly) wire:change="updateHeader('i_currency_id', $event.target.value)" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                        <option value="">— pilih —</option>
                        @foreach ($this->currencies as $c)
                            <option value="{{ $c->id_currency }}" @selected($this->pq->i_currency_id === $c->id_currency)>{{ $c->currency_code }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Kurs</label>
                    <input type="number" step="0.000001" value="{{ $this->pq->i_fx_rate }}" @disabled($readOnly)
                        wire:change="updateHeader('i_fx_rate', $event.target.value)" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                </div>
                <div>
                    <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Syarat Bayar</label>
                    <input type="text" value="{{ $this->pq->i_payment_term }}" @disabled($readOnly)
                        wire:change="updateHeader('i_payment_term', $event.target.value)" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                </div>
                <div>
                    <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Lead Time Umum (hari)</label>
                    <input type="number" value="{{ $this->pq->i_lead_time_day }}" @disabled($readOnly)
                        wire:change="updateHeader('i_lead_time_day', $event.target.value)" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                </div>
            </div>
            <div class="mt-3">
                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Catatan</label>
                <textarea rows="2" @disabled($readOnly) wire:change="updateHeader('i_notes', $event.target.value)"
                    class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">{{ $this->pq->i_notes }}</textarea>
            </div>
        </div>

        {{-- BARIS --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
            <div class="px-4 py-2.5 bg-[#f4f6fa] border-b border-[#d7dbe2] flex items-center gap-2 flex-wrap">
                <h3 class="text-xs text-navy uppercase font-bold">Rincian Penawaran</h3>
                @if (! $readOnly)
                    <div class="relative ml-2" style="max-width:320px">
                        <input type="text" wire:model.live.debounce.300ms="itemSearch" placeholder="🔍 Cari item…" class="w-64 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        @if ($this->itemResults->isNotEmpty())
                            <div class="absolute top-full left-0 right-0 mt-1 bg-white border border-[#d7dbe2] rounded shadow-lg z-10 max-h-56 overflow-y-auto">
                                @foreach ($this->itemResults as $m)
                                    <button type="button" wire:click="addLine({{ $m->material_id }})" class="w-full text-left px-2.5 py-1.5 text-xs hover:bg-[#eef3fb] border-b border-[#eef1f5] last:border-b-0">
                                        <span class="font-mono font-bold text-accent">{{ $m->material_code }}</span> — {{ $m->material_name }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    @if ($this->pq->i_pr_id)
                        <button type="button" wire:click="pullFromPr" class="text-[11px] font-bold text-white bg-accent rounded px-2.5 py-1.5">⤓ Tarik dari PR</button>
                    @endif
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs min-w-[1000px]">
                    <thead class="text-[10px] text-muted uppercase border-b border-[#eef1f5]">
                        <tr>
                            <th class="text-left px-3 py-2">#</th>
                            <th class="text-left px-3 py-2">Item</th>
                            <th class="text-right px-3 py-2">Qty</th>
                            <th class="text-left px-3 py-2">UoM</th>
                            <th class="text-right px-3 py-2">Harga (FX)</th>
                            <th class="text-right px-3 py-2">Harga (IDR)</th>
                            <th class="text-right px-3 py-2">Subtotal (IDR)</th>
                            <th class="text-right px-3 py-2">Lead Time</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->pq->lines as $line)
                            <tr wire:key="pqline-{{ $line->pq_line_id }}">
                                <td class="px-3 py-2 text-muted">{{ $line->line_no }}</td>
                                <td class="px-3 py-2">{{ $line->i_description }} @if($line->pr_line_id) <span class="text-[9px] text-muted">(dari PR)</span> @endif</td>
                                <td class="px-3 py-2 text-right">
                                    <input type="number" step="0.01" value="{{ $line->i_qty }}" @disabled($readOnly)
                                        wire:change="updateLine({{ $line->pq_line_id }}, 'i_qty', $event.target.value)" class="w-20 px-1.5 py-1 border border-[#d7dbe2] rounded text-xs text-right disabled:bg-transparent disabled:border-transparent">
                                </td>
                                <td class="px-3 py-2">{{ $line->i_uom }}</td>
                                <td class="px-3 py-2 text-right">
                                    <input type="number" step="0.01" value="{{ $line->i_price_fx }}" @disabled($readOnly)
                                        wire:change="updateLine({{ $line->pq_line_id }}, 'i_price_fx', $event.target.value)" class="w-24 px-1.5 py-1 border border-[#d7dbe2] rounded text-xs text-right disabled:bg-transparent disabled:border-transparent">
                                </td>
                                <td class="px-3 py-2 text-right font-mono text-muted">{{ number_format((float) $line->c_price_idr, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right font-mono font-semibold">{{ number_format((float) $line->c_subtotal_idr, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right">
                                    <input type="number" value="{{ $line->i_lead_time_day }}" @disabled($readOnly)
                                        wire:change="updateLine({{ $line->pq_line_id }}, 'i_lead_time_day', $event.target.value)" class="w-16 px-1.5 py-1 border border-[#d7dbe2] rounded text-xs text-right disabled:bg-transparent disabled:border-transparent">
                                </td>
                                <td class="px-3 py-2">
                                    @if (! $readOnly)
                                        <button type="button" wire:click="removeLine({{ $line->pq_line_id }})" class="text-[#a3241f] text-xs">✕</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-3 py-6 text-center text-muted text-xs">Belum ada baris. Cari item di atas atau tarik dari PR.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-[#d7dbe2] font-bold">
                            <td colspan="6" class="px-3 py-2 text-right">Total (IDR)</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $this->pq->c_total_idr, 0, ',', '.') }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- PERBANDINGAN ANTAR-SUPPLIER (kalau ada PR acuan) --}}
        @if ($this->pq->i_pr_id && $this->comparisonQuotations->count() > 1)
            @php $cheapest = $this->comparisonQuotations->min('c_total_idr'); @endphp
            <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4">
                <h3 class="text-xs text-navy uppercase font-bold mb-3">Perbandingan Supplier — PR yang sama</h3>
                <table class="w-full text-xs">
                    <thead class="text-muted"><tr><th class="text-left py-1">Supplier</th><th class="text-left py-1">No. PQ</th><th class="text-left py-1">Status</th><th class="text-right py-1">Total (IDR)</th></tr></thead>
                    <tbody>
                        @foreach ($this->comparisonQuotations as $cmp)
                            <tr class="{{ $cmp->pq_id === $this->pq->pq_id ? 'bg-[#eef3fb]' : '' }}">
                                <td class="py-1">{{ $this->comparisonSupplierNames[$cmp->i_supplier_id] ?? '—' }}</td>
                                <td class="py-1 font-mono">{{ $cmp->doc_no ?: '(draft)' }}</td>
                                <td class="py-1">{{ $cmp->status }}</td>
                                <td class="py-1 text-right font-mono {{ (float) $cmp->c_total_idr === (float) $cheapest ? 'text-[#16a34a] font-bold' : '' }}">{{ number_format((float) $cmp->c_total_idr, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($this->pq->status === 'DRAFT')
            <div class="flex justify-end gap-2 mb-8">
                <button type="button" wire:click="confirmPq" wire:confirm="Konfirmasi PQ ini? Nomor dokumen akan diterbitkan dan harga terkunci." class="bg-navy text-white text-xs font-bold px-4 py-2 rounded-md">Konfirmasi</button>
            </div>
        @elseif ($this->pq->status === 'CONFIRMED')
            <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs text-muted">Batalkan PQ ini bila tidak jadi dipakai (butuh alasan).</p>
                    <button type="button" wire:click="createPoFromPq" wire:confirm="Buat PO dari PQ ini? Semua baris akan disalin ke PO draft supplier ini." class="bg-navy text-white text-xs font-bold px-4 py-2 rounded-md">📦 Buat PO dari PQ ini</button>
                </div>
                @if ($cancelError)
                    <p class="text-[11px] text-[#a3241f] mt-2">⚠ {{ $cancelError }}</p>
                @endif
                <div class="flex gap-2 items-end mt-2">
                    <div class="flex-1">
                        <textarea wire:model="cancelReason" rows="1" placeholder="Alasan pembatalan (min. 10 karakter)..." class="w-full border border-[#d7dbe2] rounded px-2.5 py-1.5 text-xs"></textarea>
                    </div>
                    <button type="button" wire:click="cancelPq" wire:confirm="Batalkan PQ ini?" class="border border-[#d7dbe2] text-[#a3241f] text-xs font-bold px-4 py-2 rounded-md">Batalkan</button>
                </div>
            </div>
        @endif
    </div>
</div>
