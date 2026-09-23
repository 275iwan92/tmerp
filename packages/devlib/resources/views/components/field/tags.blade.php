<div class="dl-tags" data-dl-tags data-name="{{ $name }}">
    <div class="dl-tags__box">
        @foreach ((array) $value as $t)
            <span class="dl-tags__chip">{{ $t }}<input type="hidden" name="{{ $name }}[]" value="{{ $t }}"><button type="button" data-dl-tag-x>&times;</button></span>
        @endforeach
        <input id="{{ $uid }}" type="text" class="dl-tags__input" placeholder="{{ $placeholder ?? 'Ketik lalu Enter...' }}" list="{{ $uid }}-list" @disabled($disabled || $readonly)>
    </div>
    @if ($suggestions)<datalist id="{{ $uid }}-list">@foreach ($suggestions as $s)<option value="{{ $s }}">@endforeach</datalist>@endif
</div>
