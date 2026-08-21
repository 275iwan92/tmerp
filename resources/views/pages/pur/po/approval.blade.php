<?php

use App\Models\Pur\PoOrder;
use App\Services\Flow\FlowEngineService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('PO Approval Center — TELASIH MAKMUR ERP')] class extends Component
{
    public ?int $selectedDocId = null;

    public string $note = '';

    public string $errorMessage = '';

    public function mount(FlowEngineService $flow): void
    {
        $first = $flow->pendingForUser('PO', Auth::id())->first();
        $this->selectedDocId = $first?->doc_id;
    }

    #[Computed]
    public function pendingQueue()
    {
        return app(FlowEngineService::class)->pendingForUser('PO', Auth::id());
    }

    #[Computed]
    public function selectedPo(): ?PoOrder
    {
        if (! $this->selectedDocId) {
            return null;
        }

        return PoOrder::with('lines')->find($this->selectedDocId);
    }

    #[Computed]
    public function selectedSupplier()
    {
        if (! $this->selectedPo) {
            return null;
        }

        return DB::connection('tm2mst')->table('sup_supplier')->where('id_supplier', $this->selectedPo->i_supplier_id)->first();
    }

    // v1_po_item_consolidated (PROC_TIER_3 §8) -- ringkasan qty & nilai PER ITEM,
    // supaya approver tak tenggelam di detail baris (PROC_TIER_4 §4 poin 3).
    #[Computed]
    public function itemConsolidated()
    {
        if (! $this->selectedPo) {
            return collect();
        }

        return $this->selectedPo->lines
            ->groupBy(fn ($l) => $l->c_item_code ?: '(bebas)')
            ->map(function ($rows, $code) {
                return (object) [
                    'item_code' => $code,
                    'description' => $rows->first()->i_description,
                    'line_count' => $rows->count(),
                    'total_qty' => $rows->sum('i_qty'),
                    'total_net' => $rows->sum('c_line_net'),
                    'min_price' => $rows->min('i_price'),
                    'max_price' => $rows->max('i_price'),
                ];
            })->values();
    }

    #[Computed]
    public function selectedPending()
    {
        return $this->pendingQueue->firstWhere('doc_id', $this->selectedDocId);
    }

    #[Computed]
    public function history()
    {
        if (! $this->selectedDocId) {
            return collect();
        }

        return app(FlowEngineService::class)->history('PO', $this->selectedDocId);
    }

    public function select(int $docId): void
    {
        $this->selectedDocId = $docId;
        $this->note = '';
        $this->errorMessage = '';
        unset($this->selectedPo, $this->history, $this->itemConsolidated);
    }

    public function approve(FlowEngineService $flow): void
    {
        $pending = $this->selectedPending;

        if (! $pending || ! $this->selectedPo) {
            return;
        }

        $result = $flow->approve('PO', $pending->doc_id, $pending->flow_step_id, (float) $pending->amount_snapshot);

        $this->selectedPo->update(['status' => $result['status'], 'updated_by' => Auth::user()->login]);

        $this->note = '';
        $this->selectedDocId = null;
        unset($this->pendingQueue, $this->selectedPo, $this->history);
    }

    public function reject(FlowEngineService $flow): void
    {
        $pending = $this->selectedPending;

        if (! $pending || ! $this->selectedPo) {
            return;
        }

        if (trim($this->note) === '') {
            $this->errorMessage = 'Alasan penolakan wajib diisi.';

            return;
        }

        $result = $flow->reject('PO', $pending->doc_id, $pending->flow_step_id, $this->note, (float) $pending->amount_snapshot);

        $this->selectedPo->update(['status' => $result['status'], 'updated_by' => Auth::user()->login]);

        $this->note = '';
        $this->errorMessage = '';
        $this->selectedDocId = null;
        unset($this->pendingQueue, $this->selectedPo, $this->history);
    }
};
?>

