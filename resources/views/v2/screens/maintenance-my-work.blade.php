{{-- My Work (teknisi, HP) -- mockup maintenance_v2.jpg bagian 5 kanan. WO Confirmed milik teknisi yang login (emp dari sec_user.employee_id);
     kepala maintenance / admin melihat semua WO terbuka. Tombol Start / Hold / Resume / Done langsung dari kartu, isi checklist & spare
     part lewat Open (form WO). Report Problem = Maintenance Request baru. --}}
@php
    use App\Models\Prd\MntWo;
    use App\Services\Maintenance\MaintenanceService;
    use App\Support\V2\Data\Maintenance;

    $me = auth()->user();
    $all = MaintenanceService::canDecide($me) || ! $me->employee_id;
    $wos = MntWo::query()->where('stat_confirm', true)->where('stat_cancel', false)->where('stat_close', false)
        ->when(! $all, fn ($q) => $q->where('assigned_to', $me->employee_id))
        ->orderByRaw("FIELD(work_status, 'IN_PROGRESS', 'WAITING_PART', 'SCHEDULED', 'DONE')")->orderBy('priority')->orderBy('planned_start')->limit(100)->get();
    $machines = Maintenance::machines();
    $tech = Maintenance::employeeNames($wos->pluck('assigned_to'));
    $open = route('v2.screen', 'maintenance-wo').'?id=';
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

<x-dl::action-bar>
    <x-dl::button variant="danger" icon="fa-triangle-exclamation" label="Report Problem" :href="route('v2.screen', 'maintenance-request').'?new=1'" />
</x-dl::action-bar>

@forelse ($wos as $w)
    <form method="post" action="{{ route('v2.mnt.wo.save', $w->id_wo) }}">
        @csrf
        <input type="hidden" name="back" value="my-work">
        <x-dl::card :title="raw($w->doc_no.' · '.$w->priority.' · '.(MntWo::WORK[$w->work_status] ?? $w->work_status))"
            :description="raw(($machines[$w->machine_id] ?? '#'.$w->machine_id).' — '.$w->description.' · '.Maintenance::stamp($w->planned_start).($all ? ' · '.($tech[$w->assigned_to] ?? t('unassigned')) : ''))">
            <x-slot:actions>
                @if ($w->work_status === 'SCHEDULED')
                    <x-dl::button size="sm" variant="success" icon="fa-play" label="Start" type="submit" name="action" value="start" />
                @elseif ($w->work_status === 'IN_PROGRESS')
                    <x-dl::button size="sm" variant="secondary" icon="hold" label="Hold" type="submit" name="action" value="hold" />
                    <x-dl::button size="sm" variant="success" icon="fa-check" label="Done" type="submit" name="action" value="done" />
                @elseif ($w->work_status === 'WAITING_PART')
                    <x-dl::button size="sm" variant="secondary" icon="fa-play" label="Resume" type="submit" name="action" value="resume" />
                @endif
                <x-dl::button size="sm" variant="secondary" label="Open" :href="$open.$w->id_wo" />
            </x-slot:actions>
        </x-dl::card>
    </form>
@empty
    <x-dl::empty-state title="No open work orders" message="Nothing scheduled for you right now." icon="fa-screwdriver-wrench" />
@endforelse
