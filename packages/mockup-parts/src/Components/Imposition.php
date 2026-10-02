<?php

namespace MockupParts\Components;

use Devlib\Components\DevlibComponent;

// Kanvas tata letak di plano (ProDev / mockup_prodev_imposition). PENAMPIL hasil ImpositionEngine
// (zona beku: angka UP & posisi sel TIDAK dihitung ulang di sini; layar memanggil mesin lalu mengoper hasilnya).
// jobs: [['code','label','type','spec' (DSL),'dims','flat' => ['w','h'],
//         'results' => [kode plano => ['up','method','efficiency','waste','cells' => [['x','y','w','h','rot','n']]]]]]
// planos: [['code','w','h']] mm. Tiap UP digambar dengan dieline asli (TMDieline). Kalkulator PCS <-> PLANO ikut UP.
class Imposition extends DevlibComponent
{
    public function __construct(
        public array $jobs = [],
        public array $planos = [],
        public float $gripper = 10,
        public float $margin = 5,
        public float $gap = 3,
        public int $qty = 1000,
        public float $spoilage = 5,
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-mp::imposition';
    }

    public function source(): string
    {
        return $this->source ?? 'App\Services\Imposition\ImpositionEngine::run() + cells()';
    }

    public function render()
    {
        return <<<'BLADE'
            @once<script src="{{ \MockupParts\MockupPartsServiceProvider::asset('tmdieline.js') }}"></script>@endonce
            <div {{ $attributes->class('dl-impo') }} {!! $dl() !!} data-dl-impo data-jobs="{{ json_encode($jobs) }}" data-planos="{{ json_encode($planos) }}" data-gripper="{{ $gripper }}" data-margin="{{ $margin }}" data-gap="{{ $gap }}">
                <aside class="dl-impo__side">
                    <p class="dl-impo__cap">{{ \Devlib\Lang::text('Job') }}</p>
                    <div class="dl-impo__jobs" data-i2-jobs></div>
                    <p class="dl-impo__cap">{{ \Devlib\Lang::text('Plano Size') }}</p>
                    <div class="dl-segment dl-impo__planos" data-i2-planos></div>
                    <dl class="dl-impo__kv" data-i2-kv></dl>
                </aside>
                <div class="dl-impo__stage">
                    <div class="dl-impo__tools">
                        <x-dl::icon-button icon="fa-magnifying-glass-minus" title="Zoom out" data-i2-zoom="-1" /><b data-i2-zoomval>100%</b><x-dl::icon-button icon="fa-magnifying-glass-plus" title="Zoom in" data-i2-zoom="1" />
                        <x-dl::button variant="secondary" size="sm" icon="fa-rotate" label="180°" data-i2-rot :title="\Devlib\Lang::text('Rotate the selected object 180° (display only)')" />
                        <x-dl::button variant="secondary" size="sm" icon="fa-left-right" label="Mirror X" data-i2-mx />
                        <x-dl::button variant="secondary" size="sm" icon="fa-up-down" label="Mirror Y" data-i2-my />
                        <label class="dl-choice"><input type="checkbox" data-i2-numbers checked> {{ \Devlib\Lang::text('UP numbers') }}</label>
                        <span class="dl-print__spacer"></span>
                        <span class="dl-impo__sel" data-i2-sel>{{ \Devlib\Lang::text('Click an object to select it') }}</span>
                        <x-dl::button variant="secondary" size="sm" icon="download" label="SVG" data-i2-svg />
                    </div>
                    <div class="dl-impo__canvas"><div class="dl-impo__paper" data-i2-paper></div></div>
                </div>
                <aside class="dl-impo__side">
                    <p class="dl-impo__cap">{{ \Devlib\Lang::text('Imposition & Yield') }}</p>
                    <div class="dl-impo__hero" data-i2-hero></div>
                    <div class="dl-impo__bar" data-i2-bar></div>
                    <p class="dl-impo__cap">PCS ↔ PLANO</p>
                    <div class="dl-segment" data-i2-calc><button type="button" class="dl-segment__opt is-active" data-value="pcs">PCS → PLANO</button><button type="button" class="dl-segment__opt" data-value="plano">PLANO → PCS</button></div>
                    <label class="dl-impo__in"><span data-i2-inlabel>{{ \Devlib\Lang::text('Qty order (pcs)') }}</span><input type="text" inputmode="numeric" value="{{ $qty }}" data-i2-qty></label>
                    <label class="dl-impo__in"><span>{{ \Devlib\Lang::text('Spoilage / inschiet (%)') }}</span><input type="text" inputmode="decimal" value="{{ $spoilage }}" data-i2-spoil></label>
                    <div class="dl-impo__result" data-i2-result></div>
                </aside>
            </div>
            BLADE;
    }
}
