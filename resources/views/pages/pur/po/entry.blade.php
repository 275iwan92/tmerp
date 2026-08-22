<?php

use App\Models\Master\OrgDepartment;
use App\Models\Pur\PoLine;
use App\Models\Pur\PoOrder;
use App\Models\Pur\PrLineAlloc;
use App\Services\Flow\FlowEngineService;
use App\Services\Pur\PoBuilderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Purchase Order Entry — TELASIH MAKMUR ERP')] class extends Component
{
    public int $poId;

    public string $blockedMessage = '';

    // Layar approval PO tidak lagi punya form ringkas terpisah -- approver
    // menyetujui/menolak/menahan langsung dari form PO yang sama (field
    // otomatis read-only krn status != DRAFT), sama seperti pola ME29N di SAP,
    // action pane di Dynamics 365, dan approval di form PO Odoo. Lihat
    // docs/GRID_UI_STANDARD.md & memory project_procurement_module_status.
    public string $approvalNote = '';

    public string $approvalError = '';

    public function mount(int $po): void
    {
        $this->poId = $po;
    }

    #[Computed]
    public function po(): PoOrder
    {
        return PoOrder::with(['lines' => fn ($q) => $q->orderBy('line_no')])->findOrFail($this->poId);
    }

    // Baris flow_log TERAKHIR utk PO ini kalau masih pending di posisi user
    // login (SUBMITTED atau HOLD) -- null kalau bukan approver-nya / sudah diputuskan.
    #[Computed]
    public function pendingApproval()
    {
        return app(FlowEngineService::class)->pendingForUser('PO', Auth::id())->firstWhere('doc_id', $this->poId);
    }

    #[Computed]
    public function approvalHistory()
    {
        return app(FlowEngineService::class)->history('PO', $this->poId);
    }

    #[Computed]
    public function supplier()
    {
        return DB::connection('tm2mst')->table('sup_supplier')->where('id_supplier', $this->po->i_supplier_id)->first();
    }

    #[Computed]
    public function departments()
    {
        return OrgDepartment::where('is_active', true)->orderBy('department_name')->get();
    }

    // Alokasi PR yang APPROVED, belum terkonversi, dan supplier-nya cocok
    // (atau belum ditetapkan -- "rencana; final saat PO", PROC_TIER_3 §2 pr_line_alloc).
    #[Computed]
    public function availableAllocations()
    {
        return PrLineAlloc::with(['line.requisition'])
            ->where('c_is_converted', false)
            ->where(function ($q) {
                $q->whereNull('i_supplier_id')->orWhere('i_supplier_id', $this->po->i_supplier_id);
            })
            ->whereHas('line.requisition', fn ($q) => $q->where('status', 'APPROVED'))
            ->get()
            ->filter(fn ($alloc) => $alloc->line !== null);
    }

    public function addAllocation(int $allocId, PoBuilderService $builder): void
    {
        if ($this->po->status !== 'DRAFT') {
            return;
        }

        $alloc = PrLineAlloc::findOrFail($allocId);
        PoBuilderService::addAllocation($this->po, $alloc);

        $this->recalcTax();
        unset($this->po, $this->availableAllocations);
    }

    public function updateLine(int $lineId, string $field, $value): void
    {
        if ($this->po->status !== 'DRAFT') {
            return;
        }

        $allowed = ['i_price', 'i_disc_pct'];

        if (! in_array($field, $allowed, true)) {
            return;
        }

        $line = PoLine::where('po_id', $this->poId)->findOrFail($lineId);
        $line->{$field} = $value === '' ? 0 : $value;
        $line->recalcNet();
        $line->updated_by = Auth::user()->login;
        $line->save();

        $this->recalcTax();
        unset($this->po);
    }

    public function updateHeader(string $field, $value): void
    {
        if ($this->po->status !== 'DRAFT') {
            return;
        }

        $allowed = ['i_doc_date', 'i_po_type', 'i_procure_type', 'i_payment_term', 'i_delivery_date', 'i_ship_to', 'i_notes'];

        if (! in_array($field, $allowed, true)) {
            return;
        }

        $this->po->update([$field => $value === '' ? null : $value, 'updated_by' => Auth::user()->login]);
        unset($this->po);
    }

    protected function recalcTax(): void
    {
        $po = PoOrder::with('lines')->findOrFail($this->poId);
        $po->recalcTax();
        $po->save();
    }

    public function submit(FlowEngineService $flow): void
    {
        if ($this->po->status !== 'DRAFT' || $this->po->lines->isEmpty()) {
            return;
        }

        $this->recalcTax();
        $login = Auth::user()->login;
        $po = PoOrder::findOrFail($this->poId);

        $po->update([
            'doc_no' => PoOrder::nextDocNo(),
            'status' => 'SUBMITTED',
            'updated_by' => $login,
        ]);

        $result = $flow->submit('PO', $this->poId, 'SUBMITTED', (float) $po->c_grand_total);

        if ($result === null) {
            $this->blockedMessage = 'Tidak ada konfigurasi approval aktif untuk modul PO. Dokumen tersimpan sebagai SUBMITTED tapi belum masuk antrian approval siapapun — hubungi Admin.';
        }

        unset($this->po);
    }

    // PROC_TIER_2 §C-2: APPROVED -> Kirim ke Supplier -> SENT (aksi manual, bukan
    // otomatis saat approve terakhir -- officer yang menegaskan PO benar-benar
    // sudah dikirim). Status SENT ini yang jadi syarat PO muncul di worklist PV.
    public function sendToSupplier(): void
    {
        if ($this->po->status !== 'APPROVED') {
            return;
        }

        $this->po->update(['status' => 'SENT', 'sent_at' => now(), 'updated_by' => Auth::user()->login]);
        unset($this->po);
    }

    public function approveDoc(FlowEngineService $flow): void
    {
        $pending = $this->pendingApproval;

        if (! $pending) {
            return;
        }

        $result = $flow->approve('PO', $this->poId, $pending->flow_step_id, (float) $pending->amount_snapshot);

        $this->po->update(['status' => $result['status'], 'updated_by' => Auth::user()->login]);

        $this->approvalNote = '';
        $this->approvalError = '';
        unset($this->po, $this->pendingApproval, $this->approvalHistory);
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

        $result = $flow->reject('PO', $this->poId, $pending->flow_step_id, $this->approvalNote, (float) $pending->amount_snapshot);

        $this->po->update(['status' => $result['status'], 'updated_by' => Auth::user()->login]);

        $this->approvalNote = '';
        $this->approvalError = '';
        unset($this->po, $this->pendingApproval, $this->approvalHistory);
    }

    // Hold: belum mau diputuskan (mis. cash flow belum siap) -- status TIDAK
    // berubah, PO tetap SUBMITTED dan tetap di antrian approver ini sendiri.
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

        $flow->hold('PO', $this->poId, $pending->flow_step_id, $this->approvalNote, (float) $pending->amount_snapshot);

        $this->approvalNote = '';
        $this->approvalError = '';
        unset($this->pendingApproval, $this->approvalHistory);
    }

    public function delete()
    {
        if ($this->po->status !== 'DRAFT') {
            return;
        }

        // Lepas alokasi yang sudah ditarik supaya bisa dipakai PO lain lagi.
        PrLineAlloc::whereIn('alloc_id', $this->po->lines->pluck('alloc_id')->filter())
            ->update(['c_is_converted' => false]);

        $this->po->delete();

        return $this->redirect(route('pur.po.list'));
    }
};
?>

