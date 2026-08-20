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

    public function mount(int $po): void
    {
        $this->poId = $po;
    }

    #[Computed]
    public function po(): PoOrder
    {
        return PoOrder::with(['lines' => fn ($q) => $q->orderBy('line_no')])->findOrFail($this->poId);
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

        @if ($this->po->status === 'DRAFT')
            <div class="flex justify-end gap-2 mb-8">
                <button type="button" wire:click="delete" wire:confirm="Hapus PO draft ini? Alokasi PR yang sudah ditarik akan dilepas kembali." class="border border-[#d7dbe2] text-[#a3241f] text-xs font-bold px-4 py-2 rounded-md">Hapus</button>
                <button type="button" wire:click="submit" wire:confirm="Ajukan PO ini untuk approval? Nomor dokumen akan diterbitkan." class="bg-navy text-white text-xs font-bold px-4 py-2 rounded-md">Ajukan Approval</button>
            </div>
        @endif
    </div>
</div>
