{{-- Form khusus Assign Crew: layar menyerahkan satu array. Satu kotak per shift Master Shift; dropdown Shift per kotak (memilih shift yang dipakai kotak lain = keduanya bertukar).
     Di aplikasi: <x-mp::production.crew-form :crew="CrewForm::desk($machine)" /> --}}
<div class="dl-demo">
    <x-mp::production.crew-form :crew="[
        'title' => 'DIE-01 · Ruian Die Cutter', 'status' => 'active', 'action' => '#', 'back' => '#', 'hidden' => ['machine' => 1],
        'from' => '2026-10-14', 'to' => null, 'nick' => 'Plong1',
        'shifts' => [10 => 'Reguler 07:30-16:30', 1 => 'TM Production Shift 1', 2 => 'TM Production Shift 2'],
        'employees' => [27 => 'TM-00027 · ACHMAD SYARIF', 43 => 'TM-00043 · AGI PURNOMO', 104 => 'TM-00104 · ADE MAHENDRA', 9 => 'TM-00009 · AGUS BUDI SUSANTO', 16 => 'TM-00016 · AHMAD ALWI NASTAIN', 115 => 'TM-00115 · AGUS MUJIONO', 38 => 'TM-00038 · AJI SETIAWAN', 41 => 'TM-00041 · ALDI ARDIANSYAH'],
        'roles' => ['Main operator', 'Co-operator', 'Helper', 'Kabag'], 'max' => 10,
        'boxes' => [
            ['shift' => 10, 'rows' => [['id' => '27', 'role' => 'Main operator'], ['id' => '43', 'role' => 'Helper'], ['id' => '104', 'role' => 'Co-operator']], 'exists' => true],
            ['shift' => 1, 'rows' => [['id' => '9', 'role' => 'Main operator'], ['id' => '16', 'role' => 'Helper'], ['id' => '115', 'role' => 'Co-operator']], 'exists' => true],
            ['shift' => 2, 'rows' => [], 'exists' => false],
        ],
    ]" />
</div>
