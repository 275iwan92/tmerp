<?php

use App\Models\Master\ProdProduct;
use App\Models\Prd\PrdMtsForecast;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// MTS Forecast Entry -- production_scheduling_tier1.md §5A.8, tier2.md §6.
// Input manual forecast per produk per periode + banding actual -> accuracy_pct.
// SENGAJA TIDAK ADA: generator WO replenishment otomatis saat stok < safety
// level (F20, "Tier 2 lanjutan" -- di luar scope disepakati sesi ini).
new #[Title('Forecast MTS — TELASIH MAKMUR ERP')] class extends Component
{
    public bool $showModal = false;

    public ?int $productId = null;

    public string $productQuery = '';

    public string $productName = '';

    public string $periodStart = '';

    public string $periodEnd = '';

    public float $forecastQty = 0;

    public ?int $actualEditId = null;

    public float $actualQty = 0;

    #[Computed]
    public function forecasts()
    {
        return PrdMtsForecast::with('product')->orderByDesc('forecast_id')->get();
    }

    #[Computed]
    public function productResults()
    {
        if ($this->productId || mb_strlen($this->productQuery) < 2) {
            return collect();
        }

        return ProdProduct::query()
            ->where(function ($q) {
                $q->where('product_name', 'like', "%{$this->productQuery}%")
                    ->orWhere('product_code', 'like', "%{$this->productQuery}%");
            })
            ->orderBy('product_name')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function statTotal(): int
    {
        return PrdMtsForecast::count();
    }

    #[Computed]
    public function statAccurate(): int
    {
        return PrdMtsForecast::where('accuracy_pct', '>=', 0.85)->count();
    }

    #[Computed]
    public function statBelowTarget(): int
    {
        return PrdMtsForecast::whereNotNull('accuracy_pct')->where('accuracy_pct', '<', 0.85)->count();
    }

    public function openNew(): void
    {
        $this->reset(['productId', 'productQuery', 'productName', 'periodStart', 'periodEnd', 'forecastQty']);
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function selectProduct(int $id): void
    {
        $p = ProdProduct::find($id);
        if ($p) {
            $this->productId = $p->id_product;
            $this->productName = $p->product_name;
            $this->productQuery = '';
        }
    }

    public function clearProduct(): void
    {
        $this->productId = null;
        $this->productName = '';
    }

    public function save(): void
    {
        $this->validate([
            'productId' => ['required', 'integer'],
            'periodStart' => ['required', 'date'],
            'periodEnd' => ['required', 'date', 'after_or_equal:periodStart'],
            'forecastQty' => ['required', 'numeric', 'min:0.01'],
        ]);

        PrdMtsForecast::create([
            'product_id' => $this->productId,
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd,
            'forecast_qty' => $this->forecastQty,
            'input_by' => Auth::user()->login,
        ]);

        $this->showModal = false;
    }

    public function openActualEntry(int $id): void
    {
        $f = PrdMtsForecast::findOrFail($id);
        $this->actualEditId = $id;
        $this->actualQty = (float) ($f->actual_qty ?? 0);
    }

    public function closeActualEntry(): void
    {
        $this->actualEditId = null;
    }

    // Tier2 §6 langkah 2: accuracy = 1 - ABS(forecast-actual)/forecast.
    public function saveActual(): void
    {
        $this->validate(['actualQty' => ['required', 'numeric', 'min:0']]);

        $f = PrdMtsForecast::findOrFail($this->actualEditId);
        $accuracy = $f->forecast_qty > 0
            ? max(0, 1 - abs((float) $f->forecast_qty - $this->actualQty) / (float) $f->forecast_qty)
            : null;

        $f->update(['actual_qty' => $this->actualQty, 'accuracy_pct' => $accuracy]);

        $this->actualEditId = null;
    }
};
?>

<div>
    <div class="max-w-[1200px] mx-auto my-5 px-4">
        <x-grid.stat-row :count="3">
            <x-grid.stat-box tone="total" :label="t('Total Forecast')" :value="$this->statTotal" />
            <x-grid.stat-box tone="active" :label="t('Accuracy ≥ 85%')" :value="$this->statAccurate" />
            <x-grid.stat-box tone="critical" :label="t('Below Target')" :value="$this->statBelowTarget" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <span class="text-[11px] text-muted italic">{{ t('Entered by Directors/Top Management per MTS product. Automatic replenishment follows in a later session.') }}</span>
                <x-slot:newRecord>
                    <button type="button" wire:click="openNew" class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                </x-slot:newRecord>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">{{ t('Product') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">{{ t('Period') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">{{ t('Forecast') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">{{ t('Actual') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">{{ t('Accuracy') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">{{ t('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->forecasts as $f)
                            <tr wire:key="fc-{{ $f->forecast_id }}">
                                <td class="px-3 py-2 border-b border-[#eef1f5] font-semibold">{{ $f->product?->product_name ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $f->period_start->format('d/m/y') }}–{{ $f->period_end->format('d/m/y') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right">{{ number_format((float) $f->forecast_qty, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right">{{ $f->actual_qty !== null ? number_format((float) $f->actual_qty, 0, ',', '.') : '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    @if ($f->accuracy_pct !== null)
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $f->accuracy_pct >= 0.85 ? 'bg-[#dcfce7] text-[#15803d]' : 'bg-[#fffbeb] text-[#92400e]' }}">{{ number_format($f->accuracy_pct * 100, 1) }}%</span>
                                    @else
                                        <span class="text-[10px] text-muted">{{ t('not set') }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <button type="button" wire:click="openActualEntry({{ $f->forecast_id }})" class="text-[11px] font-semibold text-accent hover:underline cursor-pointer">{{ t('Enter Actual') }}</button>
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="6" :message="t('No MTS forecasts yet.')" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>

    @if ($showModal)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="closeModal">
            <div class="bg-white rounded-xl w-[420px] max-w-[95vw] shadow-2xl">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center justify-between">
                    <span>{{ t('New MTS Forecast') }}</span>
                    <button type="button" wire:click="closeModal" class="text-white/70 hover:text-white cursor-pointer">✕</button>
                </div>
                <div class="p-4 space-y-3">
                    <div class="relative">
                        <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">{{ t('Product') }}</label>
                        @if ($productId)
                            <div class="flex items-center gap-1.5">
                                <div class="flex-1"><x-form.input value="{{ $productName }}" readonly /></div>
                                <button type="button" wire:click="clearProduct" class="text-[11px] text-muted underline cursor-pointer shrink-0">{{ t('Change', 'action') }}</button>
                            </div>
                        @else
                            <input type="text" wire:model.live.debounce.400ms="productQuery" autocomplete="off" placeholder="{{ t('Type product name/code…') }}" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                            @error('productId') <span class="text-[10px] text-[#a3241f]">{{ $message }}</span> @enderror
                            @if ($productQuery && mb_strlen($productQuery) >= 2)
                                <div class="absolute left-0 right-0 mt-1 bg-white border border-[#e5e7eb] rounded-md shadow-lg z-10 max-h-40 overflow-y-auto">
                                    @forelse ($this->productResults as $p)
                                        <div wire:click="selectProduct({{ $p->id_product }})" class="px-2.5 py-1.5 text-xs cursor-pointer hover:bg-[#f4f5f8] flex items-center justify-between">
                                            <span>{{ $p->product_name }}</span>
                                            <span class="text-muted text-[10px]">{{ $p->product_code }}</span>
                                        </div>
                                    @empty
                                        <div class="px-2.5 py-1.5 text-xs text-muted">{{ t('Not found.') }}</div>
                                    @endforelse
                                </div>
                            @endif
                        @endif
                    </div>
                    <div class="grid two gap-3">
                        <div>
                            <x-form.date-picker :label="t('Period Start')" wire:model="periodStart" />
                            @error('periodStart') <span class="text-[10px] text-[#a3241f]">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <x-form.date-picker :label="t('Period End')" wire:model="periodEnd" />
                            @error('periodEnd') <span class="text-[10px] text-[#a3241f]">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">{{ t('Forecast Qty') }}</label>
                        <input type="number" wire:model="forecastQty" min="0" step="1" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                        @error('forecastQty') <span class="text-[10px] text-[#a3241f]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex justify-end gap-2 pt-3 mt-2 border-t border-[#eef1f5]">
                        <button type="button" wire:click="closeModal" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">{{ t('Cancel') }}</button>
                        <button type="button" wire:click="save" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#2c3e69] text-white cursor-pointer">{{ t('Save') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($actualEditId)
        <div class="fixed inset-0 bg-[rgba(20,26,40,.45)] z-[100] flex items-center justify-center p-5" wire:click.self="closeActualEntry">
            <div class="bg-white rounded-xl w-[360px] max-w-[95vw] shadow-2xl">
                <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center justify-between">
                    <span>{{ t('Enter Actual Qty') }}</span>
                    <button type="button" wire:click="closeActualEntry" class="text-white/70 hover:text-white cursor-pointer">✕</button>
                </div>
                <div class="p-4">
                    <label class="block text-[10px] font-bold text-muted uppercase mb-1.5">{{ t('Delivered Qty (Actual)') }}</label>
                    <input type="number" wire:model="actualQty" min="0" step="1" class="w-full px-2.5 py-1.5 border border-[#c7cfda] rounded-md text-xs">
                    @error('actualQty') <span class="text-[10px] text-[#a3241f]">{{ $message }}</span> @enderror
                    <div class="flex justify-end gap-2 pt-3 mt-2 border-t border-[#eef1f5]">
                        <button type="button" wire:click="closeActualEntry" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">{{ t('Cancel') }}</button>
                        <button type="button" wire:click="saveActual" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#2c3e69] text-white cursor-pointer">{{ t('Save') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
