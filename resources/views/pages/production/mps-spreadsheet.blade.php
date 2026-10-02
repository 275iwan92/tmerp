<?php

use App\Models\Master\EmpEmployee;
use App\Models\Master\ProdMachine;
use App\Models\Prd\PrdCrewAssignment;
use App\Models\Prd\PrdWoOperation;
use App\Services\Scheduling\PdrEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// MPS - Spreadsheet (permintaan user 2026-08-28): UI/UX kompatibel dgn
// kebiasaan lama PPIC pakai Excel/Google Sheets utk kelola MPS -- edit
// langsung di sel + filter per kolom (dropdown di header, ala Excel
// AutoFilter, permintaan eksplisit user krn dibutuhkan lihat per
// Proses/Mesin). BUKAN sumber data baru -- baca/tulis prd_wo_operation yg
// SAMA dgn WO Detail/Gantt/Kanban (1 data, 4 tampilan). Grid dibangun pakai
// DataTables 3 + KeyTable (docs/data tables/, disediakan user).
//
// Tabel di-render SEKALI oleh Livewire lalu `wire:ignore` -- DataTables
// menyisipkan wrapper DOM sendiri (search box, filter row, pagination) yang
// akan BENTROK kalau Livewire coba morph ulang subtree itu tiap request.
// Konsekuensinya: sel editable TIDAK pakai wire:change/full re-render biasa
// spt layar lain -- tiap task Livewire (updateMachine/updateOperator/
// updateStart/updateFinish) di-panggil via `$wire.call()` dari Alpine (1
// komponen `mpsRow` per <tr>, lihat @script), method me-RETURN array kecil
// (label terbaru) yg langsung dipatch ke sel terkait tanpa reload. SENGAJA
// TIDAK pakai extension Editor yg ikut ter-bundle (kontrak AJAX-nya sendiri
// bakal jadi jalur validasi ke-2 di luar Livewire, gampang menyimpang dari
// engine seiring waktu) -- validasi/persist tetap 100% lewat method
// Livewire yang SAMA gayanya dgn WO Detail/Gantt/Kanban.
//
// Keterbatasan yg disengaja (bukan bug tersembunyi): assign mesin ke task
// yg belum terjadwal bisa nge-reschedule SELURUH WO (task lain, baris
// LAIN) lewat PdrEngine -- baris lain itu TIDAK auto-update tampilannya
// (di luar jangkauan 1 baris yg di-edit), user diberi tahu lewat toast utk
// klik "🔄 Refresh" (reload polos, state DataTables dipertahankan via
// `stateSave`).
//
// griduistandard.md (stat box pastel/avatar+nama) SENGAJA tidak dipakai --
// ini eksplisit UI lama gaya spreadsheet, bukan grid standar aplikasi.
//
// Engine MPS (PdrEngine) tetap satu-satunya jalur tulis, kontekstual persis
// spt Gantt/Kanban:
// - Task BELUM terjadwal (target_start null) + assign mesin -> spt Kanban:
//   PdrEngine::backwardScheduleAndPersist() (hitung ulang seluruh WO dari
//   due_date), fallback estimasi sederhana kalau rate band blm lengkap.
// - Task SUDAH terjadwal + ganti mesin/operator/waktu manual -> spt Gantt:
//   PdrEngine::validateManualReschedule() dulu (konflik crew/durasi
//   minimum/lock QC_HOLD) sebelum commit.
// - Hanya task PENDING/ASSIGNED yang bisa diedit -- task RUNNING/BREAKDOWN/
//   dst kelola lewat WO Detail ("Lapor Breakdown"), pola sama Kanban.
new #[Title('MPS - Spreadsheet — TELASIH MAKMUR ERP')] class extends Component
{
    private const EDITABLE_STATUSES = ['PENDING', 'ASSIGNED'];

    #[Computed]
    public function operations()
    {
        return PrdWoOperation::query()
            ->whereNotIn('task_status', ['DONE', 'QC_PASSED'])
            ->with(['wo.coOrder.quotation.customer', 'wo.product', 'machine', 'crew.employee'])
            ->orderByRaw('target_start IS NULL, target_start ASC')
            ->orderBy('operation_id')
            ->limit(1000)
            ->get();
    }

    #[Computed]
    public function machines()
    {
        return ProdMachine::where('activate', true)->orderBy('machine_code')->get();
    }

    #[Computed]
    public function employeeOptions()
    {
        return EmpEmployee::where('activate', true)->orderBy('full_name')->limit(300)->get();
    }

    #[Computed]
    public function processNames()
    {
        return $this->operations->pluck('process_name')->unique()->sort()->values();
    }

    private function editableOrFail(int $operationId): ?PrdWoOperation
    {
        $op = PrdWoOperation::with(['wo', 'crew'])->find($operationId);

        if (! $op) {
            return null;
        }

        if (! in_array($op->task_status, self::EDITABLE_STATUSES, true)) {
            $this->dispatch('wo-toast', message: t('This task is already running/done -- edit it via WO Detail ("Report Breakdown").'));

            return null;
        }

        return $op;
    }

    // Tabel di-render sekali lalu di-`wire:ignore` (DataTables mengelola DOM-
    // nya sendiri -- sort/paging/search -- morph Livewire biasa akan bentrok
    // sama wrapper yg disisipkan DataTables). Karena itu method di bawah
    // TIDAK mengandalkan re-render Blade utk update tampilan -- masing2
    // me-return array kecil (label terbaru) yg dibaca langsung oleh Alpine
    // (lihat @script) utk nge-patch sel yg relevan tanpa full reload. Baris
    // LAIN di WO yang sama (kalau ikut ter-reschedule oleh engine) tetap
    // butuh klik "🔄 Refresh" manual -- keterbatasan yg disengaja & diberi
    // tahu ke user lewat toast, bukan disembunyikan.
    public function updateStart(int $operationId, string $value): array
    {
        $op = $this->editableOrFail($operationId);
        if (! $op || ! $op->target_start || $value === '') {
            return ['ok' => false];
        }

        $durationMinutes = $op->target_start->diffInMinutes($op->target_finish);
        $newStart = Carbon::parse($value);

        return $this->applyManualReschedule($op, $newStart, $newStart->copy()->addMinutes($durationMinutes));
    }

    public function updateFinish(int $operationId, string $value): array
    {
        $op = $this->editableOrFail($operationId);
        if (! $op || ! $op->target_start || $value === '') {
            return ['ok' => false];
        }

        return $this->applyManualReschedule($op, $op->target_start, Carbon::parse($value));
    }

    private function applyManualReschedule(PrdWoOperation $op, Carbon $newStart, Carbon $newFinish): array
    {
        $errors = app(PdrEngine::class)->validateManualReschedule(
            $op, $newStart, $newFinish, $op->crew->pluck('employee_id')->all()
        );

        if (! empty($errors)) {
            $this->dispatch('wo-toast', message: implode(' ', $errors));

            return ['ok' => false];
        }

        $op->update(['target_start' => $newStart, 'target_finish' => $newFinish, 'updated_by' => Auth::user()->login]);
        $this->dispatch('wo-toast', message: str_replace(':wo', $op->wo->wo_no, t('Schedule :wo updated.')));

        return [
            'ok' => true,
            'startLabel' => $newStart->format('d/m H:i'),
            'finishLabel' => $newFinish->format('d/m H:i'),
        ];
    }

    public function updateMachine(int $operationId, int $machineId): array
    {
        $op = $this->editableOrFail($operationId);
        if (! $op || $op->resource_type !== 'MACHINE') {
            return ['ok' => false];
        }

        // Task belum terjadwal -- sama spt Kanban assign dari Antrean:
        // pakai engine utk hitung backward-schedule PENUH utk WO ini.
        if (! $op->target_start) {
            $op->machine_id = $machineId;
            $op->updated_by = Auth::user()->login;
            $op->save();

            $engineOk = false;

            try {
                app(PdrEngine::class)->backwardScheduleAndPersist($op->wo);
                $op->refresh();
                $engineOk = true;
            } catch (\RuntimeException) {
                $rate = (float) ($op->rate_band_value ?? 0);
                $hours = $rate > 0 ? max(0.5, ((float) $op->qty) / $rate) : 1.0;
                $op->target_start = now();
                $op->target_finish = now()->copy()->addMinutes((int) round($hours * 60));
                $op->save();
            }

            if ($op->task_status === 'PENDING') {
                $op->transitionTo('ASSIGNED');
            }

            $note = $engineOk ? '' : ' '.t('(simple estimate -- rate band incomplete)');
            $cascadeNote = $engineOk ? ' '.t('Other tasks in this WO may have been rescheduled too -- click 🔄 Refresh to see them all.') : '';
            $this->dispatch('wo-toast', message: str_replace(':wo', $op->wo->wo_no, t('Machine :wo assigned.')).$note.$cascadeNote);

            return [
                'ok' => true,
                'machineLabel' => $op->machine?->machine_name,
                'startLabel' => $op->target_start->format('d/m H:i'),
                'finishLabel' => $op->target_finish->format('d/m H:i'),
            ];
        }

        // Task sudah terjadwal -- reassign mesin spt Gantt: validasi dulu,
        // waktu TIDAK dihitung ulang (PPIC yang putuskan lewat kolom
        // Est Mulai/Selesai kalau perlu).
        $errors = app(PdrEngine::class)->validateManualReschedule(
            $op, $op->target_start, $op->target_finish, $op->crew->pluck('employee_id')->all()
        );

        if (! empty($errors)) {
            $this->dispatch('wo-toast', message: implode(' ', $errors));

            return ['ok' => false];
        }

        $op->machine_id = $machineId;
        $op->updated_by = Auth::user()->login;
        $op->save();
        $this->dispatch('wo-toast', message: str_replace(':wo', $op->wo->wo_no, t('Machine :wo updated.')));

        return ['ok' => true, 'machineLabel' => $op->machine?->machine_name];
    }

    public function updateOperator(int $operationId, ?int $employeeId): array
    {
        $op = $this->editableOrFail($operationId);
        if (! $op) {
            return ['ok' => false];
        }

        if ($employeeId && $op->target_start && $op->target_finish) {
            $errors = app(PdrEngine::class)->validateManualReschedule(
                $op, $op->target_start, $op->target_finish, [$employeeId]
            );

            if (! empty($errors)) {
                $this->dispatch('wo-toast', message: implode(' ', $errors));

                return ['ok' => false];
            }
        }

        if ($employeeId) {
            PrdCrewAssignment::updateOrCreate(
                ['operation_id' => $op->operation_id, 'crew_role' => 'OPERATOR_UTAMA'],
                ['employee_id' => $employeeId, 'created_by' => Auth::user()->login]
            );
            $label = EmpEmployee::find($employeeId)?->full_name ?? '—';
        } else {
            PrdCrewAssignment::where('operation_id', $op->operation_id)
                ->where('crew_role', 'OPERATOR_UTAMA')->delete();
            $label = '— ('.t('empty').') —';
        }

        $this->dispatch('wo-toast', message: str_replace(':wo', $op->wo->wo_no, t('Operator :wo updated.')));

        return ['ok' => true, 'operatorLabel' => $label];
    }

    private function statusBadgeClasses(string $status): string
    {
        return match ($status) {
            'PENDING' => 'bg-[#eef1f5] text-[#4b5568]',
            'ASSIGNED' => 'bg-[#e9f1fd] text-[#2c7be5]',
            'RUNNING' => 'bg-[#e4f8ec] text-[#0e7a44]',
            'BREAKDOWN' => 'bg-[#fdecec] text-[#a3241f]',
            'QC_HOLD' => 'bg-[#fef6e7] text-[#b45309]',
            'QC_REJECTED' => 'bg-[#fdecec] text-[#a3241f]',
            'SUBCONTRACT' => 'bg-[#e9f1fd] text-[#2c7be5]',
            default => 'bg-[#eef1f5] text-[#4b5568]',
        };
    }
};
?>

