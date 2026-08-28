<?php

use App\Models\Prd\PrdWo;
use App\Models\Sdi\CoOrderDelivery;
use App\Services\Scheduling\WoGenerationService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// Screen #1 -- docs/production_scheduling_wo_generation_delta.md §1/§5.
// Picklist Plan Delivery (co_order_delivery status=PLANNED + order CONFIRMED,
// pola query sama co/delivery-plan.blade.php) YANG BELUM py WO. Pilih baris
// -> WoGenerationService jalan -> redirect Worklist WO (1 iqp_part = 1 WO,
// bisa >1 WO per baris kalau produknya multi-part).
new #[Title('Generate WO dari Plan Delivery — TELASIH MAKMUR ERP')] class extends Component
{
    public string $search = '';

    #[Computed]
    public function eligibleDeliveries()
    {
        // CLAUDE.md §2 -- whereHas/whereDoesntHave TIDAK BOLEH lintas-DB
        // (generatedWos = tm2sdi -> tm2prd). Resolver manual 2 tahap: tarik
        // dulu source_delivery_id yang sudah py WO dari tm2prd, baru exclude.
        $alreadyGeneratedIds = PrdWo::whereNotNull('source_delivery_id')->pluck('source_delivery_id');

        return CoOrderDelivery::with(['order.quotation.customer', 'item.inquiry'])
            ->where('status', 'PLANNED')
            ->whereHas('order', fn ($q) => $q->where('doc_status', 'CONFIRMED'))
            ->whereNotIn('id_delivery', $alreadyGeneratedIds)
            ->when($this->search !== '', function ($q) {
                $term = $this->search;
                $q->whereHas('order', fn ($o) => $o->where('co_no', 'like', "%{$term}%")
                    ->orWhereHas('quotation.customer', fn ($c) => $c->where('customer_name', 'like', "%{$term}%")));
            })
            ->orderBy('ship_date')
            ->limit(200)
            ->get();
    }

    #[Computed]
    public function statEligible(): int
    {
        return $this->eligibleDeliveries->count();
    }

    public function generate(int $deliveryId)
    {
        $delivery = CoOrderDelivery::findOrFail($deliveryId);

        try {
            $wos = app(WoGenerationService::class)->generateFromDelivery($delivery);
        } catch (\RuntimeException $e) {
            $this->dispatch('gen-toast', message: $e->getMessage());

            return;
        }

        if ($wos->count() === 1) {
            return $this->redirect(route('production.wo.entry', ['wo' => $wos->first()->wo_id]));
        }

        return $this->redirect(route('production.wo.list'));
    }
};
?>

<div x-data="{ toastMsg: '', toastShow: false, toastTimer: null }"
    x-on:gen-toast.window="toastMsg = $event.detail.message; toastShow = true; clearTimeout(toastTimer); toastTimer = setTimeout(() => toastShow = false, 3000)">
    <div class="max-w-[1200px] mx-auto my-5 px-4">
        <x-grid.stat-row :count="1">
            <x-grid.stat-box tone="total" label="Siap Digenerate" :value="$this->statEligible" />
        </x-grid.stat-row>

        <div class="bg-white border border-[#d9dfe8] rounded-[10px] overflow-hidden">
            <x-grid.toolbar>
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-sm">🔍</span>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari no. CO / customer…"
                        class="w-64 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
                </div>
                <span class="text-[11px] text-muted italic">Dari Plan Delivery (co/delivery-plan) yang CONFIRMED & belum punya WO — 1 produk multi-part bisa hasilkan beberapa WO sekaligus.</span>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full border-collapse text-[12.5px]">
                    <thead>
                        <tr>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">CO / Customer</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5]">Produk</th>
                            <th class="text-left px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] w-[110px]">Tgl Kirim</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] w-[100px]">Qty Kirim</th>
                            <th class="text-right px-3 py-2 bg-[#fafbfd] text-[#667] text-[11px] uppercase tracking-wide border-b border-[#eef1f5] w-[130px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->eligibleDeliveries as $d)
                            <tr wire:key="delivery-{{ $d->id_delivery }}">
                                <x-grid.identity-cell :id="$d->id_delivery" :name="$d->order?->quotation?->customer?->customer_name ?? 'GENERAL'" :code="$d->order?->co_no" />
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $d->item?->description ?? '—' }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">{{ $d->ship_date?->translatedFormat('d M Y') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right font-semibold">{{ number_format((float) $d->ship_qty, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-right">
                                    <button type="button" wire:click="generate({{ $d->id_delivery }})" wire:confirm="Generate WO dari baris ini? BOM/BOP diambil dari Inquiry, tak bisa dibatalkan (bukan dihapus, WO tetap tercatat)."
                                        class="px-3 py-1.5 rounded text-[11px] font-semibold bg-[#16a34a] text-white cursor-pointer">↗ Generate WO</button>
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="5" message="Tidak ada Plan Delivery yang siap digenerate." />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>
    </div>

    <div x-show="toastShow" x-transition x-cloak
        class="fixed bottom-5 left-1/2 -translate-x-1/2 bg-[#1f2328] text-white text-xs font-medium px-4 py-2.5 rounded-md shadow-lg z-[200]"
        x-text="toastMsg"></div>
</div>
