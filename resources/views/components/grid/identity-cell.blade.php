{{--
    griduistandard.md §3 -- kolom identitas avatar+Nama+Kode. Pakai helper
    statis App\Support\Grid\Avatar (bukan method di Livewire component induk)
    krn anonymous Blade component ini scope-nya terisolasi, tak bisa akses
    $this milik parent -- gotcha yang sama didokumentasikan utk modal §10.
--}}
@props(['id', 'name', 'code' => null])
<td {{ $attributes->merge(['class' => 'px-3 py-2 border-b border-[#eef1f5]']) }}>
    <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ \App\Support\Grid\Avatar::gradient((int) $id) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ \App\Support\Grid\Avatar::initials($name ?: '?') }}</div>
        <div>
            <div class="font-semibold text-[13px]">{{ $name ?: '—' }}</div>
            @if ($code)
                <div class="text-[11px] text-muted font-mono">{{ $code }}</div>
            @endif
        </div>
    </div>
</td>
