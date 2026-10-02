<?php

use App\Models\Pur\PvLine;
use App\Models\Pur\PvReceipt;
use App\Services\Pur\PvBuilderService;
use App\Services\Pur\PvPostingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Goods Receipt (SPB) — TELASIH MAKMUR ERP')] class extends Component
{
    public int $pvId;

    public bool $addPickerOpen = false;

    public array $checkedLineIds = [];

    public string $postMessage = '';

    public string $journalWarning = '';

    public function mount(int $pv): void
    {
        $this->pvId = $pv;
    }

    #[Computed]
    public function pv(): PvReceipt
    {
        return PvReceipt::with('lines')->findOrFail($this->pvId);
    }

    #[Computed]
    public function supplier()
    {
        return DB::connection('tm2mst')->table('sup_supplier')->where('id_supplier', $this->pv->i_supplier_id)->first();
    }

    #[Computed]
    public function blockers(): array
    {
        return PvPostingService::blockers($this->pv);
    }

    #[Computed]
    public function openLinesForAdd()
    {
        return PvBuilderService::openLinesForSupplier($this->pv->i_supplier_id);
    }

    public function updateHeader(string $field, $value): void
    {
        if (! in_array($this->pv->status, ['DRAFT', 'QC_PENDING'], true)) {
            return;
        }

        $allowed = ['i_doc_date', 'i_supplier_do_no', 'i_vehicle_no', 'i_notes'];

        if (! in_array($field, $allowed, true)) {
            return;
        }

        $this->pv->update([$field => $value === '' ? null : $value, 'updated_by' => Auth::user()->login]);
        unset($this->pv);
    }

    public function updateLine(int $lineId, string $field, $value): void
    {
        if (! in_array($this->pv->status, ['DRAFT', 'QC_PENDING'], true)) {
            return;
        }

        $allowed = ['i_qty_purchase', 'i_qty_stock', 'i_lot_no', 'i_location_code', 'i_supplier_do_no', 'is_conv_manual'];

        if (! in_array($field, $allowed, true)) {
            return;
        }

        $line = PvLine::where('pv_id', $this->pvId)->findOrFail($lineId);
        $line->{$field} = $value === '' ? null : $value;

        if (in_array($field, ['i_qty_purchase', 'i_qty_stock'], true)) {
            $line->c_conv_used = (float) $line->i_qty_purchase > 0 ? (float) $line->i_qty_stock / (float) $line->i_qty_purchase : null;
            $poLine = $line->poLine;
            $outstandingBefore = $poLine ? ((float) $poLine->i_qty - (float) $poLine->c_qty_received) : 0;
            $line->recalcVariance($outstandingBefore);
        }

        $line->updated_by = Auth::user()->login;
        $line->save();

        unset($this->pv, $this->blockers);
    }

    public function markQtyDone(int $lineId): void
    {
        $line = PvLine::where('pv_id', $this->pvId)->findOrFail($lineId);
        $line->update(['qc_qty_status' => 'DONE', 'updated_by' => Auth::user()->login]);

        if ($this->pv->status === 'DRAFT') {
            $this->pv->update(['status' => 'QC_PENDING']);
        }

        unset($this->pv, $this->blockers);
    }

    public function setQuality(int $lineId, string $value): void
    {
        $line = PvLine::where('pv_id', $this->pvId)->findOrFail($lineId);
        $qtyStock = (float) $line->i_qty_stock;

        $update = ['qc_quality_status' => $value, 'qc_by' => Auth::user()->login, 'qc_at' => now(), 'updated_by' => Auth::user()->login];

        if ($value === 'PASS') {
            $update['c_qty_accepted'] = $qtyStock;
            $update['c_qty_rejected'] = 0;
        } elseif ($value === 'FAIL') {
            $update['c_qty_accepted'] = 0;
            $update['c_qty_rejected'] = $qtyStock;
        }
        // PARTIAL: officer isi manual via updateQcQty()

        $line->update($update);

        if ($value !== 'PENDING') {
            $line->update(['c_line_value' => (float) ($update['c_qty_accepted'] ?? $line->c_qty_accepted) * (float) $line->c_unit_price]);
        }

        unset($this->pv, $this->blockers);
    }

    public function updateQcQty(int $lineId, string $field, $value): void
    {
        if (! in_array($field, ['c_qty_accepted', 'c_qty_rejected'], true)) {
            return;
        }

        $line = PvLine::where('pv_id', $this->pvId)->findOrFail($lineId);
        $line->{$field} = $value === '' ? 0 : $value;
        $line->c_line_value = (float) $line->c_qty_accepted * (float) $line->c_unit_price;
        $line->updated_by = Auth::user()->login;
        $line->save();

        unset($this->pv, $this->blockers);
    }

    public function overrideTolerance(int $lineId, string $reason): void
    {
        if (trim($reason) === '') {
            $this->postMessage = t('Override reason is required.');

            return;
        }

        $line = PvLine::where('pv_id', $this->pvId)->findOrFail($lineId);
        $line->update([
            'tol_override_by' => Auth::user()->login,
            'tol_override_at' => now(),
            'tol_override_reason' => $reason,
            'updated_by' => Auth::user()->login,
        ]);

        $this->postMessage = '';
        unset($this->pv, $this->blockers);
    }

    public function openAddPicker(): void
    {
        $this->addPickerOpen = true;
        $this->checkedLineIds = [];
    }

    public function closeAddPicker(): void
    {
        $this->addPickerOpen = false;
    }

    public function toggleAddLine(int $lineId): void
    {
        if (in_array($lineId, $this->checkedLineIds, true)) {
            $this->checkedLineIds = array_values(array_diff($this->checkedLineIds, [$lineId]));
        } else {
            $this->checkedLineIds[] = $lineId;
        }
    }

    public function addSelectedLines(): void
    {
        if (empty($this->checkedLineIds)) {
            return;
        }

        PvBuilderService::addLines($this->pv, $this->checkedLineIds);
        $this->addPickerOpen = false;
        $this->checkedLineIds = [];
        unset($this->pv, $this->openLinesForAdd);
    }

    public function post(): void
    {
        $result = PvPostingService::post($this->pv);

        if (! $result['ok']) {
            $this->postMessage = $result['message'];

            return;
        }

        $this->postMessage = '';
        $this->journalWarning = (string) ($result['journal_warning'] ?? '');
        unset($this->pv, $this->blockers);
    }

    public function delete()
    {
        if ($this->pv->status !== 'DRAFT') {
            return;
        }

        $this->pv->delete();

        return $this->redirect(route('pur.pv.list'));
    }
};
?>

