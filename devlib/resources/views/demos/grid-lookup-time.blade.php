<x-dl::lookup-grid :rows="[
        ['id' => 1, 'code' => 'S1', 'name' => 'Shift Pagi', 'start' => '07:00', 'end' => '15:00', 'break' => 60, 'active' => true],
        ['id' => 2, 'code' => 'S2', 'name' => 'Shift Siang', 'start' => '15:00', 'end' => '23:00', 'break' => 60, 'active' => true],
        ['id' => 3, 'code' => 'S3', 'name' => 'Shift Malam', 'start' => '23:00', 'end' => '07:00', 'break' => 60, 'active' => true],
        ['id' => 4, 'code' => 'NS', 'name' => 'Non Shift', 'start' => '08:00', 'end' => '16:30', 'break' => 30, 'active' => true],
    ]"
    :columns="[
        ['key' => 'code', 'label' => 'Kode', 'required' => true, 'unique' => true, 'case' => 'uppercase', 'width' => '90px'],
        ['key' => 'name', 'label' => 'Nama', 'required' => true, 'unique' => true, 'case' => 'capitalize'],
        ['key' => 'start', 'label' => 'Jam Masuk', 'type' => 'time', 'required' => true, 'width' => '110px'],
        ['key' => 'end', 'label' => 'Jam Pulang', 'type' => 'time', 'required' => true, 'width' => '110px'],
        ['key' => 'break', 'label' => 'Istirahat (menit)', 'type' => 'number', 'width' => '130px'],
        ['key' => 'active', 'label' => 'Aktif', 'type' => 'check', 'width' => '70px'],
    ]" />
