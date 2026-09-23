<div class="dl-field__box {{ $icon ? 'has-icon' : '' }}">
    @if ($icon)<i class="fa-solid {{ $icon }} dl-field__icon"></i>@endif
    @if ($multiline)
        <textarea id="{{ $uid }}" name="{{ $name }}" rows="1" class="dl-input" data-dl-autogrow placeholder="{{ $placeholder }}" @disabled($disabled)>{{ $value }}</textarea>
    @else
        <input id="{{ $uid }}" name="{{ $name }}" type="{{ $type }}" class="dl-input" value="{{ $value }}" placeholder="{{ $placeholder }}" @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif @disabled($disabled)>
    @endif
    @if ($caseToggle && ! $disabled)
        <button type="button" class="dl-field__trigger dl-case" data-dl-case title="Ganti huruf: kecil / Kapital Tiap Kata / BESAR">aa</button>
    @endif
</div>
