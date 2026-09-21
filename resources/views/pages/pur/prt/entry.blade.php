<?php

use App\Models\Master\MatRaw;
use App\Models\Pur\PrtLine;
use App\Models\Pur\PrtReturn;
use App\Models\Pur\PvLine;
use App\Services\Flow\FlowEngineService;
use App\Support\Localization\Translator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// PROC_TIER_3 §6 + mockup_purchase_return.html. Approve/Reject/Hold digabung
// ke form ini sendiri (pola PR/PO/Adjustment yang sama sesi ini) -- field
// otomatis read-only begitu status != DRAFT. "Hold" di sini = tombol "Minta
// Info" di mockup, mekanisme SAMA PERSIS dgn Hold Procurement (catatan saja,
// dokumen tetap di antrian approver). v1 TIDAK otomatis membuat Stock Out
// (doc_cat=RETURN) di Inventory saat disetujui -- rm_stock_doc_id sengaja
// NULL/manual (DDL sendiri menandainya begitu), officer follow-up terpisah.
new #[Title('Purchase Return Entry — TELASIH MAKMUR ERP')] class extends Component
{
    public int $prtId;

    public string $itemSearch = '';

    public string $approvalNote = '';

    public string $approvalError = '';

    public string $cancelReason = '';

    public string $cancelError = '';

    public function mount(int $prt): void
    {
        $this->prtId = $prt;
    }

    #[Computed]
    public function prt(): PrtReturn
    {
        return PrtReturn::with(['lines' => fn ($q) => $q->orderBy('line_no')])->findOrFail($this->prtId);
    }

    #[Computed]
    public function supplier()
    {
        return DB::connection('tm2mst')->table('sup_supplier')->where('id_supplier', $this->prt->i_supplier_id)->first();
    }

    private function alreadyReturned(int $pvLineId): float
    {
        return (float) PrtLine::where('pv_line_id', $pvLineId)
            ->whereHas('return', fn ($q) => $q->where('status', '!=', 'CANCELLED')->where('prt_id', '!=', $this->prtId))
            ->sum('i_qty');
    }

    // Baris PV yang MASIH bisa diretur (reject QC dikurangi yg sudah ditarik
    // dokumen retur lain) -- hanya relevan utk i_source=QC_REJECT.
    #[Computed]
    public function returnablePvLines()
    {
        if ($this->prt->i_source !== 'QC_REJECT' || ! $this->prt->pv_id) {
            return collect();
        }

        $addedIds = $this->prt->lines->pluck('pv_line_id')->filter()->all();

        return PvLine::where('pv_id', $this->prt->pv_id)
            ->where('c_qty_rejected', '>', 0)
            ->get()
            ->filter(fn ($l) => ! in_array($l->pv_line_id, $addedIds, true) && ((float) $l->c_qty_rejected - $this->alreadyReturned($l->pv_line_id)) > 0.0001)
            ->values();
    }

    #[Computed]
    public function itemResults()
    {
        if ($this->prt->i_source !== 'FROM_STOCK' || mb_strlen(trim($this->itemSearch)) < 2) {
            return collect();
        }
        $term = $this->itemSearch;

        return MatRaw::where('activate', true)->where('stat_confirm', true)
            ->where(fn ($q) => $q->where('material_code', 'like', "%{$term}%")->orWhere('material_name', 'like', "%{$term}%"))
            ->limit(15)->get();
    }

    #[Computed]
    public function pendingApproval()
    {
        return app(FlowEngineService::class)->pendingForUser('PRT', Auth::id())->firstWhere('doc_id', $this->prtId);
    }

    #[Computed]
    public function approvalHistory()
    {
        return app(FlowEngineService::class)->history('PRT', $this->prtId);
    }

    public function updateHeader(string $field, $value): void
    {
        if ($this->prt->status !== 'DRAFT') {
            return;
        }

        $allowed = ['i_doc_date', 'i_reason', 'i_disposition', 'replacement_due_date', 'qc_inspector', 'comm_method', 'supplier_pic', 'physical_return_date', 'expedition', 'cn_supplier_no', 'cn_received_date', 'i_notes'];
        if (! in_array($field, $allowed, true)) {
            return;
        }

        $this->prt->update([$field => $value === '' ? null : $value, 'updated_by' => Auth::user()->login]);
        unset($this->prt);
    }

    public function addFromPvLine(int $pvLineId): void
    {
        if ($this->prt->status !== 'DRAFT' || $this->prt->i_source !== 'QC_REJECT') {
            return;
        }

        $pvLine = PvLine::with('material')->findOrFail($pvLineId);
        $returnable = (float) $pvLine->c_qty_rejected - $this->alreadyReturned($pvLineId);
        $login = Auth::user()->login;
        $nextLineNo = ((int) $this->prt->lines()->max('line_no')) + 1;

        $line = PrtLine::create([
            'prt_id' => $this->prtId,
            'pv_line_id' => $pvLineId,
            'line_no' => $nextLineNo,
            'item_id' => $pvLine->item_id,
            'c_item_code' => $pvLine->c_item_code,
            'i_description' => $pvLine->i_description,
            'i_qty' => $returnable,
            'i_uom' => $pvLine->i_uom_stock,
            'c_unit_price' => $pvLine->c_unit_price,
            'is_stock_moved' => false,
            'created_by' => $login,
            'updated_by' => $login,
        ]);
        $line->recalc();
        $line->save();

        $this->recalcTotals();
        unset($this->prt, $this->returnablePvLines);
    }

    public function addManualItem(int $materialId): void
    {
        if ($this->prt->status !== 'DRAFT' || $this->prt->i_source !== 'FROM_STOCK') {
            return;
        }

        $material = MatRaw::findOrFail($materialId);
        $login = Auth::user()->login;
        $nextLineNo = ((int) $this->prt->lines()->max('line_no')) + 1;

        $line = PrtLine::create([
            'prt_id' => $this->prtId,
            'line_no' => $nextLineNo,
            'item_id' => $material->material_id,
            'c_item_code' => $material->material_code,
            'i_description' => $material->material_name,
            'i_qty' => 1,
            'i_uom' => $material->uom_stock ?: 'PCS',
            'c_unit_price' => $material->last_unitprice ?: 0,
            'is_stock_moved' => true,
            'created_by' => $login,
            'updated_by' => $login,
        ]);
        $line->recalc();
        $line->save();

        $this->itemSearch = '';
        $this->recalcTotals();
        unset($this->prt);
    }

    public function updateLine(int $lineId, string $field, $value): void
    {
        if ($this->prt->status !== 'DRAFT') {
            return;
        }

        $allowed = ['i_qty', 'c_unit_price', 'i_lot_no'];
        if (! in_array($field, $allowed, true)) {
            return;
        }

        $line = PrtLine::where('prt_id', $this->prtId)->findOrFail($lineId);
        $line->{$field} = $field === 'i_lot_no' ? $value : ($value === '' ? 0 : $value);
        $line->recalc();
        $line->updated_by = Auth::user()->login;
        $line->save();

        $this->recalcTotals();
        unset($this->prt);
    }

    public function removeLine(int $lineId): void
    {
        if ($this->prt->status !== 'DRAFT') {
            return;
        }

        PrtLine::where('prt_id', $this->prtId)->where('prt_line_id', $lineId)->delete();
        $this->recalcTotals();
        unset($this->prt);
    }

    protected function recalcTotals(): void
    {
        $prt = PrtReturn::findOrFail($this->prtId);
        $prt->recalcCreditValue();
        $prt->save();
    }

    private function amountSnapshot(): float
    {
        return (float) $this->prt->lines->sum('c_line_value');
    }

    public function submitForApproval(FlowEngineService $flow): void
    {
        $prt = $this->prt;

        if ($prt->status !== 'DRAFT' || $prt->lines->isEmpty() || trim((string) $prt->i_reason) === '' || ! $prt->i_disposition) {
            return;
        }

        $login = Auth::user()->login;
        $amount = $this->amountSnapshot();

        $prt->update([
            'doc_no' => PrtReturn::nextDocNo(),
            'status' => 'SUBMITTED',
            'updated_by' => $login,
        ]);

        $result = $flow->submit('PRT', $this->prtId, 'SUBMITTED', $amount);

        if ($result === null) {
            session()->flash('error', Translator::t('proc_prt_entry_no_flow_config', 'Tidak ada konfigurasi approval aktif untuk modul PRT. Dokumen tersimpan sebagai SUBMITTED tapi belum masuk antrian approval siapapun — hubungi Admin.'));
        } else {
            session()->flash('flash', Translator::t('proc_prt_entry_submitted_flash', 'Diajukan utk approval.'));
        }

        unset($this->prt);
    }

    public function approveDoc(FlowEngineService $flow): void
    {
        $pending = $this->pendingApproval;
        if (! $pending) {
            return;
        }

        $result = $flow->approve('PRT', $this->prtId, $pending->flow_step_id, (float) $pending->amount_snapshot);

        $this->prt->update(['status' => $result['status'], 'updated_by' => Auth::user()->login]);

        $this->approvalNote = '';
        $this->approvalError = '';
        unset($this->prt, $this->pendingApproval, $this->approvalHistory);
    }

    public function rejectDoc(FlowEngineService $flow): void
    {
        $pending = $this->pendingApproval;
        if (! $pending) {
            return;
        }

        if (mb_strlen(trim($this->approvalNote)) < 10) {
            $this->approvalError = Translator::t('proc_prt_entry_reject_reason_required', 'Alasan penolakan wajib diisi, minimal 10 karakter.');

            return;
        }

        $result = $flow->reject('PRT', $this->prtId, $pending->flow_step_id, $this->approvalNote, (float) $pending->amount_snapshot);

        $this->prt->update(['status' => $result['status'], 'updated_by' => Auth::user()->login]);

        $this->approvalNote = '';
        $this->approvalError = '';
        unset($this->prt, $this->pendingApproval, $this->approvalHistory);
    }

    // "Minta Info" di mockup -- persis mekanisme Hold Procurement: catatan
    // saja, status TIDAK berubah, tetap di antrian approver ini sendiri.
    public function holdDoc(FlowEngineService $flow): void
    {
        $pending = $this->pendingApproval;
        if (! $pending) {
            return;
        }

        if (trim($this->approvalNote) === '') {
            $this->approvalError = Translator::t('proc_prt_entry_hold_note_required', 'Catatan/pertanyaan wajib diisi utk Minta Info.');

            return;
        }

        $flow->hold('PRT', $this->prtId, $pending->flow_step_id, $this->approvalNote, (float) $pending->amount_snapshot);

        $this->approvalNote = '';
        $this->approvalError = '';
        unset($this->pendingApproval, $this->approvalHistory);
    }

    public function markComplete(): void
    {
        if ($this->prt->status !== 'CONFIRMED') {
            return;
        }

        $this->prt->update(['status' => 'COMPLETED', 'updated_by' => Auth::user()->login]);
        unset($this->prt);
    }

    public function cancelDoc(): void
    {
        if ($this->prt->status !== 'DRAFT') {
            return;
        }

        if (mb_strlen(trim($this->cancelReason)) < 10) {
            $this->cancelError = Translator::t('proc_pq_entry_cancel_reason_required', 'Alasan pembatalan wajib diisi, minimal 10 karakter.');

            return;
        }

        $this->prt->update(['status' => 'CANCELLED', 'cancel_reason' => $this->cancelReason, 'updated_by' => Auth::user()->login]);
        $this->cancelReason = '';
        $this->cancelError = '';
        unset($this->prt);
    }
};
?>

