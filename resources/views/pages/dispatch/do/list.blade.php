<?php

use App\Livewire\Concerns\HasGridPagination;
use App\Models\Master\CustCustomer;
use App\Models\Sdi\DoDelivery;
use App\Models\Sdi\DoLine;
use App\Services\Dispatch\DispatchService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

// dispatch_tier4.md §2 -- griduistandard.md diikuti persis (pola sama
// quotation/list.blade.php: "+ New Record" via picker customer, karena DO
// memang lahir dari grid ini). Kolom Aksi: ↗ Buka selalu ada, ✓ Confirm
// kondisional (status DRAFT & line_count>=1) memanggil DispatchService::confirm()
// -- method SAMA yang dipakai tombol Confirm di Entry (Tier4 §9 checklist,
// jangan duplikasi logic). Sumber grid: VIEW v_do_list (Tier3 §4.2).
//
// DEVIASI kecil dari teks Tier4 §2 ("klik New, masuk ke Entry kosong, pilih
// customer"): do_delivery.customer_id NOT NULL (Tier3 §2, DISPATCH-01 kunci
// dokumen ke 1 customer) -- jadi "+ New Record" pakai picker customer DULU
// (pola persis quotation/list.blade.php), baru redirect ke Entry yang sudah
// terisi customer tapi 0 baris ("kosong" = kosong baris, bukan kosong
// customer). Konsisten dgn constraint DB, bukan improvisasi bebas.
new #[Title('Daftar Delivery Order — TELASIH MAKMUR ERP')] class extends Component
{
    use HasGridPagination;
    use WithPagination;

    public string $search = '';

    public string $customerSearch = '';

    public bool $pickerOpen = false;

    public array $expanded = [];

    #[Computed]
    public function deliveries()
    {
        return DB::connection('tm2sdi')->table('v_do_list')
            ->when($this->search !== '', function ($q) {
                $term = $this->search;
                $q->where(function ($qq) use ($term) {
                    $qq->where('do_no', 'like', "%{$term}%")
                        ->orWhere('customer_name', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('id_delivery')
            ->paginate($this->perPage);
    }

    // Baris do_line utk grup yang sedang di-expand -- eager-load sekaligus
    // (bukan per-klik N+1, griduistandard.md §9), grouped per delivery_id.
    #[Computed]
    public function expandedLines()
    {
        if (empty($this->expanded)) {
            return collect();
        }

        return DoLine::whereIn('delivery_id', $this->expanded)
            ->where('status', '<>', 'CANCELLED')
            ->orderBy('line_no')
            ->get()
            ->groupBy('delivery_id');
    }

    public function toggleExpand(int $id): void
    {
        if (in_array($id, $this->expanded, true)) {
            $this->expanded = array_values(array_diff($this->expanded, [$id]));

            return;
        }

        $this->expanded[] = $id;
    }

    #[Computed]
    public function customerResults()
    {
        if (mb_strlen(trim($this->customerSearch)) < 2) {
            return collect();
        }

        return CustCustomer::where('activate', true)
            ->where(function ($q) {
                $q->where('customer_name', 'like', "%{$this->customerSearch}%")
                    ->orWhere('customer_code', 'like', "%{$this->customerSearch}%");
            })
            ->orderBy('customer_name')
            ->limit(15)
            ->get();
    }

    public function openPicker(): void
    {
        $this->pickerOpen = true;
        $this->customerSearch = '';
    }

    public function closePicker(): void
    {
        $this->pickerOpen = false;
    }

    public function startForCustomer(int $customerId)
    {
        $do = DoDelivery::create([
            'do_date' => now()->toDateString(),
            'customer_id' => $customerId,
            'status' => 'DRAFT',
            'created_by' => Auth::user()->login,
            'updated_by' => Auth::user()->login,
        ]);

        return $this->redirect(route('dispatch.do.entry', ['doId' => $do->id_delivery]));
    }

    public function openDelivery(int $id)
    {
        return $this->redirect(route('dispatch.do.entry', ['doId' => $id]));
    }

    public function confirmDelivery(int $id): void
    {
        $do = DoDelivery::with('lines')->findOrFail($id);

        if ($do->status !== 'DRAFT' || $do->lines->isEmpty()) {
            return;
        }

        app(DispatchService::class)->confirm($do, Auth::user()->login);

        unset($this->deliveries);
    }

    #[Computed]
    public function statTotal(): int
    {
        return DoDelivery::count();
    }

    #[Computed]
    public function statDraft(): int
    {
        return DoDelivery::where('status', 'DRAFT')->count();
    }

    #[Computed]
    public function statConfirmed(): int
    {
        return DoDelivery::where('status', 'CONFIRMED')->count();
    }

    #[Computed]
    public function statShipped(): int
    {
        return DoDelivery::where('status', 'SHIPPED')->count();
    }
};
?>

<div>
    <div class="max-w-[1400px] mx-auto my-5 px-4">
        <x-grid.stat-row :count="4">
            <x-grid.stat-box tone="total" label="Total DO" :value="$this->statTotal" />
            <x-grid.stat-box tone="warn" label="Draft" :value="$this->statDraft" />
            <x-grid.stat-box tone="extra" label="Confirmed" :value="$this->statConfirmed" />
            <x-grid.stat-box tone="active" label="Shipped" :value="$this->statShipped" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari no. DO / customer…"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <x-slot:newRecord>
                    <button type="button" wire:click="openPicker"
                        class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
                </x-slot:newRecord>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="px-2 py-2 bg-[#fafbfd] border-b border-[#eef1f5] sticky top-0 z-[1] w-[32px]"></th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1]">Delivery Order</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[110px]">Tanggal</th>
                            <th class="text-center px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[70px]">Baris</th>
                            <th class="text-center px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">Asal CO</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[100px]">Status</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[150px]">Nilai</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] sticky top-0 z-[1] w-[80px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->deliveries as $d)
                            @php($isOpen = in_array($d->id_delivery, $this->expanded, true))
                            <tr wire:key="do-{{ $d->id_delivery }}" wire:dblclick="openDelivery({{ $d->id_delivery }})" class="hover:bg-[#f7faff] cursor-pointer" title="Klik 2x untuk buka">
                                <td class="px-2 py-2 border-b border-[#eef1f5] text-center">
                                    <button type="button" wire:click.stop="toggleExpand({{ $d->id_delivery }})" title="{{ $isOpen ? 'Tutup preview baris' : 'Lihat baris' }}"
                                        class="inline-flex items-center justify-center w-6 h-6 rounded text-muted hover:bg-[#eef3fb] hover:text-accent cursor-pointer">{{ $isOpen ? '▾' : '▸' }}</button>
                                </td>
                                <x-grid.identity-cell :id="$d->id_delivery" :name="$d->customer_name ?? '—'"
                                    :code="($d->do_no ?: '(Draft)').(($d->do_type ?? 'CUSTOMER') === 'SUBCON' ? ' · SUBKON '.$d->subcon_process : '')" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ \Carbon\Carbon::parse($d->do_date)->translatedFormat('d M Y') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-center">{{ $d->line_count }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-center">
                                    @if ($d->co_count > 1)
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#f1ecfc] text-[#6d28d9]">{{ $d->co_count }} CO</span>
                                    @else
                                        <span class="text-muted">{{ $d->co_count }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ match ($d->status) {
                                        'CONFIRMED' => 'bg-[#eaf2ff] text-[#1d4ed8]',
                                        'SHIPPED' => 'bg-[#e9f9ef] text-[#16a34a]',
                                        'CANCELLED' => 'bg-[#fdecec] text-[#c0392b]',
                                        default => 'bg-[#fef6e7] text-[#b45309]',
                                    } }}">{{ $d->status }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right font-semibold">{{ number_format($d->total_value, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
                                    <x-grid.action-icon icon="↗" title="Buka Delivery Order ini" action="openDelivery({{ $d->id_delivery }})" />
                                    @if ($d->status === 'DRAFT' && $d->line_count >= 1)
                                        <x-grid.action-icon icon="✓" title="Confirm DO ini" action="confirmDelivery({{ $d->id_delivery }})"
                                            confirm="Confirm Delivery Order utk {{ $d->customer_name }}? Nomor DO akan diterbitkan &amp; baris terkunci." variant="success" />
                                    @endif
                                </td>
                            </tr>
                            @if ($isOpen)
                                <tr wire:key="do-detail-{{ $d->id_delivery }}" class="bg-[#fafbfd]">
                                    <td colspan="8" class="px-3 py-2.5 border-b border-[#eef1f5]">
                                        <table class="w-full text-[11.5px] border-collapse ml-8" style="width:calc(100% - 2rem)">
                                            <thead>
                                                <tr class="text-[10px] text-muted uppercase font-bold">
                                                    <th class="text-left px-2 py-1 w-24">No. CO</th>
                                                    <th class="text-left px-2 py-1">Item</th>
                                                    <th class="text-right px-2 py-1 w-24">Qty</th>
                                                    <th class="text-right px-2 py-1 w-24">Harga</th>
                                                    <th class="text-right px-2 py-1 w-28">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse (($this->expandedLines[$d->id_delivery] ?? collect()) as $line)
                                                    <tr class="border-t border-[#eef1f5]" wire:key="do-detail-line-{{ $line->id_line }}">
                                                        <td class="px-2 py-1.5 font-mono text-accent">{{ $line->order?->co_no ?? '—' }}</td>
                                                        <td class="px-2 py-1.5">{{ $line->item_description }}</td>
                                                        <td class="px-2 py-1.5 text-right">{{ number_format($line->qty_do, 0, ',', '.') }} {{ $line->qty_unit }}</td>
                                                        <td class="px-2 py-1.5 text-right">{{ number_format($line->unit_price, 0, ',', '.') }}</td>
                                                        <td class="px-2 py-1.5 text-right font-semibold">{{ number_format($line->subtotal, 0, ',', '.') }}</td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="5" class="text-center py-3 text-muted text-[11px]">Belum ada baris.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <x-grid.empty-row :colspan="8" message="Belum ada Delivery Order." />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>

            @include('components.grid.pagination-footer', ['paginator' => $this->deliveries])
        </div>
    </div>

    @if ($pickerOpen)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" wire:click.self="closePicker">
            <div class="bg-white rounded-lg p-4 w-[480px] shadow-xl">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-navy">Pilih Customer</h3>
                    <button type="button" wire:click="closePicker" class="text-muted hover:text-navy cursor-pointer">✕</button>
                </div>
                <input type="text" wire:model.live.debounce.300ms="customerSearch" autofocus placeholder="Ketik min. 2 huruf nama/kode customer..." class="w-full px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs mb-2">
                <div class="max-h-72 overflow-y-auto">
                    @forelse ($this->customerResults as $c)
                        <button type="button" wire:click="startForCustomer({{ $c->id_customer }})" class="w-full text-left px-2.5 py-2 rounded hover:bg-[#eef3fb] cursor-pointer border-b border-[#eef1f5] last:border-b-0">
                            <div class="text-xs font-semibold text-navy">{{ $c->customer_name }}</div>
                            <div class="text-[10.5px] text-muted">{{ $c->customer_code }}</div>
                        </button>
                    @empty
                        <div class="text-[11px] text-muted text-center py-4">
                            {{ mb_strlen(trim($customerSearch)) < 2 ? 'Ketik min. 2 huruf untuk cari.' : 'Tidak ada customer cocok.' }}
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
