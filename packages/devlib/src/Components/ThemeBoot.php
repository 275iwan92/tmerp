<?php

namespace Devlib\Components;

use Devlib\Theme;

// Dipasang SEKALI di <head>: CSS 12 tema (dari Devlib\Theme) + skrip kecil yang
// memasang tema/density/skala huruf tersimpan SEBELUM halaman tampil (tanpa kedip).
class ThemeBoot extends DevlibComponent
{
    public function __construct(public ?string $theme = null) {}

    public function tag(): string
    {
        return 'x-dl::theme-boot';
    }

    public function css(): string
    {
        return Theme::css();
    }

    public function fallback(): string
    {
        return $this->theme ?? config('devlib.theme', Theme::DEFAULT);
    }

    public function render()
    {
        return <<<'BLADE'
            <style data-dl-themes>{!! $css() !!}</style>
            <script>window.DL_LANG = @json(\Devlib\Lang::all()); window.DL_LANG_ACTIVE = @json(\Devlib\Locale::lang()); window.DL_NUM = @json(\Devlib\Locale::jsSpec()); window.DL_DICT = @json((object) \Devlib\Lang::jsDict()); window.DL_DICT_URL = @json(config('devlib.dictionary_register_url')); window.DL_LANG_URL = @json(config('devlib.lang_url')); window.DL_CSRF = @json(csrf_token());</script>
            <script>(function(d){var g=function(k,v){try{return localStorage.getItem(k)||v}catch(e){return v}};var q=(location.search+location.hash).match(/theme=([a-z_]+)/);d.dataset.dlTheme=q?q[1]:g('dl-theme',@json($fallback()));d.dataset.dlDensity=g('dl-density','normal');d.style.setProperty('--dl-font-scale',g('dl-font-scale','1'));})(document.documentElement);</script>
            BLADE;
    }
}
