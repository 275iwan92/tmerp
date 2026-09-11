<?php

use App\Models\Master\CustCustomer;
use App\Models\Sdi\DoDelivery;
use App\Models\Sdi\DoLine;
use App\Services\Dispatch\DispatchService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

// dispatch_tier4.md §3-6 -- Blok A Info Dokumen / B Customer & Ship-to /
// C CO Line Picker (DRAFT saja) / D Baris DO + footer aksi. Satu komponen
// menangani create (DoList sudah pre-create header via picker customer,
// lihat catatan deviasi di list.blade.php) dan detail read-only
// (CONFIRMED/SHIPPED/CANCELLED) -- $doId SELALU terisi di sini.
//
// PERSIST: auto-save per aksi (pull baris -> langsung INSERT do_line; hapus
// -> langsung DELETE; edit qty/harga -> langsung UPDATE) -- dicek dulu pola
// co/entry.blade.php (Tier4 §7.2 eksplisit minta ini), TIDAK ADA tombol
// "Simpan Draft" terpisah, co/entry.blade.php juga begitu.
new #[Title('Delivery Order — TELASIH MAKMUR ERP')] class extends Component
{
    public int $doId;

    public bool $showCancelModal = false;

    public string $cancelReason = '';

    public function mount(int $doId): void
    {
        $this->doId = $doId;
    }

    #[Computed]
    public function do(): DoDelivery
    {
        return DoDelivery::with(['customer', 'lines' => fn ($q) => $q->where('status', '<>', 'CANCELLED')->orderBy('line_no')])->findOrFail($this->doId);
    }

    #[Computed]
    public function totals(): array
    {
        $lines = $this->do->lines;

        return [
            'qty' => $lines->sum('qty_do'),
            'value' => $lines->sum('subtotal'),
        ];
    }

    // Tier2 §B.3: badge info "item sama, harga beda" -- murni UI, tidak mengubah data.
    #[Computed]
    public function duplicateDescriptions(): array
    {
        return $this->do->lines
            ->groupBy('item_description')
            ->filter(fn ($g) => $g->pluck('unit_price')->unique()->count() > 1)
            ->keys()
            ->all();
    }

    #[On('address-picked')]
    public function onAddressPicked(string $fieldName, ?int $areaId, ?string $areaLevel, ?string $label, ?string $postalCode): void
    {
        if ($fieldName !== 'do-ship-address' || $this->do->status !== 'DRAFT') {
            return;
        }

        $this->do->update([
            'ship_area_id' => $areaId,
            'ship_area_level' => $areaLevel,
            'ship_address_label' => $label,
            'ship_postal_code' => $postalCode,
            'updated_by' => Auth::user()->login,
        ]);

        unset($this->do);
    }

    public function updateHeader(string $field, $value): void
    {
        if ($this->do->status !== 'DRAFT') {
            return;
        }

        $allowed = ['do_date', 'scheduled_ship_date', 'ship_address_detail'];

        if (! in_array($field, $allowed, true)) {
            return;
        }

        $this->do->update([$field => $value === '' ? null : $value, 'updated_by' => Auth::user()->login]);
        unset($this->do);
    }

    // Tier4 §5: picker Blok C, sumber v_co_delivery_outstanding (Tier2 §A.2).
    #[Computed]
    public function pickerLines()
    {
        if ($this->do->status !== 'DRAFT') {
            return collect();
        }

        return DB::connection('tm2sdi')->table('v_co_delivery_outstanding')
            ->where('customer_id', $this->do->customer_id)
            ->where('qty_available', '>', 0)
            ->where('co_status', 'CONFIRMED')
            ->orderBy('co_no')->orderBy('item_description')
            ->get();
    }

    public array $checkedDeliveryIds = [];

    public function togglePick(int $coDeliveryId): void
    {
        if (in_array($coDeliveryId, $this->checkedDeliveryIds, true)) {
            $this->checkedDeliveryIds = array_values(array_diff($this->checkedDeliveryIds, [$coDeliveryId]));

            return;
        }

        $this->checkedDeliveryIds[] = $coDeliveryId;
    }

    public function pullSelected(): void
    {
        if (empty($this->checkedDeliveryIds)) {
            return;
        }

        $result = app(DispatchService::class)->pullLines($this->do, $this->checkedDeliveryIds, Auth::user()->login);

        $this->checkedDeliveryIds = [];
        $this->pullMessage = $result['message'] ?? '';

        unset($this->do, $this->pickerLines);
    }

    public string $pullMessage = '';

    public function updateLineQty(int $lineId, $value): void
    {
        $line = DoLine::where('delivery_id', $this->doId)->findOrFail($lineId);
        $result = app(DispatchService::class)->updateLineQty($line, (float) $value, Auth::user()->login);
        $this->pullMessage = $result['ok'] ? '' : $result['message'];
        unset($this->do, $this->pickerLines);
    }

    public function updateLinePrice(int $lineId, $value): void
    {
        $line = DoLine::where('delivery_id', $this->doId)->findOrFail($lineId);
        $result = app(DispatchService::class)->updateLinePrice($line, (float) $value, Auth::user()->login);
        $this->pullMessage = $result['ok'] ? '' : $result['message'];
        unset($this->do);
    }

    public function removeLine(int $lineId): void
    {
        $line = DoLine::where('delivery_id', $this->doId)->findOrFail($lineId);
        app(DispatchService::class)->removeLine($line);
        unset($this->do, $this->pickerLines);
    }

    public function confirmDo(): void
    {
        $result = app(DispatchService::class)->confirm($this->do, Auth::user()->login);
        $this->pullMessage = $result['ok'] ? '' : $result['message'];
        unset($this->do);
    }

    public function markShipped(): void
    {
        app(DispatchService::class)->markShipped($this->do, Auth::user()->login);
        unset($this->do);
    }

    public function openCancelModal(): void
    {
        $this->showCancelModal = true;
        $this->cancelReason = '';
    }

    public function closeCancelModal(): void
    {
        $this->showCancelModal = false;
    }

    public function cancelDo(): void
    {
        $result = app(DispatchService::class)->cancel($this->do, $this->cancelReason, Auth::user()->login);

        if ($result['ok']) {
            $this->showCancelModal = false;
        }

        $this->pullMessage = $result['ok'] ? '' : $result['message'];
        unset($this->do);
    }
};
?>

