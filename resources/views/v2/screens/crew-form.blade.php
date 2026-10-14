{{--
 REMARK
 Tujuan       : Form Assign Crew (draft sederhana Jo): nama crew, periode dari-sampai, sampai 10 anggota dengan peran; satu crew = satu mesin + satu shift.
 Sumber hulu  : prod_machine (nickname), emp_shift (Master Shift), karyawan produksi (MachineCrewService::employees), prod_machine_crew(+period+member).
 Input        : Query machine (wajib), shift_id (bawaan = shift pertama yang sudah punya crew), period (id atau new); isian tanggal periode, anggota + peran (x-dl::member-list).
 Output       : POST v2.crew.save (aturan di MachineCrewService): crew dibuat bila belum ada, periode + anggota disimpan; pesan merah bila nickname kosong, crew ke-5, periode bertabrakan, lebih dari 10 orang.
                 Tombol: Undo mati sampai ada perubahan; Delete mati bila crew belum ada; + New period mati bila belum ada periode tersimpan; Back / + New period bertanya bila ada perubahan.
 Dipakai oleh : Dibuka dari grid Assign Crew (v2:crew).
 Status       : Aktif (dummy). Mewarisi x-dl::form panel=top kind=master (tombol Undo, Save, Back + konfirmasinya dari objek); Delete di slot special = crew dinonaktifkan.
--}}
@php
    use App\Models\Master\ProdMachineCrew;
    use App\Services\Scheduling\Crew\MachineCrewService as Crews;
    use App\Support\V2\Src;

    $machine = Crews::assignableMachines()->findOrFail((int) request('machine'));
    $today = now()->toDateString();
    $shifts = Crews::shifts();
    $shiftId = (int) request('shift_id');
    if (! isset($shifts[$shiftId])) {
        $shiftId = (int) (ProdMachineCrew::query()->where('machine_id', $machine->machine_id)->where('activate', true)->orderBy('crew_index')->value('shift_id') ?: array_key_first($shifts));
    }
    $crew = Crews::crewFor($machine, $shiftId);
    $crew?->load('periods.members');
    $periodKey = (string) request('period', '');
    $period = null;
    if ($crew) {
        $period = ctype_digit($periodKey) ? $crew->periods->firstWhere('id_crew_period', (int) $periodKey) : null;
        if (! $period && $periodKey !== 'new') {
            $period = $crew->periods->first(fn ($p) => $p->covers($today)) ?? $crew->periods->last();
        }
    }
    $nick = trim((string) $machine->nickname);
    $crewName = $crew ? $crew->name() : ($nick !== '' ? $nick : t('Fill in nickname')).' - '.$shifts[$shiftId];
    $employees = Crews::employees();
    $src = Src::of('tm2mst.prod_machine_crew', 'tm2mst.prod_machine_crew_period', 'tm2mst.prod_machine_crew_member');
    $url = fn (array $more = []) => route('v2.screen', 'crew-form').'?'.http_build_query(['machine' => $machine->machine_id, 'shift_id' => $shiftId] + $more);
    $rows = old('members') !== null
        ? collect(old('members'))->filter(fn ($m) => ! empty($m['employee_id']))->map(fn ($m) => ['id' => (string) $m['employee_id'], 'role' => $m['role'] ?? Crews::ROLES[1]])->values()->all()
        : ($period ? $period->members->map(fn ($m) => ['id' => (string) $m->employee_id, 'role' => $m->role])->all() : []);
    $from = old('valid_from', $period?->valid_from->toDateString() ?? ($crew?->periods->max('valid_to')?->addDay()->toDateString() ?? $today));
    $to = old('valid_to', $period?->valid_to?->toDateString());
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif

<x-dl::form id="crew-form" :title="raw($machine->machine_code.' · '.$machine->machine_name)" panel="top" kind="master" :status="$crew ? 'active' : 'new'"
    :hide="['deactivate', 'activate', 'new']" :action="route('v2.crew.save')" :back="route('v2.screen', 'crew')"
    :hidden="['machine' => $machine->machine_id, 'shift_id' => $shiftId, 'period' => $period ? $period->id_crew_period : 'new']">
    <x-slot:special>
        <x-dl::button size="sm" preset="delete" label="Delete" data-f-act="delete" :disabled="! $crew" />
    </x-slot:special>
    <x-dl::form.row :cols="4">
        <x-dl::field.display class="dl-display--plain" label="Crew name" :value="raw($crewName)" :source="$src" />
        <x-dl::button variant="plain" size="sm" label="+ New period" :href="$period ? $url(['period' => 'new']) : null" :disabled="! $period" data-dl-leave />
        <x-dl::field.date name="valid_from" label="Period: from date" required :value="$from" :source="$src" />
        <x-dl::field.date name="valid_to" label="Until date (empty = open)" :value="$to" :source="$src" />
    </x-dl::form.row>
    <x-dl::hint :italic="true">{{ t('Format: Nickname Machine - Shift Name') }}</x-dl::hint>
    <x-dl::member-list name="members" title="Choose user names · maximum 10 rows" :rows="$rows" :options="$employees" :roles="Crews::ROLES" :max="Crews::MAX_MEMBERS" />
    @if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif
</x-dl::form>
