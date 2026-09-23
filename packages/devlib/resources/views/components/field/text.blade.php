<div class="dl-field__box {{ $icon ? 'has-icon' : '' }}">
    @if ($icon)<i class="fa-solid {{ $icon }} dl-field__icon"></i>@endif
    @if ($prefix)<span class="dl-field__affix">{{ $prefix }}</span>@endif
    @if ($multiline)
        <textarea id="{{ $uid }}" name="{{ $name }}" rows="1" class="dl-input" data-dl-autogrow placeholder="{{ $placeholder }}" @if ($maxlength) maxlength="{{ $maxlength }}" @endif @disabled($disabled) @readonly($readonly)>{{ $value }}</textarea>
    @else
        <input id="{{ $uid }}" name="{{ $name }}" type="{{ $type }}" class="dl-input {{ $code ? 'dl-input--code' : '' }}" value="{{ $value }}" placeholder="{{ $placeholder }}" @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif @if ($maxlength) maxlength="{{ $maxlength }}" @endif @disabled($disabled) @readonly($readonly)>
    @endif
    @if ($suffix)<span class="dl-field__affix dl-field__affix--end">{{ $suffix }}</span>@endif
    @if ($showCase())
        <button type="button" class="dl-field__trigger dl-case" data-dl-case title="Ganti huruf: kecil / Kapital Tiap Kata / BESAR">aa</button>
    @endif
</div>
@if ($counter)<div class="dl-field__counter" data-dl-counter data-max="{{ $maxlength }}"></div>@endif
