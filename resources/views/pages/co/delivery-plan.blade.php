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
// CONFIRMED yang muncul di sini -- CO masih DRAFT jadwalnya diedit di tab
// "Delivery" milik CO Entry sendiri (co/entry.blade.php), layar ini yang
// mengelola KELANJUTANNYA sesudah CO fix (kondisi lapangan berubah: truk
// penuh, prioritas geser, dst). Tidak ada tombol Confirm/Create DO/Print di
// sini (sesuai mockup) -- baris berubah jadi SHIPPED otomatis nanti begitu DO
// terbit (fase Dispatch). Toolbar cuma search (§6 GRID_UI_STANDARD, baris tak
// pernah dibuat dari grid ini sendiri -- lahir dari tab Delivery CO Entry).
new #[Title('Plan Delivery — TELASIH MAKMUR ERP')] class extends Component
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
            $this->splitError = 'Baris ini sudah SHIPPED, tidak bisa diubah.';

            return;
        }

        $qty = trim($this->splitQty) === '' ? null : (float) $this->splitQty;

        if ($qty === null) {
            $delivery->update(['ship_date' => $this->splitDate, 'updated_by' => Auth::user()->login]);
        } else {
            if ($qty <= 0 || $qty >= (float) $delivery->ship_qty) {
                $this->splitError = "Qty split harus lebih dari 0 dan kurang dari qty baris ini ({$delivery->ship_qty}).";

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
        session()->flash('flash', 'Jadwal kirim diperbarui.');
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
            $this->mergeError = 'Pilih minimal 2 baris utk digabung.';

            return;
        }

        $rows = CoOrderDelivery::whereIn('id_delivery', $this->mergeSelectedIds)->get();

        if ($rows->pluck('order_id')->unique()->count() > 1 || $rows->pluck('item_id')->unique()->count() > 1) {
            $this->mergeError = 'Baris yang digabung harus dari CO & produk yang sama.';

            return;
        }
        if ($rows->contains(fn ($r) => $r->status !== 'PLANNED')) {
            $this->mergeError = 'Hanya baris berstatus PLANNED yang bisa digabung.';

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
        session()->flash('flash', 'Baris berhasil digabung.');
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
            <x-grid.stat-box tone="total" label="Rencana Aktif" :value="$this->statTotal" />
            <x-grid.stat-box tone="critical" label="Terlambat" :value="$this->statLate" />
            <x-grid.stat-box tone="active" label="Terkirim Bulan Ini" :value="$this->statShipped" />
        </x-grid.stat-row>

        <div class="text-xs px-3.5 py-2.5 rounded-md mb-3.5 leading-relaxed bg-[#eef3fb] border border-[#c8ddfb] text-[#1d4ed8]">
            Jadwal awal diisi dari tab "Delivery" di CO Entry saat CO masih Draft. Di sini Anda cuma atur ULANG logistiknya (pecah/gabung baris, geser tanggal) sesudah CO fix — qty &amp; harga tetap dari CO, tidak bisa diubah di sini. Baris berubah jadi SHIPPED otomatis begitu Delivery Order diterbitkan (belum dibangun).
        </div>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari no. CO / customer / produk…"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <x-slot:tools>
                    @if ($mergeMode)
                        <span class="text-[11px] text-muted shrink-0">{{ count($mergeSelectedIds) }} baris dipilih</span>
                        <button type="button" wire:click="confirmMerge" @disabled(count($mergeSelectedIds) < 2)
                            class="shrink-0 px-3.5 py-1.5 rounded text-xs font-semibold bg-accent text-white disabled:opacity-40 cursor-pointer">OK, Gabungkan</button>
                        <button type="button" wire:click="toggleMergeMode" class="shrink-0 px-3.5 py-1.5 rounded text-xs font-semibold border border-[#d7dbe2] cursor-pointer">Batal</button>
                    @else
                        <button type="button" wire:click="toggleMergeMode"
                            class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">⇄ Gabung Baris</button>
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
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Customer / No. CO</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Produk</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">Qty Kirim</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[130px]">Tgl Rencana</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">Status</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[140px]">Asal</th>
                            @if (! $mergeMode)
                                <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[70px]">Aksi</th>
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
                                    @if ($late) <span title="Melewati tanggal jatuh tempo CO">⚠</span> @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $this->statusBadgeClass($d->status) }}">{{ $d->status }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-[10.5px] text-muted">
                                    {{ $d->split_from_id ? 'SPLIT dari #'.$d->split_from_id : 'Awal (dari CO Entry)' }}
                                </td>
                                @if (! $mergeMode)
                                    <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                        @if ($d->status === 'PLANNED')
                                            <x-grid.action-icon icon="✂" title="Split / Pindah Tanggal" action="openSplitModal({{ $d->id_delivery }})" />
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="$mergeMode ? 6 : 7" message="Belum ada rencana kirim dari CO yang sudah Confirmed." />
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
                    <h3 class="text-sm font-bold text-navy">Split / Pindah Tanggal Kirim</h3>
                    <button type="button" wire:click="closeSplitModal" class="text-muted hover:text-navy cursor-pointer">✕</button>
                </div>
                @if ($splitError)
                    <p class="text-[11px] text-[#a3453f] mb-2">⚠ {{ $splitError }}</p>
                @endif
                <div class="flex flex-col gap-3">
                    <x-form.input wire:model="splitQty" placeholder="Kosong = pindah semua qty">
                        <x-slot:label>Qty Split <span class="text-muted font-normal">(kosongkan kalau cuma mau pindah tanggal)</span></x-slot:label>
                    </x-form.input>
                    <x-form.date-picker label="Tanggal Baru *" wire:model="splitDate" />
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" wire:click="closeSplitModal" class="text-xs font-semibold px-3.5 py-2 rounded border border-[#d7dbe2] cursor-pointer">Batal</button>
                    <button type="button" wire:click="confirmSplit" class="text-xs font-bold px-3.5 py-2 rounded bg-accent text-white cursor-pointer">{{ trim($splitQty) === '' ? 'Pindah Tanggal' : 'Pecah jadi Baris Baru' }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
