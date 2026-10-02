<div class="dl-upload" data-dl-upload @if ($preview) data-preview @endif>
    <input id="{{ $uid }}" type="file" name="{{ $name }}{{ $multiple ? '[]' : '' }}" class="dl-upload__input" @if ($accept) accept="{{ $accept }}" @endif @if ($multiple) multiple @endif @disabled($disabled || $readonly)>
    <div class="dl-upload__placeholder" data-dl-upload-empty><span class="dl-upload__icon">📁</span><span>{{ \Devlib\Lang::text('Drag a file') }} / <strong>{{ \Devlib\Lang::text('Choose File') }}</strong>{{ $multiple ? ' '.\Devlib\Lang::text('(more than one allowed)') : '' }}</span></div>
    {{-- value = file yang sudah tersimpan: ['url' => ..., 'name' => ...]; diganti begitu user memilih file baru. --}}
    <div class="dl-upload__files" data-dl-upload-list>@if (is_array($value) && ! empty($value['url']))<div class="dl-upload__file" data-dl-upload-saved><a href="{{ $value['url'] }}" target="_blank" rel="noopener">@if ($preview)<img class="dl-upload__thumb" src="{{ $value['url'] }}" alt="">@else<span>📄</span>@endif</a><span class="dl-upload__name">{{ $value['name'] ?? basename($value['url']) }}</span><small>{{ \Devlib\Lang::text('saved') }}</small></div>@endif</div>
</div>
