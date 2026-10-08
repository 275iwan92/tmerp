<?php

namespace Devlib\Components;

// Stopwatch display (hh:mm:ss). The server gives the seconds already elapsed when the page was made; the browser keeps counting while running.
// Used by the operator screen (setup / run timer) and anywhere a job clock is shown. running=false shows a still time (paused, finished).
// tone: accent | warn | success | danger | muted.
class Timer extends DevlibComponent
{
    public function __construct(
        public int $seconds = 0,
        public bool $running = true,
        public ?string $label = null,
        public string $tone = 'accent',
    ) {}

    public function tag(): string
    {
        return 'x-dl::timer';
    }

    public function source(): string
    {
        return 'seconds elapsed (server), counted on by the browser while running';
    }

    public function text(): string
    {
        $s = max(0, $this->seconds);

        return sprintf('%02d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60);
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-timer', 'dl-timer--'.$tone]) }} {!! $dl() !!} data-dl-timer data-seconds="{{ max(0, $seconds) }}" data-running="{{ $running ? 1 : 0 }}">
                <span class="dl-timer__t" data-dl-timer-text>{{ $text() }}</span>@if ($label)<span class="dl-timer__l">{{ $label }}</span>@endif
            </div>
            BLADE;
    }
}