<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">↩ {{ Translator::t('proc_prt_entry_title', 'Purchase Return Entry') }}</b>
        <span class="text-xs text-white/75">{{ $this->prt->doc_no ?: Translator::t('proc_entry_no_docno_short', '(belum ada nomor)') }} · {{ $this->supplier?->supplier_name }}</span>
        <span class="text-[9px] font-bold px-2 py-0.5 rounded {{ $this->prt->i_source === 'QC_REJECT' ? 'bg-white/15' : 'bg-white/15' }}">{{ $this->prt->i_source === 'QC_REJECT' ? Translator::t('proc_prt_source_from_pv', 'DARI PV') : Translator::t('proc_prt_source_from_stock', 'DARI STOK') }}</span>
        <x-form.badge :tone="match ($this->prt->status) {
                'SUBMITTED' => 'warn',
                'CONFIRMED', 'COMPLETED' => 'success',
                'CANCELLED' => 'danger',
                default => 'default',
            }">{{ $this->prt->status }}</x-form.badge>
        <x-back-button :fallback="route('pur.prt.list')" title="{{ Translator::t('proc_prt_entry_back_title', 'Kembali ke Daftar Retur') }}" class="ml-auto" />
    </header>

    <div class="max-w-[1400px] mx-auto my-5 px-4">
        @php $readOnly = $this->prt->status !== 'DRAFT'; @endphp

        <x-alert.banner type="success" :message="session('flash')" />
        <x-alert.banner type="warning" :message="session('error')" />

        @if ($this->approvalHistory->last()?->action === 'HOLD')
            <div class="bg-[#f1ecfc] border border-[#ddd0f7] text-[#6b4fa0] text-xs rounded-md px-4 py-2.5 mb-4">
                ⏸ {{ Translator::t('proc_prt_entry_held_by', 'Diminta info tambahan oleh') }} {{ $this->approvalHistory->last()->created_by ?? Translator::t('proc_entry_system_fallback', 'sistem') }} · {{ $this->approvalHistory->last()->acted_at?->translatedFormat('d M Y, H:i') }} — "{{ $this->approvalHistory->last()->notes }}"
            </div>
        @endif

        {{-- HEADER --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4">
            <h3 class="text-xs text-navy uppercase font-bold mb-3">{{ Translator::t('proc_prt_entry_doc_info', 'Informasi Dokumen') }}</h3>
            <div class="grid">
                <x-form.input label="{{ Translator::t('proc_supplier_label', 'Supplier') }}" readonly value="{{ $this->supplier?->supplier_name }} ({{ $this->supplier?->supplier_code }})" class="bg-[#f8fafc]" />
                <x-form.date-picker label="{{ Translator::t('proc_prt_entry_return_date', 'Tanggal Retur') }}" value="{{ $this->prt->i_doc_date?->format('Y-m-d') }}" :disabled="$readOnly"
                    wire:change="updateHeader('i_doc_date', $event.target.value)" class="disabled:bg-[#f8fafc]" />
                @if ($this->prt->i_source === 'QC_REJECT')
                    <x-form.input label="{{ Translator::t('proc_prt_entry_ref_pv', 'Ref. PV') }}" readonly value="{{ $this->prt->sourcePv?->doc_no }}" class="bg-[#f8fafc]" />
                    <x-form.input label="{{ Translator::t('proc_prt_entry_qc_inspector', 'QC Inspector') }}" value="{{ $this->prt->qc_inspector }}" :disabled="$readOnly"
                        wire:change="updateHeader('qc_inspector', $event.target.value)" class="disabled:bg-[#f8fafc]" />
                @endif
                <x-form.select label="{{ Translator::t('proc_prt_entry_disposition_label', 'Disposisi') }} *" :disabled="$readOnly" wire:change="updateHeader('i_disposition', $event.target.value)" class="disabled:bg-[#f8fafc]"
                    :placeholder="false" :selected="$this->prt->i_disposition" :options="['CREDIT_NOTE' => Translator::t('proc_prt_disposition_credit_note', 'Credit Note'), 'REPLACEMENT' => Translator::t('proc_prt_disposition_replace', 'Ganti Barang')]" />
                @if ($this->prt->i_disposition === 'REPLACEMENT')
                    <x-form.date-picker label="{{ Translator::t('proc_prt_entry_replacement_due', 'Batas Waktu Pengganti') }}" value="{{ $this->prt->replacement_due_date?->format('Y-m-d') }}" :disabled="$readOnly"
                        wire:change="updateHeader('replacement_due_date', $event.target.value)" class="disabled:bg-[#f8fafc]" />
                @endif
            </div>
            <div class="mt-3">
                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ Translator::t('proc_prt_entry_main_reason', 'Alasan Utama') }} *</label>
                <textarea rows="2" @disabled($readOnly) wire:change="updateHeader('i_reason', $event.target.value)"
                    class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">{{ $this->prt->i_reason }}</textarea>
            </div>
        </div>

        {{-- SUMBER: pilih baris --}}
        @if (! $readOnly)
            @if ($this->prt->i_source === 'QC_REJECT')
                <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4">
                    <h3 class="text-xs text-navy uppercase font-bold mb-3">{{ Translator::t('proc_prt_entry_qc_reject_available', 'Baris Reject QC Tersedia (dari PV') }} {{ $this->prt->sourcePv?->doc_no }})</h3>
                    <div class="border border-[#d7dbe2] rounded-md max-h-56 overflow-y-auto">
                        @forelse ($this->returnablePvLines as $l)
                            <div class="flex items-center justify-between px-3 py-2 border-b border-[#eef1f5] last:border-0 text-xs">
                                <div>{{ $l->i_description }} <span class="text-muted">({{ Translator::t('proc_prt_entry_reject_qty_prefix', 'reject') }} {{ rtrim(rtrim(number_format((float) $l->c_qty_rejected, 2, ',', '.'), '0'), ',') }} {{ $l->i_uom_stock }})</span></div>
                                <button type="button" wire:click="addFromPvLine({{ $l->pv_line_id }})" class="text-[11px] font-bold text-white bg-accent rounded px-2.5 py-1">+ {{ Translator::t('shared_grid_add_row', 'Tambah') }}</button>
                            </div>
                        @empty
                            <p class="text-xs text-muted px-3 py-3">{{ Translator::t('proc_prt_entry_no_reject_qc', 'Tidak ada baris reject QC tersisa dari PV ini.') }}</p>
                        @endforelse
                    </div>
                </div>
            @else
                <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4">
                    <h3 class="text-xs text-navy uppercase font-bold mb-3">{{ Translator::t('proc_prt_entry_add_from_stock', 'Tambah Item dari Stok') }}</h3>
                    <div class="relative" style="max-width:380px">
                        <x-form.input :case-toggle="false" wire:model.live.debounce.300ms="itemSearch" placeholder="🔍 {{ Translator::t('proc_prt_entry_item_search_placeholder', 'Cari kode / nama item…') }}" />
                        @if ($this->itemResults->isNotEmpty())
                            <div class="absolute top-full left-0 right-0 mt-1 bg-white border border-[#d7dbe2] rounded shadow-lg z-10 max-h-56 overflow-y-auto">
                                @foreach ($this->itemResults as $m)
                                    <button type="button" wire:click="addManualItem({{ $m->material_id }})" class="w-full text-left px-2.5 py-1.5 text-xs hover:bg-[#eef3fb] border-b border-[#eef1f5] last:border-b-0">
                                        <span class="font-mono font-bold text-accent">{{ $m->material_code }}</span> — {{ $m->material_name }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        @endif

        {{-- BARIS --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
            <div class="px-4 py-2.5 bg-[#f4f6fa] border-b border-[#d7dbe2]"><h3 class="text-xs text-navy uppercase font-bold">{{ Translator::t('proc_prt_entry_goods_detail', 'Rincian Barang') }}</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs min-w-[1000px]">
                    <thead class="text-[10px] text-muted uppercase border-b border-[#eef1f5]">
                        <tr>
                            <th class="text-left px-3 py-2">#</th>
                            <th class="text-left px-3 py-2">{{ Translator::t('proc_pv_col_item', 'Item') }}</th>
                            <th class="text-left px-3 py-2">{{ Translator::t('proc_pv_entry_lot', 'Lot') }}</th>
                            <th class="text-right px-3 py-2">{{ Translator::t('proc_prt_entry_qty_return', 'Qty Retur') }}</th>
                            <th class="text-left px-3 py-2">{{ Translator::t('proc_pv_col_uom', 'UoM') }}</th>
                            <th class="text-right px-3 py-2">{{ Translator::t('proc_entry_price', 'Harga') }}</th>
                            <th class="text-right px-3 py-2">{{ Translator::t('proc_prt_entry_return_value', 'Nilai Retur') }}</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->prt->lines as $line)
                            <tr wire:key="prtline-{{ $line->prt_line_id }}">
                                <td class="px-3 py-2 text-muted">{{ $line->line_no }}</td>
                                <td class="px-3 py-2">{{ $line->i_description }}</td>
                                <td class="px-3 py-2">
                                    <x-form.input value="{{ $line->i_lot_no }}" :disabled="$readOnly"
                                        wire:change="updateLine({{ $line->prt_line_id }}, 'i_lot_no', $event.target.value)" class="w-20 !py-1 disabled:bg-transparent disabled:border-transparent" />
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <x-form.input type="number" step="0.01" value="{{ $line->i_qty }}" :disabled="$readOnly"
                                        wire:change="updateLine({{ $line->prt_line_id }}, 'i_qty', $event.target.value)" class="w-20 !py-1 text-right disabled:bg-transparent disabled:border-transparent" />
                                </td>
                                <td class="px-3 py-2">{{ $line->i_uom }}</td>
                                <td class="px-3 py-2 text-right">
                                    <x-form.input type="number" step="0.01" value="{{ $line->c_unit_price }}" :disabled="$readOnly || $line->pv_line_id"
                                        wire:change="updateLine({{ $line->prt_line_id }}, 'c_unit_price', $event.target.value)" class="w-24 !py-1 text-right disabled:bg-transparent disabled:border-transparent" />
                                </td>
                                <td class="px-3 py-2 text-right font-mono font-semibold">{{ number_format((float) $line->c_line_value, 0, ',', '.') }}</td>
                                <td class="px-3 py-2">
                                    @if (! $readOnly)
                                        <button type="button" wire:click="removeLine({{ $line->prt_line_id }})" class="text-[#a3241f] text-xs">✕</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-3 py-6 text-center text-muted text-xs">{{ Translator::t('proc_prt_entry_empty_lines', 'Belum ada baris. Tarik dari PV atau tambah item stok di atas.') }}</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-[#d7dbe2] font-bold">
                            <td colspan="6" class="px-3 py-2 text-right">{{ Translator::t('proc_prt_entry_total_return_value', 'Total Nilai Retur') }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $this->prt->c_credit_value, 0, ',', '.') }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- DISPOSISI & FOLLOW-UP --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4">
            <h3 class="text-xs text-navy uppercase font-bold mb-3">{{ Translator::t('proc_prt_entry_disposition_followup', 'Disposisi & Follow-up') }}</h3>
            <div class="grid">
                <x-form.input label="{{ Translator::t('proc_prt_entry_comm_method', 'Metode Komunikasi') }}" value="{{ $this->prt->comm_method }}" :disabled="$readOnly" placeholder="Email / Telp / WA" wire:change="updateHeader('comm_method', $event.target.value)" class="disabled:bg-[#f8fafc]" />
                <x-form.input label="{{ Translator::t('proc_prt_entry_supplier_pic', 'PIC Supplier') }}" value="{{ $this->prt->supplier_pic }}" :disabled="$readOnly" wire:change="updateHeader('supplier_pic', $event.target.value)" class="disabled:bg-[#f8fafc]" />
                <x-form.date-picker label="{{ Translator::t('proc_prt_entry_physical_ship_date', 'Tgl Kirim Fisik') }}" value="{{ $this->prt->physical_return_date?->format('Y-m-d') }}" :disabled="$readOnly" wire:change="updateHeader('physical_return_date', $event.target.value)" class="disabled:bg-[#f8fafc]" />
                <x-form.input label="{{ Translator::t('proc_prt_entry_expedition', 'Ekspedisi') }}" value="{{ $this->prt->expedition }}" :disabled="$readOnly" wire:change="updateHeader('expedition', $event.target.value)" class="disabled:bg-[#f8fafc]" />
                @if ($this->prt->i_disposition === 'CREDIT_NOTE')
                    <x-form.input label="{{ Translator::t('proc_prt_entry_cn_supplier_no', 'No. CN Supplier') }}" value="{{ $this->prt->cn_supplier_no }}" :disabled="$readOnly" wire:change="updateHeader('cn_supplier_no', $event.target.value)" class="disabled:bg-[#f8fafc]" />
                    <x-form.date-picker label="{{ Translator::t('proc_prt_entry_cn_received_date', 'Tgl CN Diterima') }}" value="{{ $this->prt->cn_received_date?->format('Y-m-d') }}" :disabled="$readOnly" wire:change="updateHeader('cn_received_date', $event.target.value)" class="disabled:bg-[#f8fafc]" />
                @endif
            </div>
            <div class="mt-3">
                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ Translator::t('proc_prt_entry_notes_chronology', 'Catatan / Kronologi') }}</label>
                <textarea rows="2" @disabled($readOnly) wire:change="updateHeader('i_notes', $event.target.value)" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">{{ $this->prt->i_notes }}</textarea>
            </div>
            @if ($this->prt->rm_stock_doc_id === null && in_array($this->prt->status, ['CONFIRMED', 'COMPLETED'], true))
                <p class="text-[10.5px] text-muted mt-2">ℹ {{ Translator::t('proc_prt_entry_no_stock_out_hint', 'Retur ini belum ditautkan ke dokumen Stock Out gudang manapun — kalau barangnya sudah sempat masuk stok, buat Stock Out (Adjustment) terpisah di Inventory sesuai kebutuhan.') }}</p>
            @endif
        </div>

        @if ($this->approvalHistory->isNotEmpty())
            <div class="border border-[#eef1f5] rounded-xl p-4 bg-white mb-4">
                <p class="text-[10.5px] font-bold text-muted uppercase mb-3">{{ Translator::t('proc_entry_approval_history', 'Riwayat Approval') }}</p>
                <div class="relative pl-5 border-l-2 border-[#eef1f5] ml-1 space-y-3 text-sm">
                    @foreach ($this->approvalHistory as $h)
                        <div class="relative">
                            <div class="absolute -left-[26px] top-0.5 w-3 h-3 rounded-full border-2 {{ match ($h->action) {
                                'REJECTED' => 'bg-[#fbeaea] border-[#a3241f]',
                                'HOLD' => 'bg-[#f1ecfc] border-[#6d28d9]',
                                default => 'bg-[#eaf7ee] border-[#1f8a4c]',
                            } }}"></div>
                            <p class="font-semibold text-xs">{{ $h->action }}</p>
                            <p class="text-[10.5px] text-muted">{{ Translator::t('proc_entry_by_prefix', 'Oleh:') }} {{ $h->created_by ?? Translator::t('proc_entry_system_fallback', 'sistem') }} · {{ $h->acted_at?->translatedFormat('d M Y, H:i') }}{{ $h->is_acting ? ' ('.Translator::t('proc_entry_acting_suffix', 'PLT').')' : '' }}</p>
                            @if ($h->notes)
                                <p class="text-[10.5px] {{ $h->action === 'REJECTED' ? 'text-[#a3241f]' : 'text-[#6b4fa0]' }} mt-0.5">"{{ $h->notes }}"</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($this->prt->status === 'DRAFT')
            <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-8">
                @if ($cancelError)
                    <p class="text-[11px] text-[#a3241f] mb-2">⚠ {{ $cancelError }}</p>
                @endif
                <div class="flex justify-end gap-2 items-start">
                    <div class="flex-1 max-w-[380px]">
                        <x-form.textarea wire:model="cancelReason" rows="1" placeholder="{{ Translator::t('proc_prt_entry_cancel_placeholder', 'Alasan pembatalan (min. 10 karakter, kalau mau batalkan)...') }}" />
                    </div>
                    <button type="button" wire:click="cancelDoc" wire:confirm="{{ Translator::t('proc_prt_entry_confirm_cancel', 'Batalkan dokumen retur ini?') }}" class="border border-[#d7dbe2] text-[#a3241f] text-xs font-bold px-4 py-2 rounded-md">{{ Translator::t('proc_pq_entry_btn_cancel', 'Batalkan') }}</button>
                    <button type="button" wire:click="submitForApproval" wire:confirm="{{ Translator::t('proc_prt_entry_confirm_submit', 'Ajukan retur ini utk approval Manajer Operasional? Nomor dokumen akan diterbitkan.') }}" class="bg-navy text-white text-xs font-bold px-4 py-2 rounded-md">{{ Translator::t('proc_prt_entry_btn_submit', 'Ajukan Persetujuan') }}</button>
                </div>
            </div>
        @elseif ($this->pendingApproval)
            <div class="border-2 border-dashed border-[#d7dbe2] rounded-xl p-4 bg-white mb-8">
                <p class="text-xs font-bold text-navy mb-2">{{ Translator::t('proc_entry_approval_decision', 'Keputusan Approval') }} — {{ $this->pendingApproval->pending_label ?? Translator::t('proc_entry_awaiting_decision', 'Menunggu keputusan Anda') }}</p>
                @if ($approvalError)
                    <p class="text-[11px] text-[#a3241f] mb-2">⚠ {{ $approvalError }}</p>
                @endif
                <div class="mb-3">
                    <x-form.textarea wire:model="approvalNote" rows="2" placeholder="{{ Translator::t('proc_prt_entry_approval_note_placeholder', 'Alasan penolakan, atau pertanyaan tambahan...') }}" class="!text-sm">
                        <x-slot:label>{{ Translator::t('proc_entry_note_label', 'Catatan') }} <span class="text-[#a3241f] normal-case">({{ Translator::t('proc_prt_entry_note_required_hint', 'wajib utk Tolak / Minta Info') }})</span></x-slot:label>
                    </x-form.textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="holdDoc" wire:confirm="{{ Translator::t('proc_prt_entry_confirm_hold', 'Minta info tambahan? Retur tetap di antrian Anda.') }}" class="bg-[#6d28d9] text-white px-4 py-2 rounded-lg text-sm font-semibold">⏸ {{ Translator::t('proc_prt_entry_btn_hold', 'Minta Info') }}</button>
                    <button type="button" wire:click="rejectDoc" wire:confirm="{{ Translator::t('proc_prt_entry_confirm_reject', 'Tolak retur ini? Akan dikembalikan ke draft.') }}" class="bg-[#a3241f] text-white px-4 py-2 rounded-lg text-sm font-semibold">✕ {{ Translator::t('proc_entry_btn_reject', 'Tolak') }}</button>
                    <button type="button" wire:click="approveDoc" wire:confirm="{{ Translator::t('proc_prt_entry_confirm_approve', 'Setujui retur ini?') }}" class="bg-[#1f8a4c] text-white px-4 py-2 rounded-lg text-sm font-semibold">✓ {{ Translator::t('proc_entry_btn_approve', 'Setujui') }}</button>
                </div>
            </div>
        @elseif ($this->prt->status === 'CONFIRMED')
            <div class="flex justify-end gap-2 mb-8">
                <button type="button" wire:click="markComplete" wire:confirm="{{ Translator::t('proc_prt_entry_confirm_complete', 'Tandai retur ini selesai? (barang pengganti/CN sudah diterima)') }}" class="bg-navy text-white text-xs font-bold px-4 py-2 rounded-md">✓ {{ Translator::t('proc_prt_entry_btn_complete', 'Tandai Selesai') }}</button>
            </div>
        @endif
    </div>
</div>
