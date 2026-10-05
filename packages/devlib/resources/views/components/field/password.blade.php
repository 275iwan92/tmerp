<div class="dl-field__box {{ $icon ? 'has-icon' : '' }}">
    @if ($icon)<i class="fa-solid {{ $icon }} dl-field__icon"></i>@endif
    <input id="{{ $uid }}" name="{{ $name }}" type="password" class="dl-input" value="{{ $value }}" placeholder="{{ $placeholder }}" autocomplete="{{ $autocomplete }}" @disabled($disabled) @readonly($readonly)>
    <button type="button" class="dl-field__trigger" data-dl-eye title="{{ \Devlib\Lang::text('Show / hide password') }}"><i class="fa-solid fa-eye"></i></button>
</div>
