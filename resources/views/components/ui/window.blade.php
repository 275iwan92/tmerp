@php use App\Support\Localization\Translator; @endphp
<div x-data="tmWindow({ open: @js($open), id: @js($id), w: {{ $width }}, h: {{ $height }} })"
    x-show="isOpen" x-cloak
    {{ $attributes->merge(['class' => $classes()]) }}
    :class="{ 'is-min': min, 'is-max': max }" :style="box"
    @pointerdown="focus()" @keydown.escape.stop="close()"
    role="dialog" aria-label="{{ $title }}">
    <div class="tm-win-head" @pointerdown="dragStart($event)" @dblclick="toggleMax()">
        @if ($icon)<i class="{{ $icon }}"></i>@endif
        <span class="tm-win-title">{{ $title }}</span>
        <div class="tm-win-ctl">
            <button type="button" @click="toggleMin()" :title="min ? @js(Translator::t('shared_win_restore', 'Pulihkan')) : @js(Translator::t('shared_win_minimize', 'Perkecil'))">
                <i class="fa-solid" :class="min ? 'fa-window-restore' : 'fa-window-minimize'"></i>
            </button>
            <button type="button" @click="toggleMax()" :title="max ? @js(Translator::t('shared_win_restore', 'Pulihkan')) : @js(Translator::t('shared_win_maximize', 'Perbesar'))">
                <i class="fa-regular" :class="max ? 'fa-window-restore' : 'fa-window-maximize'"></i>
            </button>
            <button type="button" class="tm-win-x" @click="close()" title="{{ Translator::t('shared_btn_close', 'Tutup') }}"><i class="fa-solid fa-xmark"></i></button>
        </div>
    </div>
    <div class="tm-win-body">{{ $slot }}</div>
</div>
