<?php

namespace Devlib\Components;

use Devlib\Theme;

// Pemilih tampilan: 12 tema (keluarga x corak) + density + skala huruf.
// Pilihan disimpan per pengguna (di DevLib: browser; di tmerp nanti: sec_user).
class ThemePicker extends DevlibComponent
{
    public function tag(): string
    {
        return 'x-dl::theme-picker';
    }

    public function source(): string
    {
        return 'Devlib\\Theme::ALL (= tm2sys.sys_theme)';
    }

    public function grid(): array
    {
        $g = [];
        foreach (Theme::ALL as $code => [$name, $family, $hue, $primary, $sidebar]) {
            $g[$family][$hue] = compact('code', 'name', 'primary', 'sidebar');
        }

        return $g;
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-themepick') }} {!! $dl() !!} data-dl-themepick>
                <div class="dl-themepick__grid">
                    <span></span>
                    @foreach (\Devlib\Theme::HUES as $hue => $hueName)<span class="dl-themepick__head">{{ $hueName }}</span>@endforeach
                    @foreach ($grid() as $family => $hues)
                        <span class="dl-themepick__head">{{ \Devlib\Theme::FAMILIES[$family] }}</span>
                        @foreach (\Devlib\Theme::HUES as $hue => $hueName)
                            @php($t = $hues[$hue])
                            <button type="button" class="dl-themepick__opt" data-theme="{{ $t['code'] }}" title="{{ $t['name'] }}" style="--a:{{ $t['primary'] }};--s:{{ $t['sidebar'] }}"><span></span><span></span></button>
                        @endforeach
                    @endforeach
                </div>
                <div class="dl-themepick__row"><b>{{ \Devlib\Lang::text('Density') }}</b>
                    @foreach (['compact' => 'Compact', 'normal' => 'Normal', 'comfortable' => 'Comfortable'] as $k => $l)<button type="button" class="dl-chipbtn" data-density="{{ $k }}">{{ \Devlib\Lang::text($l) }}</button>@endforeach
                </div>
                <div class="dl-themepick__row"><b>{{ \Devlib\Lang::text('Font size') }}</b>
                    @foreach (['0.9' => '90%', '1' => '100%', '1.1' => '110%', '1.2' => '120%'] as $k => $l)<button type="button" class="dl-chipbtn" data-scale="{{ $k }}">{{ $l }}</button>@endforeach
                </div>
            </div>
            BLADE;
    }
}
