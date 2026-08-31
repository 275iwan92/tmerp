{{--
    Satu blok task di Gantt board. Komponen generik -- semua warna/label sudah
    DIRESOLVE di layar pemanggil (lihat resources/views/pages/production/
    scheduling-board.blade.php operationVm()), komponen ini murni render +
    interaksi (drag/resize/klik/dblklik), tidak tahu domain (WO/proses/dll).
    startDrag/startResize/showPopover/openEdit & $store.gantt datang dari
    x-data="ganttBoard(...)" di komponen gantt.board (ancestor DOM, bukan Blade scope).
--}}
@props(['b'])
<div wire:key="gantt-block-{{ $b['id'] }}"
    class="task-block @if (! empty($b['urgent'])) urgent @endif @if (! empty($b['dashed'])) dashed-border @endif"
    style="left:{{ $b['left'] }}px; width:{{ $b['width'] }}px; background:{{ $b['bg'] }}; color:{{ $b['text'] }};"
    data-resource="{{ $b['resource'] }}" data-locked="{{ ! empty($b['locked']) ? '1' : '0' }}"
    :class="{ 'dimmed-out': $store.gantt.mode==='risk' && {{ ! empty($b['risk']) ? 'false' : 'true' }}, 'risk-match': $store.gantt.mode==='risk' && {{ ! empty($b['risk']) ? 'true' : 'false' }} }"
    title="{{ empty($b['locked']) ? 'Klik = detail · klik 2x = edit · seret badan = geser jadwal · seret tepi kanan = ubah durasi' : 'Klik = detail · klik 2x = edit (jadwal terkunci, tidak bisa diseret)' }}"
    @mousedown="startDrag($event, {{ $b['id'] }})"
    @click="showPopover($event, {{ $b['id'] }}, @js($b['title']), @js($b['subtitle'] ?? ''), @js($b['timeLabel']), {{ (int) $b['progress'] }}, @js($b['statusLabel']))"
    @dblclick="openEdit($event, {{ $b['id'] }}, @js($b['title']), @js($b['subtitle'] ?? ''))">
    @if (empty($b['locked']))
        <div class="resize-handle" title="Seret untuk ubah durasi" @mousedown="startResize($event, {{ $b['id'] }})"></div>
    @endif
    <div class="title">{{ $b['title'] }}</div>
    <div class="sub">{{ $b['timeLabel'] }}</div>
    <div class="progress-track"><div class="progress-fill" style="width:{{ $b['progress'] }}%"></div></div>
</div>
