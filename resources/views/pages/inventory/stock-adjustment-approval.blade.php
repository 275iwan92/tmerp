<?php

use App\Models\Inventory\FgStock;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\SfgStock;
use App\Services\Flow\FlowEngineService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// inventory_tier4.md §4.3 -- route TERPISAH dari layar entri (inventory_rules.yaml
// layar_terpisah), sama pola dgn PR/PO Approval Center: murni ANTRIAN (grid),
// klik 2x buka dokumen ASLI (stock-adjustment.blade.php, {doc} deep-link) --
// Approve/Reject/riwayat ada di sana, BUKAN duplikat di sini. 1 antrian ini
// menggabung modul AD + OP (1 "Persetujuan" per kelas stok mencakup kedua jenis).
new #[Title('Persetujuan Adjustment & Opname — TELASIH MAKMUR ERP')] class extends Component
{
    public const CATEGORY_LABELS = ['RM' => 'Bahan Baku & Bahan Pembantu', 'SFG' => 'Barang Setengah Jadi', 'FG' => 'Barang Jadi'];

    private const HEADER_MODEL = ['RM' => RmStock::class, 'FG' => FgStock::class, 'SFG' => SfgStock::class];

    public string $category;

    public string $search = '';

    public function mount(string $category): void
    {
        $category = strtoupper($category);
        abort_unless(array_key_exists($category, self::CATEGORY_LABELS), 404);
        $this->category = $category;
    }

    private function headerModel(): string
    {
        return self::HEADER_MODEL[$this->category];
    }

    #[Computed]
    public function pendingQueue()
    {
        $flow = app(FlowEngineService::class);

        return $flow->pendingForUser('AD', Auth::id())->concat($flow->pendingForUser('OP', Auth::id()));
    }

    #[Computed]
    public function documents(): array
    {
        $ids = $this->pendingQueue->pluck('doc_id')->unique();

        return $this->headerModel()::whereIn('doc_id', $ids)->get()->keyBy('doc_id')->all();
    }

    #[Computed]
    public function rows()
    {
        $docs = $this->documents;

        return $this->pendingQueue
            ->map(fn ($p) => (object) ['pending' => $p, 'doc' => $docs[$p->doc_id] ?? null])
            ->filter(fn ($r) => $r->doc !== null)
            ->when($this->search !== '', function ($rows) {
                $term = mb_strtolower($this->search);

                return $rows->filter(fn ($r) => str_contains(mb_strtolower($r->doc->doc_no ?? ''), $term)
                    || str_contains(mb_strtolower($r->doc->reason_code ?? ''), $term));
            })
            ->sortByDesc(fn ($r) => $r->pending->acted_at)
            ->values();
    }

    #[Computed]
    public function statTotal(): int
    {
        return $this->pendingQueue->count();
    }

    #[Computed]
    public function statValue(): float
    {
        return (float) $this->pendingQueue->sum('amount_snapshot');
    }

    public function openDoc(int $id)
    {
        return $this->redirect(route('inv.stock-adj.index', ['category' => $this->category, 'doc' => $id]));
    }

    public function avatarInitials(string $name): string
    {
        $words = array_values(array_filter(explode(' ', trim($name))));
        if (count($words) >= 2) {
            return mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($name, 0, 2));
    }

    private const AVATAR_PALETTE = [
        'from-[#2563eb] to-[#1d4ed8]', 'from-[#10b981] to-[#059669]', 'from-[#ef4444] to-[#dc2626]',
        'from-[#8b5cf6] to-[#6d28d9]', 'from-[#f59e0b] to-[#d97706]', 'from-[#0ea5e9] to-[#0284c7]',
    ];

    public function avatarGradient(int $id): string
    {
        return self::AVATAR_PALETTE[$id % count(self::AVATAR_PALETTE)];
    }
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <div class="grid grid-cols-2 gap-3 mb-3.5">
            <div class="rounded-lg p-3.5 shadow-sm bg-[#eaf2ff] border border-[#c8ddfb]">
                <p class="text-[10.5px] font-bold uppercase text-[#3b5b99]">Total di Antrian Saya</p>
                <p class="text-xl font-extrabold text-[#1d4ed8] mt-0.5">{{ $this->statTotal }}</p>
            </div>
            <div class="rounded-lg p-3.5 shadow-sm bg-[#fef6e7] border border-[#f5e0b3]">
                <p class="text-[10.5px] font-bold uppercase text-[#8a6d00]">Nilai Absolut Total</p>
                <p class="text-xl font-extrabold text-[#b45309] mt-0.5">{{ number_format($this->statValue, 0, ',', '.') }}</p>
            </div>
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <div class="flex items-center gap-2 bg-white border-b border-[#e2e7ee] px-2.5 py-2 overflow-x-auto">
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari no. dokumen / alasan…"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <span class="flex-1"></span>
                <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-[#eef1f5] text-[#4b5568]">{{ self::CATEGORY_LABELS[$category] }}</span>
            </div>

            <div class="max-h-[65vh] overflow-auto">
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Dokumen</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">Jenis</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[130px]">Diajukan</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[150px]">Posisi Approval</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[140px]">Nilai Absolut</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">Status</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[60px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rows as $r)
                            <tr wire:key="adjq-{{ $r->doc->doc_id }}" wire:dblclick="openDoc({{ $r->doc->doc_id }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka">
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ $this->avatarGradient($r->doc->doc_id) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ $this->avatarInitials($r->doc->doc_no ?: 'DOC') }}</div>
                                        <div>
                                            <div class="font-semibold text-[13px] font-mono">{{ $r->doc->doc_no }}</div>
                                            <div class="text-[11px] text-muted">{{ $r->doc->reason_code ?: '—' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $r->doc->doc_cat === 'OP' ? 'Opname' : 'Adjustment' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $r->pending->acted_at?->translatedFormat('d M Y') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-[10.5px]">{{ $r->pending->pending_label ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right font-semibold">{{ number_format((float) $r->pending->amount_snapshot, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#fff5e0] text-[#8a5a00]">MENUNGGU</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <button type="button" wire:click.stop="openDoc({{ $r->doc->doc_id }})" title="Buka" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">↗</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-8 text-muted text-sm">Tidak ada dokumen menunggu approval Anda saat ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
