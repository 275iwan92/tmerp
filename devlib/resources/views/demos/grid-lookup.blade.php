<x-dl::lookup-grid :rows="[
        ['id' => 1, 'code' => 'ISL', 'name' => 'Islam', 'sort' => 1, 'active' => true],
        ['id' => 2, 'code' => 'KRS', 'name' => 'Kristen Protestan', 'sort' => 2, 'active' => true],
        ['id' => 3, 'code' => 'KAT', 'name' => 'Katolik', 'sort' => 3, 'active' => true],
        ['id' => 4, 'code' => 'HIN', 'name' => 'Hindu', 'sort' => 4, 'active' => true],
        ['id' => 5, 'code' => 'BUD', 'name' => 'Buddha', 'sort' => 5, 'active' => true],
        ['id' => 6, 'code' => 'KHC', 'name' => 'Khonghucu', 'sort' => 6, 'active' => true],
    ]"
    :columns="[
        ['key' => 'code', 'label' => 'Kode', 'required' => true, 'unique' => true, 'case' => 'uppercase', 'width' => '110px'],
        ['key' => 'name', 'label' => 'Nama', 'required' => true, 'unique' => true, 'case' => 'capitalize'],
        ['key' => 'sort', 'label' => 'Urutan', 'type' => 'number', 'width' => '90px'],
        ['key' => 'active', 'label' => 'Aktif', 'type' => 'check', 'width' => '70px'],
    ]" />
