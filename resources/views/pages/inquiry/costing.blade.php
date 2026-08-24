<?php

use App\Models\Master\MatCoatingRate;
use App\Models\Master\MatGrammage;
use App\Models\Master\MatSheetRate;
use App\Models\Sdi\IqpCosting;
use App\Models\Sdi\IqpCostingPart;
use App\Models\Sdi\IqpCostingPartCoating;
use App\Models\Sdi\IqpCostingPriceTier;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\IqpLayout;
use App\Models\Sdi\IqpPart;
use App\Services\Costing\CostingEngine;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Costing (HPP) — TELASIH MAKMUR ERP')] class extends Component
{
    public int $inquiryId;

    public int $costingId;

    public string $engineError = '';

    public function mount(int $inquiry): void
    {
        $this->inquiryId = $inquiry;

        $costing = IqpCosting::firstOrCreate(
            ['inquiry_id' => $inquiry],
            [
                'calc_type' => 'NEW',
                'doc_status' => 'DRAFT',
                'created_by' => Auth::user()->login,
                'updated_by' => Auth::user()->login,
            ]
        );

        // Auto-fill dimensi dari inquiry (sekali, saat header masih kosong).
        if (! $costing->i_dim_panjang_mm) {
            $firstPart = IqpInquiry::find($inquiry)?->parts()->orderBy('part_seq')->first();
            if ($firstPart) {
                $costing->fill([
                    'i_dim_panjang_mm' => $firstPart->flat_w,
                    'i_dim_lebar_mm' => $firstPart->flat_h,
                ])->save();
            }
        }

        // Price tier auto-generate dari iqp_tier (1:1 per qty tier), kalau belum ada.
        if ($costing->priceTiers()->count() === 0) {
            foreach (IqpInquiry::find($inquiry)?->tiers ?? [] as $tier) {
                IqpCostingPriceTier::create([
                    'costing_id' => $costing->id_costing,
                    'tier_id' => $tier->id_tier,
                    'qty_pcs' => $tier->qty,
                    'is_primary' => $tier->is_primary,
                    'created_by' => Auth::user()->login,
                    'updated_by' => Auth::user()->login,
                ]);
            }
        }

        $this->costingId = $costing->id_costing;
    }

    #[Computed]
    public function costing(): IqpCosting
    {
        return IqpCosting::with(['parts.coatings', 'priceTiers', 'inquiry'])->findOrFail($this->costingId);
    }

    #[Computed]
    public function grammages()
    {
        return MatGrammage::where('is_active', true)->orderBy('grammage_name')->get();
    }

    #[Computed]
    public function coatingRates()
    {
        return MatCoatingRate::where('is_active', true)->orderBy('coating_type')->get();
    }

    #[Computed]
    public function sheetRates()
    {
        return MatSheetRate::where('is_active', true)->orderBy('substance_name')->orderBy('flute_type')->get();
    }

    public function addPart(string $partType = 'DC'): void
    {
        $costing = $this->costing;

        $data = [
            'costing_id' => $costing->id_costing,
            'no_urut' => $costing->parts()->count() + 1,
            'part_name' => '',
            'part_type_code' => in_array($partType, ['DC', 'SHEET_DIGITAL'], true) ? $partType : 'DC',
            'qty_per_set' => 1,
            'created_by' => Auth::user()->login,
            'updated_by' => Auth::user()->login,
        ];

        if ($data['part_type_code'] === 'DC') {
            $data['i_cut_per_plano'] = 1;
            $data['i_isi_per_lb'] = 1;
            $data['i_jumlah_warna'] = 4;
        } else {
            $data['i_isi_per_lb_kerja'] = 1;
            $data['supplier_sheet_code'] = 'UJK';
        }

        IqpCostingPart::create($data);

        unset($this->costing);
    }

    // Tambah part dari iqp_part (Inquiry) — auto-fill plano/cut dari Imposition
    // yang sudah confirmed, kalau ada (lihat inquiry/imposition.blade.php confirm()).
    public function addPartFromInquiry(int $inquiryPartId): void
    {
        $costing = $this->costing;
        $iqpPart = IqpPart::where('inquiry_id', $this->inquiryId)->findOrFail($inquiryPartId);
        $layout = IqpLayout::where('part_id', $iqpPart->id_part)->where('is_confirmed', true)->first();

        $data = [
            'costing_id' => $costing->id_costing,
            'part_id' => $iqpPart->id_part,
            'no_urut' => $costing->parts()->count() + 1,
            'part_name' => $iqpPart->part_name,
            'part_type_code' => 'DC',
            'qty_per_set' => $iqpPart->qty_per_sku ?: 1,
            'i_cut_per_plano' => $layout->up_count ?? 1,
            'i_isi_per_lb' => 1,
            'i_jumlah_warna' => 4,
            'created_by' => Auth::user()->login,
            'updated_by' => Auth::user()->login,
        ];

        if ($layout) {
            $data['i_panjang_plano_cm'] = round($layout->plano_w_mm / 10, 4);
            $data['i_lebar_plano_cm'] = round($layout->plano_h_mm / 10, 4);
        }

        IqpCostingPart::create($data);

        unset($this->costing);
    }

    #[Computed]
    public function inquiryParts()
    {
        return IqpPart::where('inquiry_id', $this->inquiryId)->orderBy('part_seq')->get();
    }

    public function removePart(int $partId): void
    {
        IqpCostingPart::whereKey($partId)->where('costing_id', $this->costingId)->delete();
        unset($this->costing);
    }

    public function addCoating(int $partId): void
    {
        $part = IqpCostingPart::where('costing_id', $this->costingId)->findOrFail($partId);

        IqpCostingPartCoating::create([
            'costing_part_id' => $part->id_costing_part,
            'seq' => $part->coatings()->count() + 1,
            'coating_type' => 'WB',
            'index_coating' => MatCoatingRate::where('coating_type', 'WB')->where('is_active', true)->value('index_coating'),
        ]);

        unset($this->costing);
    }

    public function removeCoating(int $coatingId): void
    {
        IqpCostingPartCoating::whereKey($coatingId)
            ->whereHas('costingPart', fn ($q) => $q->where('costing_id', $this->costingId))
            ->delete();
        unset($this->costing);
    }

    public function updatePart(int $partId, string $field, $value): void
    {
        $allowed = [
            'part_name', 'grammage_id', 'i_dc_persen_override', 'i_panjang_plano_cm', 'i_lebar_plano_cm',
            'i_cut_per_plano', 'i_isi_per_lb', 'i_jumlah_warna', 'i_ongkos_cetak_rim', 'i_ongkos_plong_lb',
            'i_ongkos_lem_pc', 'i_film_faktor', 'i_film_rate', 'i_pisau_faktor', 'i_pisau_rate_rp', 'i_ongkos_plate_warna',
            'i_dim_panjang_mm', 'i_dim_lebar_mm', 'i_dim_tinggi_mm', 'i_lidah_lem_mm', 'flute_type', 'sheet_rate_id',
            'supplier_sheet_code', 'i_isi_per_lb_kerja', 'i_ongkos_print_pc', 'i_ongkos_diecut_pc', 'i_ongkos_slitter_pc',
            'i_ongkos_slotter_pc', 'i_ongkos_join_pc', 'i_ongkos_partisi_pc', 'i_ongkos_layer_pc', 'i_ongkos_qc_pack_pc',
            'i_ongkos_kirim_pc',
        ];

        if (! in_array($field, $allowed, true)) {
            return;
        }

        IqpCostingPart::where('costing_id', $this->costingId)->whereKey($partId)->update([$field => $value === '' ? null : $value]);
        unset($this->costing);
    }

    public function updateCoating(int $coatingId, string $field, $value): void
    {
        if (! in_array($field, ['coating_type', 'index_coating'], true)) {
            return;
        }

        $coating = IqpCostingPartCoating::whereKey($coatingId)
            ->whereHas('costingPart', fn ($q) => $q->where('costing_id', $this->costingId))
            ->first();

        if (! $coating) {
            return;
        }

        if ($field === 'coating_type') {
            $rate = MatCoatingRate::where('coating_type', $value)->where('is_active', true)->value('index_coating');
            $coating->update(['coating_type' => $value, 'index_coating' => $rate]);
        } else {
            $coating->update([$field => $value]);
        }

        unset($this->costing);
    }

    public function updateTier(int $tierId, string $field, $value): void
    {
        if (! in_array($field, ['tolerance_pct', 'margin_pct', 'rounding_mode', 'rounding_step'], true)) {
            return;
        }

        IqpCostingPriceTier::where('costing_id', $this->costingId)->whereKey($tierId)->update([$field => $value]);
        unset($this->costing);
    }

    public function hitung(): void
    {
        $this->engineError = '';

        try {
            (new CostingEngine)->run($this->costing);
        } catch (\Throwable $e) {
            $this->engineError = $e->getMessage();
        }

        unset($this->costing);
    }

    public function submitToDireksi(): void
    {
        $costing = $this->costing;

        if ($costing->doc_status !== 'CALCULATED') {
            return;
        }

        $costing->update(['doc_status' => 'PENDING_APPROVAL']);
        unset($this->costing);
    }

    // "+ Tambahkan ke Quotation" -- tempel inquiry ini (kalau sudah published) ke
    // draft Quotation customer yang sedang berjalan (bikin baru kalau belum ada),
    // lalu pindah ke Quotation Entry. Lihat App\Services\Sales\QuotationBuilder.
    public function addToQuotation()
    {
        $inquiry = $this->costing->inquiry;

        if (! $inquiry || ! $inquiry->customer_id) {
            return;
        }

        $quotation = \App\Services\Sales\QuotationBuilder::findOrCreateDraft($inquiry->customer_id);
        \App\Services\Sales\QuotationBuilder::addInquiry($quotation, $inquiry);

        return $this->redirect(route('quotation.entry', ['quotation' => $quotation->id_quotation]));
    }
};
?>

