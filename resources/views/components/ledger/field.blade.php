@props(['label', 'name' => null, 'full' => false, 'hint' => null])
<div @class(['lg-field', 'full' => $full])>
    <label>{{ $label }}</label>
    {{ $slot }}
    @if ($hint)
        <span class="hint">{{ $hint }}</span>
    @endif
    @if ($name)
        @error($name)
            <span class="err">{{ $message }}</span>
        @enderror
    @endif
</div>
