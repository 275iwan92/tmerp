<div {{ $attributes->class(['dl-field', 'dl-field--'.$control(), 'dl-field--'.$size, 'is-invalid' => $error, 'is-readonly' => $readonly]) }} {!! $dl() !!} data-dl-field="{{ $name }}">
    @if ($label !== '')
        <div class="dl-field__labelrow">
            <label class="dl-field__label" for="{{ $uid }}" @if ($i18n) data-i18n="{{ $i18n }}" @endif>{{ $label }}</label>
            @if ($required)<span class="dl-field__req">*</span>@endif
            @if ($tooltip)
                <span class="dl-tip"><span class="dl-tip__icon">i</span><span class="dl-tip__text">{{ $tooltip }}</span></span>
            @endif
        </div>
    @endif
    @include('devlib::components.field.'.$control())
    <div class="dl-field__error" data-dl-field-error>{{ $error }}</div>
    @if ($hint !== null)
        <div class="dl-field__hint" data-dl-field-hint>{{ $hint }}</div>
    @endif
</div>
