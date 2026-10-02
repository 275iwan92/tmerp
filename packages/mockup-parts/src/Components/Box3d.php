<?php

namespace MockupParts\Components;

use Devlib\Components\DevlibComponent;

// Preview 3D kotak dari ukuran jadi (P x L x T cm) + artwork: muka depan/belakang/samping/atas diambil dari
// potongan gambar dieline (fraksi x0,x1,y0,y1). Bawaan fraksi = tata letak dieline Proof Digital (panel utama
// kiri, panel samping kanan, sirip atas). Geser untuk memutar (JS Box3d).
class Box3d extends DevlibComponent
{
    public const FACES = ['front' => [.045, .734, .204, .79], 'side' => [.734, .981, .204, .77], 'top' => [.045, .734, 0, .2]];

    public function __construct(
        public array $dims = [],
        public ?string $front = null,
        public ?string $back = null,
        public array $faces = self::FACES,
        public int $scale = 5,
    ) {}

    public function tag(): string
    {
        return 'x-mp::box3d';
    }

    public function jsClass(): ?string
    {
        return 'Box3d';
    }

    public function px(string $k): int
    {
        return (int) round(((float) ($this->dims[$k] ?? 10)) * $this->scale);
    }

    // background-size/position supaya potongan [x0,x1,y0,y1] gambar mengisi satu muka.
    public function face(?string $url, string $key): string
    {
        if (! $url) {
            return '';
        }
        [$x0, $x1, $y0, $y1] = $this->faces[$key];
        $w = $x1 - $x0;
        $h = $y1 - $y0;

        return sprintf("background-image:url('%s');background-size:%.2f%% %.2f%%;background-position:%.2f%% %.2f%%", e($url), 100 / $w, 100 / $h,
            $w < 1 ? $x0 / (1 - $w) * 100 : 0, $h < 1 ? $y0 / (1 - $h) * 100 : 0);
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('mp-box3d') }} {!! $dl() !!} aria-label="{{ \Devlib\Lang::text('3D preview, drag to rotate') }}">
                <div class="mp-box3d__cube" style="--p:{{ $px('p') }}px;--l:{{ $px('l') }}px;--t:{{ $px('t') }}px">
                    <div class="mp-box3d__f is-front" style="{{ $face($front, 'front') }}"></div>
                    <div class="mp-box3d__f is-back" style="{{ $face($back ?? $front, 'front') }}"></div>
                    <div class="mp-box3d__f is-right" style="{{ $face($front, 'side') }}"></div>
                    <div class="mp-box3d__f is-left" style="{{ $face($back ?? $front, 'side') }}"></div>
                    <div class="mp-box3d__f is-top" style="{{ $face($front, 'top') }}"></div>
                    <div class="mp-box3d__f is-bottom"></div>
                </div>
            </div>
            BLADE;
    }
}
