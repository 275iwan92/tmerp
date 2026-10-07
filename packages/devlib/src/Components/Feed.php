<?php

namespace Devlib\Components;

use Illuminate\Support\Str;

// Umpan gaya blog / berita (user 2026-10-07): banyak narasi dibaca berurutan di satu halaman, tanpa membuka satu per satu.
// $posts = [['title', 'meta' (penulis · jenis · tanggal), 'body' (markdown), 'badge', 'tone', 'url', 'pinned'], ...]; yang
// 3 kolom (2 / 1 di layar sempit), cuplikan + Read more / Show less. Yang disematkan tampil dengan tanda pin. Isi markdown ditampilkan penuh (judul, daftar, tabel); HTML mentah di isi di-escape.
class Feed extends DevlibComponent
{
    public function __construct(
        public array $posts = [],
        public string $empty = 'Nothing to read yet.',
    ) {}

    public function tag(): string
    {
        return 'x-dl::feed';
    }

    public function html(?string $markdown): string
    {
        return Str::markdown((string) $markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-feed') }} {!! $dl() !!} data-dl-feed>
                @forelse ($posts as $p)
                    <article class="dl-feed__post">
                        <header>
                            <h3>@if (! empty($p['pinned']))<i class="fa-solid fa-thumbtack"></i> @endif{{ $p['title'] ?? '' }}</h3>
                            @if (! empty($p['badge']))<x-dl::badge :label="$p['badge']" :tone="$p['tone'] ?? 'neutral'" />@endif
                        </header>
                        @if (! empty($p['meta']))<small class="dl-feed__meta">{{ $p['meta'] }}</small>@endif
                        <div class="dl-prose dl-feed__body">{!! $html($p['body'] ?? '') !!}</div>
                        <button type="button" class="dl-btn dl-btn--secondary dl-btn--sm dl-feed__more" data-feed-toggle data-more="{{ \Devlib\Lang::text('Read more') }}" data-less="{{ \Devlib\Lang::text('Show less') }}">{{ \Devlib\Lang::text('Read more') }}</button>
                        @if (! empty($p['url']))<a class="dl-btn dl-btn--secondary dl-btn--sm" href="{{ $p['url'] }}" target="_blank" rel="noopener">{{ \Devlib\Lang::text('Open Link') }}</a>@endif
                    </article>
                @empty
                    <p class="dl-feed__empty">{{ \Devlib\Lang::text($empty) }}</p>
                @endforelse
            </div>
            BLADE;
    }
}
