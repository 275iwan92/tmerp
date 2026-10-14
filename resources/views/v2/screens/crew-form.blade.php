{{--
 REMARK
 Tujuan       : Form Assign Crew: satu mesin (dari grid), satu kotak crew per shift produksi aktif di Master Shift (jumlahnya ikut master, tanpa Delete); di tiap kotak shift bisa dipilih (urutan bebas, satu shift dipakai sekali), nama crew terbentuk dari nickname + shift, sampai 10 anggota dengan peran.
 Sumber hulu  : prod_machine (nickname), emp_shift (Master Shift), karyawan produksi (MachineCrewService::employees), prod_machine_crew(+period+member).
 Input        : Query machine (wajib); isian periode dari-sampai (berlaku untuk semua kotak); per kotak: shift (crews[i][shift_id]) dan anggota + peran (x-dl::member-list crews[i][members]).
 Output       : POST v2.crew.save -> MachineCrewService::saveMachine (semua kotak atau tidak sama sekali): kotak berisi = crew shift itu dibuat / diperbarui; kotak kosong = crew shift itu dinonaktifkan.
                 Pesan merah: nickname kosong, shift kembar, periode bertabrakan, lebih dari 10 orang, orang bentrok di crew lain. Memilih shift yang sudah dipakai kotak lain = kedua kotak bertukar shift (tanpa error).
                 Tombol: Undo mati sampai ada perubahan; Back bertanya bila ada perubahan.
 Dipakai oleh : Dibuka dari grid Assign Crew (v2:crew).
 Status       : Aktif (dummy). Mewarisi x-dl::form panel=top kind=master (Undo, Save, Back + konfirmasinya dari objek); layout tiga kotak = pilihan Jo 14-Okt-2026 (alternatif A) dengan pilihan shift per kotak.
--}}
@php
    use App\Services\Scheduling\Crew\MachineCrewService as Crews;
    use App\Support\V2\Src;

    $machine = Crews::assignableMachines()->findOrFail((int) request('machine'));
    $today = now()->toDateString();
    $shifts = Crews::shifts();
    $nick = trim((string) $machine->nickname);
    $nickText = $nick !== '' ? $nick : t('Fill in nickname');
    $employees = Crews::employees();
    $src = Src::of('tm2mst.prod_machine_crew', 'tm2mst.prod_machine_crew_period', 'tm2mst.prod_machine_crew_member');

    // Satu kotak per shift produksi aktif di Master Shift (jumlah kotak mengikuti master, tanpa batas tetap); isi dari input terakhir (bila simpan gagal) atau dari crew dan periode yang berlaku.
    $sent = old('crews');
    $boxes = [];
    $period = null;
    foreach (array_keys($shifts) as $i => $shiftId) {
        $crew = Crews::crewFor($machine, $shiftId)?->load('periods.members');
        $p = $crew?->periods->first(fn ($x) => $x->covers($today)) ?? $crew?->periods->last();
        $period ??= $p;
        $rows = $sent !== null
            ? collect($sent[$i]['members'] ?? [])->filter(fn ($m) => ! empty($m['employee_id']))->map(fn ($m) => ['id' => (string) $m['employee_id'], 'role' => $m['role'] ?? Crews::ROLES[1]])->values()->all()
            : ($p ? $p->members->map(fn ($m) => ['id' => (string) $m->employee_id, 'role' => $m->role])->all() : []);
        $boxes[] = ['shift' => (int) ($sent[$i]['shift_id'] ?? $shiftId), 'rows' => $rows, 'exists' => (bool) $crew];
    }
    $from = old('valid_from', $period?->valid_from->toDateString() ?? $today);
    $to = old('valid_to', $period?->valid_to?->toDateString());
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif

<x-dl::form id="crew-form" :title="raw($machine->machine_code.' · '.$machine->machine_name)" panel="top" kind="master" :status="collect($boxes)->contains('exists', true) ? 'active' : 'new'"
    :hide="['deactivate', 'activate', 'new', 'delete']" :action="route('v2.crew.save')" :back="route('v2.screen', 'crew')" :hidden="['machine' => $machine->machine_id]">
    <x-dl::form.row :cols="4">
        <x-dl::field.date name="valid_from" label="Period: from date" required :value="$from" :source="$src" />
        <x-dl::field.date name="valid_to" label="Until date (empty = open)" :value="$to" :source="$src" />
    </x-dl::form.row>
    <x-dl::form.row :cols="count($boxes)">
        @foreach ($boxes as $i => $b)
            <x-dl::box>
                <x-dl::field.select name="crews[{{ $i }}][shift_id]" label="Shift" required :options="$shifts" :value="$b['shift']" :source="$src" data-dl-swap="crew-shift" data-dl-was="{{ $b['shift'] }}" />
                <x-dl::field.display class="dl-display--plain dl-display--lead" label="Crew name" :value="raw($nickText.' - '.$shifts[$b['shift']])" :source="$src" data-dl-echo="crews[{{ $i }}][shift_id]" data-dl-echo-prefix="{{ $nickText }} -" />
                <x-dl::member-list :name="'crews['.$i.'][members]'" title="Choose user names · maximum 10 rows" :rows="$b['rows']" :options="$employees" :roles="Crews::ROLES" :max="Crews::MAX_MEMBERS" />
            </x-dl::box>
        @endforeach
    </x-dl::form.row>
    @if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif
</x-dl::form>
