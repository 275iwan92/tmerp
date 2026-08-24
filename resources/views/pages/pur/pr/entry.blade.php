<?php

use App\Models\Master\DocRmprefix1;
use App\Models\Master\DocRmprefix2;
use App\Models\Master\DocRmprefix3;
use App\Models\Master\DocRmprefix4;
use App\Models\Master\DocRmprefix5;
use App\Models\Master\MRawMaterial;
use App\Models\Master\OrgDepartment;
use App\Models\Pur\PrLine;
use App\Models\Pur\PrRequisition;
use App\Services\Flow\FlowEngineService;
use App\Services\Pur\PrAllocationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Purchase Requisition Entry — TELASIH MAKMUR ERP')] class extends Component
{
    public int $prId;

    public string $itemSource = 'MASTER_ITEM';

    public ?string $pick1 = null;

    public ?string $pick2 = null;

    public ?string $pick3 = null;

    public ?string $pick4 = null;

    public ?string $pick5 = null;

    public string $freeDescription = '';

    public string $freeUom = '';

    // Layar approval PR tidak lagi punya form ringkas terpisah -- approver
    // menyetujui/menolak/menahan langsung dari form PR yang sama (field
    // otomatis read-only krn status != DRAFT). Lihat docs/GRID_UI_STANDARD.md
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
        return OrgDepartment::where('is_active', true)->orderBy('department_name')->get();
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
        return DocRmprefix1::orderBy('category1')->get();
    }

    #[Computed]
    public function cat2Options()
    {
        if (! $this->pick1) {
            return collect();
        }

        return DocRmprefix2::where('category1', $this->pick1)->orderBy('category2')->get();
    }

    #[Computed]
    public function cat3Options()
    {
        if (! $this->pick1 || ! $this->pick2) {
            return collect();
        }

        return DocRmprefix3::where('category1', $this->pick1)->where('category2', $this->pick2)->orderBy('category3')->get();
    }

    #[Computed]
    public function cat4Options()
    {
        if (! $this->pick1 || ! $this->pick2 || ! $this->pick3) {
            return collect();
        }

        return DocRmprefix4::where('category1', $this->pick1)->where('category2', $this->pick2)->where('category3', $this->pick3)->orderBy('category4')->get();
    }

    #[Computed]
    public function cat5Options()
    {
        if (! $this->pick1 || ! $this->pick2 || ! $this->pick3 || ! $this->pick4) {
            return collect();
        }

        return DocRmprefix5::where('category1', $this->pick1)->where('category2', $this->pick2)
            ->where('category3', $this->pick3)->where('category4', $this->pick4)->orderBy('category5')->get();
    }

    // Material yang cocok sejauh level dipilih -- tidak wajib sampai level 5,
    // banyak kategori (Ink/Glue/Chemical) hanya sedalam level 3 di master.
    #[Computed]
    public function matchingMaterials()
    {
        if (! $this->pick1) {
            return collect();
        }

        $q = MRawMaterial::where('is_active', 1)->where('category1', $this->pick1);

        foreach (['pick2' => 'category2', 'pick3' => 'category3', 'pick4' => 'category4', 'pick5' => 'category5'] as $prop => $col) {
            if ($this->{$prop}) {
                $q->where($col, $this->{$prop});
            }
        }

        return $q->orderBy('material_name')->limit(40)->get();
    }

    public function setPick(int $level, ?string $value): void
    {
        $this->{"pick{$level}"} = $value === '' ? null : $value;

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

        $material = MRawMaterial::findOrFail($materialId);
        $prefix1 = DocRmprefix1::where('category1', $material->category1)->first();
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
            'i_est_price' => $material->last_unitprice,
            'i_department_id' => $this->pr->i_department_id,
            'i_coa_account_id' => $prefix1?->default_coa_account_id,
            'is_stock' => $prefix1?->stock_class !== 'SERVICE',
            'created_by' => $login,
            'updated_by' => $login,
        ]);
        $line->recalcSubtotal();
        $line->save();

        $this->pick1 = $this->pick2 = $this->pick3 = $this->pick4 = $this->pick5 = null;
        $this->recalcTotal();
        unset($this->pr);
    }

    public function addFreeText(): void
    {
        if ($this->pr->status !== 'DRAFT' || trim($this->freeDescription) === '') {
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
            'i_coa_account_id' => null,
            'is_stock' => false,
            'created_by' => $login,
            'updated_by' => $login,
        ]);

        $this->freeDescription = '';
        $this->freeUom = '';
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

        $line = PrLine::where('pr_id', $this->prId)->findOrFail($lineId);
        $line->{$field} = $value === '' ? null : $value;
        $line->recalcSubtotal();
        $line->updated_by = Auth::user()->login;
        $line->save();

        $this->recalcTotal();
        unset($this->pr);
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
            $this->blockedMessage = 'Tidak ada konfigurasi approval aktif untuk modul PR. Dokumen tersimpan sebagai SUBMITTED tapi belum masuk antrian approval siapapun — hubungi Admin.';
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
            $this->approvalError = 'Alasan penolakan wajib diisi.';

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
            $this->approvalError = 'Catatan alasan Hold wajib diisi.';

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
        <b class="text-[15px]">🧾 Purchase Requisition Entry</b>
        <span class="text-xs text-white/75">{{ $this->pr->doc_no ?: '(belum ada nomor - digenerate saat submit)' }}</span>
        <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full
            {{ match ($this->pr->status) {
                'DRAFT' => 'bg-[#eef1f5] text-[#4b5568]',
                'SUBMITTED' => 'bg-[#fff5e0] text-[#8a5a00]',
                'APPROVED', 'PARTIALLY_ORDERED', 'FULLY_ORDERED', 'CLOSED' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                'REJECTED', 'CANCELLED' => 'bg-[#fbeaea] text-[#a3241f]',
                default => 'bg-[#eef1f5] text-[#4b5568]',
            } }}">{{ $this->pr->status }}</span>
        <a href="{{ route('pur.pr.list') }}" title="Kembali ke Daftar PR"
            class="ml-auto inline-flex items-center gap-2 pl-3.5 pr-1 py-1 bg-[#43508a] rounded-full no-underline hover:brightness-110">
            <span class="text-[11px] font-bold uppercase tracking-wide text-white">Back</span>
            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#43508a] text-xs font-bold">←</span>
        </a>
    </header>

    <div class="max-w-[1400px] mx-auto my-5 px-4">

        @if ($blockedMessage)
            <div class="bg-[#fff5e0] border border-[#e0c26a] text-[#8a5a00] text-xs rounded-md px-4 py-2.5 mb-4">
                ⚠ {{ $blockedMessage }}
            </div>
        @endif

        @if ($this->approvalHistory->last()?->action === 'HOLD')
            <div class="bg-[#f1ecfc] border border-[#ddd0f7] text-[#6b4fa0] text-xs rounded-md px-4 py-2.5 mb-4">
                ⏸ Ditahan oleh {{ $this->approvalHistory->last()->created_by ?? 'sistem' }} · {{ $this->approvalHistory->last()->acted_at?->translatedFormat('d M Y, H:i') }} — "{{ $this->approvalHistory->last()->notes }}"
            </div>
        @endif

        {{-- HEADER --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4">
            <h3 class="text-xs text-navy uppercase font-bold mb-3">Informasi Umum</h3>
            <div class="grid grid-cols-4 gap-3">
                <div>
                    <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Tanggal PR</label>
                    <input type="date" value="{{ $this->pr->i_doc_date?->format('Y-m-d') }}" @disabled($this->pr->status !== 'DRAFT')
                        wire:change="updateHeader('i_doc_date', $event.target.value)" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                </div>
                <div>
                    <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Departemen Peminta <span class="text-[#a3241f]">*</span></label>
                    <select @disabled($this->pr->status !== 'DRAFT') wire:change="updateHeader('i_department_id', $event.target.value)" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                        <option value="">— pilih —</option>
                        @foreach ($this->departments as $dept)
                            <option value="{{ $dept->department_id }}" @selected($this->pr->i_department_id === $dept->department_id)>{{ $dept->department_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Sumber</label>
                    <input readonly value="MANUAL" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs bg-[#f8fafc] text-muted">
                </div>
                <div>
                    <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Tgl Dibutuhkan</label>
                    <input type="date" value="{{ $this->pr->i_need_date?->format('Y-m-d') }}" @disabled($this->pr->status !== 'DRAFT')
                        wire:change="updateHeader('i_need_date', $event.target.value)" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                </div>
            </div>
            <div class="mt-3">
                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Catatan / Justifikasi</label>
                <textarea rows="2" @disabled($this->pr->status !== 'DRAFT') wire:change="updateHeader('i_notes', $event.target.value)"
                    class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">{{ $this->pr->i_notes }}</textarea>
            </div>
        </div>

        @if ($this->pr->status === 'DRAFT')
            {{-- ITEM PICKER --}}
            <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4" x-data="{ tab: 'master' }">
                <div class="flex items-center gap-2 mb-3">
                    <button type="button" @click="tab = 'master'" :class="tab === 'master' ? 'bg-navy text-white' : 'bg-[#eef1f5] text-[#4b5568]'" class="text-[11px] font-bold px-3 py-1.5 rounded">◆ Item Master</button>
                    <button type="button" @click="tab = 'free'" :class="tab === 'free' ? 'bg-navy text-white' : 'bg-[#eef1f5] text-[#4b5568]'" class="text-[11px] font-bold px-3 py-1.5 rounded">Teks Bebas</button>
                </div>

                <div x-show="tab === 'master'">
                    <div class="grid grid-cols-5 gap-2 mb-2">
                        <div>
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Kategori</label>
                            <select wire:change="setPick(1, $event.target.value)" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                <option value="">— pilih —</option>
                                @foreach ($this->cat1Options as $o)
                                    <option value="{{ $o->category1 }}" @selected($pick1 === $o->category1)>{{ $o->category1 }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Jenis</label>
                            <select wire:change="setPick(2, $event.target.value)" @disabled(! $pick1) class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                                <option value="">— pilih —</option>
                                @foreach ($this->cat2Options as $o)
                                    <option value="{{ $o->category2 }}" @selected($pick2 === $o->category2)>{{ $o->category2 }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Gramatur/Spec</label>
                            <select wire:change="setPick(3, $event.target.value)" @disabled(! $pick2) class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                                <option value="">— pilih —</option>
                                @foreach ($this->cat3Options as $o)
                                    <option value="{{ $o->category3 }}" @selected($pick3 === $o->category3)>{{ $o->category3 }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Ukuran <span class="text-muted normal-case font-normal">(opsional)</span></label>
                            <select wire:change="setPick(4, $event.target.value)" @disabled(! $pick3 || $this->cat4Options->isEmpty()) class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                                <option value="">— semua —</option>
                                @foreach ($this->cat4Options as $o)
                                    <option value="{{ $o->category4 }}" @selected($pick4 === $o->category4)>{{ $o->category4 }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Merk <span class="text-muted normal-case font-normal">(opsional)</span></label>
                            <select wire:change="setPick(5, $event.target.value)" @disabled(! $pick4 || $this->cat5Options->isEmpty()) class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                                <option value="">— semua —</option>
                                @foreach ($this->cat5Options as $o)
                                    <option value="{{ $o->category5 }}" @selected($pick5 === $o->category5)>{{ $o->category5 }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    @if ($pick1)
                        <div class="border border-[#d7dbe2] rounded-md max-h-56 overflow-y-auto mt-2">
                            @forelse ($this->matchingMaterials as $m)
                                <button type="button" wire:click="addMasterItem({{ $m->material_id }})" wire:key="mat-{{ $m->material_id }}"
                                    class="w-full flex items-center justify-between text-left px-3 py-1.5 border-b border-[#eef1f5] last:border-0 hover:bg-[#eef3fb] text-xs">
                                    <span><b class="font-mono text-[11px]">{{ $m->material_code }}</b> — {{ $m->material_name }}</span>
                                    <span class="text-muted text-[10px]">{{ $m->uom_purchase }} @if($m->is_fx_sensitive) <span class="text-[#a3241f]">⚠ kurs</span> @endif</span>
                                </button>
                            @empty
                                <p class="text-xs text-muted px-3 py-2">Tidak ada item cocok pada kombinasi ini.</p>
                            @endforelse
                        </div>
                    @endif
                </div>

                <div x-show="tab === 'free'" class="grid grid-cols-[1fr_140px_auto] gap-2 items-end">
                    <div>
                        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Deskripsi</label>
                        <input type="text" wire:model="freeDescription" placeholder="mis. Laptop Dell Latitude, Renovasi kantin" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                    </div>
                    <div>
                        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">UoM</label>
                        <input type="text" wire:model="freeUom" placeholder="Unit" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                    </div>
                    <button type="button" wire:click="addFreeText" class="text-[11px] font-bold text-white bg-navy rounded px-3 py-1.5">＋ Tambah</button>
                </div>
                <p class="text-[11px] text-muted mt-2">Teks bebas dipakai untuk pembelian sekali pakai (mesin, laptop, renovasi, jasa tak terdaftar) — departemen &amp; akun COA wajib diisi manual per baris.</p>
            </div>
        @endif

        {{-- ITEM LINES --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
            <div class="px-4 py-2.5 bg-[#f4f6fa] border-b border-[#d7dbe2]">
                <h3 class="text-xs text-navy uppercase font-bold">Item Permintaan</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs min-w-[1100px]">
                    <thead class="text-[10px] text-muted uppercase border-b border-[#eef1f5]">
                        <tr>
                            <th class="text-left px-3 py-2">#</th>
                            <th class="text-left px-3 py-2">Item</th>
                            <th class="text-right px-3 py-2">Qty</th>
                            <th class="text-left px-3 py-2">UoM</th>
                            <th class="text-right px-3 py-2">Est. Harga</th>
                            <th class="text-right px-3 py-2">Est. Subtotal</th>
                            <th class="text-left px-3 py-2">Departemen</th>
                            <th class="text-left px-3 py-2">Akun COA</th>
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
                                        <input type="text" value="{{ $line->i_description }}" @disabled($this->pr->status !== 'DRAFT')
                                            wire:change="updateLine({{ $line->pr_line_id }}, 'i_description', $event.target.value)" class="w-full px-1.5 py-1 border border-[#d7dbe2] rounded text-xs disabled:bg-transparent disabled:border-transparent">
                                        <span class="text-[9px] text-[#8a5a00] bg-[#fff5e0] px-1 rounded">FREE TEXT</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <input type="number" step="0.01" value="{{ $line->i_qty }}" @disabled($this->pr->status !== 'DRAFT')
                                        wire:change="updateLine({{ $line->pr_line_id }}, 'i_qty', $event.target.value)" class="w-20 px-1.5 py-1 border border-[#d7dbe2] rounded text-xs text-right disabled:bg-transparent disabled:border-transparent">
                                </td>
                                <td class="px-3 py-2">
                                    @if ($line->i_item_source === 'FREE_TEXT')
                                        <input type="text" value="{{ $line->i_uom }}" @disabled($this->pr->status !== 'DRAFT')
                                            wire:change="updateLine({{ $line->pr_line_id }}, 'i_uom', $event.target.value)" class="w-16 px-1.5 py-1 border border-[#d7dbe2] rounded text-xs disabled:bg-transparent disabled:border-transparent">
                                    @else
                                        {{ $line->i_uom }}
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <input type="number" step="0.01" value="{{ $line->i_est_price }}" @disabled($this->pr->status !== 'DRAFT') placeholder="opsional"
                                        wire:change="updateLine({{ $line->pr_line_id }}, 'i_est_price', $event.target.value)" class="w-24 px-1.5 py-1 border border-[#d7dbe2] rounded text-xs text-right disabled:bg-transparent disabled:border-transparent">
                                </td>
                                <td class="px-3 py-2 text-right font-mono">{{ $line->c_est_subtotal !== null ? number_format((float) $line->c_est_subtotal, 0, ',', '.') : '—' }}</td>
                                <td class="px-3 py-2">
                                    <select wire:change="updateLine({{ $line->pr_line_id }}, 'i_department_id', $event.target.value)" @disabled($this->pr->status !== 'DRAFT') class="w-full px-1.5 py-1 border border-[#d7dbe2] rounded text-xs disabled:bg-transparent disabled:border-transparent">
                                        <option value="">— pilih —</option>
                                        @foreach ($this->departments as $dept)
                                            <option value="{{ $dept->department_id }}" @selected($line->i_department_id === $dept->department_id)>{{ $dept->department_name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-3 py-2">
                                    <select wire:change="updateLine({{ $line->pr_line_id }}, 'i_coa_account_id', $event.target.value)" @disabled($this->pr->status !== 'DRAFT') class="w-full px-1.5 py-1 border border-[#d7dbe2] rounded text-xs disabled:bg-transparent disabled:border-transparent">
                                        <option value="">— pilih —</option>
                                        @foreach ($this->coaAccounts as $acc)
                                            <option value="{{ $acc->account_id }}" @selected($line->i_coa_account_id === $acc->account_id)>{{ $acc->account_code }} - {{ $acc->account_name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-3 py-2">
                                    @if ($this->pr->status === 'DRAFT')
                                        <button type="button" wire:click="removeLine({{ $line->pr_line_id }})" wire:confirm="Hapus baris ini?" class="text-[#a3241f] text-xs">✕</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-3 py-6 text-center text-muted text-xs">Belum ada baris item. Pilih dari Item Master atau tambah Teks Bebas di atas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-[#d7dbe2] font-bold">
                            <td colspan="5" class="px-3 py-2 text-right">Total Estimasi</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $this->pr->c_est_total, 0, ',', '.') }}</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        @if ($this->approvalHistory->isNotEmpty())
            <div class="border border-[#eef1f5] rounded-xl p-4 bg-white mb-4">
                <p class="text-[10.5px] font-bold text-muted uppercase mb-3">Riwayat Approval</p>
                <div class="relative pl-5 border-l-2 border-[#eef1f5] ml-1 space-y-3 text-sm">
                    @foreach ($this->approvalHistory as $h)
                        <div class="relative">
                            <div class="absolute -left-[26px] top-0.5 w-3 h-3 rounded-full border-2 {{ match ($h->action) {
                                'REJECTED' => 'bg-[#fbeaea] border-[#a3241f]',
                                'HOLD' => 'bg-[#f1ecfc] border-[#6d28d9]',
                                default => 'bg-[#eaf7ee] border-[#1f8a4c]',
                            } }}"></div>
                            <p class="font-semibold text-xs">{{ $h->action }}</p>
                            <p class="text-[10.5px] text-muted">Oleh: {{ $h->created_by ?? 'sistem' }} · {{ $h->acted_at?->translatedFormat('d M Y, H:i') }}{{ $h->is_acting ? ' (PLT)' : '' }}</p>
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
                <button type="button" wire:click="delete" wire:confirm="Hapus PR draft ini? Tindakan tidak bisa dibatalkan." class="border border-[#d7dbe2] text-[#a3241f] text-xs font-bold px-4 py-2 rounded-md">Hapus</button>
                <button type="button" wire:click="save" class="border border-[#d7dbe2] text-navy text-xs font-bold px-4 py-2 rounded-md">Simpan Draft</button>
                <button type="button" wire:click="submit" wire:confirm="Ajukan PR ini untuk approval? Nomor dokumen akan diterbitkan dan baris tak bisa diedit lagi." class="bg-navy text-white text-xs font-bold px-4 py-2 rounded-md">Ajukan Approval</button>
            </div>
        @elseif ($this->pendingApproval)
            <div class="border-2 border-dashed border-[#d7dbe2] rounded-xl p-4 bg-white mb-8">
                <p class="text-xs font-bold text-navy mb-2">Keputusan Approval — {{ $this->pendingApproval->pending_label ?? 'Menunggu keputusan Anda' }}</p>
                @if ($approvalError)
                    <p class="text-[11px] text-[#a3241f] mb-2">⚠ {{ $approvalError }}</p>
                @endif
                <div class="mb-3">
                    <label class="text-xs text-muted">Catatan <span class="text-[#a3241f]">(wajib utk Tolak / Hold)</span></label>
                    <textarea wire:model="approvalNote" rows="2" placeholder="Alasan penolakan, atau catatan hold (mis. cash flow belum siap)..." class="w-full border border-[#d7dbe2] rounded-lg px-3 py-2 mt-1 text-sm"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="holdDoc" wire:confirm="Tahan PR ini? Tetap di antrian Anda, diputuskan lagi nanti." class="bg-[#6d28d9] text-white px-4 py-2 rounded-lg text-sm font-semibold">⏸ Hold</button>
                    <button type="button" wire:click="rejectDoc" wire:confirm="Tolak PR ini?" class="bg-[#a3241f] text-white px-4 py-2 rounded-lg text-sm font-semibold">✕ Tolak</button>
                    <button type="button" wire:click="approveDoc" wire:confirm="Setujui PR ini?" class="bg-[#1f8a4c] text-white px-4 py-2 rounded-lg text-sm font-semibold">✓ Setujui</button>
                </div>
            </div>
        @endif
    </div>
</div>
