@props(['label', 'value', 'colspan'])
<tr class="bg-[#f8fafc]">
    <td colspan="{{ $colspan }}" class="px-3 py-1.5 text-[11px] font-bold text-navy border-b border-[#eef1f5]">
        {{ $label }}: {{ $value }}
    </td>
</tr>
