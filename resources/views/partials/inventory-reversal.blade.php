{{-- Cancel (pembalik) + Duplicate untuk dokumen stok POSTED; logikanya App\Livewire\Concerns\HasStockReversal. --}}
@php($rv = $this->reversalState())
@if ($editingId && $readOnly && ($rv['can'] || $rv['cancelled'] || $rv['reverses'] || $rv['refusal']))
    <div class="flex flex-col gap-2">
        @if ($rv['cancelled'])
            <div class="text-xs px-3 py-2.5 rounded-md bg-[#fdecec] border border-[#f5c2c2] text-[#c0392b]">
                <b>{{ t('Cancelled') }}</b> · {{ raw($rv['reason']) }}@if ($rv['reversal']) · {{ t('reversed by') }} <b>{{ raw($rv['reversal']['no']) }}</b>@endif · {{ t('correct document: use Duplicate') }}
            </div>
        @elseif ($rv['reverses'])
            <div class="text-xs px-3 py-2.5 rounded-md bg-[#f1ecfb] border border-[#d9cdf3] text-[#6b3fb3]">{{ t('Reversal of') }} <b>{{ raw($rv['reverses']['no']) }}</b></div>
        @elseif (! $rv['can'] && $rv['refusal'])
            <div class="text-xs text-muted">{{ raw($rv['refusal']) }}</div>
        @endif

        <div class="flex gap-2 justify-end">
            @if ($rv['can'])
                <button type="button" wire:click="openReverse" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e11d48] text-white cursor-pointer">{{ t('Cancel') }}</button>
            @endif
            @if ($rv['can'] || $rv['cancelled'])
                <button type="button" wire:click="duplicateDoc" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">{{ t('Duplicate') }}</button>
            @endif
        </div>

        @if ($reverseOpen && $rv['can'])
            <div class="border border-[#d9dfe8] rounded-md p-4 flex flex-col gap-3 bg-white max-w-[640px]">
                <div class="text-sm font-semibold">{{ t('Cancel document') }} {{ raw($doc_no) }}</div>
                <x-form.textarea label="{{ t('Reason *') }}" wire:model="reverseReason" rows="2" />
                @error('reverseReason') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                <ul class="text-xs text-muted list-disc pl-5 flex flex-col gap-1">
                    <li>{{ t('The system posts a reversal document in the opposite direction through the normal posting path; the Stock Card stays continuous.') }}</li>
                    <li>{{ t('This document becomes Cancelled and keeps pointing to its reversal. A usage journal is reversed too.') }}</li>
                    <li>{{ t('Not affected: Stock Adjustment and Stock Loss & Gain.') }}</li>
                    <li>{{ t('Refused when the date falls in a closed inventory period, or when the reversal would make a balance negative.') }}</li>
                </ul>
                <div class="flex gap-2 justify-end">
                    <button type="button" wire:click="closeReverse" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e6eaf0] text-[#333] cursor-pointer">{{ t('Close') }}</button>
                    <button type="button" wire:click="reverseDoc" class="px-4 py-2 rounded-md text-xs font-semibold bg-[#e11d48] text-white cursor-pointer">{{ t('Cancel Document') }}</button>
                </div>
            </div>
        @endif
    </div>
@endif
