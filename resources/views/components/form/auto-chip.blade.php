{{--
    Penanda field yang terisi otomatis dari sumber lain (mis. Quotation dari
    inquiry/customer). state: auto | override | null (tak tampil).
    field diisi -> chip "override" bisa diklik = wire:click resetAuto('field').
    Tanpa field = hanya legenda.
--}}
@props(['state' => null, 'field' => null])
@if ($state === 'auto')
    <span {{ $attributes }}><x-ui.badge tone="info" size="xs" pill>{{ t('auto') }}</x-ui.badge></span>
@elseif ($state === 'override')
    @if ($field)
        <button type="button" {{ $attributes->merge(['class' => 'cursor-pointer bg-transparent border-0 p-0']) }} wire:click="resetAuto('{{ $field }}')" title="{{ t('Restore the automatic value') }}">
            <x-ui.badge tone="warn" size="xs" pill icon="fa-rotate-left">{{ t('override') }}</x-ui.badge>
        </button>
    @else
        <span {{ $attributes }}><x-ui.badge tone="warn" size="xs" pill>{{ t('override') }}</x-ui.badge></span>
    @endif
@endif