<div>
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">🚚 {{ $this->pv->label() }}</b>
        <span class="text-xs text-white/75">{{ $this->supplier?->supplier_name }} · {{ $this->pv->i_warehouse_code }}</span>
        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-[#e2e0ff] text-[#4b3fa8]">🔒 {{ t('Side B — no prices on this screen except the value panel') }}</span>
        @if (($this->pv->c_po_count ?? 0) > 1)
            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-[#fff1f2] text-[#9f1239]">{{ $this->pv->c_po_count }} PO</span>
        @endif
        <span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full
            {{ match ($this->pv->status) {
                'DRAFT' => 'bg-[#eef1f5] text-[#4b5568]',
                'QC_PENDING' => 'bg-[#fff5e0] text-[#8a5a00]',
                'POSTED' => 'bg-[#eaf7ee] text-[#1f8a4c]',
                'CANCELLED' => 'bg-[#fbeaea] text-[#a3241f]',
                default => 'bg-[#eef1f5] text-[#4b5568]',
            } }}">{{ $this->pv->status }}</span>
        <x-back-button :fallback="route('pur.pv.list')" title="{{ t('Back to Receipt List') }}" class="ml-auto" />
    </header>

    <div class="max-w-[1500px] mx-auto my-5 px-4">
        {{-- HEADER --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-4 grid gap-3">
            <x-form.date-picker label="{{ t('Receive Date') }}" value="{{ $this->pv->i_doc_date?->format('Y-m-d') }}" wire:change="updateHeader('i_doc_date', $event.target.value)" />
            <x-form.input label="{{ t('Main Delivery Note') }}" value="{{ $this->pv->i_supplier_do_no }}" wire:change="updateHeader('i_supplier_do_no', $event.target.value)" />
            <x-form.input label="{{ t('Vehicle No.') }}" value="{{ $this->pv->i_vehicle_no }}" wire:change="updateHeader('i_vehicle_no', $event.target.value)" />
            <div>
                <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ t('Covered PO') }}</label>
                <div class="flex items-center gap-1.5 flex-wrap pt-1.5">
                    @foreach (explode(', ', (string) $this->pv->c_po_doc_list) as $poNo)
                        @if ($poNo !== '')
                            <span class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded bg-[#ede9fe] text-[#5b21b6]">{{ $poNo }}</span>
                        @endif
                    @endforeach
                    @if ($this->pv->status !== 'POSTED')
                        <button type="button" wire:click="openAddPicker" class="text-[10.5px] font-bold text-accent">＋ {{ t('Other PO') }}</button>
                    @endif
                </div>
            </div>
            <div class="col-span-4">
                <x-form.input label="{{ t('Notes') }}" value="{{ $this->pv->i_notes }}" wire:change="updateHeader('i_notes', $event.target.value)" />
            </div>
        </div>

        @if ($postMessage)
            <div class="bg-[#fff5e0] border border-[#e0c26a] text-[#8a5a00] text-xs rounded-md px-4 py-2.5 mb-4">⚠ {{ $postMessage }}</div>
        @endif
        @if ($journalWarning)
            <div class="bg-[#f1ecfc] border border-[#ddd0f7] text-[#6b4fa0] text-xs rounded-md px-4 py-2.5 mb-4">ℹ {{ $journalWarning }} {{ t('Complete the Inventory/GR-IR account in Raw Material Master or the related category.') }}</div>
        @endif

        {{-- BARIS --}}
        <div class="bg-white border border-[#d7dbe2] rounded-md mb-4 overflow-hidden">
            <div class="px-4 py-2.5 bg-[#f4f6fa] border-b border-[#d7dbe2]">
                <h3 class="text-xs text-navy uppercase font-bold">{{ t('Receipt Lines — Dual UOM (Purchase / Stock)') }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs min-w-[1400px]">
                    <thead class="text-[10px] text-muted uppercase border-b border-[#eef1f5]">
                        <tr>
                            <th class="text-left px-2 py-2">#</th>
                            <th class="text-left px-2 py-2">{{ t('PO Origin') }}</th>
                            <th class="text-left px-2 py-2">{{ t('Item') }}</th>
                            <th class="text-right px-2 py-2">{{ t('Purchase Qty') }}</th>
                            <th class="text-left px-2 py-2">{{ t('UoM') }}</th>
                            <th class="text-right px-2 py-2">{{ t('Stock Qty') }}</th>
                            <th class="text-left px-2 py-2">{{ t('UoM') }}</th>
                            <th class="text-left px-2 py-2">{{ t('QC Quality') }}</th>
                            <th class="text-right px-2 py-2">{{ t('Accepted') }}</th>
                            <th class="text-right px-2 py-2">{{ t('Rejected') }}</th>
                            <th class="text-left px-2 py-2">{{ t('Lot') }}</th>
                            <th class="text-left px-2 py-2">{{ t('Location') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eef1f5]">
                        @foreach ($this->pv->lines as $line)
                            <tr wire:key="pvline-{{ $line->pv_line_id }}" class="{{ $line->is_tol_exceeded && ! $line->tol_override_by ? 'bg-[#fff8ec]' : '' }}">
                                <td class="px-2 py-2 text-muted">{{ $line->line_no }}</td>
                                <td class="px-2 py-2 text-[10px] font-mono text-[#5b21b6]">{{ $line->c_po_doc_no }}<br>{{ t('line') }} {{ $line->c_po_line_no }}</td>
                                <td class="px-2 py-2">
                                    <span class="font-mono text-[10px] text-muted">{{ $line->c_item_code }}</span><br>{{ $line->i_description }}
                                    @if ($line->is_tol_exceeded)
                                        <span class="text-[9px] text-[#a3241f]">⚠ {{ number_format((float) $line->c_qty_variance_pct, 1) }}% {{ t('vs limit') }} {{ $line->c_tol_pct }}%</span>
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-right">
                                    <x-form.input type="number" step="0.01" value="{{ $line->i_qty_purchase }}" wire:change="updateLine({{ $line->pv_line_id }}, 'i_qty_purchase', $event.target.value)" class="w-20 !py-1 text-right" />
                                </td>
                                <td class="px-2 py-2">{{ $line->i_uom_purchase }}</td>
                                <td class="px-2 py-2 text-right">
                                    <x-form.input type="number" step="0.01" value="{{ $line->i_qty_stock }}" wire:change="updateLine({{ $line->pv_line_id }}, 'i_qty_stock', $event.target.value)" class="w-20 !py-1 text-right" />
                                </td>
                                <td class="px-2 py-2">{{ $line->i_uom_stock }}</td>
                                <td class="px-2 py-2">
                                    <x-form.select wire:change="setQuality({{ $line->pv_line_id }}, $event.target.value)" class="!py-1" :placeholder="false"
                                        :selected="$line->qc_quality_status" :options="['PENDING' => t('— pending —'), 'PASS' => 'PASS', 'PARTIAL' => 'PARTIAL', 'FAIL' => 'FAIL']" />
                                    @if ($line->qc_qty_status !== 'DONE')
                                        <button type="button" wire:click="markQtyDone({{ $line->pv_line_id }})" class="block text-[10px] text-accent font-semibold mt-1">{{ t('Mark QC Qty Done') }}</button>
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-right">
                                    <x-form.input type="number" step="0.01" value="{{ $line->c_qty_accepted }}" :disabled="$line->qc_quality_status !== 'PARTIAL'"
                                        wire:change="updateQcQty({{ $line->pv_line_id }}, 'c_qty_accepted', $event.target.value)" class="w-20 !py-1 text-right disabled:bg-[#f8fafc]" />
                                </td>
                                <td class="px-2 py-2 text-right">
                                    <x-form.input type="number" step="0.01" value="{{ $line->c_qty_rejected }}" :disabled="$line->qc_quality_status !== 'PARTIAL'"
                                        wire:change="updateQcQty({{ $line->pv_line_id }}, 'c_qty_rejected', $event.target.value)" class="w-20 !py-1 text-right disabled:bg-[#f8fafc]" />
                                </td>
                                <td class="px-2 py-2">
                                    <x-form.input value="{{ $line->i_lot_no }}" wire:change="updateLine({{ $line->pv_line_id }}, 'i_lot_no', $event.target.value)" class="w-24 !py-1" />
                                </td>
                                <td class="px-2 py-2">
                                    <x-form.input value="{{ $line->i_location_code }}" wire:change="updateLine({{ $line->pv_line_id }}, 'i_location_code', $event.target.value)" class="w-20 !py-1" />
                                </td>
                            </tr>
                            @if ($line->is_tol_exceeded && ! $line->tol_override_by)
                                <tr wire:key="tol-{{ $line->pv_line_id }}">
                                    <td colspan="12" class="px-2 pb-2">
                                        <div class="bg-[#fff5e0] border border-[#e0c26a] rounded-md p-2 flex items-center gap-2" x-data="{ reason: '' }">
                                            <span class="text-[11px] font-bold text-[#8a5a00]">⚠ {{ t('Tolerance exceeded — holding POST') }}</span>
                                            <input type="text" x-model="reason" placeholder="{{ t('Override reason...') }}" class="flex-1 px-2 py-1 border border-[#e0c26a] rounded text-xs">
                                            <button type="button" @click="$wire.overrideTolerance({{ $line->pv_line_id }}, reason)" class="text-[11px] font-bold text-white bg-navy px-2.5 py-1 rounded">{{ t('Override') }}</button>
                                        </div>
                                    </td>
                                </tr>
                            @elseif ($line->tol_override_by)
                                <tr wire:key="tolok-{{ $line->pv_line_id }}">
                                    <td colspan="12" class="px-2 pb-2">
                                        <div class="text-[10.5px] text-[#1f8a4c]">✔ {{ t('Tolerance overridden by') }} {{ $line->tol_override_by }} · {{ $line->tol_override_reason }}</div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($this->pv->status !== 'POSTED' && $this->pv->status !== 'CANCELLED')
            <div class="bg-white border border-[#d7dbe2] rounded-md p-4 mb-8 flex items-center gap-3">
                @if (! empty($this->blockers))
                    <span class="text-xs font-bold text-[#8a5a00] bg-[#fff5e0] px-3 py-1.5 rounded">{{ t('POST locked —') }} {{ implode(' ', $this->blockers) }}</span>
                @else
                    <span class="text-xs font-bold text-[#1f8a4c] bg-[#eaf7ee] px-3 py-1.5 rounded">✔ {{ t('All gates passed, ready to POST') }}</span>
                @endif
                <div class="ml-auto flex gap-2">
                    @if ($this->pv->status === 'DRAFT')
                        <button type="button" wire:click="delete" wire:confirm="{{ t('Delete this draft receipt?') }}" class="border border-[#d7dbe2] text-[#a3241f] text-xs font-bold px-4 py-2 rounded-md">{{ t('Delete') }}</button>
                    @endif
                    <button type="button" wire:click="post" @disabled(! empty($this->blockers)) wire:confirm="{{ t('POST to stock? The related PO will be recalculated.') }}" class="bg-navy text-white text-xs font-bold px-4 py-2 rounded-md disabled:opacity-40">📦 {{ t('POST') }}</button>
                </div>
            </div>
        @endif
    </div>

    {{-- ADD PICKER --}}
    @if ($addPickerOpen)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-6" wire:click.self="closeAddPicker">
            <div class="bg-white rounded-lg w-full max-w-3xl max-h-[80vh] flex flex-col shadow-xl">
                <div class="flex items-center justify-between px-4 py-3 border-b border-[#eef1f5]">
                    <h3 class="text-sm font-bold text-navy">{{ t('Add Lines from Another PO') }} — {{ $this->supplier?->supplier_name }}</h3>
                    <button type="button" wire:click="closeAddPicker" class="text-muted hover:text-navy cursor-pointer">✕</button>
                </div>
                <div class="flex-1 overflow-y-auto">
                    <table class="w-full text-xs border-collapse">
                        <thead>
                            <tr class="bg-[#f4f5f8]">
                                <th class="px-3 py-2"></th>
                                <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold">{{ t('PO No.') }}</th>
                                <th class="text-left px-3 py-2 text-[10.5px] text-muted uppercase font-bold">{{ t('Item') }}</th>
                                <th class="text-right px-3 py-2 text-[10.5px] text-muted uppercase font-bold">{{ t('Remaining') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->openLinesForAdd as $line)
                                <tr class="border-b border-[#eef1f5] cursor-pointer" wire:click="toggleAddLine({{ $line->po_line_id }})">
                                    <td class="px-3 py-2"><input type="checkbox" @checked(in_array($line->po_line_id, $checkedLineIds)) onclick="event.preventDefault()"></td>
                                    <td class="px-3 py-2 font-mono text-accent">{{ $line->order->doc_no }} #{{ $line->line_no }}</td>
                                    <td class="px-3 py-2">{{ $line->i_description }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $line->i_qty - (float) $line->c_qty_received, 2, ',', '.') }} {{ $line->i_uom }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-[#eef1f5] flex justify-end gap-2">
                    <button type="button" wire:click="closeAddPicker" class="text-xs font-semibold px-3 py-1.5 rounded border border-[#d7dbe2]">{{ t('Cancel') }}</button>
                    <button type="button" wire:click="addSelectedLines" @disabled(empty($checkedLineIds)) class="text-xs font-bold px-3 py-1.5 rounded bg-accent text-white disabled:opacity-40">{{ t('Add') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
