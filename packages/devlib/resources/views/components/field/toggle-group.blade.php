@php($opts = array_is_list($options) ? array_combine($options, $options) : $options)
<div class="dl-segment" data-dl-segment>
    <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-dl-value>
    @foreach ($opts as $v => $l)
        <button type="button" class="dl-segment__opt {{ (string) $value === (string) $v ? 'is-active' : '' }}" data-value="{{ $v }}" @disabled($disabled || $readonly)>{{ $l }}</button>
    @endforeach
</div>
