<?php

use App\Models\Master\WhWarehouse;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// docs/checklistmockuperp.md "Nota Alokasi / Label Rak — belum digarap
// (sengaja ditunda, [P] diprioritaskan setelah Form/Grid)". Form/Grid
// Warehouse sudah lama live -- ini bagian [P]-nya. BEDA bentuk dari Nota
// transaksi lain (bukan 1 dokumen formal ber-signature, tapi LEMBAR LABEL
// -- grid kartu kecil siap gunting/tempel per lokasi rak), jadi TIDAK pakai
// komponen resources/views/components/print/* (dirancang utk Nota
// header+parties+items+signatures, bentuk beda total) -- layout sendiri,
// print CSS standalone.
new #[Title('Label Rak — TELASIH MAKMUR ERP')] class extends Component
{
    public int $warehouseId;

    public function mount(int $warehouse): void
    {
        $this->warehouseId = $warehouse;
    }

    #[Computed]
    public function warehouse(): WhWarehouse
    {
        return WhWarehouse::with(['locations' => fn ($q) => $q->where('activate', true)])->findOrFail($this->warehouseId);
    }
};
?>

<div class="label-page">
    <div class="no-print toolbar">
        <a href="{{ route('master.warehouse.edit', ['warehouse' => $this->warehouseId]) }}" class="back">← Kembali ke Gudang</a>
        <button type="button" onclick="window.print()" class="print-btn">🖨 Cetak</button>
    </div>

    @if ($this->warehouse->locations->isEmpty())
        <div class="no-print empty">Gudang ini belum punya lokasi/bin aktif.</div>
    @else
        <div class="label-grid">
            @foreach ($this->warehouse->locations as $loc)
                <div class="label-card" wire:key="lbl-{{ $loc->location_id }}">
                    <div class="wh-name">{{ $this->warehouse->warehouse_code }} · {{ $this->warehouse->warehouse_name }}</div>
                    <div class="loc-code">{{ $loc->location_code }}</div>
                    <div class="rcs">R{{ $loc->row_code }}-C{{ $loc->col_code }}-S{{ $loc->stack_code }}</div>
                    @if ($loc->location_name)
                        <div class="loc-name">{{ $loc->location_name }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <style>
        .label-page { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; background: #eef1f5; min-height: 100vh; padding: 16px; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
        .toolbar .back { font-size: 12px; font-weight: 700; color: #43508a; text-decoration: none; }
        .toolbar .print-btn { padding: 8px 16px; border-radius: 6px; border: none; background: #101b30; color: #fff; font-size: 12px; font-weight: 700; cursor: pointer; }
        .empty { text-align: center; color: #667; padding: 40px; font-size: 13px; }

        .label-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 3mm;
            background: #fff;
            padding: 8mm;
            border-radius: 6px;
        }
        .label-card {
            border: 1.5px dashed #a9b4c4;
            border-radius: 4px;
            padding: 3mm 2.5mm;
            text-align: center;
            break-inside: avoid;
        }
        .label-card .wh-name { font-size: 6.5pt; color: #667; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 2mm; }
        .label-card .loc-code { font-size: 15pt; font-weight: 800; color: #101b30; font-family: Consolas, monospace; }
        .label-card .rcs { font-size: 8pt; color: #4c85ff; font-weight: 700; margin-top: 1mm; }
        .label-card .loc-name { font-size: 7pt; color: #556; margin-top: 1mm; }

        @media print {
            .no-print { display: none !important; }
            .label-page { background: #fff; padding: 0; }
            .label-grid { padding: 0; border-radius: 0; grid-template-columns: repeat(4, 1fr); }
            .label-card { border: 1px solid #999; }
            @page { size: A4; margin: 10mm; }
        }
    </style>
</div>