<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">🚚 Delivery Order — Entry</b>
        <span class="text-xs text-white/75">{{ $this->do->do_no ?: '(auto saat Confirm)' }} · {{ $this->do->customer?->customer_name }}</span>
        <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full {{ match ($this->do->status) {
            'CONFIRMED' => 'bg-[#eaf2ff] text-[#1d4ed8]',
            'SHIPPED' => 'bg-[#eaf7ee] text-[#1f8a4c]',
            'CANCELLED' => 'bg-[#fdecec] text-[#c0392b]',
            default => 'bg-[#fff5e0] text-[#8a5a00]',
        } }}">{{ $this->do->status }}</span>

        <div class="ml-auto flex items-center gap-4">
            <div class="text-right leading-tight">
                <div class="text-[9.5px] uppercase text-white/55 font-semibold">Total Nilai</div>
                <div class="text-sm font-extrabold">{{ number_format($this->totals['value'], 0, ',', '.') }}</div>
            </div>

            @if ($this->do->status === 'DRAFT')
                <button type="button" wire:click="confirmDo" wire:confirm="Confirm Delivery Order ini? Nomor DO akan diterbitkan &amp; baris terkunci."
                    @disabled($this->do->lines->isEmpty())
                    class="px-3.5 py-1.5 rounded-md text-xs font-bold bg-[#22a06b] text-white cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">✓ Confirm DO</button>
                <button type="button" wire:click="openCancelModal" class="px-3.5 py-1.5 rounded-md text-xs font-bold bg-white text-[#c0392b] border border-[#f5c6c6] cursor-pointer">Batalkan</button>
            @elseif ($this->do->status === 'CONFIRMED')
                <button type="button" wire:click="markShipped" wire:confirm="Tandai seluruh baris DO ini sebagai Terkirim?"
                    class="px-3.5 py-1.5 rounded-md text-xs font-bold bg-[#22a06b] text-white cursor-pointer">📦 Tandai Terkirim</button>
                <button type="button" wire:click="openCancelModal" class="px-3.5 py-1.5 rounded-md text-xs font-bold bg-white text-[#c0392b] border border-[#f5c6c6] cursor-pointer">Batalkan</button>
                <a href="{{ route('dispatch.do.print', ['doId' => $this->doId]) }}" class="px-3.5 py-1.5 rounded-md text-xs font-bold bg-[#43508a] text-white no-underline cursor-pointer">🖨️ Cetak DO</a>
                <a href="{{ route('dispatch.do.print-surat-jalan', ['doId' => $this->doId]) }}" class="px-3.5 py-1.5 rounded-md text-xs font-bold bg-[#43508a] text-white no-underline cursor-pointer">🖨️ Surat Jalan</a>
            @elseif ($this->do->status === 'SHIPPED')
                <a href="{{ route('dispatch.do.print', ['doId' => $this->doId]) }}" class="px-3.5 py-1.5 rounded-md text-xs font-bold bg-[#43508a] text-white no-underline cursor-pointer">🖨️ Cetak DO</a>
                <a href="{{ route('dispatch.do.print-surat-jalan', ['doId' => $this->doId]) }}" class="px-3.5 py-1.5 rounded-md text-xs font-bold bg-[#43508a] text-white no-underline cursor-pointer">🖨️ Surat Jalan</a>
            @endif

            <a href="{{ route('dispatch.do.index') }}" title="Kembali ke Daftar Delivery Order"
                class="inline-flex items-center gap-2 pl-3.5 pr-1 py-1 bg-[#43508a] rounded-full no-underline hover:brightness-110">
                <span class="text-[11px] font-bold uppercase tracking-wide text-white">Back</span>
                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#43508a] text-[13px]"><i class="fa-solid fa-arrow-left"></i></span>
            </a>
        </div>
    </header>

    <div class="max-w-[1100px] mx-auto my-5 px-4">
        @if ($pullMessage)
            <div class="text-xs px-3.5 py-2.5 rounded-md mb-3.5 bg-[#fdecec] border border-[#f5c6c6] text-[#a3453f]">{{ $pullMessage }}</div>
        @endif

        {{-- BLOK A: Informasi Dokumen --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4">
            <h3 class="text-xs text-navy uppercase font-bold mb-3">Informasi Dokumen</h3>
            <div class="grid three">
                <x-form.input label="No. Delivery Order" readonly value="{{ $this->do->do_no ?: '(auto saat Confirm)' }}" class="bg-[#f8fafc]" />
                <x-form.date-picker label="Tanggal DO" value="{{ $this->do->do_date?->format('Y-m-d') }}" :disabled="$this->do->status !== 'DRAFT'"
                    wire:change="updateHeader('do_date', $event.target.value)" class="disabled:bg-[#f8fafc]" />
                <x-form.date-picker label="Rencana Kirim" value="{{ $this->do->scheduled_ship_date?->format('Y-m-d') }}" :disabled="$this->do->status !== 'DRAFT'"
                    wire:change="updateHeader('scheduled_ship_date', $event.target.value)" class="disabled:bg-[#f8fafc]" />
            </div>
        </div>

        {{-- BLOK B: Customer & Ship-to --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4">
            <h3 class="text-xs text-navy uppercase font-bold mb-3">Customer &amp; Ship-to</h3>
            <div class="grid two">
                <x-form.input label="Customer" readonly value="{{ $this->do->customer?->customer_name }} ({{ $this->do->customer?->customer_code }})" class="bg-[#f8fafc] font-semibold" />
                <div>
                    <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Alamat Kirim</label>
                    <livewire:address-picker
                        field-name="do-ship-address"
                        :initial-area-id="$this->do->ship_area_id"
                        :initial-area-level="$this->do->ship_area_level"
                        :initial-label="$this->do->ship_address_label"
                        wire:key="do-ship-picker-{{ $this->doId }}" />
                </div>
                <div class="col-span-2">
                    <x-form.input label="Detail Alamat (jalan, no., RT/RW, patokan)" value="{{ $this->do->ship_address_detail }}" :disabled="$this->do->status !== 'DRAFT'"
                        wire:change="updateHeader('ship_address_detail', $event.target.value)" placeholder="Jl. ..., No. ..." class="disabled:bg-[#f8fafc]" />
                </div>
            </div>
        </div>

        {{-- BLOK C: CO Line Picker (DRAFT saja) --}}
        @if ($this->do->status === 'DRAFT')
            <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4">
                <h3 class="text-xs text-navy uppercase font-bold mb-3">Tarik Baris dari Customer Order</h3>
                <div class="max-h-64 overflow-y-auto border border-[#eef1f5] rounded">
                    <table class="w-full text-[11.5px] border-collapse">
                        <thead>
                            <tr class="bg-[#fafbfd] text-[10px] text-muted uppercase font-bold sticky top-0">
                                <th class="px-2 py-1.5 w-8"></th>
                                <th class="text-left px-2 py-1.5 w-24">No. CO</th>
                                <th class="text-left px-2 py-1.5">Item</th>
                                <th class="text-right px-2 py-1.5 w-24">Qty Tersedia</th>
                                <th class="text-center px-2 py-1.5 w-16">UoM</th>
                                <th class="text-right px-2 py-1.5 w-24">Harga/Unit</th>
                                <th class="text-center px-2 py-1.5 w-24">Jadwal Kirim</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->pickerLines as $p)
                                <tr wire:key="pick-{{ $p->co_delivery_id }}" wire:click="togglePick({{ $p->co_delivery_id }})" class="border-t border-[#eef1f5] hover:bg-[#eef3fb] cursor-pointer">
                                    <td class="px-2 py-1.5 text-center"><input type="checkbox" class="pointer-events-none" @checked(in_array($p->co_delivery_id, $checkedDeliveryIds, true))></td>
                                    <td class="px-2 py-1.5 font-mono text-accent font-bold">{{ $p->co_no }}</td>
                                    <td class="px-2 py-1.5">{{ $p->item_description }}</td>
                                    <td class="px-2 py-1.5 text-right font-semibold">{{ number_format($p->qty_available, 2) }}</td>
                                    <td class="px-2 py-1.5 text-center">{{ $p->qty_unit }}</td>
                                    <td class="px-2 py-1.5 text-right">{{ number_format($p->unit_price, 0, ',', '.') }}</td>
                                    <td class="px-2 py-1.5 text-center text-muted">{{ $p->ship_date ? \Carbon\Carbon::parse($p->ship_date)->format('d/m/Y') : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center py-6 text-muted text-[11px]">Tidak ada jadwal kirim outstanding utk customer ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="flex justify-end mt-2.5">
                    <button type="button" wire:click="pullSelected" @disabled(empty($checkedDeliveryIds))
                        class="px-3.5 py-1.5 rounded-md text-xs font-bold bg-accent text-white cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">↓ Tarik ke Delivery Order</button>
                </div>
            </div>
        @endif

        {{-- BLOK D: Baris DO + Footer --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4">
            <h3 class="text-xs text-navy uppercase font-bold mb-3">Baris Delivery Order</h3>
            <table class="w-full text-[11.5px] border-collapse">
                <thead>
                    <tr class="bg-[#fafbfd] text-[10px] text-muted uppercase font-bold">
                        <th class="text-left px-2 py-1.5 w-24">No. CO</th>
                        <th class="text-left px-2 py-1.5">Item</th>
                        <th class="text-right px-2 py-1.5 w-28">Qty DO</th>
                        <th class="text-right px-2 py-1.5 w-28">Harga/Unit</th>
                        <th class="text-right px-2 py-1.5 w-28">Subtotal</th>
                        @if ($this->do->status === 'DRAFT')
                            <th class="w-10"></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->do->lines as $line)
                        <tr wire:key="line-{{ $line->id_line }}" class="border-t border-[#eef1f5]">
                            <td class="px-2 py-1.5 font-mono text-accent font-bold">{{ $line->order?->co_no ?? '—' }}</td>
                            <td class="px-2 py-1.5">
                                {{ $line->item_description }}
                                @if (in_array($line->item_description, $this->duplicateDescriptions, true))
                                    <span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded-full bg-[#fef6e7] text-[#8a6d00] ml-1">⚠ item sama, harga beda</span>
                                @endif
                            </td>
                            <td class="px-2 py-1.5 text-right">
                                @if ($this->do->status === 'DRAFT')
                                    <input type="number" step="0.01" value="{{ $line->qty_do }}" wire:change="updateLineQty({{ $line->id_line }}, $event.target.value)"
                                        class="w-24 text-right px-1.5 py-1 border border-[#d7dbe2] rounded text-xs">
                                @else
                                    {{ number_format($line->qty_do, 2) }}
                                @endif
                                {{ $line->qty_unit }}
                            </td>
                            <td class="px-2 py-1.5 text-right">
                                @if ($this->do->status === 'DRAFT')
                                    <input type="number" step="0.01" value="{{ $line->unit_price }}" wire:change="updateLinePrice({{ $line->id_line }}, $event.target.value)"
                                        class="w-24 text-right px-1.5 py-1 border border-[#d7dbe2] rounded text-xs">
                                @else
                                    {{ number_format($line->unit_price, 0, ',', '.') }}
                                @endif
                            </td>
                            <td class="px-2 py-1.5 text-right font-semibold">{{ number_format($line->subtotal, 0, ',', '.') }}</td>
                            @if ($this->do->status === 'DRAFT')
                                <td class="px-2 py-1.5 text-center">
                                    <button type="button" wire:click="removeLine({{ $line->id_line }})" wire:confirm="Hapus baris ini?" class="text-red-500 hover:text-red-700 cursor-pointer">✕</button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-6 text-muted text-[11px]">Belum ada baris. Tarik dari Customer Order di atas.</td></tr>
                    @endforelse
                </tbody>
                @if ($this->do->lines->isNotEmpty())
                    <tfoot>
                        <tr class="bg-[#f4f6fa] font-bold border-t border-[#c8ccd4]">
                            <td class="px-2 py-2" colspan="2">Total</td>
                            <td class="px-2 py-2 text-right">{{ number_format($this->totals['qty'], 2) }}</td>
                            <td class="px-2 py-2"></td>
                            <td class="px-2 py-2 text-right">{{ number_format($this->totals['value'], 0, ',', '.') }}</td>
                            @if ($this->do->status === 'DRAFT')
                                <td></td>
                            @endif
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    @if ($showCancelModal)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" wire:click.self="closeCancelModal">
            <div class="bg-white rounded-lg p-4 w-[420px] shadow-xl">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-navy">Batalkan Delivery Order</h3>
                    <button type="button" wire:click="closeCancelModal" class="text-muted hover:text-navy cursor-pointer">✕</button>
                </div>
                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Alasan Pembatalan (wajib)</label>
                <textarea rows="3" wire:model="cancelReason" class="w-full px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs mb-3" placeholder="Jelaskan alasan pembatalan..."></textarea>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeCancelModal" class="px-3.5 py-1.5 rounded-md text-xs font-semibold bg-white border border-[#d7dbe2] cursor-pointer">Batal</button>
                    <button type="button" wire:click="cancelDo" @disabled(trim($cancelReason) === '') class="px-3.5 py-1.5 rounded-md text-xs font-bold bg-[#c0392b] text-white cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">Batalkan DO</button>
                </div>
            </div>
        </div>
    @endif
</div>
