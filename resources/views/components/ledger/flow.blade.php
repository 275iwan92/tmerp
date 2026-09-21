@props(['nodes' => []])
{{-- nodes: [[judul, keterangan, hitungan-opsional], ...] -- alur proses blueprint. --}}
<div class="lg-flow">
    <div class="lg-flowrow">
        @foreach ($nodes as $i => $n)
            @if ($i > 0)<span class="lg-arrow">→</span>@endif
            <div class="lg-node {{ ! empty($n[2]) ? 'on' : '' }}">
                <span class="sym">{{ $i + 1 }}</span>
                <b>{{ $n[0] }}</b>
                <span class="t">{{ $n[1] }}</span>
                @if (! empty($n[2]))<span class="cnt">{{ $n[2] }}</span>@endif
            </div>
        @endforeach
    </div>
</div>
