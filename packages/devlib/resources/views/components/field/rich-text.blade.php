<div class="dl-rich" data-dl-rich>
    <div class="dl-rich__bar">
        <button type="button" data-cmd="bold" title="Tebal"><b>B</b></button><button type="button" data-cmd="italic" title="Miring"><i>I</i></button><button type="button" data-cmd="underline" title="Garis bawah"><u>U</u></button>
        <button type="button" data-cmd="insertUnorderedList" title="Daftar"><i class="fa-solid fa-list-ul"></i></button><button type="button" data-cmd="insertOrderedList" title="Daftar bernomor"><i class="fa-solid fa-list-ol"></i></button>
        <button type="button" data-cmd="removeFormat" title="Hapus format"><i class="fa-solid fa-eraser"></i></button>
    </div>
    <div id="{{ $uid }}" class="dl-rich__area" contenteditable="{{ $disabled || $readonly ? 'false' : 'true' }}" style="min-height:calc({{ $rows }} * 1.5em)" data-placeholder="{{ $placeholder }}">{!! $value !!}</div>
    <textarea name="{{ $name }}" hidden data-dl-rich-value>{{ $value }}</textarea>
</div>
