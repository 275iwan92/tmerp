<?php

use App\Models\Master\FlowLog;
use App\Models\Pur\PrRequisition;
use App\Services\Flow\FlowEngineService;
use App\Services\Pur\PrAllocationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('PR Approval Center — TELASIH MAKMUR ERP')] class extends Component
{
    public ?int $selectedDocId = null;

    public string $note = '';

    public string $errorMessage = '';

    public function mount(FlowEngineService $flow): void
    {
        $first = $flow->pendingForUser('PR', Auth::id())->first();
        $this->selectedDocId = $first?->doc_id;
    }

    #[Computed]
    public function pendingQueue()
    {
        return app(FlowEngineService::class)->pendingForUser('PR', Auth::id());
    }

    #[Computed]
    public function selectedPr(): ?PrRequisition
    {
        if (! $this->selectedDocId) {
            return null;
        }

        return PrRequisition::with(['lines', 'department'])->find($this->selectedDocId);
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

        return app(FlowEngineService::class)->history('PR', $this->selectedDocId);
    }

    public function select(int $docId): void
    {
        $this->selectedDocId = $docId;
        $this->note = '';
        $this->errorMessage = '';
        unset($this->selectedPr, $this->history);
    }

    public function approve(FlowEngineService $flow): void
    {
        $pending = $this->selectedPending;

        if (! $pending || ! $this->selectedPr) {
            return;
        }

        $result = $flow->approve('PR', $pending->doc_id, $pending->flow_step_id, (float) $pending->amount_snapshot);

        $this->selectedPr->update(['status' => $result['status'], 'updated_by' => Auth::user()->login]);

        // Flow selesai (tak ada step lanjutan) -> siapkan alokasi default per
        // baris supaya PO Entry (ambil dari pr_line_alloc) selalu punya sumber.
        if ($result['final']) {
            PrAllocationService::createDefaultAllocationsForPr($this->selectedPr);
        }

        $this->note = '';
        $this->selectedDocId = null;
        unset($this->pendingQueue, $this->selectedPr, $this->history);
    }

    public function reject(FlowEngineService $flow): void
    {
        $pending = $this->selectedPending;

        if (! $pending || ! $this->selectedPr) {
            return;
        }

        if (trim($this->note) === '') {
            $this->errorMessage = 'Alasan penolakan wajib diisi.';

            return;
        }

        $result = $flow->reject('PR', $pending->doc_id, $pending->flow_step_id, $this->note, (float) $pending->amount_snapshot);

        $this->selectedPr->update(['status' => $result['status'], 'updated_by' => Auth::user()->login]);

        $this->note = '';
        $this->errorMessage = '';
        $this->selectedDocId = null;
        unset($this->pendingQueue, $this->selectedPr, $this->history);
    }
};
?>

