<div class="dl-rich" data-dl-rich>
    <div class="dl-rich__bar">
        <button type="button" data-cmd="bold" title="{{ \Devlib\Lang::text('Bold') }}"><b>B</b></button><button type="button" data-cmd="italic" title="{{ \Devlib\Lang::text('Italic') }}"><i>I</i></button><button type="button" data-cmd="underline" title="{{ \Devlib\Lang::text('Underline') }}"><u>U</u></button>
        <button type="button" data-cmd="insertUnorderedList" title="{{ \Devlib\Lang::text('Bulleted list') }}"><i class="fa-solid fa-list-ul"></i></button><button type="button" data-cmd="insertOrderedList" title="{{ \Devlib\Lang::text('Numbered list') }}"><i class="fa-solid fa-list-ol"></i></button>
        <button type="button" data-cmd="removeFormat" title="{{ \Devlib\Lang::text('Clear formatting') }}"><i class="fa-solid fa-eraser"></i></button>
    </div>
    <div id="{{ $uid }}" class="dl-rich__area" contenteditable="{{ $disabled || $readonly ? 'false' : 'true' }}" style="min-height:calc({{ $rows }} * 1.5em)" data-placeholder="{{ $placeholder }}">{!! $value !!}</div>
    <textarea name="{{ $name }}" hidden data-dl-rich-value>{{ $value }}</textarea>
</div>
