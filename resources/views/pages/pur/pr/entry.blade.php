<?php

/*
 * REMARK
 * Tujuan       : Layar entri Purchase Requisition (komponen v1 yang dipakai v2); harga taksiran baris bahan master diisi PaperPriceEngine bila ada harga kertas supplier usulan.
 * Sumber hulu  : Master Raw Material (supplier usulan, gramasi, ukuran), Supplier Paper Price.
 * Input        : Pilihan bahan master / teks bebas, qty, tanggal butuh.
 * Output       : pr_line + pr_requisition (Draft).
 * Dipakai oleh : Purchase Requisition v2 (PurchaseRequisition::component), Material Planning > Buy.
 * Status       : Aktif; harga kertas = saran engine, petugas boleh menimpa.
 */

use App\Models\Master\MatCategory;
use App\Models\Master\MatRaw;
use App\Models\Master\OrgDepartment;
use App\Models\Pur\PrLine;
use App\Models\Pur\PrRequisition;
use App\Services\Flow\FlowEngineService;
use App\Services\Pur\PaperPriceEngine;
use App\Services\Pur\PrAllocationService;
use App\Support\Localization\Translator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Purchase Requisition Entry — TELASIH MAKMUR ERP')] class extends Component
{
    public int $prId;

    public string $itemSource = 'MASTER_ITEM';

    // pick = id_category dari mat_category, per level
    public ?int $pick1 = null;

    public ?int $pick2 = null;

    public ?int $pick3 = null;

    public ?int $pick4 = null;

    public ?int $pick5 = null;

    public string $freeDescription = '';

    public string $freeUom = '';

    public string $freeAccountId = '';

    public string $freeError = '';

    // Pencarian cepat (kode/nama) -- alternatif utk user yang sudah hafal
    // kodenya, supaya tak wajib klik 5 dropdown kategori kalau tak perlu.
    // Cascade kategori TETAP ada di bawahnya utk yang belum hafal.
    public string $itemSearch = '';

    // Layar approval PR tidak lagi punya form ringkas terpisah -- approver
    // menyetujui/menolak/menahan langsung dari form PR yang sama (field
    // otomatis read-only krn status != DRAFT). Lihat docs/griduistandard.md
    // & memory project_procurement_module_status.
    public string $approvalNote = '';

    public string $approvalError = '';

    public function mount(int $pr): void
    {
        $this->prId = $pr;
    }

    #[Computed]
    public function pr(): PrRequisition
    {
        return PrRequisition::with(['lines' => fn ($q) => $q->orderBy('line_no')])->findOrFail($this->prId);
    }

    // Baris flow_log TERAKHIR utk PR ini kalau masih pending di posisi user
    // login (SUBMITTED atau HOLD) -- null kalau bukan approver-nya / sudah diputuskan.
    #[Computed]
    public function pendingApproval()
    {
        return app(FlowEngineService::class)->pendingForUser('PR', Auth::id())->firstWhere('doc_id', $this->prId);
    }

    #[Computed]
    public function approvalHistory()
    {
        return app(FlowEngineService::class)->history('PR', $this->prId);
    }

    #[Computed]
    public function departments()
    {
        return OrgDepartment::where('activate', true)->orderBy('department_name')->get();
    }

    #[Computed]
    public function coaAccounts()
    {
        return DB::connection('tm2acc')->table('fin_account')
            ->where('is_postable', 1)
            ->orderBy('account_code')
            ->get(['account_id', 'account_code', 'account_name']);
    }

    #[Computed]
    public function cat1Options()
    {
        return MatCategory::level(1)->where('activate', true)->orderBy('category_name')->get();
    }

    #[Computed]
    public function cat2Options()
    {
        return $this->pick1 ? MatCategory::level(2)->where('parent_id', $this->pick1)->where('activate', true)->orderBy('category_name')->get() : collect();
    }

    #[Computed]
    public function cat3Options()
    {
        return $this->pick2 ? MatCategory::level(3)->where('parent_id', $this->pick2)->where('activate', true)->orderBy('category_name')->get() : collect();
    }

    #[Computed]
    public function cat4Options()
    {
        return $this->pick3 ? MatCategory::level(4)->where('parent_id', $this->pick3)->where('activate', true)->orderBy('category_name')->get() : collect();
    }

    #[Computed]
    public function cat5Options()
    {
        return $this->pick4 ? MatCategory::level(5)->where('parent_id', $this->pick4)->where('activate', true)->orderBy('category_name')->get() : collect();
    }

    // Material yang cocok sejauh level dipilih -- tidak wajib sampai level 5,
    // banyak kategori (Ink/Glue/Chemical) hanya sedalam level 3 di master.
    // mat_raw.category1..5 masih TEXT -- resolve id_category terpilih
    // jadi nama sekali di sini, baru dipakai filter (bukan cascading di atas).
    #[Computed]
    public function matchingMaterials()
    {
        if (! $this->pick1) {
            return collect();
        }

        $names = MatCategory::whereIn('id_category', array_filter([$this->pick1, $this->pick2, $this->pick3, $this->pick4, $this->pick5]))
            ->pluck('category_name', 'level');

        $q = MatRaw::where('activate', 1)->where('stat_confirm', true)->where('category1', $names[1] ?? null);

        foreach ([2 => 'category2', 3 => 'category3', 4 => 'category4', 5 => 'category5'] as $level => $col) {
            if (isset($names[$level])) {
                $q->where($col, $names[$level]);
            }
        }

        return $q->orderBy('material_name')->limit(40)->get();
    }

    #[Computed]
    public function itemSearchResults()
    {
        if (mb_strlen(trim($this->itemSearch)) < 2) {
            return collect();
        }
        $term = $this->itemSearch;

        return MatRaw::where('activate', true)->where('stat_confirm', true)
            ->where(fn ($q) => $q->where('material_code', 'like', "%{$term}%")->orWhere('material_name', 'like', "%{$term}%"))
            ->orderBy('material_name')->limit(15)->get();
    }

    public function setPick(int $level, ?string $value): void
    {
        $this->{"pick{$level}"} = $value === '' ? null : (int) $value;

        // reset level di bawahnya supaya kombinasi tidak nyangkut
        foreach (range($level + 1, 5) as $deeper) {
            $this->{"pick{$deeper}"} = null;
        }
    }

    public function addMasterItem(int $materialId): void
    {
        if ($this->pr->status !== 'DRAFT') {
            return;
        }

        $material = MatRaw::findOrFail($materialId);
        $prefix1 = MatCategory::level(1)->where('category_name', $material->category1)->first();
        $login = Auth::user()->login;

        $nextLineNo = ((int) $this->pr->lines()->max('line_no')) + 1;

        $line = PrLine::create([
            'pr_id' => $this->prId,
            'line_no' => $nextLineNo,
            'i_item_source' => 'MASTER_ITEM',
            'item_id' => $material->material_id,
            'c_item_code' => $material->material_code,
            'i_description' => $material->material_name,
            'i_qty' => 1,
            'i_uom' => $material->uom_purchase ?: 'PCS',
            'i_est_price' => app(PaperPriceEngine::class)->priceFor($material, $material->suggest_supplier_id) ?? $material->last_unitprice,
            'i_department_id' => $this->pr->i_department_id,
            'i_coa_account_id' => $prefix1?->default_coa_account_id,
            'is_stock' => $prefix1?->stock_class !== 'SERVICE',
            'created_by' => $login,
            'updated_by' => $login,
        ]);
        $line->recalcSubtotal();
        $line->save();

        $this->pick1 = $this->pick2 = $this->pick3 = $this->pick4 = $this->pick5 = null;
        $this->itemSearch = '';
        $this->recalcTotal();
        unset($this->pr);
    }

    public function addFreeText(): void
    {
        $this->freeError = '';

        if ($this->pr->status !== 'DRAFT' || trim($this->freeDescription) === '') {
            return;
        }

        // pr_line.i_coa_account_id NOT NULL, dan teks bebas tak punya kategori yang menuntun ke akun
        // (beda dgn Item Master) -- jadi akun dipilih saat menambah, bukan sesudah baris tersimpan.
        if (! $this->isPostableAccount($this->freeAccountId)) {
            $this->freeError = t('Choose the account for the free-text item.');

            return;
        }

        $login = Auth::user()->login;
        $nextLineNo = ((int) $this->pr->lines()->max('line_no')) + 1;

        PrLine::create([
            'pr_id' => $this->prId,
            'line_no' => $nextLineNo,
            'i_item_source' => 'FREE_TEXT',
            'item_id' => null,
            'i_description' => $this->freeDescription,
            'i_qty' => 1,
            'i_uom' => $this->freeUom ?: '-',
            'i_department_id' => $this->pr->i_department_id,
            'i_coa_account_id' => (int) $this->freeAccountId,
            'is_stock' => false,
            'created_by' => $login,
            'updated_by' => $login,
        ]);

        $this->freeDescription = '';
        $this->freeUom = '';
        $this->freeAccountId = '';
        $this->recalcTotal();
        unset($this->pr);
    }

    public function updateLine(int $lineId, string $field, $value): void
    {
        if ($this->pr->status !== 'DRAFT') {
            return;
        }

        $allowed = ['i_description', 'i_qty', 'i_uom', 'i_est_price', 'i_department_id', 'i_coa_account_id', 'i_need_date', 'i_notes'];

        if (! in_array($field, $allowed, true)) {
            return;
        }

        // Kolom NOT NULL: pilihan kosong / isian yang dikosongkan diabaikan (nilai lama tetap), bukan disimpan NULL.
        if (($value === '' || $value === null) && in_array($field, ['i_description', 'i_qty', 'i_uom', 'i_department_id', 'i_coa_account_id'], true)) {
            return;
        }

        if ($field === 'i_coa_account_id' && ! $this->isPostableAccount($value)) {
            return;
        }

        $line = PrLine::where('pr_id', $this->prId)->findOrFail($lineId);
        $line->{$field} = $value === '' ? null : $value;
        $line->recalcSubtotal();
        $line->updated_by = Auth::user()->login;
        $line->save();

        $this->recalcTotal();
        unset($this->pr);
    }

    // Pengganti FK lintas-DB ke tm2acc.fin_account (is_postable=1).
    protected function isPostableAccount($accountId): bool
    {
        return (int) $accountId > 0 && $this->coaAccounts->contains('account_id', (int) $accountId);
    }

    public function removeLine(int $lineId): void
    {
        if ($this->pr->status !== 'DRAFT') {
            return;
        }

        PrLine::where('pr_id', $this->prId)->where('pr_line_id', $lineId)->delete();
        $this->recalcTotal();
        unset($this->pr);
    }

    public function updateHeader(string $field, $value): void
    {
        if ($this->pr->status !== 'DRAFT') {
            return;
        }

        $allowed = ['i_doc_date', 'i_department_id', 'i_need_date', 'i_notes'];

        if (! in_array($field, $allowed, true)) {
            return;
        }

        $this->pr->update([$field => $value === '' ? null : $value, 'updated_by' => Auth::user()->login]);
        unset($this->pr);
    }

    protected function recalcTotal(): void
    {
        $total = PrLine::where('pr_id', $this->prId)->sum('c_est_subtotal');
        PrRequisition::whereKey($this->prId)->update(['c_est_total' => $total]);
    }

    public function save(): void
    {
        unset($this->pr);
    }

    public string $blockedMessage = '';

    public function submit(FlowEngineService $flow): void
    {
        if ($this->pr->status !== 'DRAFT' || $this->pr->lines->isEmpty() || ! $this->pr->i_department_id) {
            return;
        }

        $this->recalcTotal();
        $login = Auth::user()->login;
        $amount = (float) PrRequisition::whereKey($this->prId)->value('c_est_total');

        $this->pr->update([
            'doc_no' => PrRequisition::nextDocNo(),
            'status' => 'SUBMITTED',
            'updated_by' => $login,
        ]);

        $result = $flow->submit('PR', $this->prId, 'SUBMITTED', $amount);

        if ($result === null) {
            $this->blockedMessage = Translator::t('proc_pr_entry_no_flow_config', 'Tidak ada konfigurasi approval aktif untuk modul PR. Dokumen tersimpan sebagai SUBMITTED tapi belum masuk antrian approval siapapun — hubungi Admin.');
        }

        unset($this->pr);
    }

    public function approveDoc(FlowEngineService $flow): void
    {
        $pending = $this->pendingApproval;

        if (! $pending) {
            return;
        }

        $result = $flow->approve('PR', $this->prId, $pending->flow_step_id, (float) $pending->amount_snapshot);

        $this->pr->update(['status' => $result['status'], 'updated_by' => Auth::user()->login]);

        // Flow selesai (tak ada step lanjutan) -> siapkan alokasi default per
        // baris supaya PO Entry (ambil dari pr_line_alloc) selalu punya sumber.
        if ($result['final']) {
            PrAllocationService::createDefaultAllocationsForPr($this->pr);
        }

        $this->approvalNote = '';
        $this->approvalError = '';
        unset($this->pr, $this->pendingApproval, $this->approvalHistory);
    }

    public function rejectDoc(FlowEngineService $flow): void
    {
        $pending = $this->pendingApproval;

        if (! $pending) {
            return;
        }

        if (trim($this->approvalNote) === '') {
            $this->approvalError = Translator::t('proc_entry_reject_reason_required', 'Alasan penolakan wajib diisi.');

            return;
        }

        $result = $flow->reject('PR', $this->prId, $pending->flow_step_id, $this->approvalNote, (float) $pending->amount_snapshot);

        $this->pr->update(['status' => $result['status'], 'updated_by' => Auth::user()->login]);

        $this->approvalNote = '';
        $this->approvalError = '';
        unset($this->pr, $this->pendingApproval, $this->approvalHistory);
    }

    // Hold: belum mau diputuskan -- status TIDAK berubah, PR tetap SUBMITTED
    // dan tetap di antrian approver ini sendiri.
    public function holdDoc(FlowEngineService $flow): void
    {
        $pending = $this->pendingApproval;

        if (! $pending) {
            return;
        }

        if (trim($this->approvalNote) === '') {
            $this->approvalError = Translator::t('proc_entry_hold_reason_required', 'Catatan alasan Hold wajib diisi.');

            return;
        }

        $flow->hold('PR', $this->prId, $pending->flow_step_id, $this->approvalNote, (float) $pending->amount_snapshot);

        $this->approvalNote = '';
        $this->approvalError = '';
        unset($this->pendingApproval, $this->approvalHistory);
    }

    public function delete()
    {
        if ($this->pr->status !== 'DRAFT') {
            return;
        }

        $this->pr->delete();

        return $this->redirect(route('pur.pr.list'));
    }
};
?>

