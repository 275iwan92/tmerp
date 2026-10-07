@php
    $pat = fn ($a, $b, $sat = null) => ['Sen' => [$a, $b], 'Sel' => [$a, $b], 'Rab' => [$a, $b], 'Kam' => [$a, $b], 'Jum' => [$a, $b], 'Sab' => $sat ?? [$a, $b], 'Min' => null];
@endphp
<x-dl::roster-board title="KOMORI GL-440 (5 warna)" week="Minggu 37 · 07–13 Sep 2026"
    :days="[['key' => 'Sen', 'date' => '07/09'], ['key' => 'Sel', 'date' => '08/09'], ['key' => 'Rab', 'date' => '09/09'], ['key' => 'Kam', 'date' => '10/09'], ['key' => 'Jum', 'date' => '11/09'], ['key' => 'Sab', 'date' => '12/09'], ['key' => 'Min', 'date' => '13/09', 'off' => true]]"
    :shifts="[
        ['key' => 'REG', 'label' => 'Reguler', 'tone' => 'success', 'capacity' => 4, 'pattern' => $pat('07:30', '16:30', ['07:30', '15:30'])],
        ['key' => 'S1', 'label' => 'Shift 1', 'tone' => 'accent', 'capacity' => 6, 'pattern' => $pat('05:00', '13:15')],
        ['key' => 'S2', 'label' => 'Shift 2', 'tone' => 'warn', 'capacity' => 6, 'pattern' => $pat('13:15', '21:15')],
        ['key' => 'S3', 'label' => 'Shift 3', 'tone' => 'violet', 'capacity' => 4, 'pattern' => $pat('21:15', '05:00')],
    ]"
    :people="[
        ['id' => 1, 'name' => 'Satrio Kurniawan', 'code' => 'TM1001', 'role' => 'Leader', 'skills' => 4], ['id' => 2, 'name' => 'Galih Raharjo', 'code' => 'TM1004', 'role' => 'Helper', 'skills' => 2],
        ['id' => 3, 'name' => 'Taufik Handoko', 'code' => 'TM1005', 'role' => 'Helper', 'skills' => 1], ['id' => 4, 'name' => 'Budi Syahputra', 'code' => 'TM1009', 'role' => 'Operator', 'skills' => 3],
        ['id' => 5, 'name' => 'Bagus Setiawan', 'code' => 'TM1011', 'role' => 'Operator', 'skills' => 3], ['id' => 6, 'name' => 'Imam Pratama', 'code' => 'TM1013', 'role' => 'Operator', 'skills' => 2],
        ['id' => 7, 'name' => 'Andik Saputra', 'code' => 'TM1016', 'role' => 'Helper', 'skills' => 1], ['id' => 8, 'name' => 'Slamet Prasetya', 'code' => 'TM1017', 'role' => 'Helper', 'skills' => 3],
        ['id' => 9, 'name' => 'Wahyu Syahputra', 'code' => 'TM1018', 'role' => 'Helper', 'skills' => 3], ['id' => 10, 'name' => 'Agus Wibowo', 'code' => 'TM1019', 'role' => 'Operator', 'skills' => 1],
        ['id' => 11, 'name' => 'Pandu Ramadhan', 'code' => 'TM1024', 'role' => 'Leader', 'skills' => 3], ['id' => 12, 'name' => 'Eko Wibowo', 'code' => 'TM1014', 'role' => 'Helper', 'skills' => 2],
    ]"
    :assign="[1 => 'S1', 2 => 'S1', 3 => 'S1', 4 => 'S2', 5 => 'S2', 6 => 'S2', 12 => 'S2']" />

<h4 style="margin:20px 0 6px">Locked week (published): the board is read-only</h4>
<x-dl::roster-board title="Locked example" week="Week 38" :locked="true" :days="[['key' => 'Mon', 'date' => '14/09'], ['key' => 'Tue', 'date' => '15/09']]"
    :shifts="[['key' => 'REG', 'label' => 'Regular', 'tone' => 'success', 'capacity' => 2, 'pattern' => ['Mon' => ['07:30', '16:30'], 'Tue' => ['07:30', '16:30']]]]"
    :people="[['id' => 1, 'name' => 'Satrio Kurniawan', 'code' => 'TM1001', 'role' => 'Leader', 'skills' => 4], ['id' => 2, 'name' => 'Galih Raharjo', 'code' => 'TM1004', 'role' => 'Helper', 'skills' => 2]]" :assign="[1 => 'REG']" />
