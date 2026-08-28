<?php

use App\Models\Master\MatRaw;
use App\Models\Master\OrgCompany;
use App\Models\Prd\PrdWo;
use App\Models\Sdi\IqpPartMaterial;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Print form WO -- docs/production_scheduling_wo_generation_delta.md §7,
// docs/printnotastandard.md. Isi manual di lapangan: Bill of Process dgn
// kolom KOSONG (actual start/finish, qty baik/reject/waste, paraf operator)
// -- pencatatan digital tetap lewat F15 "Catat Output" di WO Detail, form
// ini semata backup/checklist fisik shift/mesin yang belum tentu selalu
// pegang tablet. Bill of Material ditampilkan sbg referensi (bukan
// utk diisi). Diselaraskan 2026-08-28 sama kolom baru WO Detail hasil
// rombak (Operator terjadwal + Qty Kebutuhan/Tersedia/Harus Dibeli) --
// dipola persis dari billOfMaterialRows() wo-entry.blade.php, sengaja
// diduplikasi (bukan diekstrak ke trait) supaya print tetap berdiri sendiri.
new #[Title('Cetak Work Order — TELASIH MAKMUR ERP')] class extends Component
{
    public int $woId;

    public function mount(int $wo): void
    {
        $this->woId = $wo;
    }

    #[Computed]
    public function wo(): PrdWo
    {
        return PrdWo::with(['coOrder', 'product', 'operations.crew.employee' => fn ($q) => $q->orderBy('seq_no')])->findOrFail($this->woId);
    }

    #[Computed]
    public function company(): ?OrgCompany
    {
        return OrgCompany::where('company_code', 'TM')->first();
    }

    #[Computed]
    public function billOfMaterial()
    {
        if (! $this->wo->source_iqp_part_id) {
            return collect();
        }

        return IqpPartMaterial::where('part_id', $this->wo->source_iqp_part_id)->orderBy('layer_seq')->get();
    }

    #[Computed]
    public function billOfMaterialRows()
    {
        return $this->billOfMaterial->map(function ($mat) {
            $rawmat = $mat->rawmat_id ? MatRaw::find($mat->rawmat_id) : null;
            $unit = $rawmat?->uom_stock ?: 'Pcs';
            $qtyKebutuhan = (float) $this->wo->qty;
            $qtyTersedia = $mat->rawmat_id
                ? (float) DB::connection('tm2inv')->table('v_rm_available')->where('material_id', $mat->rawmat_id)->sum('qty_available')
                : 0.0;

            return (object) [
                'name' => $mat->rawmat_name,
                'unit' => $unit,
                'qty_kebutuhan' => $qtyKebutuhan,
                'qty_tersedia' => $qtyTersedia,
                'qty_harus_dibeli' => max(0, $qtyKebutuhan - $qtyTersedia),
            ];
        });
    }
};
?>

