@php
    // Nama fiktif. Posisi/unit mengikuti mockup; certs = unit yang sudah bersertifikat (tanpa certs = semua unit).
    $ppl = [
        [1, 'REZA', 'UV'], [2, 'ANDRE', 'UV'], [3, 'MAULANA', 'Laminating'], [4, 'FIKRI', 'Laminating'], [5, 'HARIS', 'Laminating'], [6, 'DIMAS', 'Laminating'],
        [7, 'ALIF', 'Plong'], [8, 'RIZAL', 'Plong'], [9, 'YUSUF HADI', 'Plong'], [10, 'ILHAM', 'Finishing'], [11, 'NINING', 'Finishing'], [12, 'MARNI', 'Finishing'],
        [13, 'SUSI', 'Finishing'], [14, 'TATI', 'Finishing'], [15, 'SULASTRI', 'Finishing'], [16, 'IKA', 'Finishing'], [17, 'FAHMI', 'Packing'], [18, 'SAIFUL', 'Packing'],
        [19, 'SUMIATI', 'Manual'], [20, 'SRI WAHYU', 'Manual'], [21, 'RATNA', 'Manual'], [22, 'WAHYU TRI', 'Plong'], [23, 'ZAINAL ARIF', 'Plong'], [24, 'LILIS', 'Manual'],
        [25, 'GUNAWAN', 'Laminating'], [26, 'SETYO', 'Packing'],
    ];
    $people = array_map(fn ($p) => ['id' => $p[0], 'name' => $p[1], 'group' => $p[2]], $ppl);
    $people[] = ['id' => 30, 'name' => 'AGUS', 'group' => 'Borongan', 'borongan' => true];
    $people[] = ['id' => 31, 'name' => 'MUHSIN', 'group' => 'Borongan', 'borongan' => true];
    $people[] = ['id' => 32, 'name' => 'RENI', 'group' => 'Borongan', 'borongan' => true];
    $people[] = ['id' => 33, 'name' => 'ENDAH', 'group' => 'Borongan', 'borongan' => true];
    $people[] = ['id' => 34, 'name' => 'MIRA', 'group' => 'Borongan', 'borongan' => true];
    $people[1]['certs'] = ['UV']; $people[22]['certs'] = ['LAMINATING']; // Zainal Arif (Plong) belum bersertifikat PLONG 2
    $all = [0, 1, 2, 3, 4, 5];
    $a = fn ($pos, $sh, $id, $days = null) => ['pos' => $pos, 'shift' => $sh, 'id' => $id, 'days' => $days ?? $all];
    $assign = [
        $a('UV', 'S1', 1), $a('UV', 'S2', 2),
        $a('LAM', 'S1', 3), $a('LAM', 'S1', 4), $a('LAM', 'S2', 5), $a('LAM', 'S2', 6),
        $a('PLONG2', 'S1', 7), $a('PLONG2', 'S2', 8, [0, 1, 2]), $a('PLONG2', 'S2', 9, [3, 4, 5]),
        $a('FG3', 'S1', 10), $a('FG3', 'S1', 11), $a('FG3', 'S1', 12), $a('FG3', 'S1', 13),
        $a('FG3', 'S2', 30), $a('FG3', 'S2', 14), $a('FG3', 'S2', 15), $a('FG3', 'S2', 16),
        $a('SHRINK', 'S1', 17), $a('SHRINK', 'S1', 31), $a('SHRINK', 'S2', 18), $a('SHRINK', 'S2', 32),
        $a('MANUAL', 'REG', 19), $a('MANUAL', 'REG', 20), $a('MANUAL', 'REG', 33, [0]), $a('MANUAL', 'REG', 34, [0]), $a('MANUAL', 'REG', 11, [0]),
        $a('HITUNG', 'REG', 21),
    ];
@endphp
<x-dl::formation week="MINGGU 38" range="14 Sep – 19 Sep 2026" :today="4"
    :shifts="['S1' => ['label' => 'Shift 1', 'time' => '05:00–13:15', 'tone' => 'blue'], 'S2' => ['label' => 'Shift 2', 'time' => '13:15–21:15', 'tone' => 'amber'], 'REG' => ['label' => 'Reguler', 'time' => '07:30–16:30', 'tone' => 'slate']]"
    :days="[['key' => 'Sen', 'date' => '14 Sep'], ['key' => 'Sel', 'date' => '15 Sep'], ['key' => 'Rab', 'date' => '16 Sep'], ['key' => 'Kam', 'date' => '17 Sep'], ['key' => 'Jum', 'date' => '18 Sep'], ['key' => 'Sab', 'date' => '19 Sep']]"
    :positions="[
        ['code' => 'UV', 'name' => 'UV', 'shifts' => ['S1', 'S2'], 'group' => 'UV'],
        ['code' => 'LILIN', 'name' => 'LILIN', 'shifts' => [], 'group' => 'Finishing'],
        ['code' => 'LAM', 'name' => 'LAMINATING OTOMATIS', 'shifts' => ['S1', 'S2'], 'group' => 'Laminating'],
        ['code' => 'PLONG2', 'name' => 'PLONG 2', 'shifts' => ['S1', 'S2'], 'group' => 'Plong'],
        ['code' => 'FG3', 'name' => 'FG 3', 'shifts' => ['S1', 'S2'], 'group' => 'Finishing'],
        ['code' => 'SHRINK', 'name' => 'SHRINK', 'shifts' => ['S1', 'S2'], 'group' => 'Packing'],
        ['code' => 'STITCH', 'name' => 'STITCHING', 'shifts' => [], 'group' => 'Finishing'],
        ['code' => 'MANUAL', 'name' => 'MANUAL', 'shifts' => ['REG'], 'group' => 'Manual'],
        ['code' => 'HITUNG', 'name' => 'HITUNG', 'shifts' => ['REG'], 'group' => 'Manual'],
    ]"
    :people="$people" :assign="$assign" />
