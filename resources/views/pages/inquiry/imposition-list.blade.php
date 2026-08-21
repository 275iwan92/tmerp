<?php

use App\Models\Sdi\IqpInquiry;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

// Worklist "Imposition Engine" (menu SALES_IMPO). Grid inquiry yang sudah
// confirmed (sub_state di luar DRAFT/CANCELLED/LOST) dan punya part -- klik 2x
// buka layar imposition per-inquiry yang SUDAH ADA (inquiry/imposition.blade.php,
// tidak diubah di sini, cuma dituju). RECALC dikecualikan dari daftar default:
// docs/SALES_FLOW_inquiry_lifecycle.md -- RECALC reuse layout lama via
// IqpInquiry::cloneStructureFrom(), tidak wajib diimposisi ulang -- checkbox
// "Tampilkan RECALC" tetap ada kalau user mau cek ulang manual.
new #[Title('Imposition Engine — TELASIH MAKMUR ERP')] class extends Component
{
    #[Url]
    public string $fq = '';

    #[Url]
    public bool $showRecalc = false;

    public function open(int $id)
    {
        return redirect()->route('inquiry.imposition', ['inquiry' => $id]);
    }

    // Sama seperti hub.blade.php/form.blade.php -- pola pill status dipakai lintas layar Inquiry.
    public function statusPillClass(string $state): string
    {
        return match ($state) {
            'DRAFT' => 'draft',
            'WAITING_COST', 'SUBMITTED', 'PRICE_CALCULATED' => 'submit',
            'PRICE_APPROVED' => 'priceok',
            'QUOTED' => 'quoted',
            'PROVING', 'PROVE_APPROVED' => 'proving',
            'CONVERTED' => 'ok',
            'CANCELLED', 'LOST' => 'cancelled',
            default => 'draft',
        };
    }

    public function impositionProgress(IqpInquiry $inquiry): string
    {
        $total = $inquiry->parts->count();
        $done = $inquiry->parts->filter(fn ($p) => (bool) $p->layout?->is_confirmed)->count();

        return "{$done}/{$total}";
    }

    public function isImpositionDone(IqpInquiry $inquiry): bool
    {
        return $inquiry->parts->isNotEmpty() && $inquiry->parts->every(fn ($p) => (bool) $p->layout?->is_confirmed);
    }

    #[Computed]
    public function inquiries()
    {
        return IqpInquiry::query()
            ->with(['customer', 'parts.layout'])
            ->whereNotIn('sub_state', ['DRAFT', 'CANCELLED', 'LOST'])
            ->whereHas('parts')
            ->when(! $this->showRecalc, fn ($q) => $q->where('inquiry_type', '!=', 'RECALC'))
            ->when($this->fq !== '', function ($q) {
                $q->where(function ($qq) {
                    $qq->where('inquiry_no', 'like', "%{$this->fq}%")
                        ->orWhere('product_draft_name', 'like', "%{$this->fq}%");
                });
            })
            ->orderByDesc('id_inquiry')
            ->limit(200)
            ->get();
    }

    #[Computed]
    public function statTotal(): int
    {
        return $this->inquiries->count();
    }

    #[Computed]
    public function statDone(): int
    {
        return $this->inquiries->filter(fn ($i) => $this->isImpositionDone($i))->count();
    }

    #[Computed]
    public function statPending(): int
    {
        return $this->statTotal - $this->statDone;
    }
};
?>

<div>
    <header class="app-header">
        <b>🔀 Imposition Engine</b>
        <span class="sub">Worklist inquiry siap diimposisi</span>
        <a class="back" href="{{ route('inquiry.hub') }}">← Inquiry Hub</a>
    </header>

    <div class="wrap">
        <div class="grid three" style="margin-bottom:14px">
            <div class="card" style="margin-bottom:0"><div class="body">
                <div class="hint" style="margin:0">Total Perlu Imposisi</div>
                <div style="font-size:22px;font-weight:800;color:var(--color-navy)">{{ $this->statTotal }}</div>
            </div></div>
            <div class="card" style="margin-bottom:0"><div class="body">
                <div class="hint" style="margin:0">Selesai (semua part terkonfirmasi)</div>
                <div style="font-size:22px;font-weight:800;color:#16a34a">{{ $this->statDone }}</div>
            </div></div>
            <div class="card" style="margin-bottom:0"><div class="body">
                <div class="hint" style="margin:0">Belum Selesai</div>
                <div style="font-size:22px;font-weight:800;color:#c0392b">{{ $this->statPending }}</div>
            </div></div>
        </div>

        <div class="card">
            <div class="toolbar">
                <input type="text" wire:model.live.debounce.400ms="fq" placeholder="Cari no. inkuiri / nama produk…" style="max-width:280px">
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer">
                    <input type="checkbox" wire:model.live="showRecalc" style="width:auto"> Tampilkan RECALC (biasanya reuse layout lama)
                </label>
                <span style="margin-left:auto;font-size:11px;color:#8a94a6">{{ $this->statTotal }} inkuiri</span>
            </div>
            <div class="body" style="padding:0">
                <div class="qwrap">
                    <table class="qtbl">
                        <thead>
                            <tr>
                                <th>No. Inkuiri</th><th>Customer</th><th>Produk</th>
                                <th>Tipe</th><th>Status</th>
                                <th style="text-align:center">Progres Part</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->inquiries as $row)
                                <tr wire:key="imp-{{ $row->id_inquiry }}" wire:dblclick="open({{ $row->id_inquiry }})" style="cursor:pointer" title="Klik 2x untuk buka Imposition">
                                    <td><span class="qdoc">{{ $row->inquiry_no ?: '—' }}</span></td>
                                    <td>{{ $row->is_prospect ? $row->prospect_name : $row->customer?->customer_name }}</td>
                                    <td>{{ $row->product_draft_name }}</td>
                                    <td><span class="pill {{ strtolower($row->inquiry_type) }}">{{ $row->inquiry_type }}</span></td>
                                    <td><span class="pill {{ $this->statusPillClass($row->sub_state) }}">{{ $row->sub_state }}</span></td>
                                    <td style="text-align:center">
                                        <span class="pill {{ $this->isImpositionDone($row) ? 'ok' : 'submit' }}">{{ $this->impositionProgress($row) }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><div class="qempty">Tidak ada inkuiri yang perlu imposisi.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