<x-print.document :theme="['accent' => '#2c3e69', 'accent-soft' => '#eef1f7']" info="{{ $this->wo->wo_no }} · {{ $this->wo->wo_status }}">
    <x-slot:toolbar>
        <a href="{{ route('production.wo.entry', ['wo' => $this->woId]) }}" class="sc">← Kembali ke WO Detail</a>
    </x-slot:toolbar>

    <x-print.sheet>
        <x-print.header :company="$this->company" doc-title="WORK ORDER" :doc-no="$this->wo->wo_no">
            <x-slot:meta>
                Tanggal Cetak: {{ now()->translatedFormat('d F Y H:i') }}
                · Tipe: <span class="ref">{{ $this->wo->wo_type }}</span>
                @if ($this->wo->is_hot_job) · <span class="ref">HOT JOB</span> @endif
            </x-slot:meta>
        </x-print.header>

        <x-print.parties columns="1.2fr 1fr">
            <x-print.party label="RINCIAN ORDER">
                <div class="nm">{{ $this->wo->product?->product_name ?? '—' }}</div>
                <div class="dt">
                    No. CO: <b>{{ $this->wo->coOrder?->co_no ?? '—' }}</b>
                    @if ($this->wo->part_code) · Part: <b>{{ $this->wo->part_code }}</b> @endif<br>
                    Qty Rencana: <b>{{ number_format((float) $this->wo->qty, 0, ',', '.') }} pcs</b>
                </div>
            </x-print.party>
            <x-print.party label="JADWAL">
                <div class="dt">
                    Due Date: <b>{{ $this->wo->due_date?->translatedFormat('d F Y H:i') ?? '—' }}</b><br>
                    Bahan Dibutuhkan: <b>{{ $this->wo->material_needed_by?->translatedFormat('d F Y') ?? '—' }}</b>
                </div>
            </x-print.party>
        </x-print.parties>

        <x-print.section number="1" title="Bill of Process" hint="kolom putih terisi rencana, kolom kosong isi manual di lapangan">
            <table class="bop">
                <thead>
                    <tr>
                        <th style="width:4%">Seq</th>
                        <th style="width:13%">Proses</th>
                        <th style="width:12%">Mesin</th>
                        <th style="width:10%">Operator</th>
                        <th style="width:8%">Target/Jam</th>
                        <th style="width:8%">Rencana Mulai</th>
                        <th style="width:8%">Aktual Mulai</th>
                        <th style="width:8%">Aktual Selesai</th>
                        <th style="width:8%">Qty Baik</th>
                        <th style="width:7%">Reject</th>
                        <th style="width:7%">Waste</th>
                        <th style="width:7%">Paraf</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->wo->operations as $op)
                        <tr>
                            <td class="c">{{ $op->seq_no }}</td>
                            <td class="l"><b>{{ $op->process_name ?: '—' }}</b></td>
                            <td class="l">{{ $op->resource_type === 'MACHINE' ? ($op->machine?->machine_name ?? '—') : 'Manual' }}</td>
                            <td class="l">{{ $op->operatorUtama()?->employee?->full_name ?? '—' }}</td>
                            <td class="c">{{ $op->rate_band_value ? number_format((float) $op->rate_band_value, 0, ',', '.') : '—' }}</td>
                            <td class="c">{{ $op->target_start?->format('d/m H:i') ?? '—' }}</td>
                            <td class="blank"></td>
                            <td class="blank"></td>
                            <td class="blank"></td>
                            <td class="blank"></td>
                            <td class="blank"></td>
                            <td class="blank"></td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="c" style="padding:14px;color:var(--print-muted)">Belum ada proses.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-print.section>

        @if ($this->billOfMaterialRows->isNotEmpty())
            <x-print.section number="2" title="Bill of Material" hint="Qty Kebutuhan/Tersedia referensi -- kolom Diambil isi manual saat pengambilan gudang">
                <table class="bom">
                    <thead>
                        <tr>
                            <th class="l" style="width:40%">Material</th>
                            <th style="width:18%">Qty Kebutuhan</th>
                            <th style="width:18%">Qty Tersedia (Gudang)</th>
                            <th style="width:24%">Qty Diambil (paraf)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->billOfMaterialRows as $row)
                            <tr>
                                <td class="l">{{ $row->name }}</td>
                                <td>{{ number_format($row->qty_kebutuhan, 0, ',', '.') }} {{ $row->unit }}</td>
                                <td>{{ number_format($row->qty_tersedia, 0, ',', '.') }} {{ $row->unit }}</td>
                                <td class="blank"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-print.section>
        @endif

        <x-print.signatures title="PENGESAHAN LAPANGAN">
            <x-print.signature role="Dibuat oleh" sub="PPIC Officer" />
            <x-print.signature role="Operator Mesin" sub="Nama jelas &amp; paraf" />
            <x-print.signature role="Diperiksa QC" sub="Nama jelas &amp; paraf" />
        </x-print.signatures>

        <x-print.footer page="Halaman 1 dari 1">
            {{ $this->wo->wo_no }} · dicetak {{ now()->format('d/m/Y H:i') }} · dokumen kerja internal, bukan dokumen ke pelanggan
        </x-print.footer>
    </x-print.sheet>

    <style>
        .print-doc table.bop{width:100%;border-collapse:collapse;font-size:8pt;border:1px solid var(--print-line)}
        .print-doc table.bop th{background:var(--print-accent);color:#fff;font-size:6.6pt;letter-spacing:.4px;
                     padding:5px 5px;text-align:center;font-weight:700}
        .print-doc table.bop td{padding:7px 5px;border-bottom:1px solid var(--print-line2);text-align:center;
                     vertical-align:middle;font-variant-numeric:tabular-nums}
        .print-doc table.bop td.l{text-align:left}
        .print-doc table.bop td.c{text-align:center}
        .print-doc table.bop td.blank{height:22px;border-left:1px dashed var(--print-line2)}

        .print-doc table.bom{width:100%;border-collapse:collapse;font-size:8.4pt;border:1px solid var(--print-line)}
        .print-doc table.bom th{background:var(--print-accent-soft);color:var(--print-accent);font-size:6.8pt;
                     letter-spacing:.4px;padding:5px 8px;text-align:center;font-weight:700}
        .print-doc table.bom th.l{text-align:left}
        .print-doc table.bom td{padding:6px 8px;border-bottom:1px solid var(--print-line2);text-align:center}
        .print-doc table.bom td.l{text-align:left;font-weight:600}
    </style>
</x-print.document>
