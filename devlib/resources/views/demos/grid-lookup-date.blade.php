<x-dl::lookup-grid :rows="[
        ['id' => 1, 'date' => '2026-01-01', 'name' => 'Tahun Baru Masehi', 'paid' => true, 'active' => true],
        ['id' => 2, 'date' => '2026-05-01', 'name' => 'Hari Buruh Internasional', 'paid' => true, 'active' => true],
        ['id' => 3, 'date' => '2026-08-17', 'name' => 'Hari Kemerdekaan RI', 'paid' => true, 'active' => true],
        ['id' => 4, 'date' => '2026-12-24', 'name' => 'Cuti Bersama Natal', 'paid' => false, 'active' => true],
        ['id' => 5, 'date' => '2026-12-25', 'name' => 'Hari Raya Natal', 'paid' => true, 'active' => true],
    ]"
    :columns="[
        ['key' => 'date', 'label' => 'Tanggal', 'type' => 'date', 'required' => true, 'unique' => true, 'width' => '130px'],
        ['key' => 'name', 'label' => 'Keterangan', 'required' => true, 'case' => 'capitalize'],
        ['key' => 'paid', 'label' => 'Dibayar', 'type' => 'check', 'width' => '80px'],
        ['key' => 'active', 'label' => 'Aktif', 'type' => 'check', 'width' => '70px'],
    ]" />
