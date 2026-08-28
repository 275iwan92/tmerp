<?php

use App\Models\Master\MatPlanoSize;
use App\Models\Sdi\IqpCostingPart;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\IqpLayout;
use App\Models\Sdi\IqpPart;
use App\Services\Imposition\ImpositionEngine;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Imposition Engine — TELASIH MAKMUR ERP')] class extends Component
{
    public int $inquiryId;

    public ?int $partId = null;

    public function mount(int $inquiry): void
    {
        $this->inquiryId = $inquiry;

        $firstPart = IqpPart::where('inquiry_id', $inquiry)->orderBy('part_seq')->first();
        $this->partId = $firstPart?->id_part;

        if ($this->partId) {
            $this->ensureLayout($this->partId);
        }
    }

    protected function ensureLayout(int $partId): void
    {
        if (IqpLayout::where('part_id', $partId)->exists()) {
            return;
        }

        $part = IqpPart::findOrFail($partId);
        $default = MatPlanoSize::where('activate', true)->orderBy('sort_order')->first();

        IqpLayout::create([
            'part_id' => $partId,
            'flat_w_mm' => $part->flat_w ?: 0,
            'flat_h_mm' => $part->flat_h ?: 0,
            'gap_mm' => 3,
            'plano_size_id' => $default?->id_plano_size,
            'plano_w_mm' => $default?->width_mm ?? 890,
            'plano_h_mm' => $default?->height_mm ?? 1250,
            'gripper_mm' => 12,
            'margin_mm' => 8,
            'method' => 'TEGAK',
            'created_by' => Auth::user()->login,
            'updated_by' => Auth::user()->login,
        ]);

        $this->recompute($partId);
    }

    protected function recompute(int $partId): void
    {
        $layout = IqpLayout::where('part_id', $partId)->first();

        if (! $layout || $layout->stat_confirm) {
            return;
        }

        $engine = new ImpositionEngine;
        $result = $engine->run(
            (float) $layout->plano_w_mm, (float) $layout->plano_h_mm,
            (float) $layout->flat_w_mm, (float) $layout->flat_h_mm,
            (float) $layout->gap_mm, (float) $layout->gripper_mm, (float) $layout->margin_mm
        );
        $cells = $engine->cells($result, (float) $layout->margin_mm, (float) $layout->gripper_mm, (float) $layout->gap_mm);

        $layout->update([
            'method' => $result['method'],
            'up_count' => $result['up'],
            'efficiency_pct' => $result['efficiency_pct'],
            'waste_pct' => $result['waste_pct'],
            'layout_detail' => ['layout' => $result['layout'], 'cells' => $cells],
            'updated_by' => Auth::user()->login,
        ]);
    }

    #[Computed]
    public function inquiry(): IqpInquiry
    {
        return IqpInquiry::findOrFail($this->inquiryId);
    }

    #[Computed]
    public function parts()
    {
        return IqpPart::where('inquiry_id', $this->inquiryId)->orderBy('part_seq')->get();
    }

    #[Computed]
    public function layout(): ?IqpLayout
    {
        return $this->partId ? IqpLayout::where('part_id', $this->partId)->first() : null;
    }

    #[Computed]
    public function planoSizes()
    {
        return MatPlanoSize::where('activate', true)->orderBy('sort_order')->get();
    }

    #[Computed]
    public function suggestions()
    {
        $layout = $this->layout;

        if (! $layout) {
            return collect();
        }

        $engine = new ImpositionEngine;

        return $this->planoSizes
            ->map(function ($p) use ($engine, $layout) {
                $r = $engine->run(
                    (float) $p->width_mm, (float) $p->height_mm,
                    (float) $layout->flat_w_mm, (float) $layout->flat_h_mm,
                    (float) $layout->gap_mm, (float) $layout->gripper_mm, (float) $layout->margin_mm
                );

                return (object) [
                    'id' => $p->id_plano_size,
                    'name' => $p->plano_name,
                    'up' => $r['up'],
                    'eff' => $r['efficiency_pct'],
                ];
            })
            ->sort(fn ($a, $b) => $b->up <=> $a->up ?: $b->eff <=> $a->eff)
            ->values();
    }

    #[Computed]
    public function visual(): ?array
    {
        $layout = $this->layout;

        if (! $layout || ! $layout->layout_detail) {
            return null;
        }

        $maxW = 440;
        $maxH = 300;
        $pw = (float) $layout->plano_w_mm;
        $ph = (float) $layout->plano_h_mm;
        $scale = ($pw > 0 && $ph > 0) ? min($maxW / $pw, $maxH / $ph) : 0;

        $cells = collect($layout->layout_detail['cells'] ?? [])->map(fn ($c) => [
            'left' => round($c['x'] * $scale, 1),
            'top' => round($c['y'] * $scale, 1),
            'width' => max(0, round($c['w'] * $scale, 1) - 1),
            'height' => max(0, round($c['h'] * $scale, 1) - 1),
            'rot' => $c['rot'],
            'n' => $c['n'],
        ]);

        return [
            'boxW' => round($pw * $scale, 1),
            'boxH' => round($ph * $scale, 1),
            'gripH' => round((float) $layout->gripper_mm * $scale, 1),
            'cells' => $cells,
        ];
    }

    public function switchPart(int $partId): void
    {
        $this->partId = $partId;
        $this->ensureLayout($partId);
        unset($this->layout, $this->visual, $this->suggestions);
    }

    public function updateField(string $field, $value): void
    {
        $allowed = ['flat_w_mm', 'flat_h_mm', 'gap_mm', 'plano_w_mm', 'plano_h_mm', 'gripper_mm', 'margin_mm'];

        if (! in_array($field, $allowed, true) || ! $this->layout || $this->layout->stat_confirm) {
            return;
        }

        $this->layout->update([$field => $value === '' ? 0 : $value, 'updated_by' => Auth::user()->login]);
        $this->recompute($this->partId);
        unset($this->layout, $this->visual, $this->suggestions);
    }

    public function pickPlano(int $planoSizeId): void
    {
        if (! $this->layout || $this->layout->stat_confirm) {
            return;
        }

        $p = MatPlanoSize::find($planoSizeId);

        if (! $p) {
            return;
        }

        $this->layout->update([
            'plano_size_id' => $p->id_plano_size,
            'plano_w_mm' => $p->width_mm,
            'plano_h_mm' => $p->height_mm,
            'updated_by' => Auth::user()->login,
        ]);
        $this->recompute($this->partId);
        unset($this->layout, $this->visual, $this->suggestions);
    }

    public function confirm(): void
    {
        $layout = $this->layout;

        if (! $layout || $layout->up_count < 1) {
            return;
        }

        $layout->update([
            'stat_confirm' => true,
            'confirmed_by' => Auth::user()->login,
            'confirmed_at' => now(),
        ]);

        // Auto-fill ke Costing kalau costing_part utk part ini sudah ada (productcosting_tier1-4.md
        // §1.1: imposition "dokumen sendiri, memberi up_count/plano" -> mengalir ke costing).
        IqpCostingPart::where('part_id', $layout->part_id)->update([
            'i_panjang_plano_cm' => round($layout->plano_w_mm / 10, 4),
            'i_lebar_plano_cm' => round($layout->plano_h_mm / 10, 4),
            'i_cut_per_plano' => $layout->up_count,
            'updated_by' => Auth::user()->login,
        ]);

        unset($this->layout);
    }

    public function unlock(): void
    {
        if (! $this->layout) {
            return;
        }

        $this->layout->update(['stat_confirm' => false, 'confirmed_by' => null, 'confirmed_at' => null]);
        unset($this->layout);
    }
};
?>

