{{--
    Gantt board generik -- diekstrak dari Scheduling Board (production_scheduling_
    tier4.md §3) supaya bisa dipakai layar Gantt lain (mis. Schedule Maintenance,
    PROD_MTN_SCH, masih placeholder). Layar pemanggil WAJIB sudah meresolve semua
    warna/label/posisi-px ke bentuk row/block generik -- lihat kontrak di
    resources/views/pages/production/scheduling-board.blade.php (operationVm(),
    idleGapsVm()). Komponen ini TIDAK tahu apa itu WO/mesin/proses.

    Interaksi (drag geser waktu, tarik tepi kanan ubah durasi, klik = popover,
    dblklik = modal ganti resource+label) memanggil method Livewire di LAYAR
    PEMANGGIL lewat nama method yang dikirim sbg prop (move-method/resize-method/
    reassign-method) -- komponen ini tidak perlu tahu nama domain method aslinya.

    Highlight lintas-boundary (stat card di halaman -> blok di komponen ini)
    lewat Alpine store global `gantt` ($store.gantt.mode), bukan x-data lokal --
    supaya halaman pemanggil bisa memicu highlight dari LUAR komponen.
--}}
@props([
    'rows' => [],
    'secondaryRows' => [],
    'secondaryLabel' => null,
    'resourceHeaderLabel' => t('Resource'),
    'timelineStart' => 8,
    'timelineEnd' => 20,
    'pxPerHour' => 70,
    'moveMethod' => 'moveGanttBlock',
    'resizeMethod' => 'resizeGanttBlock',
    'reassignMethod' => 'reassignGanttBlock',
    'resourceOptions' => [],
    'labelOptions' => [],
    'labelFieldName' => t('Category'),
    'emptyMessage' => t('No data.'),
])

