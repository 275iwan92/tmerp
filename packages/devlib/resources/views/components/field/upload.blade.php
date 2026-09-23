<div class="dl-upload" data-dl-upload>
    <input id="{{ $uid }}" type="file" name="{{ $name }}" class="dl-upload__input" @if ($accept) accept="{{ $accept }}" @endif @disabled($disabled)>
    <div class="dl-upload__placeholder" data-dl-upload-empty><span class="dl-upload__icon">📁</span><span>Drag file / <strong>Pilih File</strong></span></div>
    <div class="dl-upload__file" data-dl-upload-file><span>📄</span><span class="dl-upload__name" data-dl-upload-name></span><button type="button" data-dl-upload-remove title="Hapus">✕</button></div>
</div>
