@php($opts = array_is_list($options) ? array_combine($options, $options) : $options)
<div class="dl-choices">
    @foreach ($opts as $v => $l)
        <label class="dl-choice"><input type="radio" name="{{ $name }}" value="{{ $v }}" @checked((string) $value === (string) $v) @disabled($disabled || $readonly)> {{ $l }}</label>
    @endforeach
    @if ($readonly && ! $disabled && $value !== null)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
</div>