<div x-data="ganttBoard({{ $timelineStart }}, {{ $timelineEnd }}, {{ $pxPerHour }}, '{{ $moveMethod }}', '{{ $resizeMethod }}', '{{ $reassignMethod }}')"
    x-on:mousemove.window="onMouseMove($event)" x-on:mouseup.window="onMouseUp($event)"
    x-on:board-toast.window="showToast($event.detail.message)">

    <div class="board">
        <div class="board-inner">
            <div class="ruler">
                <div class="ruler-label">{{ $resourceHeaderLabel }}</div>
                <div class="ruler-track">
                    @for ($h = $timelineStart; $h <= $timelineEnd; $h++)
                        <div class="ruler-tick" style="left:{{ ($h - $timelineStart) * $pxPerHour }}px">{{ sprintf('%02d:00', $h) }}</div>
                    @endfor
                </div>
            </div>

            @forelse ($rows as $row)
                <x-gantt.row :row="$row" />
            @empty
                <div class="empty-state">{{ $emptyMessage }}</div>
            @endforelse

            @if (count($secondaryRows))
                @if ($secondaryLabel)
                    <div class="divider-row">{{ $secondaryLabel }}</div>
                @endif
                @foreach ($secondaryRows as $row)
                    <x-gantt.row :row="$row" />
                @endforeach
            @endif
        </div>
    </div>

    <div class="popover" :class="{ show: popover.show }" :style="`top:${popover.y}px; left:${popover.x}px;`" @click.outside="popover.show=false">
        <button class="close-pop" @click="popover.show=false">&times;</button>
        <h3 x-text="popover.title"></h3>
        <dl>
            <dt x-show="popover.subtitle">{{ t('Detail') }}</dt><dd x-show="popover.subtitle" x-text="popover.subtitle"></dd>
            <dt>{{ t('Timing') }}</dt><dd x-text="popover.time"></dd>
            <dt>{{ t('Progress') }}</dt><dd x-text="popover.progress + '%'"></dd>
            <dt>{{ t('Status') }}</dt><dd x-text="popover.status"></dd>
        </dl>
    </div>

    <div class="modal-backdrop" :class="{ show: editing }">
        <div class="edit-modal" @click.outside="editing = null" x-show="editing">
            <div class="edit-modal-header">
                <h3>{{ t('Edit Task') }}</h3>
                <button @click="editing = null">&times;</button>
            </div>
            <template x-if="editing">
                <div class="edit-modal-body">
                    <div class="edit-field">
                        <label>{{ t('Task') }}</label>
                        <input :value="editing.title" disabled>
                    </div>
                    <div class="edit-field">
                        <label>{{ t('Resource') }}</label>
                        <select x-model="editing.resource">
                            @foreach ($resourceOptions as $opt)
                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="edit-field">
                        <label>{{ $labelFieldName }}</label>
                        <select x-model="editing.label">
                            @foreach ($labelOptions as $l)
                                <option value="{{ $l }}">{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </template>
            <div class="edit-modal-footer">
                <button class="btn-cancel" @click="editing = null">{{ t('Cancel') }}</button>
                <button class="btn-save" @click="$wire.call(reassignMethod, editing.id, editing.resource, editing.label); editing = null">{{ t('Save') }}</button>
            </div>
        </div>
    </div>

    <div class="toast" :class="{ show: toastShow }" x-text="toastMsg"></div>
</div>

<style>
    .board { background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; overflow-x: auto; position: relative; padding-bottom: 8px; }
    .board-inner { min-width: 1060px; }
    .ruler { display: flex; border-bottom: 1px solid #e5e7eb; background: #fafafa; font-size: 11px; color: #6b7280; font-weight: 500; }
    .ruler-label { width: 220px; flex: none; padding: 10px 16px; }
    .ruler-track { position: relative; flex: 1; height: 34px; }
    .ruler-tick { position: absolute; top: 0; height: 100%; display: flex; align-items: center; transform: translateX(-50%); }
    .board-row { display: flex; align-items: stretch; border-bottom: 1px solid #f0f0f0; }
    .row-label { width: 220px; flex: none; padding: 10px 16px; display: flex; flex-direction: column; justify-content: center; }
    .row-label-main { font-size: 13px; font-weight: 500; color: #1f2328; }
    .row-label-sub { font-size: 10px; color: #6b7280; margin-top: 2px; }
    .row-track { position: relative; flex: 1; min-height: 62px; min-width: 840px; background-image: repeating-linear-gradient(to right, #f5f5f4 0, #f5f5f4 1px, transparent 1px, transparent 70px); }
    .task-block { position: absolute; top: 8px; height: 46px; border-radius: 7px; font-size: 11px; padding: 6px 8px; overflow: hidden; cursor: grab; user-select: none; box-shadow: 0 1px 2px rgba(0,0,0,.06); }
    .task-block.dragging { cursor: grabbing; opacity: .85; box-shadow: 0 6px 14px rgba(0,0,0,.18); z-index: 5; }
    .task-block .title { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .task-block .sub { font-size: 9.5px; opacity: .85; white-space: nowrap; }
    .task-block:hover .resize-handle { opacity: 1; }
    .resize-handle { position: absolute; top: 0; right: 0; width: 10px; height: 100%; cursor: ew-resize; opacity: 0; transition: opacity .15s ease; }
    .resize-handle::after { content: ''; position: absolute; right: 2px; top: 50%; transform: translateY(-50%); width: 3px; height: 18px; border-radius: 2px; background: rgba(0,0,0,.35); }
    .progress-track { position: absolute; left: 6px; right: 6px; bottom: 5px; height: 4px; background: rgba(255,255,255,.5); border-radius: 3px; overflow: hidden; }
    .progress-fill { height: 100%; background: rgba(0,0,0,.42); border-radius: 3px; }
    .task-block.idle-block { cursor: default; background: repeating-linear-gradient(45deg,#f3f4f6,#f3f4f6 6px,#e5e7eb 6px,#e5e7eb 12px); color: #9ca3af; display:flex; align-items:center; justify-content:center; box-shadow:none; }
    .task-block.urgent { outline: 2px solid #dc2626; outline-offset: -2px; }
    .task-block.dashed-border { border: 2px dashed #d97706; cursor: not-allowed; }
    .task-block.risk-match { box-shadow: 0 0 0 2px #1f2328, 0 1px 2px rgba(0,0,0,.06); }
    .task-block.dimmed-out { opacity: .22; }
    .divider-row { padding: 8px 16px; background: #fafafa; border-bottom: 1px solid #e5e7eb; font-size: 11px; font-weight: 600; color: #6b7280; }
    .empty-state { padding: 40px 16px; text-align: center; color: #9ca3af; font-size: 13px; }
    .popover { position: fixed; z-index: 50; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,.15); padding: 14px 16px; width: 240px; font-size: 12px; display: none; }
    .popover.show { display: block; }
    .popover h3 { font-size: 13px; margin-bottom: 8px; color: #1f2328; }
    .popover dl { display: grid; grid-template-columns: auto 1fr; gap: 4px 8px; }
    .popover dt { color: #6b7280; }
    .popover dd { color: #1f2328; font-weight: 500; text-align: right; }
    .popover .close-pop { position: absolute; top: 8px; right: 10px; border: none; background: none; color: #9ca3af; cursor: pointer; font-size: 14px; }
    .modal-backdrop { position: fixed; inset: 0; background: rgba(31,35,40,.55); z-index: 60; display: none; align-items: center; justify-content: center; padding: 16px; }
    .modal-backdrop.show { display: flex; }
    .edit-modal { background: #fff; border-radius: 14px; width: 100%; max-width: 380px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,.2); }
    .edit-modal-header { padding: 16px 20px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
    .edit-modal-header h3 { font-size: 14px; font-weight: 600; }
    .edit-modal-header button { border: none; background: none; color: #9ca3af; font-size: 18px; cursor: pointer; }
    .edit-modal-body { padding: 16px 20px; display: flex; flex-direction: column; gap: 12px; }
    .edit-field label { font-size: 11px; color: #6b7280; display: block; margin-bottom: 4px; }
    .edit-field select, .edit-field input { width: 100%; font-size: 13px; border: 1px solid #e5e7eb; border-radius: 8px; padding: 8px 10px; font-family: inherit; }
    .edit-modal-footer { padding: 14px 20px; border-top: 1px solid #f0f0f0; display: flex; justify-content: flex-end; gap: 8px; background: #fafafa; }
    .btn-cancel { background: none; color: #6b7280; font-size: 12px; padding: 8px 16px; border-radius: 8px; border: none; cursor: pointer; font-family: inherit; }
    .btn-save { background: #1d4ed8; color: #fff; font-size: 12px; font-weight: 500; padding: 8px 18px; border-radius: 8px; border: none; cursor: pointer; font-family: inherit; }
    .toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%) translateY(10px); background: #1f2328; color: #fff; font-size: 12px; padding: 10px 18px; border-radius: 999px; opacity: 0; transition: opacity .2s ease, transform .2s ease; z-index: 70; pointer-events: none; }
    .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
</style>

@once
    <script>
        document.addEventListener('alpine:init', () => {
            // Store global -- dipakai LINTAS boundary komponen: stat card di
            // halaman pemanggil memicu highlight blok di dalam komponen Gantt
            // board ini, tanpa perlu tahu struktur internal Alpine-nya. Satu papan aktif
            // per halaman (cukup utk kebutuhan saat ini -- lihat catatan
            // ekstraksi di project memory kalau nanti butuh multi-papan).
            Alpine.store('gantt', {
                mode: null,
                toggle(m) { this.mode = (this.mode === m ? null : m); },
                clear() { this.mode = null; },
            });

            Alpine.data('ganttBoard', (timelineStart, timelineEnd, pxPerHour, moveMethod, resizeMethod, reassignMethod) => ({
                TIMELINE_START: timelineStart,
                TIMELINE_END: timelineEnd,
                PX_PER_HOUR: pxPerHour,
                SNAP: 0.25,
                moveMethod, resizeMethod, reassignMethod,
                drag: null,
                resize: null,
                suppressClick: false,
                editing: null,
                popover: { show: false, x: 0, y: 0, title: '', subtitle: '', time: '', progress: 0, status: '' },
                toastMsg: '',
                toastShow: false,
                toastTimer: null,

                showToast(msg) {
                    this.toastMsg = msg;
                    this.toastShow = true;
                    clearTimeout(this.toastTimer);
                    this.toastTimer = setTimeout(() => { this.toastShow = false; }, 2200);
                },

                startDrag(e, blockId) {
                    if (e.target.classList.contains('resize-handle')) return;
                    const block = e.currentTarget;
                    if (block.dataset.locked === '1') return;
                    e.preventDefault();
                    this.drag = {
                        blockId, block,
                        startMouseX: e.clientX,
                        startLeft: parseFloat(block.style.left),
                        trackWidth: block.parentElement.offsetWidth,
                        blockWidth: block.offsetWidth,
                    };
                    block.classList.add('dragging');
                },

                startResize(e, blockId) {
                    e.preventDefault();
                    e.stopPropagation();
                    const block = e.currentTarget.closest('.task-block');
                    if (block.dataset.locked === '1') return;
                    this.resize = {
                        blockId, block,
                        startMouseX: e.clientX,
                        startWidth: block.offsetWidth,
                        trackWidth: block.parentElement.offsetWidth,
                        blockLeft: parseFloat(block.style.left),
                    };
                    block.classList.add('dragging');
                },

                onMouseMove(e) {
                    if (this.drag) {
                        const dx = e.clientX - this.drag.startMouseX;
                        let newLeft = this.drag.startLeft + dx;
                        newLeft = Math.max(0, Math.min(newLeft, this.drag.trackWidth - this.drag.blockWidth));
                        const snapped = Math.round((newLeft / this.PX_PER_HOUR) / this.SNAP) * this.SNAP;
                        this.drag.block.style.left = (snapped * this.PX_PER_HOUR) + 'px';
                    } else if (this.resize) {
                        const dx = e.clientX - this.resize.startMouseX;
                        let newWidth = this.resize.startWidth + dx;
                        const minWidth = this.PX_PER_HOUR * 0.5;
                        const maxWidth = this.resize.trackWidth - this.resize.blockLeft;
                        newWidth = Math.max(minWidth, Math.min(newWidth, maxWidth));
                        const snapped = Math.round((newWidth / this.PX_PER_HOUR) / this.SNAP) * this.SNAP;
                        this.resize.block.style.width = (snapped * this.PX_PER_HOUR) + 'px';
                    }
                },

                onMouseUp() {
                    if (this.drag) {
                        const newStart = this.TIMELINE_START + (parseFloat(this.drag.block.style.left) / this.PX_PER_HOUR);
                        const blockId = this.drag.blockId;
                        this.drag.block.classList.remove('dragging');
                        this.drag = null;
                        this.suppressClick = true;
                        setTimeout(() => { this.suppressClick = false; }, 80);
                        this.$wire.call(this.moveMethod, blockId, newStart);
                    }
                    if (this.resize) {
                        const newDuration = parseFloat(this.resize.block.style.width) / this.PX_PER_HOUR;
                        const blockId = this.resize.blockId;
                        this.resize.block.classList.remove('dragging');
                        this.resize = null;
                        this.suppressClick = true;
                        setTimeout(() => { this.suppressClick = false; }, 80);
                        this.$wire.call(this.resizeMethod, blockId, newDuration);
                    }
                },

                showPopover(e, blockId, title, subtitle, time, progress, status) {
                    if (this.suppressClick) return;
                    const rect = e.currentTarget.getBoundingClientRect();
                    this.popover = {
                        show: true,
                        x: Math.min(rect.left, window.innerWidth - 260),
                        y: rect.bottom + 8,
                        title, subtitle, time, progress, status,
                    };
                },

                openEdit(e, blockId, title, currentLabel) {
                    const block = e.currentTarget;
                    this.editing = {
                        id: blockId,
                        title,
                        resource: block.dataset.resource,
                        label: currentLabel,
                    };
                },
            }));
        });
    </script>
@endonce