@assets
<link rel="stylesheet" href="{{ asset('vendor/datatables/datatables.min.css') }}">
<script src="{{ asset('vendor/datatables/datatables.min.js') }}"></script>
@endassets

<div x-data="{ toastMsg: '', toastShow: false, toastTimer: null, woModalUrl: null }"
    x-on:wo-toast.window="toastMsg = $event.detail.message; toastShow = true; clearTimeout(toastTimer); toastTimer = setTimeout(() => toastShow = false, 3200)"
    x-on:keydown.escape.window="if (woModalUrl) window.location.reload()">
    <header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">
        <b class="text-[15px]">🧮 {{ t('MPS - Spreadsheet') }}</b>
        <span class="text-[10.5px] text-white/60">{{ str_replace(':n', $this->operations->count(), t(':n active tasks (not Done yet)')) }}</span>
        <div class="ml-auto flex items-center gap-2">
            <a href="{{ route('production.scheduling-board') }}" class="inline-flex items-center gap-2 pl-3.5 pr-3 py-1 bg-[#43508a] rounded-full hover:brightness-110 no-underline">
                <span class="text-[11px] font-bold uppercase tracking-wide text-white">📅 {{ t('Gantt Chart') }}</span>
            </a>
            <a href="{{ route('production.monitoring') }}" class="inline-flex items-center gap-2 pl-3.5 pr-3 py-1 bg-[#43508a] rounded-full hover:brightness-110 no-underline">
                <span class="text-[11px] font-bold uppercase tracking-wide text-white">🗂 {{ t('Kanban Board') }}</span>
            </a>
            <button type="button" onclick="window.location.reload()" class="inline-flex items-center gap-2 pl-3.5 pr-3 py-1 bg-white/10 rounded-full hover:brightness-110 cursor-pointer border-none">
                <span class="text-[11px] font-bold uppercase tracking-wide text-white">🔄 {{ t('Refresh') }}</span>
            </button>
        </div>
    </header>

    <div class="max-w-full mx-auto my-4 px-4">
        <p class="text-[10.5px] text-muted mb-2.5">
            {!! t('Double-click a <b>Machine / Operator / Est. Start / Est. Finish</b> cell to edit -- just like Excel/Google Sheets.') !!}
            {{ t('Header row 2 = per-column filter (dropdown for Process/Machine/Operator/Status, free text for other columns).') }}
            {{ t('All changes are still validated by the Scheduling Engine (operator conflicts, minimum duration, QC Hold lock) just like MPS - Gantt Chart.') }}
            {!! t('Only <b>Pending/Assigned</b> tasks can be edited.') !!}
        </p>

        <div wire:ignore>
            <table id="mps-sheet" class="display w-full text-[11.5px]">
                <thead>
                    <tr class="bg-[#f5f7fa]">
                        <th>{{ t('WO No') }}</th>
                        <th>{{ t('Customer') }}</th>
                        <th>{{ t('Product / Part') }}</th>
                        <th>{{ t('Process') }}</th>
                        <th>{{ t('Machine') }}</th>
                        <th>{{ t('Operator') }}</th>
                        <th>{{ t('Qty') }}</th>
                        <th>{{ t('Target/Hour') }}</th>
                        <th>{{ t('Est. Start') }}</th>
                        <th>{{ t('Est. Finish') }}</th>
                        <th>{{ t('Status') }}</th>
                        <th>{{ t('Due Date') }}</th>
                    </tr>
                    <tr class="mps-filter-row bg-white">
                        <th><input type="text" class="mps-filter" data-col="0" placeholder="{{ t('search WO...') }}"></th>
                        <th><input type="text" class="mps-filter" data-col="1" placeholder="{{ t('search customer...') }}"></th>
                        <th><input type="text" class="mps-filter" data-col="2" placeholder="{{ t('search product...') }}"></th>
                        <th>
                            <select class="mps-filter" data-col="3" data-exact="1">
                                <option value="">{{ t('All Processes') }}</option>
                                @foreach ($this->processNames as $p)
                                    <option value="{{ $p }}">{{ $p }}</option>
                                @endforeach
                            </select>
                        </th>
                        <th>
                            <select class="mps-filter" data-col="4" data-exact="1">
                                <option value="">{{ t('All Machines') }}</option>
                                <option value="— {{ t('Manual') }} —">— {{ t('Manual') }} —</option>
                                @foreach ($this->machines as $m)
                                    <option value="{{ $m->machine_name }}">{{ $m->machine_code }} · {{ $m->machine_name }}</option>
                                @endforeach
                            </select>
                        </th>
                        <th>
                            <select class="mps-filter" data-col="5" data-exact="1">
                                <option value="">{{ t('All Operators') }}</option>
                                @foreach ($this->employeeOptions as $e)
                                    <option value="{{ $e->full_name }}">{{ $e->full_name }}</option>
                                @endforeach
                            </select>
                        </th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th>
                            <select class="mps-filter" data-col="10" data-exact="1">
                                <option value="">{{ t('All Statuses') }}</option>
                                @foreach (['PENDING', 'ASSIGNED', 'RUNNING', 'BREAKDOWN', 'QC_HOLD', 'QC_REJECTED', 'SUBCONTRACT'] as $s)
                                    <option value="{{ $s }}">{{ $s }}</option>
                                @endforeach
                            </select>
                        </th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->operations as $op)
                        @php
                            $editable = in_array($op->task_status, ['PENDING', 'ASSIGNED'], true);
                            $currentEmp = $op->crew->firstWhere('crew_role', 'OPERATOR_UTAMA');
                            $machineLabel = $op->resource_type === 'MACHINE' ? ($op->machine?->machine_name ?? '— '.t('select machine').' —') : '— '.t('Manual').' —';
                            $operatorLabel = $currentEmp?->employee?->full_name ?? '— ('.t('empty').') —';
                            $startLabel = $op->target_start?->format('d/m H:i') ?? ($editable ? '— ('.t('assign a machine first').')' : '—');
                            $finishLabel = $op->target_finish?->format('d/m H:i') ?? '—';
                        @endphp
                        <tr wire:key="op-row-{{ $op->operation_id }}"
                            x-data="mpsRow({
                                opId: {{ $op->operation_id }},
                                editable: @js($editable),
                                isMachine: @js($op->resource_type === 'MACHINE'),
                                hasSchedule: @js((bool) $op->target_start),
                                machineLabel: @js($machineLabel),
                                operatorLabel: @js($operatorLabel),
                                startLabel: @js($startLabel),
                                finishLabel: @js($finishLabel),
                            })">
                            <td class="font-mono font-bold text-accent">
                                {{-- Buka WO Detail sbg popup (iframe modal) di atas spreadsheet -- permintaan
                                     user: link biasa navigasi penuh & tombol Back browser tidak balik ke
                                     Spreadsheet dgn benar. href tetap ada utk ctrl/cmd+klik & buka tab baru
                                     (native browser, tak disentuh JS); klik polos dicegat -> buka modal. --}}
                                <a href="{{ route('production.wo.entry', ['wo' => $op->wo_id]) }}"
                                    x-on:click="if (! $event.ctrlKey && ! $event.metaKey) { $event.preventDefault(); woModalUrl = $event.target.href }"
                                    class="text-accent no-underline hover:underline">{{ $op->wo?->wo_no ?? '—' }}</a>
                            </td>
                            <td>{{ $op->wo?->coOrder?->quotation?->customer?->customer_name ?? 'GENERAL' }}</td>
                            <td>{{ $op->wo?->product?->product_name ?? '—' }} <span class="text-muted">({{ $op->wo?->part_code ?? '—' }})</span></td>
                            <td>{{ $op->process_name }}</td>

                            {{-- Mesin: klik 2x reveal <select> -- SENGAJA <template x-if> (bukan x-show/select
                                 permanen): DataTables baca textContent sel utk sort/search, x-show cuma
                                 sembunyikan CSS (elemen + isi option-nya TETAP ada di DOM & tetap kebaca),
                                 cuma x-if yg beneran melepas elemen dari DOM saat tidak diedit. --}}
                            <td @dblclick="if (editable && isMachine) startEdit('machine')">
                                <span x-show="!editingMachine" x-text="machineLabel" class="border-b border-dashed border-transparent hover:border-[#c7cfda]">{{ $machineLabel }}</span>
                                @if ($editable && $op->resource_type === 'MACHINE')
                                    <template x-if="editingMachine">
                                        <select x-ref="machineSelect"
                                            x-on:change="commitMachine($event.target.value)" x-on:blur="editingMachine = false"
                                            class="w-full px-1.5 py-1 border border-accent rounded text-[11px] bg-white">
                                            @unless ($op->machine_id)
                                                <option value="" disabled selected>— {{ t('select machine') }} —</option>
                                            @endunless
                                            @foreach ($this->machines as $m)
                                                <option value="{{ $m->machine_id }}" @selected($op->machine_id === $m->machine_id)>{{ $m->machine_code }} · {{ $m->machine_name }}</option>
                                            @endforeach
                                        </select>
                                    </template>
                                @endif
                            </td>

                            {{-- Operator: sama pola, alasan sama --}}
                            <td @dblclick="if (editable) startEdit('operator')">
                                <span x-show="!editingOperator" x-text="operatorLabel" class="border-b border-dashed border-transparent hover:border-[#c7cfda]">{{ $operatorLabel }}</span>
                                @if ($editable)
                                    <template x-if="editingOperator">
                                        <select x-ref="operatorSelect"
                                            x-on:change="commitOperator($event.target.value)" x-on:blur="editingOperator = false"
                                            class="w-full px-1.5 py-1 border border-accent rounded text-[11px] bg-white">
                                            <option value="">— ({{ t('empty') }}) —</option>
                                            @foreach ($this->employeeOptions as $e)
                                                <option value="{{ $e->employee_id }}" @selected($currentEmp?->employee_id === $e->employee_id)>{{ $e->full_name }}</option>
                                            @endforeach
                                        </select>
                                    </template>
                                @endif
                            </td>

                            <td class="text-right font-mono">{{ number_format((float) $op->qty, 0) }}</td>
                            <td class="text-right font-mono">{{ $op->rate_band_value ? number_format((float) $op->rate_band_value, 0) : '—' }}</td>

                            {{-- Est Mulai: klik 2x reveal <input datetime-local>, sama alasan x-if --}}
                            <td @dblclick="if (editable && hasSchedule) startEdit('start')">
                                <span x-show="!editingStart" x-text="startLabel" class="border-b border-dashed border-transparent hover:border-[#c7cfda]">{{ $startLabel }}</span>
                                @if ($editable && $op->target_start)
                                    <template x-if="editingStart">
                                        <input x-ref="startInput" type="datetime-local"
                                            value="{{ $op->target_start->format('Y-m-d\TH:i') }}"
                                            x-on:change="commitStart($event.target.value)" x-on:blur="editingStart = false"
                                            class="w-full px-1.5 py-1 border border-accent rounded text-[11px]">
                                    </template>
                                @endif
                            </td>

                            {{-- Est Selesai: sama pola --}}
                            <td @dblclick="if (editable && hasSchedule) startEdit('finish')">
                                <span x-show="!editingFinish" x-text="finishLabel" class="border-b border-dashed border-transparent hover:border-[#c7cfda]">{{ $finishLabel }}</span>
                                @if ($editable && $op->target_start)
                                    <template x-if="editingFinish">
                                        <input x-ref="finishInput" type="datetime-local"
                                            value="{{ $op->target_finish?->format('Y-m-d\TH:i') }}"
                                            x-on:change="commitFinish($event.target.value)" x-on:blur="editingFinish = false"
                                            class="w-full px-1.5 py-1 border border-accent rounded text-[11px]">
                                    </template>
                                @endif
                            </td>

                            <td><span class="text-[9.5px] font-bold px-1.5 py-0.5 rounded-full uppercase {{ $this->statusBadgeClasses($op->task_status) }}">{{ $op->task_status }}</span></td>
                            <td>{{ $op->wo?->due_date?->format('d/m/Y') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div x-show="toastShow" x-transition x-cloak
        class="fixed bottom-5 left-1/2 -translate-x-1/2 bg-[#1f2328] text-white text-xs font-medium px-4 py-2.5 rounded-md shadow-lg z-[200] max-w-[80vw] text-center"
        x-text="toastMsg"></div>

    {{-- Popup WO Detail -- iframe di atas Spreadsheet (halaman ini TIDAK pernah
         ditinggalkan, jadi tak ada masalah "Back tak balik ke Spreadsheet").
         Tutup = reload halaman ini (bukan cuma tutup modal) supaya perubahan
         yg mungkin dibuat di WO Detail (operator/waktu/priority dll) langsung
         kelihatan di sheet -- state DataTables (halaman/sort/filter) tetap
         terjaga lewat `stateSave` (lihat @script). --}}
    <div x-show="woModalUrl" x-cloak x-transition.opacity
        class="fixed inset-0 bg-black/60 z-[300] flex items-center justify-center p-4 sm:p-8"
        x-on:click.self="window.location.reload()">
        <div class="bg-white rounded-lg w-full h-full max-w-[1400px] relative shadow-2xl flex flex-col overflow-hidden">
            <div class="flex items-center justify-between bg-navy px-4 py-2 shrink-0">
                <span class="text-white text-[11px] font-bold uppercase tracking-wide">📋 {{ t('WO Detail') }}</span>
                <button type="button" x-on:click="window.location.reload()"
                    class="text-white text-[11px] font-bold uppercase tracking-wide bg-white/10 hover:bg-white/20 rounded-full px-3 py-1 border-none cursor-pointer">
                    ✕ {{ t('Close') }}
                </button>
            </div>
            <iframe x-show="woModalUrl" :src="woModalUrl" class="w-full grow border-0"></iframe>
        </div>
    </div>
</div>

@script
<script>
    let mpsSheetTable = null;

    function escapeRegex(v) {
        return v.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function initMpsSheet() {
        const el = document.getElementById('mps-sheet');
        if (! el || typeof DataTable === 'undefined') return;

        mpsSheetTable = new DataTable(el, {
            keys: true,
            paging: true,
            pageLength: 50,
            lengthMenu: [25, 50, 100, 250],
            order: [],
            orderCellsTop: true, // baris sort tetap header pertama, baris ke-2 dikhususkan utk filter
            stateSave: true,
            stateDuration: -1, // localStorage, bukan cookie/session -- bertahan lintas kunjungan
            language: {
                search: @js('🔎 '.t('Search (all columns):')),
                lengthMenu: @js(t('Show _MENU_ rows')),
                info: @js(t('Rows _START_–_END_ of _TOTAL_')),
                paginate: { previous: '‹', next: '›' },
            },
        });

        // Filter per kolom ala Excel AutoFilter -- dropdown utk kolom
        // kategorikal (exact match via regex anchor), teks bebas utk sisanya.
        el.querySelectorAll('.mps-filter-row .mps-filter').forEach((control) => {
            const colIndex = parseInt(control.dataset.col, 10);
            const column = mpsSheetTable.column(colIndex);
            const exact = control.dataset.exact === '1';

            control.addEventListener(control.tagName === 'SELECT' ? 'change' : 'keyup', () => {
                const value = control.value;
                if (! value) {
                    column.search('').draw();
                } else if (exact) {
                    column.search('^' + escapeRegex(value) + '$', true, false).draw();
                } else {
                    column.search(value).draw();
                }
            });
        });
    }

    function refreshRowIndex(tr) {
        if (mpsSheetTable) {
            mpsSheetTable.row(tr).invalidate('dom').draw(false);
        }
    }

    Alpine.data('mpsRow', ({ opId, editable, isMachine, hasSchedule, machineLabel, operatorLabel, startLabel, finishLabel }) => ({
        opId, editable, isMachine, hasSchedule, machineLabel, operatorLabel, startLabel, finishLabel,
        editingMachine: false, editingOperator: false, editingStart: false, editingFinish: false,

        startEdit(field) {
            this.editingMachine = field === 'machine';
            this.editingOperator = field === 'operator';
            this.editingStart = field === 'start';
            this.editingFinish = field === 'finish';

            this.$nextTick(() => {
                const refName = { machine: 'machineSelect', operator: 'operatorSelect', start: 'startInput', finish: 'finishInput' }[field];
                const el = this.$refs[refName];
                el?.focus();
                el?.showPicker?.();
            });
        },

        async commitMachine(value) {
            this.editingMachine = false;
            if (value === '') return;
            const r = await this.$wire.call('updateMachine', this.opId, value);
            if (r && r.ok) {
                this.machineLabel = r.machineLabel ?? this.machineLabel;
                if (r.startLabel !== undefined) {
                    this.startLabel = r.startLabel;
                    this.finishLabel = r.finishLabel;
                    this.hasSchedule = true;
                }
            }
            this.$nextTick(() => refreshRowIndex(this.$el));
        },

        async commitOperator(value) {
            this.editingOperator = false;
            const r = await this.$wire.call('updateOperator', this.opId, value === '' ? null : value);
            if (r && r.ok) {
                this.operatorLabel = r.operatorLabel ?? this.operatorLabel;
            }
            this.$nextTick(() => refreshRowIndex(this.$el));
        },

        async commitStart(value) {
            this.editingStart = false;
            if (! value) return;
            const r = await this.$wire.call('updateStart', this.opId, value);
            if (r && r.ok) {
                this.startLabel = r.startLabel;
                this.finishLabel = r.finishLabel;
            }
            this.$nextTick(() => refreshRowIndex(this.$el));
        },

        async commitFinish(value) {
            this.editingFinish = false;
            if (! value) return;
            const r = await this.$wire.call('updateFinish', this.opId, value);
            if (r && r.ok) {
                this.finishLabel = r.finishLabel;
            }
            this.$nextTick(() => refreshRowIndex(this.$el));
        },
    }));

    initMpsSheet();
</script>
@endscript