<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">🧾 {{ Translator::t('proc_pr_entry_title', 'Purchase Requisition Entry') }}</b>
        <span class="text-xs text-white/75">{{ $this->pr->doc_no ?: Translator::t('proc_entry_no_docno_yet', '(belum ada nomor - digenerate saat submit)') }}</span>
        <x-form.badge :tone="match ($this->pr->status) {
                'SUBMITTED' => 'warn',
                'APPROVED', 'PARTIALLY_ORDERED', 'FULLY_ORDERED', 'CLOSED' => 'success',
                'REJECTED', 'CANCELLED' => 'danger',
                default => 'default',
            }">{{ $this->pr->status }}</x-form.badge>
        @unless (in_array($this->pr->status, ['DRAFT', 'SUBMITTED'], true))
            <a href="{{ route('pur.pr.print', ['pr' => $this->prId]) }}" target="_blank"
                class="bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">🖨 {{ Translator::t('proc_pr_entry_btn_print', 'Cetak PR') }}</a>
        @endunless
        <x-back-button :fallback="route('pur.pr.list')" title="{{ Translator::t('proc_pr_entry_back_title', 'Kembali ke Daftar PR') }}" class="ml-auto" />
    </header>

    <div class="max-w-[1400px] mx-auto my-5 px-4">

        @if ($blockedMessage)
            <div class="bg-[#fff5e0] border border-[#e0c26a] text-[#8a5a00] text-xs rounded-md px-4 py-2.5 mb-4">
                ⚠ {{ $blockedMessage }}
            </div>
        @endif

        @if ($this->approvalHistory->last()?->action === 'HOLD')
            <div class="bg-[#f1ecfc] border border-[#ddd0f7] text-[#6b4fa0] text-xs rounded-md px-4 py-2.5 mb-4">
                ⏸ {{ Translator::t('proc_entry_held_by', 'Ditahan oleh') }} {{ $this->approvalHistory->last()->created_by ?? Translator::t('proc_entry_system_fallback', 'sistem') }} · {{ $this->approvalHistory->last()->acted_at?->translatedFormat('d M Y, H:i') }} — "{{ $this->approvalHistory->last()->notes }}"
            </div>
        @endif

        {{-- HEADER --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4">
            <h3 class="text-xs text-navy uppercase font-bold mb-3">{{ Translator::t('proc_entry_general_info', 'Informasi Umum') }}</h3>
            <div class="grid">
                <x-form.date-picker label="{{ Translator::t('proc_pr_entry_col_date', 'Tanggal PR') }}" value="{{ $this->pr->i_doc_date?->format('Y-m-d') }}" :disabled="$this->pr->status !== 'DRAFT'"
                    wire:change="updateHeader('i_doc_date', $event.target.value)" class="disabled:bg-[#f8fafc]" />
                <x-form.select :disabled="$this->pr->status !== 'DRAFT'" wire:change="updateHeader('i_department_id', $event.target.value)" class="disabled:bg-[#f8fafc]"
                    :selected="$this->pr->i_department_id" :options="$this->departments->pluck('department_name', 'department_id')->all()">
                    <x-slot:label>{{ Translator::t('proc_pr_entry_requesting_dept', 'Departemen Peminta') }} <span class="text-[#a3241f]">*</span></x-slot:label>
                </x-form.select>
                <x-form.input label="{{ Translator::t('proc_pr_col_source', 'Sumber') }}" readonly value="MANUAL" class="bg-[#f8fafc]" />
                <x-form.date-picker label="{{ Translator::t('proc_entry_need_date', 'Tgl Dibutuhkan') }}" value="{{ $this->pr->i_need_date?->format('Y-m-d') }}" :disabled="$this->pr->status !== 'DRAFT'"
                    wire:change="updateHeader('i_need_date', $event.target.value)" class="disabled:bg-[#f8fafc]" />
            </div>
            <div class="mt-3">
                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ Translator::t('proc_pr_entry_notes_label', 'Catatan / Justifikasi') }}</label>
                <textarea rows="2" @disabled($this->pr->status !== 'DRAFT') wire:change="updateHeader('i_notes', $event.target.value)"
                    class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">{{ $this->pr->i_notes }}</textarea>
            </div>
        </div>

        @if ($this->pr->status === 'DRAFT')
            {{-- ITEM PICKER --}}
            <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4" x-data="{ tab: 'master' }">
                <div class="flex items-center gap-2 mb-3">
                    <button type="button" @click="tab = 'master'" :class="tab === 'master' ? 'bg-navy text-white' : 'bg-[#eef1f5] text-[#4b5568]'" class="text-[11px] font-bold px-3 py-1.5 rounded">◆ {{ Translator::t('proc_entry_item_master_tab', 'Item Master') }}</button>
                    <button type="button" @click="tab = 'free'" :class="tab === 'free' ? 'bg-navy text-white' : 'bg-[#eef1f5] text-[#4b5568]'" class="text-[11px] font-bold px-3 py-1.5 rounded">{{ Translator::t('proc_entry_free_text_tab', 'Teks Bebas') }}</button>
                </div>

                <div x-show="tab === 'master'">
                    <div class="relative mb-3" style="max-width:420px">
                        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ Translator::t('proc_entry_quick_search', 'Cari Cepat') }} <span class="text-muted normal-case font-normal">({{ Translator::t('proc_entry_quick_search_hint', 'sudah hafal kode/nama?') }})</span></label>
                        <x-form.input :case-toggle="false" wire:model.live.debounce.300ms="itemSearch" placeholder="🔍 {{ Translator::t('proc_entry_item_search_placeholder', 'Ketik kode atau nama item…') }}" />
                        @if ($this->itemSearchResults->isNotEmpty())
                            <div class="absolute top-full left-0 right-0 mt-1 bg-white border border-[#d7dbe2] rounded shadow-lg z-10 max-h-56 overflow-y-auto">
                                @foreach ($this->itemSearchResults as $m)
                                    <button type="button" wire:click="addMasterItem({{ $m->material_id }})" wire:key="qsearch-{{ $m->material_id }}" class="w-full flex items-center justify-between text-left px-3 py-1.5 border-b border-[#eef1f5] last:border-0 hover:bg-[#eef3fb] text-xs">
                                        <span><b class="font-mono text-[11px]">{{ $m->material_code }}</b> — {{ $m->material_name }}</span>
                                        <span class="text-muted text-[10px]">{{ $m->uom_purchase }} @if($m->is_fx_sensitive) <span class="text-[#a3241f]">⚠ {{ Translator::t('proc_entry_fx_sensitive', 'kurs') }}</span> @endif</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-[10.5px] text-muted uppercase font-semibold">{{ Translator::t('proc_entry_browse_by_category', 'atau telusuri per kategori') }}</span>
                        <span class="flex-1 border-t border-[#eef1f5]"></span>
                    </div>
                    <div class="[display:grid] grid-cols-5 gap-2 mb-2">
                        <x-form.select label="{{ Translator::t('proc_entry_cat_category', 'Kategori') }}" wire:change="setPick(1, $event.target.value)"
                            :selected="$pick1" :options="$this->cat1Options->pluck('category_name', 'id_category')->all()" />
                        <x-form.select label="{{ Translator::t('proc_entry_cat_type', 'Jenis') }}" wire:change="setPick(2, $event.target.value)" :disabled="! $pick1" class="disabled:bg-[#f8fafc]"
                            :selected="$pick2" :options="$this->cat2Options->pluck('category_name', 'id_category')->all()" />
                        <x-form.select label="{{ Translator::t('proc_entry_cat_spec', 'Gramatur/Spec') }}" wire:change="setPick(3, $event.target.value)" :disabled="! $pick2" class="disabled:bg-[#f8fafc]"
                            :selected="$pick3" :options="$this->cat3Options->pluck('category_name', 'id_category')->all()" />
                        <x-form.select wire:change="setPick(4, $event.target.value)" :disabled="! $pick3 || $this->cat4Options->isEmpty()" class="disabled:bg-[#f8fafc]"
                            placeholder="{{ Translator::t('shared_option_all', '— semua —') }}" :selected="$pick4" :options="$this->cat4Options->pluck('category_name', 'id_category')->all()">
                            <x-slot:label>{{ Translator::t('proc_entry_cat_size', 'Ukuran') }} <span class="text-muted normal-case font-normal">({{ Translator::t('shared_optional', 'opsional') }})</span></x-slot:label>
                        </x-form.select>
                        <x-form.select wire:change="setPick(5, $event.target.value)" :disabled="! $pick4 || $this->cat5Options->isEmpty()" class="disabled:bg-[#f8fafc]"
                            placeholder="{{ Translator::t('shared_option_all', '— semua —') }}" :selected="$pick5" :options="$this->cat5Options->pluck('category_name', 'id_category')->all()">
                            <x-slot:label>{{ Translator::t('proc_entry_cat_brand', 'Merk') }} <span class="text-muted normal-case font-normal">({{ Translator::t('shared_optional', 'opsional') }})</span></x-slot:label>
                        </x-form.select>
                    </div>

                    @if ($pick1)
                        <div class="border border-[#d7dbe2] rounded-md max-h-56 overflow-y-auto mt-2">
                            @forelse ($this->matchingMaterials as $m)
                                <button type="button" wire:click="addMasterItem({{ $m->material_id }})" wire:key="mat-{{ $m->material_id }}"
                                    class="w-full flex items-center justify-between text-left px-3 py-1.5 border-b border-[#eef1f5] last:border-0 hover:bg-[#eef3fb] text-xs">
                                    <span><b class="font-mono text-[11px]">{{ $m->material_code }}</b> — {{ $m->material_name }}</span>
                                    <span class="text-muted text-[10px]">{{ $m->uom_purchase }} @if($m->is_fx_sensitive) <span class="text-[#a3241f]">⚠ {{ Translator::t('proc_entry_fx_sensitive', 'kurs') }}</span> @endif</span>
                                </button>
                            @empty
                                <p class="text-xs text-muted px-3 py-2">{{ Translator::t('proc_entry_no_matching_item', 'Tidak ada item cocok pada kombinasi ini.') }}</p>
                            @endforelse
                        </div>
                    @endif
                </div>

                <div x-show="tab === 'free'" class="flex gap-2 items-end">
                    <div class="flex-1">
                        <x-form.input label="{{ Translator::t('proc_entry_description', 'Deskripsi') }}" wire:model="freeDescription" placeholder="{{ Translator::t('proc_pr_entry_free_desc_placeholder', 'mis. Laptop Dell Latitude, Renovasi kantin') }}" />
                    </div>
                    <div class="w-[140px] shrink-0">
                        <x-form.input label="{{ Translator::t('proc_pv_col_uom', 'UoM') }}" wire:model="freeUom" placeholder="{{ Translator::t('proc_entry_unit_placeholder', 'Unit') }}" />
                    </div>
                    <div class="flex-1">
                        <x-form.select wire:model="freeAccountId" :selected="$freeAccountId"
                            :options="$this->coaAccounts->mapWithKeys(fn ($acc) => [$acc->account_id => $acc->account_code.' - '.$acc->account_name])->all()">
                            <x-slot:label>{{ Translator::t('proc_entry_coa_account', 'Akun COA') }} <span class="text-red-600">*</span></x-slot:label>
                        </x-form.select>
                    </div>
                    <button type="button" wire:click="addFreeText" class="text-[11px] font-bold text-white bg-navy rounded px-3 py-1.5">＋ {{ Translator::t('shared_grid_add_row', 'Tambah') }}</button>
                </div>
                @if ($freeError)
                    <p class="text-[11px] text-red-600 mb-2">⚠ {{ $freeError }}</p>
                @endif
                <p class="text-[11px] text-muted mt-2">{{ Translator::t('proc_pr_entry_free_text_hint', 'Teks bebas dipakai untuk pembelian sekali pakai (mesin, laptop, renovasi, jasa tak terdaftar) — departemen & akun COA wajib diisi manual per baris.') }}</p>
            </div>
        @endif

        {{-- ITEM LINES --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
            <div class="px-4 py-2.5 bg-[#f4f6fa] border-b border-[#d7dbe2]">
                <h3 class="text-xs text-navy uppercase font-bold">{{ Translator::t('proc_pr_entry_request_items', 'Item Permintaan') }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs min-w-[1100px]">
                    <thead class="text-[10px] text-muted uppercase border-b border-[#eef1f5]">
                        <tr>
                            <th class="text-left px-3 py-2">#</th>
                            <th class="text-left px-3 py-2">{{ Translator::t('proc_pv_col_item', 'Item') }}</th>
                            <th class="text-right px-3 py-2">{{ Translator::t('shared_qty', 'Qty') }}</th>
                            <th class="text-left px-3 py-2">{{ Translator::t('proc_pv_col_uom', 'UoM') }}</th>
                            <th class="text-right px-3 py-2">{{ Translator::t('proc_entry_est_price', 'Est. Harga') }}</th>
                            <th class="text-right px-3 py-2">{{ Translator::t('proc_entry_est_subtotal', 'Est. Subtotal') }}</th>
                            <th class="text-left px-3 py-2">{{ Translator::t('proc_entry_department', 'Departemen') }}</th>
                            <th class="text-left px-3 py-2">{{ Translator::t('proc_entry_coa_account', 'Akun COA') }}</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eef1f5]">
                        @forelse ($this->pr->lines as $line)
                            <tr wire:key="line-{{ $line->pr_line_id }}">
                                <td class="px-3 py-2 text-muted">{{ $line->line_no }}</td>
                                <td class="px-3 py-2">
                                    @if ($line->i_item_source === 'MASTER_ITEM')
                                        <span class="font-mono text-[10px] text-muted">{{ $line->c_item_code }}</span><br>{{ $line->i_description }}
                                    @else
                                        <x-form.input value="{{ $line->i_description }}" :disabled="$this->pr->status !== 'DRAFT'"
                                            wire:change="updateLine({{ $line->pr_line_id }}, 'i_description', $event.target.value)" class="!py-1 disabled:bg-transparent disabled:border-transparent" />
                                        <span class="text-[9px] text-[#8a5a00] bg-[#fff5e0] px-1 rounded">{{ Translator::t('proc_entry_free_text_badge', 'FREE TEXT') }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <x-form.input type="number" step="0.01" value="{{ $line->i_qty }}" :disabled="$this->pr->status !== 'DRAFT'"
                                        wire:change="updateLine({{ $line->pr_line_id }}, 'i_qty', $event.target.value)" class="w-20 !py-1 text-right disabled:bg-transparent disabled:border-transparent" />
                                </td>
                                <td class="px-3 py-2">
                                    @if ($line->i_item_source === 'FREE_TEXT')
                                        <x-form.input value="{{ $line->i_uom }}" :disabled="$this->pr->status !== 'DRAFT'"
                                            wire:change="updateLine({{ $line->pr_line_id }}, 'i_uom', $event.target.value)" class="w-16 !py-1 disabled:bg-transparent disabled:border-transparent" />
                                    @else
                                        {{ $line->i_uom }}
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <x-form.input type="number" step="0.01" value="{{ $line->i_est_price }}" :disabled="$this->pr->status !== 'DRAFT'" placeholder="{{ Translator::t('shared_optional', 'opsional') }}"
                                        wire:change="updateLine({{ $line->pr_line_id }}, 'i_est_price', $event.target.value)" class="w-24 !py-1 text-right disabled:bg-transparent disabled:border-transparent" />
                                </td>
                                <td class="px-3 py-2 text-right font-mono">{{ $line->c_est_subtotal !== null ? number_format((float) $line->c_est_subtotal, 0, ',', '.') : '—' }}</td>
                                <td class="px-3 py-2">
                                    <x-form.select wire:change="updateLine({{ $line->pr_line_id }}, 'i_department_id', $event.target.value)" :disabled="$this->pr->status !== 'DRAFT'" class="!py-1 disabled:bg-transparent disabled:border-transparent"
                                        :selected="$line->i_department_id" :options="$this->departments->pluck('department_name', 'department_id')->all()" />
                                </td>
                                <td class="px-3 py-2">
                                    <x-form.select wire:change="updateLine({{ $line->pr_line_id }}, 'i_coa_account_id', $event.target.value)" :disabled="$this->pr->status !== 'DRAFT'" class="!py-1 disabled:bg-transparent disabled:border-transparent"
                                        :selected="$line->i_coa_account_id" :options="$this->coaAccounts->mapWithKeys(fn ($acc) => [$acc->account_id => $acc->account_code.' - '.$acc->account_name])->all()" />
                                </td>
                                <td class="px-3 py-2">
                                    @if ($this->pr->status === 'DRAFT')
                                        <button type="button" wire:click="removeLine({{ $line->pr_line_id }})" wire:confirm="{{ Translator::t('shared_confirm_delete_row', 'Hapus baris ini?') }}" class="text-[#a3241f] text-xs">✕</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-3 py-6 text-center text-muted text-xs">{{ Translator::t('proc_pr_entry_empty_lines', 'Belum ada baris item. Pilih dari Item Master atau tambah Teks Bebas di atas.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-[#d7dbe2] font-bold">
                            <td colspan="5" class="px-3 py-2 text-right">{{ Translator::t('proc_entry_est_total', 'Total Estimasi') }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $this->pr->c_est_total, 0, ',', '.') }}</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
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

        @if ($this->pr->status === 'DRAFT')
            <div class="flex justify-end gap-2 mb-8">
                <button type="button" wire:click="delete" wire:confirm="{{ Translator::t('proc_pr_entry_confirm_delete', 'Hapus PR draft ini? Tindakan tidak bisa dibatalkan.') }}" class="border border-[#d7dbe2] text-[#a3241f] text-xs font-bold px-4 py-2 rounded-md">{{ Translator::t('shared_grid_delete', 'Hapus') }}</button>
                <button type="button" wire:click="save" class="border border-[#d7dbe2] text-navy text-xs font-bold px-4 py-2 rounded-md">{{ Translator::t('proc_entry_save_draft', 'Simpan Draft') }}</button>
                <button type="button" wire:click="submit" wire:confirm="{{ Translator::t('proc_pr_entry_confirm_submit', 'Ajukan PR ini untuk approval? Nomor dokumen akan diterbitkan dan baris tak bisa diedit lagi.') }}" class="bg-navy text-white text-xs font-bold px-4 py-2 rounded-md">{{ Translator::t('proc_entry_submit_approval', 'Ajukan Approval') }}</button>
            </div>
        @elseif ($this->pendingApproval)
            <div class="border-2 border-dashed border-[#d7dbe2] rounded-xl p-4 bg-white mb-8">
                <p class="text-xs font-bold text-navy mb-2">{{ Translator::t('proc_entry_approval_decision', 'Keputusan Approval') }} — {{ $this->pendingApproval->pending_label ?? Translator::t('proc_entry_awaiting_decision', 'Menunggu keputusan Anda') }}</p>
                @if ($approvalError)
                    <p class="text-[11px] text-[#a3241f] mb-2">⚠ {{ $approvalError }}</p>
                @endif
                <div class="mb-3">
                    <x-form.textarea wire:model="approvalNote" rows="2" placeholder="{{ Translator::t('proc_entry_approval_note_placeholder', 'Alasan penolakan, atau catatan hold (mis. cash flow belum siap)...') }}" class="!text-sm">
                        <x-slot:label>{{ Translator::t('proc_entry_note_label', 'Catatan') }} <span class="text-[#a3241f] normal-case">({{ Translator::t('proc_entry_note_required_hint', 'wajib utk Tolak / Hold') }})</span></x-slot:label>
                    </x-form.textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="holdDoc" wire:confirm="{{ Translator::t('proc_pr_entry_confirm_hold', 'Tahan PR ini? Tetap di antrian Anda, diputuskan lagi nanti.') }}" class="bg-[#6d28d9] text-white px-4 py-2 rounded-lg text-sm font-semibold">⏸ {{ Translator::t('proc_entry_btn_hold', 'Hold') }}</button>
                    <button type="button" wire:click="rejectDoc" wire:confirm="{{ Translator::t('proc_pr_entry_confirm_reject', 'Tolak PR ini?') }}" class="bg-[#a3241f] text-white px-4 py-2 rounded-lg text-sm font-semibold">✕ {{ Translator::t('proc_entry_btn_reject', 'Tolak') }}</button>
                    <button type="button" wire:click="approveDoc" wire:confirm="{{ Translator::t('proc_pr_entry_confirm_approve', 'Setujui PR ini?') }}" class="bg-[#1f8a4c] text-white px-4 py-2 rounded-lg text-sm font-semibold">✓ {{ Translator::t('proc_entry_btn_approve', 'Setujui') }}</button>
                </div>
            </div>
        @endif
    </div>
</div>
