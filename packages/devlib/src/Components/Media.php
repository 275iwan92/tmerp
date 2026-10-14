<?php

/*
 * REMARK
 * Tujuan       : Gambar atau frame dokumen (img / iframe) sebagai objek, supaya layar tidak menulis tag media mentah.
 * Sumber hulu  : URL berkas dari layar (mis. peta alur, berkas sumber penawaran supplier).
 * Input        : src, alt (teks gambar / judul frame), as = img (bawaan) | frame | video; kelas dan atribut lain diteruskan.
 * Output       : <img src alt>, <iframe src title>, atau <video controls src>; gaya dari kelas yang diteruskan (mis. mp-file-preview, mp-flowmap).
 * Dipakai oleh : screens/help (Application's Flow), screens/purchase-quotation (pratinjau berkas sumber).
 * Status       : Aktif.
 */

namespace Devlib\Components;

class Media extends DevlibComponent
{
    public function __construct(
        public string $src = '',
        public string $alt = '',
        public string $as = 'img',
    ) {}

    public function tag(): string
    {
        return 'x-dl::media';
    }

    public function render()
    {
        return <<<'BLADE'
            @if ($as === 'video')
                <video class="dl-media-video" {{ $attributes }} src="{{ $src }}" title="{{ $alt }}" controls preload="metadata"></video>
            @elseif ($as === 'frame')
                <iframe {{ $attributes }} src="{{ $src }}" title="{{ $alt }}"></iframe>
            @else
                <img {{ $attributes }} src="{{ $src }}" alt="{{ $alt }}">
            @endif
            BLADE;
    }
}
