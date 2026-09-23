<div class="dl-upload" data-dl-upload @if ($preview) data-preview @endif>
    <input id="{{ $uid }}" type="file" name="{{ $name }}{{ $multiple ? '[]' : '' }}" class="dl-upload__input" @if ($accept) accept="{{ $accept }}" @endif @if ($multiple) multiple @endif @disabled($disabled || $readonly)>
    <div class="dl-upload__placeholder" data-dl-upload-empty><span class="dl-upload__icon">📁</span><span>Drag file / <strong>Pilih File</strong>{{ $multiple ? ' (boleh lebih dari satu)' : '' }}</span></div>
    <div class="dl-upload__files" data-dl-upload-list></div>
</div>