<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">📦 Purchase Order Entry</b>
        <span class="text-xs text-white/75">{{ $this->po->doc_no ?: '(belum ada nomor)' }} · {{ $this->supplier?->supplier_name }}</span>
        <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full
            {{ match ($this->po->status) {
                'DRAFT' => 'bg-[#eef1f5] text-[#4b5568]',
                'SUBMITTED' => 'bg-[#fff5e0] text-[#8a5a00]',
                'APPROVED', 'SENT', 'PARTIALLY_RECEIVED', 'RECEIVED', 'CLOSED' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                'REJECTED', 'CANCELLED' => 'bg-[#fbeaea] text-[#a3241f]',
                default => 'bg-[#eef1f5] text-[#4b5568]',
            } }}">{{ $this->po->status }}</span>
        <a href="{{ route('pur.po.list') }}" class="ml-auto bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">← Daftar PO</a>
    </header>

    <div class="max-w-[1400px] mx-auto my-5 px-4">
        @if ($blockedMessage)
            <div class="bg-[#fff5e0] border border-[#e0c26a] text-[#8a5a00] text-xs rounded-md px-4 py-2.5 mb-4">⚠ {{ $blockedMessage }}</div>
        @endif

        @if ($this->approvalHistory->last()?->action === 'HOLD')
            <div class="bg-[#f1ecfc] border border-[#ddd0f7] text-[#6b4fa0] text-xs rounded-md px-4 py-2.5 mb-4">
                ⏸ Ditahan oleh {{ $this->approvalHistory->last()->created_by ?? 'sistem' }} · {{ $this->approvalHistory->last()->acted_at?->translatedFormat('d M Y, H:i') }} — "{{ $this->approvalHistory->last()->notes }}"
            </div>
        @endif

        @if ($this->pendingApproval)
            @if ($this->po->i_procure_type === 'CAPEX')
                <div class="bg-[#fdf2ec] border border-[#f0c6a0] text-[#8a4b00] text-xs rounded-md px-4 py-2.5 mb-4">⚠ Tipe pengadaan CAPEX — nilai besar, non-rutin. Periksa lebih cermat.</div>
            @endif
            @if ($this->po->i_po_type === 'IMPORT')
                <div class="bg-[#eef3fb] border border-[#9cc0f0] text-[#1e3a8a] text-xs rounded-md px-4 py-2.5 mb-4">⚠ PO Import — kelengkapan dokumen impor (`po_import_doc`) masih INACTIVE fase ini, belum ada checklist.</div>
            @endif
        @endif

        {{-- HEADER --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4">
            <h3 class="text-xs text-navy uppercase font-bold mb-3">Informasi Umum</h3>
            <div class="grid grid-cols-4 gap-3">
                <div>
                    <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Supplier</label>
                    <input readonly value="{{ $this->supplier?->supplier_name }} ({{ $this->supplier?->supplier_code }})" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs bg-[#f8fafc] text-muted">
                </div>
                <div>
                    <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Tanggal PO</label>
                    <input type="date" value="{{ $this->po->i_doc_date?->format('Y-m-d') }}" @disabled($this->po->status !== 'DRAFT')
                        wire:change="updateHeader('i_doc_date', $event.target.value)" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                </div>
                <div>
                    <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Tipe PO</label>
                    <select @disabled($this->po->status !== 'DRAFT') wire:change="updateHeader('i_po_type', $event.target.value)" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                        <option value="LOCAL" @selected($this->po->i_po_type === 'LOCAL')>Local</option>
                        <option value="IMPORT" @selected($this->po->i_po_type === 'IMPORT')>Import</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Tipe Pengadaan</label>
                    <select @disabled($this->po->status !== 'DRAFT') wire:change="updateHeader('i_procure_type', $event.target.value)" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                        <option value="REPEAT" @selected($this->po->i_procure_type === 'REPEAT')>Repeat (rutin)</option>
                        <option value="ONE_TIME" @selected($this->po->i_procure_type === 'ONE_TIME')>One-Time</option>
                        <option value="CAPEX" @selected($this->po->i_procure_type === 'CAPEX')>Capex</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Syarat Bayar</label>
                    <input type="text" value="{{ $this->po->i_payment_term }}" placeholder="mis. Net 30" @disabled($this->po->status !== 'DRAFT')
                        wire:change="updateHeader('i_payment_term', $event.target.value)" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                </div>
                <div>
                    <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Tgl Kirim</label>
                    <input type="date" value="{{ $this->po->i_delivery_date?->format('Y-m-d') }}" @disabled($this->po->status !== 'DRAFT')
                        wire:change="updateHeader('i_delivery_date', $event.target.value)" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                </div>
                <div class="col-span-2">
                    <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Kirim Ke</label>
                    <input type="text" value="{{ $this->po->i_ship_to }}" @disabled($this->po->status !== 'DRAFT')
                        wire:change="updateHeader('i_ship_to', $event.target.value)" class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">
                </div>
            </div>
            <div class="mt-3">
                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Catatan</label>
                <textarea rows="2" @disabled($this->po->status !== 'DRAFT') wire:change="updateHeader('i_notes', $event.target.value)"
                    class="w-full px-2 py-1.5 border border-[#d7dbe2] rounded text-xs disabled:bg-[#f8fafc]">{{ $this->po->i_notes }}</textarea>
            </div>
        </div>

        @if ($this->po->status === 'DRAFT')
            {{-- SUMBER: alokasi PR tersedia --}}
            <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4">
                <h3 class="text-xs text-navy uppercase font-bold mb-3">Sumber — Alokasi PR Tersedia (supplier ini / belum ditetapkan)</h3>
                <div class="border border-[#d7dbe2] rounded-md max-h-64 overflow-y-auto">
                    @forelse ($this->availableAllocations as $alloc)
                        <div class="flex items-center justify-between px-3 py-2 border-b border-[#eef1f5] last:border-0 text-xs">
                            <div>
                                <span class="font-mono text-[10.5px] text-accent">{{ $alloc->line->requisition->doc_no }}</span>
                                — {{ $alloc->line->i_description }}
                                <span class="text-muted">({{ rtrim(rtrim(number_format((float) $alloc->i_qty, 2, ',', '.'), '0'), ',') }} {{ $alloc->line->i_uom }})</span>
                            </div>
                            <button type="button" wire:click="addAllocation({{ $alloc->alloc_id }})" class="text-[11px] font-bold text-white bg-accent rounded px-2.5 py-1">+ Tambah</button>
                        </div>
                    @empty
                        <p class="text-xs text-muted px-3 py-3">Tidak ada alokasi PR yang tersedia untuk supplier ini saat ini.</p>
                    @endforelse
                </div>
                <p class="text-[11px] text-muted mt-2">Alokasi dari PR berbeda boleh digabung ke satu PO ini selama supplier sama — baris tetap membawa asal PR masing-masing.</p>
            </div>
        @endif

        {{-- BARIS --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
            <div class="px-4 py-2.5 bg-[#f4f6fa] border-b border-[#d7dbe2]">
                <h3 class="text-xs text-navy uppercase font-bold">Baris Pesanan</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs min-w-[1200px]">
                    <thead class="text-[10px] text-muted uppercase border-b border-[#eef1f5]">
                        <tr>
                            <th class="text-left px-3 py-2">#</th>
                            <th class="text-left px-3 py-2">Item</th>
                            <th class="text-left px-3 py-2">Kelas</th>
                            <th class="text-right px-3 py-2">Qty</th>
                            <th class="text-left px-3 py-2">UoM</th>
                            <th class="text-right px-3 py-2">Harga</th>
                            <th class="text-right px-3 py-2">Disc %</th>
                            <th class="text-right px-3 py-2">Net</th>
                            <th class="text-left px-3 py-2">Asal PR</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eef1f5]">
                        @forelse ($this->po->lines as $line)
                            <tr wire:key="poline-{{ $line->po_line_id }}">
                                <td class="px-3 py-2 text-muted">{{ $line->line_no }}</td>
                                <td class="px-3 py-2"><span class="font-mono text-[10px] text-muted">{{ $line->c_item_code }}</span><br>{{ $line->i_description }}</td>
                                <td class="px-3 py-2">
                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded {{ $line->i_line_class === 'SERVICE' ? 'bg-[#f3eaff] text-[#6b2fb3]' : 'bg-[#fff5e0] text-[#8a5a00]' }}">{{ $line->i_line_class }}</span>
                                </td>
                                <td class="px-3 py-2 text-right font-mono">{{ rtrim(rtrim(number_format((float) $line->i_qty, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="px-3 py-2">{{ $line->i_uom }}</td>
                                <td class="px-3 py-2 text-right">
                                    <input type="number" step="0.01" value="{{ $line->i_price }}" @disabled($this->po->status !== 'DRAFT')
                                        wire:change="updateLine({{ $line->po_line_id }}, 'i_price', $event.target.value)" class="w-24 px-1.5 py-1 border border-[#d7dbe2] rounded text-xs text-right disabled:bg-transparent disabled:border-transparent">
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <input type="number" step="0.01" value="{{ $line->i_disc_pct }}" @disabled($this->po->status !== 'DRAFT')
                                        wire:change="updateLine({{ $line->po_line_id }}, 'i_disc_pct', $event.target.value)" class="w-16 px-1.5 py-1 border border-[#d7dbe2] rounded text-xs text-right disabled:bg-transparent disabled:border-transparent">
                                </td>
                                <td class="px-3 py-2 text-right font-mono font-semibold">{{ number_format((float) $line->c_line_net, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-[10.5px] text-muted">{{ $line->allocation?->line?->requisition?->doc_no }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-3 py-6 text-center text-muted text-xs">Belum ada baris. Tarik alokasi PR di atas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- RINGKASAN PAJAK --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4 ml-auto max-w-[380px]">
            <h3 class="text-xs text-navy uppercase font-bold mb-2">Ringkasan Pajak</h3>
            <div class="text-sm space-y-1">
                <div class="flex justify-between"><span class="text-muted">DPP</span><span class="font-mono">{{ number_format((float) $this->po->c_dpp, 0, ',', '.') }}</span></div>
                <div class="flex justify-between"><span class="text-muted">PPN 11%</span><span class="font-mono">{{ number_format((float) $this->po->c_ppn, 0, ',', '.') }}</span></div>
                <div class="flex justify-between border-t border-[#eef1f5] pt-1 font-bold"><span>Total Tagihan</span><span class="font-mono">{{ number_format((float) $this->po->c_grand_total, 0, ',', '.') }}</span></div>
                <div class="flex justify-between text-[#a3241f]"><span>PPh 23 (jasa) 2%</span><span class="font-mono">({{ number_format((float) $this->po->c_pph, 0, ',', '.') }})</span></div>
                <div class="flex justify-between border-t border-[#eef1f5] pt-1 font-bold text-navy"><span>Dibayar ke Supplier</span><span class="font-mono">{{ number_format((float) $this->po->c_net_payable, 0, ',', '.') }}</span></div>
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

        @if ($this->po->status === 'DRAFT')
            <div class="flex justify-end gap-2 mb-8">
                <button type="button" wire:click="delete" wire:confirm="Hapus PO draft ini? Alokasi PR yang sudah ditarik akan dilepas kembali." class="border border-[#d7dbe2] text-[#a3241f] text-xs font-bold px-4 py-2 rounded-md">Hapus</button>
                <button type="button" wire:click="submit" wire:confirm="Ajukan PO ini untuk approval? Nomor dokumen akan diterbitkan." class="bg-navy text-white text-xs font-bold px-4 py-2 rounded-md">Ajukan Approval</button>
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
                    <button type="button" wire:click="holdDoc" wire:confirm="Tahan PO ini? Tetap di antrian Anda, diputuskan lagi nanti." class="bg-[#6d28d9] text-white px-4 py-2 rounded-lg text-sm font-semibold">⏸ Hold</button>
                    <button type="button" wire:click="rejectDoc" wire:confirm="Tolak PO ini?" class="bg-[#a3241f] text-white px-4 py-2 rounded-lg text-sm font-semibold">✕ Tolak</button>
                    <button type="button" wire:click="approveDoc" wire:confirm="Setujui PO ini?" class="bg-[#1f8a4c] text-white px-4 py-2 rounded-lg text-sm font-semibold">✓ Setujui</button>
                </div>
            </div>
        @elseif ($this->po->status === 'APPROVED')
            <div class="flex justify-end gap-2 mb-8">
                <button type="button" wire:click="sendToSupplier" wire:confirm="Tandai PO ini sudah dikirim ke supplier?" class="bg-navy text-white text-xs font-bold px-4 py-2 rounded-md">📤 Kirim ke Supplier</button>
            </div>
        @endif
    </div>
</div>