<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">📐 TELASIH MAKMUR — Imposition Engine</b>
        <span class="text-xs text-white/75">{{ $this->inquiry->inquiry_no }} · {{ $this->inquiry->product_draft_name }}</span>
        <a href="{{ route('inquiry.costing', ['inquiry' => $this->inquiryId]) }}" class="ml-auto bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md no-underline hover:brightness-110">→ Costing</a>
        <a href="{{ route('inquiry.hub') }}" title="Kembali ke Inquiry Hub"
            class="inline-flex items-center gap-2 pl-3.5 pr-1 py-1 bg-[#43508a] rounded-full no-underline hover:brightness-110">
            <span class="text-[11px] font-bold uppercase tracking-wide text-white">Back</span>
            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#43508a] text-xs font-bold">←</span>
        </a>
    </header>

    <div class="max-w-[1180px] mx-auto my-5 px-4">

        {{-- ============ PART SWITCHER ============ --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md p-3 mb-4 flex items-center gap-2 flex-wrap">
            <span class="text-[10.5px] text-muted uppercase font-semibold">Part:</span>
            @forelse ($this->parts as $p)
                <button type="button" wire:click="switchPart({{ $p->id_part }})"
                    class="text-xs font-semibold px-3 py-1.5 rounded-full border cursor-pointer {{ $this->partId === $p->id_part ? 'bg-navy text-white border-navy' : 'bg-[#f4f6fa] text-navy border-[#d7dbe2]' }}">
                    {{ $p->part_name ?: 'Part #'.$p->part_seq }}
                    @if ($p->layout?->stat_confirm) <span class="text-[10px]">✓</span> @endif
                </button>
            @empty
                <span class="text-xs text-muted">Belum ada part di inquiry ini — isi dulu di Inquiry New/Recalc/Revise.</span>
            @endforelse
        </div>

        @if ($this->layout)
        {{-- flex, BUKAN class="grid" -- app.css .grid{} unlayered override
             grid-cols/md:grid-cols manapun jadi 4 kolom sama rata (lihat catatan
             di co/entry.blade.php & docs/FORM_UI_STANDARD.md). --}}
        <div class="flex flex-col md:flex-row gap-3.5">

            {{-- KIRI: input --}}
            <div class="md:w-[320px] md:shrink-0">
                @if ($this->layout->stat_confirm)
                    <div class="bg-[#eaf7ee] border border-[#a9d9bb] text-[#1f8a4c] text-xs px-3.5 py-2.5 rounded-md mb-3.5 flex items-center justify-between gap-2">
                        <span>✓ Terkonfirmasi oleh {{ $this->layout->confirmed_by }} · {{ $this->layout->confirmed_at?->format('d/m/Y H:i') }}</span>
                        <button type="button" wire:click="unlock" class="text-[10.5px] font-bold underline cursor-pointer whitespace-nowrap">Buka kunci</button>
                    </div>
                @endif

                <div class="bg-white border border-[#d7dbe2] rounded-md mb-3.5 overflow-hidden">
                    <h3 class="text-xs text-navy uppercase font-bold px-3.5 py-2 bg-[#f4f6fa] border-b border-[#d7dbe2]">📦 Bentangan Flat (1 up)</h3>
                    <div class="p-3.5">
                        <div class="grid two gap-2 mb-2.5">
                            <div class="flex flex-col gap-0.5">
                                <label class="text-[11px] text-muted font-semibold">Flat W (mm)</label>
                                <input type="number" step="0.1" value="{{ $this->layout->flat_w_mm }}" @disabled($this->layout->stat_confirm)
                                    wire:change="updateField('flat_w_mm', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px] disabled:bg-[#eef0f3]">
                            </div>
                            <div class="flex flex-col gap-0.5">
                                <label class="text-[11px] text-muted font-semibold">Flat H (mm)</label>
                                <input type="number" step="0.1" value="{{ $this->layout->flat_h_mm }}" @disabled($this->layout->stat_confirm)
                                    wire:change="updateField('flat_h_mm', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px] disabled:bg-[#eef0f3]">
                            </div>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <label class="text-[11px] text-muted font-semibold">Gap antar-up (mm)</label>
                            <input type="number" step="0.1" value="{{ $this->layout->gap_mm }}" @disabled($this->layout->stat_confirm)
                                wire:change="updateField('gap_mm', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px] disabled:bg-[#eef0f3]">
                        </div>
                        <div class="text-[10.5px] text-muted mt-1.5">Dari dieline (iqp_part.flat_w/flat_h). Bisa dioverride bila perlu.</div>
                    </div>
                </div>

                <div class="bg-white border border-[#d7dbe2] rounded-md mb-3.5 overflow-hidden">
                    <h3 class="text-xs text-navy uppercase font-bold px-3.5 py-2 bg-[#f4f6fa] border-b border-[#d7dbe2]">📄 Plano</h3>
                    <div class="p-3.5">
                        <div class="grid two gap-2 mb-2.5">
                            <div class="flex flex-col gap-0.5">
                                <label class="text-[11px] text-muted font-semibold">Plano W (mm)</label>
                                <input type="number" step="0.1" value="{{ $this->layout->plano_w_mm }}" @disabled($this->layout->stat_confirm)
                                    wire:change="updateField('plano_w_mm', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px] disabled:bg-[#eef0f3]">
                            </div>
                            <div class="flex flex-col gap-0.5">
                                <label class="text-[11px] text-muted font-semibold">Plano H (mm)</label>
                                <input type="number" step="0.1" value="{{ $this->layout->plano_h_mm }}" @disabled($this->layout->stat_confirm)
                                    wire:change="updateField('plano_h_mm', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px] disabled:bg-[#eef0f3]">
                            </div>
                        </div>
                        <div class="grid two gap-2 mb-2.5">
                            <div class="flex flex-col gap-0.5">
                                <label class="text-[11px] text-muted font-semibold">Gripper (mm)</label>
                                <input type="number" step="0.1" value="{{ $this->layout->gripper_mm }}" @disabled($this->layout->stat_confirm)
                                    wire:change="updateField('gripper_mm', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px] disabled:bg-[#eef0f3]">
                            </div>
                            <div class="flex flex-col gap-0.5">
                                <label class="text-[11px] text-muted font-semibold">Margin tepi (mm)</label>
                                <input type="number" step="0.1" value="{{ $this->layout->margin_mm }}" @disabled($this->layout->stat_confirm)
                                    wire:change="updateField('margin_mm', $event.target.value)" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px] disabled:bg-[#eef0f3]">
                            </div>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <label class="text-[11px] text-muted font-semibold">Metode terpilih</label>
                            <input readonly value="{{ $this->layout->method }}" class="px-2 py-1.5 border border-[#c7cfda] rounded text-[13px] font-bold bg-[#f0f7f4] text-[#0e5c38]">
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-[#d7dbe2] rounded-md overflow-hidden">
                    <h3 class="text-xs text-navy uppercase font-bold px-3.5 py-2 bg-[#f4f6fa] border-b border-[#d7dbe2]">💡 Saran Plano Terbaik</h3>
                    <table class="w-full text-xs">
                        <thead>
                            <tr>
                                <th class="text-left px-2 py-1.5 text-[9.5px] text-muted uppercase font-bold border-b border-[#e2e7ee]">Plano</th>
                                <th class="text-right px-2 py-1.5 text-[9.5px] text-muted uppercase font-bold border-b border-[#e2e7ee]">Up</th>
                                <th class="text-right px-2 py-1.5 text-[9.5px] text-muted uppercase font-bold border-b border-[#e2e7ee]">Efisiensi</th>
                                <th class="border-b border-[#e2e7ee]"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $bestUp = $this->suggestions->first()?->up; @endphp
                            @foreach ($this->suggestions as $s)
                                <tr class="{{ $s->up === $bestUp ? 'bg-[#f3fbf7]' : '' }}" wire:key="sug-{{ $s->id }}">
                                    <td class="px-2 py-1.5 font-semibold text-navy border-b border-[#eef1f5]">{{ $s->name }}{{ $s->up === $bestUp ? ' ★' : '' }}</td>
                                    <td class="px-2 py-1.5 text-right border-b border-[#eef1f5]">{{ $s->up }}</td>
                                    <td class="px-2 py-1.5 text-right border-b border-[#eef1f5] font-bold {{ $s->eff >= 75 ? 'text-[#0e8a4f]' : ($s->eff >= 55 ? 'text-[#8a5a00]' : 'text-[#c0392b]') }}">{{ number_format($s->eff, 0) }}%</td>
                                    <td class="px-2 py-1.5 text-right border-b border-[#eef1f5]">
                                        @unless ($this->layout->stat_confirm)
                                            <button type="button" wire:click="pickPlano({{ $s->id }})" class="text-[11px] font-semibold text-accent cursor-pointer">pilih</button>
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- KANAN: hasil + visual --}}
            <div class="md:flex-1 min-w-0">
                <div class="bg-white border border-[#d7dbe2] rounded-md overflow-hidden">
                    <h3 class="text-xs text-navy uppercase font-bold px-3.5 py-2 bg-[#f4f6fa] border-b border-[#d7dbe2] flex items-center justify-between">
                        <span>🎯 Hasil Imposition</span>
                        <button type="button" wire:click="confirm" @disabled($this->layout->stat_confirm || $this->layout->up_count < 1)
                            class="px-3 py-1.5 rounded text-[11px] font-bold bg-[#22a06b] text-white cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">✓ Konfirmasi</button>
                    </h3>
                    <div class="p-3.5">
                        <div class="grid three gap-2.5 mb-3">
                            <div class="bg-[#f3fbf7] border border-[#22a06b] rounded-md px-3 py-2.5 text-center">
                                <div class="text-[9.5px] text-muted uppercase font-bold">Total UP / plano</div>
                                <div class="text-xl font-extrabold text-[#0e5c38]">{{ $this->layout->up_count }}</div>
                                <div class="text-[10px] text-muted">{{ $this->layout->method }}</div>
                            </div>
                            <div class="bg-[#f8fafc] border border-[#e2e7ee] rounded-md px-3 py-2.5 text-center">
                                <div class="text-[9.5px] text-muted uppercase font-bold">Efisiensi</div>
                                <div class="text-xl font-extrabold text-navy">{{ number_format($this->layout->efficiency_pct, 0) }}%</div>
                                <div class="text-[10px] text-muted">area kepakai</div>
                            </div>
                            <div class="bg-[#f8fafc] border border-[#e2e7ee] rounded-md px-3 py-2.5 text-center">
                                <div class="text-[9.5px] text-muted uppercase font-bold">Waste</div>
                                <div class="text-xl font-extrabold text-navy">{{ number_format($this->layout->waste_pct, 0) }}%</div>
                                <div class="text-[10px] text-muted">sisa plano</div>
                            </div>
                        </div>

                        <div class="bg-[#fbfcfd] border border-[#dde3ec] rounded-md p-4 flex items-center justify-center min-h-[340px]">
                            @if ($this->visual)
                                <div class="relative bg-white border-2 border-navy" style="width:{{ $this->visual['boxW'] }}px;height:{{ $this->visual['boxH'] }}px">
                                    <div class="absolute top-0 left-0 right-0 bg-[repeating-linear-gradient(45deg,#e9edf3,#e9edf3_4px,#f4f6fa_4px,#f4f6fa_8px)] border-b border-dashed border-[#a9b4c4] flex items-center justify-center text-[7px] text-[#8a94a6]"
                                        style="height:{{ $this->visual['gripH'] }}px">gripper</div>
                                    @foreach ($this->visual['cells'] as $c)
                                        <div class="absolute flex items-center justify-center text-[8px] font-bold {{ $c['rot'] ? 'bg-[#7c5cd6]/10 border border-[#7c5cd6] text-[#5b3fa0]' : 'bg-[#2c7be5]/10 border border-[#2c7be5] text-[#1560a8]' }}"
                                            style="left:{{ $c['left'] }}px;top:{{ $c['top'] }}px;width:{{ $c['width'] }}px;height:{{ $c['height'] }}px">{{ $c['n'] }}</div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="flex gap-3.5 text-[10.5px] text-[#556] mt-2 flex-wrap">
                            <span class="flex items-center gap-1"><span class="w-3.5 h-2.5 rounded-sm inline-block bg-[#2c7be5]/30 border border-[#2c7be5]"></span> tegak</span>
                            <span class="flex items-center gap-1"><span class="w-3.5 h-2.5 rounded-sm inline-block bg-[#7c5cd6]/30 border border-[#7c5cd6]"></span> diputar 90°</span>
                            <span class="flex items-center gap-1"><span class="w-3.5 h-2.5 rounded-sm inline-block bg-[repeating-linear-gradient(45deg,#e9edf3,#e9edf3_3px,#f4f6fa_3px,#f4f6fa_6px)]"></span> gripper</span>
                        </div>

                        <div class="text-[11.5px] px-2.5 py-2 rounded-md leading-relaxed mt-2.5 bg-[#e8f4ff] border border-[#b8d9f5] text-[#1560a8]">
                            💡 Engine mencoba <b>tegak</b>, <b>putar 90°</b>, dan <b>campur</b> → memilih UP terbanyak:
                            <b>{{ $this->layout->method }} = {{ $this->layout->up_count }} up</b> (efisiensi {{ number_format($this->layout->efficiency_pct, 0) }}%).
                            Product Designer dapat override bila perlu pertimbangan arah serat/grain.
                            @if ($this->layout->stat_confirm)
                                <br>✓ Sudah dikonfirmasi — nilai plano &amp; cut sudah mengalir otomatis ke Costing (bila part ini sudah ditambahkan di sana).
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
