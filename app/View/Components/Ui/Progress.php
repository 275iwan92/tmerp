<?php

namespace App\View\Components\Ui;

// Bar persentase kecil. marker = garis penanda (mis. target) dalam persen.
class Progress extends UiComponent
{
    public float $pct;

    public ?float $markerPct;

    public function __construct(
        float|int|null $value = 0,
        string $tone = 'info',
        float|int|null $marker = null,
        public ?string $markerTitle = null,
        public float $min = 0,
    ) {
        $this->variant = $tone;
        $this->pct = max($min, min(100, (float) $value));
        $this->markerPct = $marker === null ? null : max(1, min(99, (float) $marker));
    }

    protected function base(): string
    {
        return 'h-1.5 rounded-full bg-line-soft overflow-hidden relative';
    }

    public function fillClass(): string
    {
        return 'h-full rounded-full '.Tone::solid($this->variant);
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->merge(['class' => $classes()]) }}>
                <div class="{{ $fillClass() }}" style="width:{{ $pct }}%"></div>
                @if ($markerPct !== null)
                    <span class="absolute top-0 h-full w-[2px] bg-ink" style="left:{{ $markerPct }}%" @if ($markerTitle) title="{{ $markerTitle }}" @endif></span>
                @endif
            </div>
            BLADE;
    }
}
