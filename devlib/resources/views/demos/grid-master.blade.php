<x-dl::grid id="employee" :rows="\App\Support\DemoData::employees()" search="Cari nama, kode, jabatan..."
    :columns="[
        ['key' => 'name', 'label' => 'Pegawai', 'type' => 'identity', 'code' => 'code'],
        ['key' => 'dept', 'label' => 'Departemen'],
        ['key' => 'position', 'label' => 'Jabatan'],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['TETAP' => 'success', 'KONTRAK' => 'info', 'HARIAN' => 'warn']],
        ['key' => 'join_date', 'label' => 'Tgl Masuk', 'type' => 'date'],
        ['key' => 'city', 'label' => 'Kota'],
        ['key' => 'phone', 'label' => 'No. HP', 'visible' => false],
    ]"
    :stats="[
        ['label' => 'Total Pegawai'],
        ['label' => 'Aktif', 'where' => ['active' => true], 'tone' => 'success'],
        ['label' => 'Kontrak', 'where' => ['status' => 'KONTRAK'], 'tone' => 'warn'],
        ['label' => 'Nonaktif', 'where' => ['active' => false], 'tone' => 'danger'],
    ]"
    :filters="['dept' => 'Departemen', 'status' => 'Status', 'city' => 'Kota']"
    :bulk="[['key' => 'export', 'label' => 'Export terpilih', 'icon' => 'export'], ['key' => 'deactivate', 'label' => 'Nonaktifkan', 'icon' => 'deactivate', 'variant' => 'danger', 'confirm' => 'Nonaktifkan semua baris terpilih?']]"
    :expand="['phone', 'city', 'join_date']" />