<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">✅ PO Approval Center</b>
        <span class="text-xs text-white/75">Khusus Purchase Order</span>
        <a href="{{ route('pur.po.list') }}" class="ml-auto bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">← Daftar PO</a>
    </header>

    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h1 class="text-lg font-bold text-navy">PO Menunggu Approval Saya</h1>
                <p class="text-xs text-muted">Antrian ditentukan dari jabatan Anda (termasuk penugasan PLT bila ada).</p>
            </div>
            <span class="bg-[#fff5e0] text-[#8a5a00] text-xs font-bold px-3 py-1.5 rounded-lg">{{ $this->pendingQueue->count() }} PO Menunggu</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[380px_1fr] gap-4">
            {{-- LIST KIRI --}}
            <div class="bg-white border border-[#d7dbe2] rounded-md p-3 h-fit">
                @forelse ($this->pendingQueue as $p)
                    <button type="button" wire:click="select({{ $p->doc_id }})" wire:key="q-{{ $p->doc_id }}"
                        class="w-full text-left border rounded-lg p-3 mb-2 cursor-pointer {{ $selectedDocId === $p->doc_id ? 'border-accent bg-[#eef3fb]' : 'border-[#d7dbe2] hover:border-accent' }}">
                        <div class="flex justify-between text-xs font-semibold">
                            <span>PO#{{ $p->doc_id }}</span>
                            <span class="{{ $selectedDocId === $p->doc_id ? 'text-accent' : '' }}">Rp {{ number_format((float) $p->amount_snapshot, 0, ',', '.') }}</span>
                        </div>
                        <p class="text-[10.5px] text-muted mt-0.5">{{ $p->created_by }} · {{ $p->acted_at?->translatedFormat('d M Y') }}</p>
                    </button>
                @empty
                    <p class="text-xs text-muted text-center py-6">Tidak ada PO menunggu approval Anda saat ini.</p>
                @endforelse
            </div>

            {{-- DETAIL KANAN --}}
            <div class="bg-white border border-[#d7dbe2] rounded-md p-5">
                @if (! $this->selectedPo)
                    <p class="text-xs text-muted text-center py-10">Pilih PO di kiri untuk melihat detail.</p>
                @else
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="font-bold text-base text-navy">{{ $this->selectedPo->doc_no }} — Purchase Order</h2>
                            <p class="text-xs text-muted">Diajukan oleh: {{ $this->selectedPo->created_by }} · Supplier: {{ $this->selectedSupplier?->supplier_name }}</p>
                        </div>
                        <span class="bg-[#fff5e0] text-[#8a5a00] text-xs font-bold px-2 py-0.5 rounded">{{ $this->selectedPending?->pending_label ?? 'Menunggu' }}</span>
                    </div>

                    @if ($this->selectedPo->i_procure_type === 'CAPEX')
                        <div class="bg-[#fdf2ec] border border-[#f0c6a0] text-[#8a4b00] text-xs rounded-md px-3 py-2 mb-3">⚠ Tipe pengadaan CAPEX — nilai besar, non-rutin. Periksa lebih cermat.</div>
                    @endif
                    @if ($this->selectedPo->i_po_type === 'IMPORT')
                        <div class="bg-[#eef3fb] border border-[#9cc0f0] text-[#1e3a8a] text-xs rounded-md px-3 py-2 mb-3">⚠ PO Import — kelengkapan dokumen impor (`po_import_doc`) masih INACTIVE fase ini, belum ada checklist.</div>
                    @endif

                    <div class="bg-[#f8fafc] border border-[#eef1f5] rounded-lg p-3 mb-3 text-sm">
                        <div class="grid grid-cols-4 gap-3 mb-2">
                            <div><span class="text-muted text-[10.5px] uppercase font-semibold">Nilai</span><br><b>Rp {{ number_format((float) $this->selectedPo->c_grand_total, 0, ',', '.') }}</b></div>
                            <div><span class="text-muted text-[10.5px] uppercase font-semibold">Tipe</span><br>{{ $this->selectedPo->i_po_type }} · {{ $this->selectedPo->i_procure_type }}</div>
                            <div><span class="text-muted text-[10.5px] uppercase font-semibold">Syarat Bayar</span><br>{{ $this->selectedPo->i_payment_term ?? '—' }}</div>
                            <div><span class="text-muted text-[10.5px] uppercase font-semibold">Tgl Kirim</span><br>{{ $this->selectedPo->i_delivery_date?->translatedFormat('d M Y') ?? '—' }}</div>
                        </div>
                    </div>

                    <div class="border border-[#eef1f5] rounded-lg p-3 mb-3">
                        <p class="text-[10.5px] font-bold text-muted uppercase mb-2">Ringkasan Konsolidasi per Item</p>
                        <table class="w-full text-xs">
                            <thead class="text-muted"><tr><th class="text-left py-1">Kode</th><th class="text-left py-1">Item</th><th class="text-right py-1">Baris</th><th class="text-right py-1">Qty</th><th class="text-right py-1">Harga min-max</th><th class="text-right py-1">Nilai</th></tr></thead>
                            <tbody>
                                @foreach ($this->itemConsolidated as $ic)
                                    <tr>
                                        <td class="py-1 font-mono">{{ $ic->item_code }}</td>
                                        <td class="py-1">{{ $ic->description }}</td>
                                        <td class="text-right">{{ $ic->line_count }}</td>
                                        <td class="text-right">{{ rtrim(rtrim(number_format((float) $ic->total_qty, 2, ',', '.'), '0'), ',') }}</td>
                                        <td class="text-right font-mono">{{ number_format((float) $ic->min_price, 0, ',', '.') }}@if($ic->min_price != $ic->max_price) – {{ number_format((float) $ic->max_price, 0, ',', '.') }}@endif</td>
                                        <td class="text-right font-mono">{{ number_format((float) $ic->total_net, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <details class="border border-[#eef1f5] rounded-lg p-3 mb-3">
                        <summary class="text-[10.5px] font-bold text-muted uppercase cursor-pointer">Detail Baris Penuh ({{ $this->selectedPo->lines->count() }} baris)</summary>
                        <table class="w-full text-xs mt-2">
                            <thead class="text-muted"><tr><th class="text-left py-1">Item</th><th class="text-left py-1">Kelas</th><th class="text-right py-1">Qty</th><th class="text-right py-1">Harga</th><th class="text-right py-1">Net</th></tr></thead>
                            <tbody>
                                @foreach ($this->selectedPo->lines as $line)
                                    <tr>
                                        <td class="py-1">{{ $line->i_description }}</td>
                                        <td class="py-1">{{ $line->i_line_class }}</td>
                                        <td class="text-right">{{ rtrim(rtrim(number_format((float) $line->i_qty, 2, ',', '.'), '0'), ',') }} {{ $line->i_uom }}</td>
                                        <td class="text-right font-mono">{{ number_format((float) $line->i_price, 0, ',', '.') }}</td>
                                        <td class="text-right font-mono">{{ number_format((float) $line->c_line_net, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </details>

                    <div class="bg-[#f8fafc] border border-[#eef1f5] rounded-lg p-3 mb-3 text-sm max-w-[380px] ml-auto">
                        <div class="flex justify-between"><span class="text-muted text-xs">DPP</span><span class="font-mono text-xs">{{ number_format((float) $this->selectedPo->c_dpp, 0, ',', '.') }}</span></div>
                        <div class="flex justify-between"><span class="text-muted text-xs">PPN</span><span class="font-mono text-xs">{{ number_format((float) $this->selectedPo->c_ppn, 0, ',', '.') }}</span></div>
                        <div class="flex justify-between font-bold"><span class="text-xs">Total Tagihan</span><span class="font-mono text-xs">{{ number_format((float) $this->selectedPo->c_grand_total, 0, ',', '.') }}</span></div>
                        <div class="flex justify-between text-[#a3241f]"><span class="text-xs">PPh 23</span><span class="font-mono text-xs">({{ number_format((float) $this->selectedPo->c_pph, 0, ',', '.') }})</span></div>
                        <div class="flex justify-between font-bold text-navy border-t border-[#eef1f5] pt-1"><span class="text-xs">Dibayar</span><span class="font-mono text-xs">{{ number_format((float) $this->selectedPo->c_net_payable, 0, ',', '.') }}</span></div>
                    </div>

                    <div class="border border-[#eef1f5] rounded-xl p-4 bg-white mb-4">
                        <p class="text-[10.5px] font-bold text-muted uppercase mb-3">Riwayat Dokumen</p>
                        <div class="relative pl-5 border-l-2 border-[#eef1f5] ml-1 space-y-3 text-sm">
                            @foreach ($this->history as $h)
                                <div class="relative">
                                    <div class="absolute -left-[26px] top-0.5 w-3 h-3 rounded-full border-2 {{ $h->action === 'REJECTED' ? 'bg-[#fbeaea] border-[#a3241f]' : 'bg-[#eaf7ee] border-[#1f8a4c]' }}"></div>
                                    <p class="font-semibold text-xs">{{ $h->action }}</p>
                                    <p class="text-[10.5px] text-muted">Oleh: {{ $h->created_by ?? 'sistem' }} · {{ $h->acted_at?->translatedFormat('d M Y, H:i') }}{{ $h->is_acting ? ' (PLT)' : '' }}</p>
                                    @if ($h->notes)
                                        <p class="text-[10.5px] text-[#a3241f] mt-0.5">"{{ $h->notes }}"</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @if ($this->selectedPo->status === 'SUBMITTED' && $this->selectedPending)
                        <div class="border-2 border-dashed border-[#d7dbe2] rounded-xl p-4 bg-white">
                            @if ($errorMessage)
                                <p class="text-[11px] text-[#a3241f] mb-2">⚠ {{ $errorMessage }}</p>
                            @endif
                            <div class="mb-3">
                                <label class="text-xs text-muted">Catatan Approval / Alasan Penolakan <span class="text-[#a3241f]">*</span></label>
                                <textarea wire:model="note" rows="2" placeholder="Wajib diisi bila menolak..." class="w-full border border-[#d7dbe2] rounded-lg px-3 py-2 mt-1 text-sm"></textarea>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" wire:click="reject" wire:confirm="Tolak PO ini?" class="bg-[#a3241f] text-white px-4 py-2 rounded-lg text-sm font-semibold">✕ Tolak</button>
                                <button type="button" wire:click="approve" wire:confirm="Setujui PO ini?" class="bg-[#1f8a4c] text-white px-4 py-2 rounded-lg text-sm font-semibold">✓ Setujui</button>
                            </div>
                        </div>
                    @else
                        <p class="text-xs text-muted text-center py-3">PO ini sudah tidak menunggu approval di posisi Anda.</p>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
