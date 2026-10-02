<?php

use App\Models\Sdi\CoOrderDelivery;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

// docs/mockups/co_delivery_plan_mockup.html -- hub logistik lintas-CO, MURNI
// penjadwalan (split/gabung/geser tanggal kirim). TIDAK menyentuh stok/COGS
// sama sekali -- itu domain Delivery Order/Dispatch (belum dibangun,
// financeaccounting_tier1.md Keputusan B: HPP diakui saat DO terbit). Hanya CO yang sudah
// CONFIRMED yang muncul di sini. Rename "Plan Delivery" -> "Customer Plan
// Delivery" (CPDEL, keputusan user 2026-09-03) -- baris awal skrng datang dari
// ENGINE otomatis (co/entry.blade.php::confirmCo(), full qty @ due_date per
// item) begitu CO Confirm, BUKAN cuma manual dari tab "Delivery" CO Entry lagi
// (manual masih tersedia sbg override saat CO Draft, engine cuma isi item yg
// belum disentuh manual). Layar ini yang mengelola KELANJUTANNYA sesudah CO
// fix (kondisi lapangan berubah: truk penuh, prioritas geser, dst) -- SALES
// bisa SPLIT/GABUNG baris atau biarkan seperti hasil engine. Tidak ada tombol
// Confirm/Create DO/Print di sini (sesuai mockup) -- baris berubah jadi
// SHIPPED otomatis nanti begitu DO terbit (fase Dispatch). Toolbar cuma
// search (§6 GRID_UI_STANDARD).
new #[Title('Customer Plan Delivery — TELASIH MAKMUR ERP')] class extends Component
{
    public string $search = '';

    public bool $mergeMode = false;

    public array $mergeSelectedIds = [];

    public string $mergeError = '';

    // ---- modal Split/Pindah Tanggal ----
    public bool $splitModalOpen = false;

    public ?int $splitDeliveryId = null;

    public string $splitQty = '';

    public string $splitDate = '';

    public string $splitError = '';

    #[Computed]
    public function deliveries()
    {
        return CoOrderDelivery::with(['order.quotation.customer', 'item'])
            ->whereHas('order', fn ($q) => $q->where('doc_status', 'CONFIRMED'))
            ->when($this->search !== '', function ($q) {
                $term = $this->search;
                $q->where(function ($qq) use ($term) {
                    $qq->whereHas('order', fn ($o) => $o->where('co_no', 'like', "%{$term}%")
                        ->orWhereHas('quotation.customer', fn ($c) => $c->where('customer_name', 'like', "%{$term}%")))
                        ->orWhereHas('item', fn ($i) => $i->where('description', 'like', "%{$term}%"));
                });
            })
            ->orderBy('ship_date')
            ->limit(300)
            ->get();
    }

    private function isLate(CoOrderDelivery $d): bool
    {
        if ($d->status !== 'PLANNED' || ! $d->item?->due_date) {
            return false;
        }

        return $d->item->due_date->isPast() || ($d->ship_date && $d->ship_date->gt($d->item->due_date));
    }

    #[Computed]
    public function statTotal(): int
    {
        return $this->deliveries->where('status', 'PLANNED')->count();
    }

    #[Computed]
    public function statLate(): int
    {
        return $this->deliveries->filter(fn ($d) => $this->isLate($d))->count();
    }

    #[Computed]
    public function statShipped(): int
    {
        return $this->deliveries->where('status', 'SHIPPED')
            ->filter(fn ($d) => $d->ship_date?->isCurrentMonth())
            ->count();
    }

    public function openSplitModal(int $id): void
    {
        $this->splitDeliveryId = $id;
        $this->splitQty = '';
        $delivery = CoOrderDelivery::findOrFail($id);
        $this->splitDate = $delivery->ship_date?->format('Y-m-d') ?? now()->toDateString();
        $this->splitError = '';
        $this->splitModalOpen = true;
    }

    public function closeSplitModal(): void
    {
        $this->splitModalOpen = false;
    }

    // Kalau splitQty kosong = cuma pindah tanggal (baris yang sama). Kalau
    // diisi = qty itu dipecah jadi baris PLANNED baru di tanggal baru, sisanya
    // tetap di baris asal pada tanggal asal -- total qty selalu konservasi,
    // tidak perlu assertBalance terpisah selama splitQty < qty baris asal.
    public function confirmSplit(): void
    {
        $delivery = CoOrderDelivery::findOrFail($this->splitDeliveryId);

        if ($delivery->status !== 'PLANNED') {
            $this->splitError = t('This row is already SHIPPED and cannot be changed.');

            return;
        }

        $qty = trim($this->splitQty) === '' ? null : (float) $this->splitQty;

        if ($qty === null) {
            $delivery->update(['ship_date' => $this->splitDate, 'updated_by' => Auth::user()->login]);
        } else {
            if ($qty <= 0 || $qty >= (float) $delivery->ship_qty) {
                $this->splitError = str_replace(':qty', $delivery->ship_qty, t('Split qty must be more than 0 and less than the qty of this row (:qty).'));

                return;
            }

            $login = Auth::user()->login;
            CoOrderDelivery::create([
                'order_id' => $delivery->order_id,
                'item_id' => $delivery->item_id,
                'shipment_type' => 'PARTIAL',
                'ship_date' => $this->splitDate,
                'ship_qty' => $qty,
                'status' => 'PLANNED',
                'split_from_id' => $delivery->id_delivery,
                'created_by' => $login,
                'updated_by' => $login,
            ]);
            $delivery->update([
                'ship_qty' => (float) $delivery->ship_qty - $qty,
                'shipment_type' => 'PARTIAL',
                'updated_by' => $login,
            ]);
        }

        unset($this->deliveries, $this->statTotal, $this->statLate, $this->statShipped);
        $this->splitModalOpen = false;
        session()->flash('flash', t('Delivery schedule updated.'));
    }

    public function toggleMergeMode(): void
    {
        $this->mergeMode = ! $this->mergeMode;
        $this->mergeSelectedIds = [];
        $this->mergeError = '';
    }

    public function toggleMergeSelect(int $id): void
    {
        if (in_array($id, $this->mergeSelectedIds, true)) {
            $this->mergeSelectedIds = array_values(array_diff($this->mergeSelectedIds, [$id]));
        } else {
            $this->mergeSelectedIds[] = $id;
        }
    }

    // Gabung cuma boleh utk baris PLANNED dari CO+item yg SAMA -- disatukan ke
    // baris bertanggal PALING AWAL (menang), sisanya dihapus setelah qty-nya
    // dijumlahkan ke baris yang menang.
    public function confirmMerge(): void
    {
        if (count($this->mergeSelectedIds) < 2) {
            $this->mergeError = t('Choose at least 2 rows to merge.');

            return;
        }

        $rows = CoOrderDelivery::whereIn('id_delivery', $this->mergeSelectedIds)->get();

        if ($rows->pluck('order_id')->unique()->count() > 1 || $rows->pluck('item_id')->unique()->count() > 1) {
            $this->mergeError = t('Merged rows must come from the same CO & product.');

            return;
        }
        if ($rows->contains(fn ($r) => $r->status !== 'PLANNED')) {
            $this->mergeError = t('Only rows with PLANNED status can be merged.');

            return;
        }

        $winner = $rows->sortBy('ship_date')->first();
        $totalQty = $rows->sum('ship_qty');
        $login = Auth::user()->login;

        $winner->update(['ship_qty' => $totalQty, 'updated_by' => $login]);
        CoOrderDelivery::whereIn('id_delivery', $rows->pluck('id_delivery')->diff([$winner->id_delivery]))->delete();

        unset($this->deliveries, $this->statTotal, $this->statLate, $this->statShipped);
        $this->mergeMode = false;
        $this->mergeSelectedIds = [];
        session()->flash('flash', t('Rows merged successfully.'));
    }

    public function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'SHIPPED' => 'bg-[#e9f9ef] text-[#16a34a]',
            default => 'bg-[#fef6e7] text-[#b45309]',
        };
    }

    // Componentization grid (2026-08-27): avatarInitials()/avatarGradient()
    // dipindah ke App\Support\Grid\Avatar (dipakai <x-grid.identity-cell>).
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">

        <x-alert.banner type="success" :message="session('flash')" />

        <x-grid.stat-row :count="3">
            <x-grid.stat-box tone="total" :label="t('Active Plans')" :value="$this->statTotal" />
            <x-grid.stat-box tone="critical" :label="t('Late')" :value="$this->statLate" />
            <x-grid.stat-box tone="active" :label="t('Shipped This Month')" :value="$this->statShipped" />
        </x-grid.stat-row>

        <div class="text-xs px-3.5 py-2.5 rounded-md mb-3.5 leading-relaxed bg-[#eef3fb] border border-[#c8ddfb] text-[#1d4ed8]">
            {{ t('The initial schedule is created AUTOMATICALLY when the CO is Confirmed (full qty @ Due Date per product) — or manually from the "Delivery" tab in CO Entry if Sales set it earlier while the CO was still Draft.') }} {{ t('Here you RE-arrange the logistics (split/merge rows, shift dates) after the CO is final — qty & price stay from the CO and cannot be changed here.') }} {{ t('Rows become SHIPPED automatically once the Delivery Order is issued.') }}
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ t('Search CO no. / customer / product…') }}"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <x-slot:tools>
                    @if ($mergeMode)
                        <span class="text-[11px] text-muted shrink-0">{{ str_replace(':n', count($mergeSelectedIds), t(':n rows selected')) }}</span>
                        <button type="button" wire:click="confirmMerge" @disabled(count($mergeSelectedIds) < 2)
                            class="shrink-0 px-3.5 py-1.5 rounded text-xs font-semibold bg-accent text-white disabled:opacity-40 cursor-pointer">{{ t('OK, Merge') }}</button>
                        <button type="button" wire:click="toggleMergeMode" class="shrink-0 px-3.5 py-1.5 rounded text-xs font-semibold border border-[#d7dbe2] cursor-pointer">{{ t('Cancel') }}</button>
                    @else
                        <button type="button" wire:click="toggleMergeMode"
                            class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">⇄ {{ t('Merge Rows') }}</button>
                    @endif
                </x-slot:tools>
            </x-grid.toolbar>
            @if ($mergeError)
                <div class="text-[11px] px-3.5 py-2 bg-[#fdecec] text-[#a3453f]">⚠ {{ $mergeError }}</div>
            @endif

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            @if ($mergeMode)
                                <th class="w-[36px] bg-[#fafbfd] border-b border-[#eef1f5] sticky top-0 z-[1]"></th>
                            @endif
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Customer / CO No.') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">{{ t('Product') }}</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">{{ t('Ship Qty') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[130px]">{{ t('Planned Date') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">{{ t('Status') }}</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[140px]">{{ t('Origin') }}</th>
                            @if (! $mergeMode)
                                <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[70px]">{{ t('Action') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->deliveries as $d)
                            @php $late = $this->isLate($d); @endphp
                            <tr wire:key="dlv-{{ $d->id_delivery }}" class="{{ $late ? 'bg-[#fff8f7]' : '' }} {{ $mergeMode ? 'cursor-pointer hover:bg-[#eef3fb]' : '' }}" @if($mergeMode) wire:click="toggleMergeSelect({{ $d->id_delivery }})" @endif>
                                @if ($mergeMode)
                                    <td class="px-3 py-2 border-b border-[#eef1f5] text-center">
                                        <input type="checkbox" @checked(in_array($d->id_delivery, $mergeSelectedIds)) onclick="event.preventDefault()" @disabled($d->status !== 'PLANNED')>
                                    </td>
                                @endif
                                <x-grid.identity-cell :id="$d->id_delivery" :name="$d->order?->quotation?->customer?->customer_name ?? '—'" :code="$d->order?->co_no" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $d->item?->description }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right font-semibold">{{ rtrim(rtrim(number_format((float) $d->ship_qty, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    {{ $d->ship_date?->translatedFormat('d M Y') ?? '—' }}
                                    @if ($late) <span title="{{ t('Past the CO due date') }}">⚠</span> @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->statusBadgeClass($d->status) }}">{{ $d->status }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-[10.5px] text-muted">
                                    {{ $d->split_from_id ? str_replace(':id', $d->split_from_id, t('SPLIT from #:id')) : t('Initial (from CO Entry)') }}
                                </td>
                                @if (! $mergeMode)
                                    <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                        @if ($d->status === 'PLANNED')
                                            <x-grid.action-icon icon="fa-scissors" :title="t('Split / Move Date')" action="openSplitModal({{ $d->id_delivery }})" />
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="$mergeMode ? 6 : 7" :message="t('No delivery plans from Confirmed COs yet.')" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>

    @if ($splitModalOpen)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" wire:click.self="closeSplitModal">
            <div class="bg-white rounded-lg p-4 w-[400px] shadow-xl">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-navy">{{ t('Split / Move Delivery Date') }}</h3>
                    <button type="button" wire:click="closeSplitModal" class="text-muted hover:text-navy cursor-pointer">✕</button>
                </div>
                @if ($splitError)
                    <p class="text-[11px] text-[#a3453f] mb-2">⚠ {{ $splitError }}</p>
                @endif
                <div class="flex flex-col gap-3">
                    <x-form.input wire:model="splitQty" :placeholder="t('Empty = move the whole qty')">
                        <x-slot:label>{{ t('Split Qty') }} <span class="text-muted font-normal">({{ t('leave empty to only move the date') }})</span></x-slot:label>
                    </x-form.input>
                    <x-form.date-picker :label="t('New Date *')" wire:model="splitDate" />
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" wire:click="closeSplitModal" class="text-xs font-semibold px-3.5 py-2 rounded border border-[#d7dbe2] cursor-pointer">{{ t('Cancel') }}</button>
                    <button type="button" wire:click="confirmSplit" class="text-xs font-bold px-3.5 py-2 rounded bg-accent text-white cursor-pointer">{{ trim($splitQty) === '' ? t('Move Date') : t('Split into New Row') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