<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">🧮 TELASIH MAKMUR — Costing (HPP)</b>
        <span class="text-xs text-white/75">{{ $this->costing->inquiry?->inquiry_no ?? '—' }} · {{ $this->costing->inquiry?->product_draft_name }}</span>
        <button type="button" wire:click="addToQuotation" class="ml-auto bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md cursor-pointer hover:brightness-110">＋ Tambahkan ke Quotation</button>
        <a href="{{ route('inquiry.nota', ['inquiry' => $this->inquiryId]) }}" class="bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">→ Nota</a>
        <a href="{{ route('inquiry.imposition', ['inquiry' => $this->inquiryId]) }}" class="bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">→ Imposition</a>
        <a href="{{ route('inquiry.hub') }}" title="Kembali ke Inquiry Hub"
            class="inline-flex items-center gap-2 pl-3.5 pr-1 py-1 bg-[#43508a] rounded-full no-underline hover:brightness-110">
            <span class="text-[11px] font-bold uppercase tracking-wide text-white">Back</span>
            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#43508a] text-xs font-bold">←</span>
        </a>
    </header>

    <div class="max-w-[1180px] mx-auto my-5 px-4">

        @if ($engineError)
            <div class="bg-red-50 border border-red-300 text-red-700 text-xs px-3.5 py-2.5 rounded-md mb-3">⚠ {{ $engineError }}</div>
        @endif

        {{-- ============ HEADER ============ --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4 flex items-center gap-4 flex-wrap">
            <div>
                <div class="text-[10.5px] text-muted uppercase font-semibold">Tipe</div>
                <div class="text-sm font-bold text-navy">{{ $this->costing->calc_type }}</div>
            </div>
            <div>
                <div class="text-[10.5px] text-muted uppercase font-semibold">Status</div>
                <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full inline-block bg-[#eaf7ee] text-[#1f8a4c]">{{ $this->costing->doc_status }}</span>
            </div>
            <div>
                <div class="text-[10.5px] text-muted uppercase font-semibold">Dimensi Jadi (mm)</div>
                <div class="text-sm">{{ $this->costing->i_dim_panjang_mm }} × {{ $this->costing->i_dim_lebar_mm }} × {{ $this->costing->i_dim_tinggi_mm }}</div>
            </div>
            <div>
                <div class="text-[10.5px] text-muted uppercase font-semibold">PPN</div>
                <div class="text-sm">{{ $this->costing->ppn_mode }} ({{ $this->costing->ppn_pct }}%)</div>
            </div>
            <div class="ml-auto flex gap-2">
                <button type="button" wire:click="hitung" class="px-4 py-2 rounded text-xs font-semibold bg-accent text-white cursor-pointer">⚙ Hitung</button>
                <button type="button" wire:click="submitToDireksi" @disabled($this->costing->doc_status !== 'CALCULATED')
                    class="px-4 py-2 rounded text-xs font-semibold bg-[#22a06b] text-white cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">Submit ke Direksi</button>
            </div>
        </div>

        {{-- ============ PART REPEATER (DC only) ============ --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2.5 bg-[#f4f6fa] border-b border-[#d7dbe2]">
                <h3 class="text-xs text-navy uppercase font-bold">Part</h3>
                <div class="flex gap-1.5 items-center">
                    <button type="button" wire:click="addPart('DC')" class="text-[11px] font-bold text-accent bg-[#eef3fb] border border-dashed border-[#9cc0f0] rounded px-2.5 py-1 cursor-pointer">＋ Part DC</button>
                    <button type="button" wire:click="addPart('SHEET_DIGITAL')" class="text-[11px] font-bold text-accent bg-[#eef3fb] border border-dashed border-[#9cc0f0] rounded px-2.5 py-1 cursor-pointer">＋ Part Sheet</button>
                    @if ($this->inquiryParts->isNotEmpty())
                        <select id="add-from-part-{{ $this->costingId }}" class="text-[11px] px-2 py-1 border border-dashed border-[#9cc0f0] rounded text-navy">
                            <option value="">— dari part inquiry —</option>
                            @foreach ($this->inquiryParts as $ip)
                                <option value="{{ $ip->id_part }}">{{ $ip->part_name ?: 'Part #'.$ip->part_seq }}{{ $ip->layout?->is_confirmed ? ' ✓' : '' }}</option>
                            @endforeach
                        </select>
                        <button type="button"
                            @click="const s=document.getElementById('add-from-part-{{ $this->costingId }}'); if(s.value){ $wire.addPartFromInquiry(parseInt(s.value)); s.value=''; }"
                            class="text-[11px] font-bold text-accent bg-[#eef3fb] border border-dashed border-[#9cc0f0] rounded px-2.5 py-1 cursor-pointer">＋</button>
                    @endif
                </div>
            </div>

            @forelse ($this->costing->parts as $part)
                <div class="border-b border-[#d7dbe2] last:border-b-0 p-4" wire:key="part-{{ $part->id_costing_part }}">
                    <div class="flex items-center gap-2 mb-3">
                        <input type="text" value="{{ $part->part_name }}" placeholder="Nama part (mis. Bag Atas)"
                            wire:change="updatePart({{ $part->id_costing_part }}, 'part_name', $event.target.value)"
                            class="flex-1 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs font-semibold">
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-[#d8ecff] text-[#1560a8]">{{ $part->part_type_code }}</span>
                        <button type="button" wire:click="removePart({{ $part->id_costing_part }})" class="text-red-500 text-xs cursor-pointer px-2">✕ Hapus</button>
                    </div>

                    @if ($part->part_type_code === 'DC')
                    <div class="grid grid-cols-4 gap-2.5 mb-3">
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Gramatur</label>
                            <select wire:change="updatePart({{ $part->id_costing_part }}, 'grammage_id', $event.target.value)" class="px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                <option value="">— pilih —</option>
                                @foreach ($this->grammages as $g)
                                    <option value="{{ $g->id_grammage }}" @selected($part->grammage_id === $g->id_grammage)>{{ $g->grammage_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Plano P×L (cm)</label>
                            <div class="flex gap-1">
                                <input type="number" step="0.01" value="{{ $part->i_panjang_plano_cm }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_panjang_plano_cm', $event.target.value)" class="w-1/2 px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                <input type="number" step="0.01" value="{{ $part->i_lebar_plano_cm }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_lebar_plano_cm', $event.target.value)" class="w-1/2 px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                            </div>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Cut / Isi</label>
                            <div class="flex gap-1">
                                <input type="number" step="0.01" value="{{ $part->i_cut_per_plano }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_cut_per_plano', $event.target.value)" class="w-1/2 px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                <input type="number" step="0.01" value="{{ $part->i_isi_per_lb }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_isi_per_lb', $event.target.value)" class="w-1/2 px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                            </div>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Jumlah Warna</label>
                            <input type="number" value="{{ $part->i_jumlah_warna }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_jumlah_warna', $event.target.value)" class="px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2.5 mb-3">
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Ongkos Cetak /rim</label>
                            <input type="number" step="0.01" value="{{ $part->i_ongkos_cetak_rim }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_cetak_rim', $event.target.value)" class="px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Ongkos Plong /lb</label>
                            <input type="number" step="0.01" value="{{ $part->i_ongkos_plong_lb }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_plong_lb', $event.target.value)" class="px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Ongkos Lem /pc</label>
                            <input type="number" step="0.01" value="{{ $part->i_ongkos_lem_pc }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_lem_pc', $event.target.value)" class="px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        </div>
                    </div>

                    <div class="grid grid-cols-4 gap-2.5 mb-3">
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Film Faktor</label>
                            <input type="number" step="0.01" value="{{ $part->i_film_faktor }}" placeholder="0.25" wire:change="updatePart({{ $part->id_costing_part }}, 'i_film_faktor', $event.target.value)" class="px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Pisau Faktor</label>
                            <input type="number" step="0.01" value="{{ $part->i_pisau_faktor }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_pisau_faktor', $event.target.value)" class="px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Pisau Rate (Rp)</label>
                            <input type="number" step="0.01" value="{{ $part->i_pisau_rate_rp }}" placeholder="default part type" wire:change="updatePart({{ $part->id_costing_part }}, 'i_pisau_rate_rp', $event.target.value)" class="px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Plate/warna (Rp)</label>
                            <input type="number" step="0.01" value="{{ $part->i_ongkos_plate_warna }}" placeholder="default 90.000" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_plate_warna', $event.target.value)" class="px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        </div>
                    </div>

                    {{-- COATING GRID (nested) — hidden utk SHEET_DIGITAL (TOOL_SKIP, §B.1) --}}
                    <div class="border border-dashed border-[#cfd7e3] rounded-md p-2.5">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10.5px] font-bold text-navy uppercase">Coating (berurutan)</span>
                            <button type="button" wire:click="addCoating({{ $part->id_costing_part }})" class="text-[10.5px] font-bold text-accent cursor-pointer">＋ Layer</button>
                        </div>
                        @forelse ($part->coatings as $coating)
                            <div class="flex items-center gap-2 mb-1.5" wire:key="coating-{{ $coating->id_coating }}">
                                <span class="text-[10px] text-muted w-4">{{ $coating->seq }}.</span>
                                <select wire:change="updateCoating({{ $coating->id_coating }}, 'coating_type', $event.target.value)" class="px-2 py-1 border border-[#d7dbe2] rounded text-[11px]">
                                    @foreach ($this->coatingRates as $r)
                                        <option value="{{ $r->coating_type }}" @selected($coating->coating_type === $r->coating_type)>{{ $r->coating_type }}</option>
                                    @endforeach
                                </select>
                                <span class="text-[11px] text-muted">index: {{ $coating->index_coating }}</span>
                                <span class="text-[11px] ml-auto">{{ $coating->c_cost_per_pc !== null ? number_format($coating->c_cost_per_pc, 2) : '—' }}/pc</span>
                                <button type="button" wire:click="removeCoating({{ $coating->id_coating }})" class="text-red-500 text-[11px] cursor-pointer">✕</button>
                            </div>
                        @empty
                            <div class="text-[10.5px] text-muted">Tidak ada coating.</div>
                        @endforelse
                    </div>

                    @elseif ($part->part_type_code === 'SHEET_DIGITAL')
                    <div class="grid grid-cols-4 gap-2.5 mb-3">
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Panjang×Lebar×Tinggi (mm)</label>
                            <div class="flex gap-1">
                                <input type="number" step="0.01" value="{{ $part->i_dim_panjang_mm }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_dim_panjang_mm', $event.target.value)" class="w-1/3 px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                <input type="number" step="0.01" value="{{ $part->i_dim_lebar_mm }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_dim_lebar_mm', $event.target.value)" class="w-1/3 px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                <input type="number" step="0.01" value="{{ $part->i_dim_tinggi_mm }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_dim_tinggi_mm', $event.target.value)" class="w-1/3 px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                            </div>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Lidah Lem (mm)</label>
                            <input type="number" step="0.01" value="{{ $part->i_lidah_lem_mm }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_lidah_lem_mm', $event.target.value)" class="px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Jenis Flute</label>
                            <select wire:change="updatePart({{ $part->id_costing_part }}, 'flute_type', $event.target.value)" class="px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                <option value="">— pilih —</option>
                                @foreach (['B/F', 'C/F', 'BC/F'] as $ft)
                                    <option value="{{ $ft }}" @selected($part->flute_type === $ft)>{{ $ft }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Substance</label>
                            <select wire:change="updatePart({{ $part->id_costing_part }}, 'sheet_rate_id', $event.target.value)" class="px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                <option value="">— pilih —</option>
                                @foreach ($this->sheetRates as $sr)
                                    <option value="{{ $sr->id_sheet_rate }}" @selected($part->sheet_rate_id === $sr->id_sheet_rate)>{{ $sr->substance_name }} ({{ $sr->flute_type }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-4 gap-2.5 mb-3">
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Supplier</label>
                            <input type="text" value="{{ $part->supplier_sheet_code }}" wire:change="updatePart({{ $part->id_costing_part }}, 'supplier_sheet_code', $event.target.value)" class="px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Isi/Lb Kerja</label>
                            <input type="number" step="0.01" value="{{ $part->i_isi_per_lb_kerja }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_isi_per_lb_kerja', $event.target.value)" class="px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Ongkos Print /pc</label>
                            <input type="number" step="0.01" value="{{ $part->i_ongkos_print_pc }}" placeholder="dikali 2 di engine" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_print_pc', $event.target.value)" class="px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Ongkos Diecut /pc</label>
                            <input type="number" step="0.01" value="{{ $part->i_ongkos_diecut_pc }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_diecut_pc', $event.target.value)" class="px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        </div>
                    </div>

                    <div class="grid grid-cols-4 gap-2.5 mb-3">
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Slitter/Slotter /pc</label>
                            <div class="flex gap-1">
                                <input type="number" step="0.01" value="{{ $part->i_ongkos_slitter_pc }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_slitter_pc', $event.target.value)" class="w-1/2 px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                <input type="number" step="0.01" value="{{ $part->i_ongkos_slotter_pc }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_slotter_pc', $event.target.value)" class="w-1/2 px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                            </div>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Join/Partisi/Layer /pc</label>
                            <div class="flex gap-1">
                                <input type="number" step="0.01" value="{{ $part->i_ongkos_join_pc }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_join_pc', $event.target.value)" class="w-1/3 px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                <input type="number" step="0.01" value="{{ $part->i_ongkos_partisi_pc }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_partisi_pc', $event.target.value)" class="w-1/3 px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                                <input type="number" step="0.01" value="{{ $part->i_ongkos_layer_pc }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_layer_pc', $event.target.value)" class="w-1/3 px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                            </div>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">QC+Pak /pc</label>
                            <input type="number" step="0.01" value="{{ $part->i_ongkos_qc_pack_pc }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_qc_pack_pc', $event.target.value)" class="px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-[10px] text-muted uppercase font-semibold">Kirim /pc</label>
                            <input type="number" step="0.01" value="{{ $part->i_ongkos_kirim_pc }}" wire:change="updatePart({{ $part->id_costing_part }}, 'i_ongkos_kirim_pc', $event.target.value)" class="px-2 py-1.5 border border-[#d7dbe2] rounded text-xs">
                        </div>
                    </div>
                    @endif

                    @if ($part->c_total_part_cost_pc !== null)
                        @if ($part->part_type_code === 'SHEET_DIGITAL')
                            <div class="bg-[#eef3fb] border border-[#c3d8f5] rounded-md px-3 py-2 text-[11.5px] text-[#1560a8] flex gap-4 flex-wrap mb-2">
                                <span>Sheet {{ $part->c_panjang_sheet_cm }}×{{ $part->c_lebar_sheet_cm }}cm</span>
                                <span>Isi trim: <b>{{ $part->c_isi_trim }}</b></span>
                                <span>Lebar trim: <b>{{ $part->c_lebar_trim_cm }}cm</b></span>
                                <span>Running meter: <b>{{ number_format($part->c_running_meter, 2) }}</b></span>
                                <span>Diskon/surcharge: <b>{{ $part->c_discount_rm }}</b></span>
                            </div>
                        @endif
                        <div class="mt-1 bg-[#f0f7f4] border border-[#c6e5d5] rounded-md px-3 py-2 text-[11.5px] text-[#177a4b] flex gap-4 flex-wrap">
                            <span>Bahan: <b>{{ number_format($part->c_paper_cost_pc, 2) }}</b></span>
                            <span>Proses: <b>{{ number_format($part->c_cetak_cost_pc + $part->c_plong_cost_pc + $part->c_lem_cost_pc, 2) }}</b></span>
                            <span>Coating: <b>{{ number_format($part->c_total_coating_pc, 2) }}</b></span>
                            <span>Tooling (order): <b>{{ number_format($part->c_tooling_cost_order, 0) }}</b></span>
                            <span>Total/pc (tanpa tooling): <b>{{ number_format($part->c_total_part_cost_pc, 2) }}</b></span>
                        </div>
                    @endif
                </div>
            @empty
                <div class="text-center py-8 text-muted text-sm">Belum ada part. Klik "Tambah Part" untuk mulai.</div>
            @endforelse
        </div>

        {{-- ============ PRICE TIER PANEL ============ --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md overflow-hidden">
            <h3 class="text-xs text-navy uppercase font-bold px-4 py-2.5 bg-[#f4f6fa] border-b border-[#d7dbe2]">Ladder Harga per Tier</h3>
            <table class="w-full border-collapse text-xs">
                <thead>
                    <tr>
                        <th class="text-left px-3 py-2 bg-[#f4f5f8] text-muted text-[10.5px] uppercase border-b border-[#d7dbe2]">Qty</th>
                        <th class="text-left px-3 py-2 bg-[#f4f5f8] text-muted text-[10.5px] uppercase border-b border-[#d7dbe2]">Toleransi %</th>
                        <th class="text-left px-3 py-2 bg-[#f4f5f8] text-muted text-[10.5px] uppercase border-b border-[#d7dbe2]">Margin %</th>
                        <th class="text-left px-3 py-2 bg-[#f4f5f8] text-muted text-[10.5px] uppercase border-b border-[#d7dbe2]">Rounding</th>
                        <th class="text-left px-3 py-2 bg-[#f4f5f8] text-muted text-[10.5px] uppercase border-b border-[#d7dbe2]">HPP/pc</th>
                        <th class="text-left px-3 py-2 bg-[#f4f5f8] text-muted text-[10.5px] uppercase border-b border-[#d7dbe2]">Harga Jual/pc (excl PPN)</th>
                        <th class="text-left px-3 py-2 bg-[#f4f5f8] text-muted text-[10.5px] uppercase border-b border-[#d7dbe2]">Incl PPN</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->costing->priceTiers as $tier)
                        <tr wire:key="tier-{{ $tier->id_price_tier }}" class="{{ $tier->is_primary ? 'bg-[#eef5ff]' : '' }}">
                            <td class="px-3 py-2 border-b border-[#eef1f5] font-semibold">{{ number_format($tier->qty_pcs) }}{{ $tier->is_primary ? ' ★' : '' }}</td>
                            <td class="px-3 py-2 border-b border-[#eef1f5]"><input type="number" step="0.1" value="{{ $tier->tolerance_pct }}" wire:change="updateTier({{ $tier->id_price_tier }}, 'tolerance_pct', $event.target.value)" class="w-16 px-1.5 py-1 border border-[#d7dbe2] rounded text-xs"></td>
                            <td class="px-3 py-2 border-b border-[#eef1f5]"><input type="number" step="0.1" value="{{ $tier->margin_pct }}" wire:change="updateTier({{ $tier->id_price_tier }}, 'margin_pct', $event.target.value)" class="w-16 px-1.5 py-1 border border-[#d7dbe2] rounded text-xs"></td>
                            <td class="px-3 py-2 border-b border-[#eef1f5]">
                                <select wire:change="updateTier({{ $tier->id_price_tier }}, 'rounding_mode', $event.target.value)" class="px-1.5 py-1 border border-[#d7dbe2] rounded text-xs">
                                    @foreach (['NEAREST', 'UP', 'DOWN'] as $m)
                                        <option value="{{ $m }}" @selected($tier->rounding_mode === $m)>{{ $m }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $tier->c_hpp_pc !== null ? number_format($tier->c_hpp_pc, 2) : '—' }}</td>
                            <td class="px-3 py-2 border-b border-[#eef1f5] font-bold text-navy">{{ $tier->price_per_pcs !== null ? number_format($tier->price_per_pcs, 2) : '—' }}</td>
                            <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $tier->c_after_ppn_pc !== null ? number_format($tier->c_after_ppn_pc, 2) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-6 text-muted">Belum ada tier qty (dari Inquiry).</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</div>