<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">✅ PR Approval Center</b>
        <span class="text-xs text-white/75">Khusus Purchase Requisition</span>
        <a href="{{ route('pur.pr.list') }}" class="ml-auto bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">← Daftar PR</a>
    </header>

    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h1 class="text-lg font-bold text-navy">PR Menunggu Approval Saya</h1>
                <p class="text-xs text-muted">Antrian ditentukan dari jabatan Anda (termasuk penugasan PLT bila ada).</p>
            </div>
            <span class="bg-[#fff5e0] text-[#8a5a00] text-xs font-bold px-3 py-1.5 rounded-lg">{{ $this->pendingQueue->count() }} PR Menunggu</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[380px_1fr] gap-4">
            {{-- LIST KIRI --}}
            <div class="bg-white border border-[#d7dbe2] rounded-md p-3 h-fit">
                @forelse ($this->pendingQueue as $p)
                    <button type="button" wire:click="select({{ $p->doc_id }})" wire:key="q-{{ $p->doc_id }}"
                        class="w-full text-left border rounded-lg p-3 mb-2 cursor-pointer {{ $selectedDocId === $p->doc_id ? 'border-accent bg-[#eef3fb]' : 'border-[#d7dbe2] hover:border-accent' }}">
                        <div class="flex justify-between text-xs font-semibold">
                            <span>PR#{{ $p->doc_id }}</span>
                            <span class="{{ $selectedDocId === $p->doc_id ? 'text-accent' : '' }}">Rp {{ number_format((float) $p->amount_snapshot, 0, ',', '.') }}</span>
                        </div>
                        <p class="text-[10.5px] text-muted mt-0.5">{{ $p->created_by }} · {{ $p->acted_at?->translatedFormat('d M Y') }}</p>
                    </button>
                @empty
                    <p class="text-xs text-muted text-center py-6">Tidak ada PR menunggu approval Anda saat ini.</p>
                @endforelse
            </div>

            {{-- DETAIL KANAN --}}
            <div class="bg-white border border-[#d7dbe2] rounded-md p-5">
                @if (! $this->selectedPr)
                    <p class="text-xs text-muted text-center py-10">Pilih PR di kiri untuk melihat detail.</p>
                @else
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="font-bold text-base text-navy">{{ $this->selectedPr->doc_no }} — Purchase Requisition</h2>
                            <p class="text-xs text-muted">Diajukan oleh: {{ $this->selectedPr->created_by }} · Departemen {{ $this->selectedPr->department?->department_name }}</p>
                        </div>
                        <span class="bg-[#fff5e0] text-[#8a5a00] text-xs font-bold px-2 py-0.5 rounded">{{ $this->selectedPending?->pending_label ?? 'Menunggu' }}</span>
                    </div>

                    <div class="bg-[#f8fafc] border border-[#eef1f5] rounded-lg p-3 mb-3 text-sm">
                        <div class="grid grid-cols-3 gap-3 mb-2">
                            <div><span class="text-muted text-[10.5px] uppercase font-semibold">Nilai</span><br><b>Rp {{ number_format((float) $this->selectedPr->c_est_total, 0, ',', '.') }}</b></div>
                            <div><span class="text-muted text-[10.5px] uppercase font-semibold">Departemen</span><br>{{ $this->selectedPr->department?->department_name }}</div>
                            <div><span class="text-muted text-[10.5px] uppercase font-semibold">Dibutuhkan</span><br>{{ $this->selectedPr->i_need_date?->translatedFormat('d M Y') ?? '—' }}</div>
                        </div>
                        <table class="w-full text-xs mt-2 border-t border-[#eef1f5] pt-2">
                            <thead class="text-muted"><tr><th class="text-left py-1">Item</th><th class="text-right py-1">Qty</th><th class="text-right py-1">Est. Harga</th><th class="text-right py-1">Est. Subtotal</th></tr></thead>
                            <tbody>
                                @foreach ($this->selectedPr->lines as $line)
                                    <tr>
                                        <td class="py-1">{{ $line->i_description }}</td>
                                        <td class="text-right">{{ rtrim(rtrim(number_format((float) $line->i_qty, 2, ',', '.'), '0'), ',') }} {{ $line->i_uom }}</td>
                                        <td class="text-right font-mono">{{ $line->i_est_price !== null ? number_format((float) $line->i_est_price, 0, ',', '.') : '—' }}</td>
                                        <td class="text-right font-mono">{{ $line->c_est_subtotal !== null ? number_format((float) $line->c_est_subtotal, 0, ',', '.') : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
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

                    @if ($this->selectedPr->status === 'SUBMITTED' && $this->selectedPending)
                        <div class="border-2 border-dashed border-[#d7dbe2] rounded-xl p-4 bg-white">
                            @if ($errorMessage)
                                <p class="text-[11px] text-[#a3241f] mb-2">⚠ {{ $errorMessage }}</p>
                            @endif
                            <div class="mb-3">
                                <label class="text-xs text-muted">Catatan Approval / Alasan Penolakan <span class="text-[#a3241f]">*</span></label>
                                <textarea wire:model="note" rows="2" placeholder="Wajib diisi bila menolak..." class="w-full border border-[#d7dbe2] rounded-lg px-3 py-2 mt-1 text-sm"></textarea>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" wire:click="reject" wire:confirm="Tolak PR ini?" class="bg-[#a3241f] text-white px-4 py-2 rounded-lg text-sm font-semibold">✕ Tolak</button>
                                <button type="button" wire:click="approve" wire:confirm="Setujui PR ini?" class="bg-[#1f8a4c] text-white px-4 py-2 rounded-lg text-sm font-semibold">✓ Setujui</button>
                            </div>
                        </div>
                    @else
                        <p class="text-xs text-muted text-center py-3">PR ini sudah tidak menunggu approval di posisi Anda.</p>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
