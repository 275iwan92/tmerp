<?php

use App\Models\Sdi\IqpInquiry;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

// Worklist "Product Costing" (menu SALES_COST, tapi digate module:COSTING --
// beda dari layar Sales lain di modul ini, lihat CLAUDE.md #4 Sisi A rahasia).
// Grid inquiry yang sudah confirmed & punya part, klik 2x buka layar costing
// per-inquiry yang SUDAH ADA (inquiry/costing.blade.php, tidak diubah, cuma
// dituju). Kolom "Status Costing" pakai doc_status dari iqp_costing kalau
// sudah pernah dibuka (costing.blade.php auto-create saat kunjungan pertama,
// jadi "Belum dimulai" itu wajar utk inquiry yang belum pernah disentuh Costing).
new #[Title('Product Costing — TELASIH MAKMUR ERP')] class extends Component
{
    #[Url]
    public string $fq = '';

    #[Url]
    public string $fdoc = '';

    public function open(int $id)
    {
        return redirect()->route('inquiry.costing', ['inquiry' => $id]);
    }

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

    public function docStatusPillClass(?string $status): string
    {
        return match ($status) {
            'CALCULATED' => 'submit',
            'PENDING_APPROVAL' => 'proving',
            'PUBLISHED' => 'ok',
            'FAILED' => 'cancelled',
            default => 'draft', // null / DRAFT = belum dimulai
        };
    }

    #[Computed]
    public function inquiries()
    {
        return IqpInquiry::query()
            ->with(['customer', 'costing'])
            ->whereNotIn('sub_state', ['DRAFT', 'CANCELLED', 'LOST'])
            ->whereHas('parts')
            ->when($this->fdoc !== '', function ($q) {
                if ($this->fdoc === 'NONE') {
                    $q->whereDoesntHave('costing');
                } else {
                    $q->whereHas('costing', fn ($qq) => $qq->where('doc_status', $this->fdoc));
                }
            })
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
    public function statNotStarted(): int
    {
        return $this->inquiries->whereNull('costing')->count();
    }

    #[Computed]
    public function statPendingApproval(): int
    {
        return $this->inquiries->filter(fn ($i) => $i->costing?->doc_status === 'PENDING_APPROVAL')->count();
    }

    #[Computed]
    public function statPublished(): int
    {
        return $this->inquiries->filter(fn ($i) => $i->costing?->doc_status === 'PUBLISHED')->count();
    }
};
?>

<div>
    <header class="app-header">
        <b>🧮 Product Costing</b>
        <span class="sub">Worklist inquiry siap dihitung HPP — Sisi A, rahasia</span>
        <a class="back" href="{{ route('inquiry.hub') }}">← Inquiry Hub</a>
    </header>

    <div class="wrap">
        <div class="note o">🔒 Layar ini khusus role Costing/Direksi — angka HPP &amp; margin tidak boleh bocor ke Sisi B (Sales) sebelum <b>doc_status = PUBLISHED</b> (approve Direksi).</div>

        <div class="grid" style="margin-bottom:14px">
            <div class="card" style="margin-bottom:0"><div class="body">
                <div class="hint" style="margin:0">Total Perlu Costing</div>
                <div style="font-size:22px;font-weight:800;color:var(--color-navy)">{{ $this->statTotal }}</div>
            </div></div>
            <div class="card" style="margin-bottom:0"><div class="body">
                <div class="hint" style="margin:0">Belum Dimulai</div>
                <div style="font-size:22px;font-weight:800;color:#8a4b00">{{ $this->statNotStarted }}</div>
            </div></div>
            <div class="card" style="margin-bottom:0"><div class="body">
                <div class="hint" style="margin:0">Menunggu Approval Direksi</div>
                <div style="font-size:22px;font-weight:800;color:#4b3fa8">{{ $this->statPendingApproval }}</div>
            </div></div>
            <div class="card" style="margin-bottom:0"><div class="body">
                <div class="hint" style="margin:0">Published (Deklasifikasi ke Sales)</div>
                <div style="font-size:22px;font-weight:800;color:#16a34a">{{ $this->statPublished }}</div>
            </div></div>
        </div>

        <div class="card">
            <div class="toolbar">
                <input type="text" wire:model.live.debounce.400ms="fq" placeholder="Cari no. inkuiri / nama produk…" style="max-width:280px">
                <select wire:model.live="fdoc">
                    <option value="">Semua status costing</option>
                    <option value="NONE">Belum dimulai</option>
                    <option value="DRAFT">DRAFT</option>
                    <option value="CALCULATED">CALCULATED</option>
                    <option value="PENDING_APPROVAL">PENDING_APPROVAL</option>
                    <option value="PUBLISHED">PUBLISHED</option>
                    <option value="FAILED">FAILED</option>
                </select>
                <span style="margin-left:auto;font-size:11px;color:#8a94a6">{{ $this->statTotal }} inkuiri</span>
            </div>
            <div class="body" style="padding:0">
                <div class="qwrap">
                    <table class="qtbl">
                        <thead>
                            <tr>
                                <th>No. Inkuiri</th><th>Customer</th><th>Produk</th>
                                <th>Tipe</th><th>Status Inquiry</th><th>Status Costing</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->inquiries as $row)
                                <tr wire:key="cost-{{ $row->id_inquiry }}" wire:dblclick="open({{ $row->id_inquiry }})" style="cursor:pointer" title="Klik 2x untuk buka Costing">
                                    <td><span class="qdoc">{{ $row->inquiry_no ?: '—' }}</span></td>
                                    <td>{{ $row->is_prospect ? $row->prospect_name : $row->customer?->customer_name }}</td>
                                    <td>{{ $row->product_draft_name }}</td>
                                    <td><span class="pill {{ strtolower($row->inquiry_type) }}">{{ $row->inquiry_type }}</span></td>
                                    <td><span class="pill {{ $this->statusPillClass($row->sub_state) }}">{{ $row->sub_state }}</span></td>
                                    <td><span class="pill {{ $this->docStatusPillClass($row->costing?->doc_status) }}">{{ $row->costing?->doc_status ?? 'Belum dimulai' }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><div class="qempty">Tidak ada inkuiri yang perlu costing.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
