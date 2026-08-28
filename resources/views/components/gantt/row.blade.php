{{--
    Satu baris resource di Gantt board (mesin, orang, atau resource lain).
    'row' = ['resource', 'label', 'sublabel'?, 'blocks' => [...], 'idle' => [...]].
    idle[] sudah berisi left/width px terhitung (Livewire\Pages\...::idleGapsVm()),
    komponen ini tidak menghitung ulang posisi apa pun.
--}}
@props(['row'])
<div class="board-row" wire:key="gantt-row-{{ $row['resource'] }}">
    <div class="row-label">
        <div class="row-label-main">{{ $row['label'] }}</div>
        @if (! empty($row['sublabel']))
            <div class="row-label-sub">{{ $row['sublabel'] }}</div>
        @endif
    </div>
    <div class="row-track">
        @foreach ($row['idle'] ?? [] as $gap)
            <div class="task-block idle-block"
                :class="{ 'dimmed-out': $store.gantt.mode==='risk', 'risk-match': $store.gantt.mode==='idle' && {{ ! empty($gap['flag']) ? 'true' : 'false' }} }"
                style="left:{{ $gap['left'] }}px; width:{{ $gap['width'] }}px;">idle</div>
        @endforeach

        @foreach ($row['blocks'] ?? [] as $b)
            <x-gantt.task-block :b="$b" />
        @endforeach
    </div>
</div>
