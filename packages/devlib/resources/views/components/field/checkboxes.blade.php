@php
    $opts = array_is_list($options) ? array_combine($options, $options) : $options;
    $vals = array_map('strval', (array) $value);
@endphp
<div class="dl-choices">
    @foreach ($opts as $v => $l)
        <label class="dl-choice"><input type="checkbox" name="{{ $name }}[]" value="{{ $v }}" @checked(in_array((string) $v, $vals, true)) @disabled($disabled || $readonly)> {{ $l }}</label>
    @endforeach
    @if ($readonly && ! $disabled)@foreach ($vals as $v)<input type="hidden" name="{{ $name }}[]" value="{{ $v }}">@endforeach @endif
</div>
