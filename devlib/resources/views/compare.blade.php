@extends('layout', ['title' => $mockup['title']])
@section('body')
<div class="dl-compare" data-dl="halaman banding · khusus situs DevLib" data-dl-class="bukan komponen library" data-dl-src="docs/mockups/{{ $mockup['file'] }}">
    <div class="dl-compare__bar">
        <b>{{ $mockup['title'] }}</b>
        <span class="dl-compare__file">{{ $mockup['file'] }}</span>
        @if ($component)
            <span class="dl-compare__file">⇄ {{ $component['tag'] }}</span>
        @endif
        <span class="dl-compare__spacer"></span>
        @if ($component && $component['preview'] === 'preview.login')
            @foreach ($models as $key => $label)
                <button type="button" class="dl-chipbtn {{ $key === 'split' ? 'is-active' : '' }}" data-model="{{ $key }}">{{ $label }}</button>
            @endforeach
            <span class="dl-compare__file">|</span>
        @endif
        <button type="button" class="dl-chipbtn is-active" data-view="side">Berdampingan</button>
        <button type="button" class="dl-chipbtn" data-view="mockup">Mockup saja</button>
        <a class="dl-chipbtn" href="{{ route('mockup.file', ['file' => $mockup['file']]) }}" target="_blank">Tab baru ↗</a>
    </div>
    <div class="dl-compare__body">
        <div class="dl-compare__col">
            <div class="dl-compare__cap">Mockup asli (dari Anda)</div>
            @if ($mockup['type'] === 'html')
                <iframe src="{{ route('mockup.file', ['file' => $mockup['file']]) }}"></iframe>
            @else
                <div class="dl-compare__img"><img src="{{ route('mockup.file', ['file' => $mockup['file']]) }}" alt=""></div>
            @endif
        </div>
        <div class="dl-compare__col dl-compare__col--lib">
            <div class="dl-compare__cap">Komponen library DevLib {{ $component ? '— '.$component['tag'] : '' }}</div>
            @if ($component)
                <iframe data-lib-frame src="{{ route($component['preview']) }}"></iframe>
            @else
                <div class="dl-compare__empty"><div>Komponen untuk mockup ini <b>belum dibangun</b>.<br>Nanti tampil di sini untuk dibandingkan dengan mockup di kiri.</div></div>
            @endif
        </div>
    </div>
</div>
<script>
document.querySelectorAll('[data-view]').forEach(b => b.addEventListener('click', () => {
    document.querySelectorAll('[data-view]').forEach(x => x.classList.toggle('is-active', x === b));
    document.querySelector('.dl-compare').classList.toggle('is-mockup-only', b.dataset.view === 'mockup');
}));
document.querySelectorAll('[data-model]').forEach(b => b.addEventListener('click', () => {
    document.querySelectorAll('[data-model]').forEach(x => x.classList.toggle('is-active', x === b));
    document.querySelector('[data-lib-frame]').src = @json(route('preview.login')) + '?model=' + b.dataset.model;
}));
</script>
@endsection
