<x-dl::org-chart :nodes="[
    ['id' => 1, 'parent' => null, 'title' => 'Direktur Utama', 'dept' => 'Direksi', 'people' => ['Hendra Wijaya']],
    ['id' => 2, 'parent' => 1, 'title' => 'Operational Manager', 'dept' => 'Operasional', 'people' => ['Sarah Kusuma']],
    ['id' => 3, 'parent' => 1, 'title' => 'Finance Manager', 'dept' => 'Keuangan', 'people' => ['Budi Hartono']],
    ['id' => 4, 'parent' => 2, 'title' => 'Kepala Produksi', 'dept' => 'Produksi', 'people' => ['Joko Santoso']],
    ['id' => 5, 'parent' => 2, 'title' => 'Kepala Gudang', 'dept' => 'Gudang', 'people' => ['Rina Wulandari']],
    ['id' => 6, 'parent' => 2, 'title' => 'Kepala PPIC', 'dept' => 'PPIC', 'people' => []],
    ['id' => 7, 'parent' => 4, 'title' => 'Leader Shift Cetak', 'dept' => 'Produksi', 'people' => ['Andi Pratama', 'Fajar Saputra']],
    ['id' => 8, 'parent' => 4, 'title' => 'Operator Pond', 'dept' => 'Produksi', 'people' => ['Maya Lestari', 'Oki Hidayat', 'Putri Rahma']],
    ['id' => 9, 'parent' => 5, 'title' => 'Staff Gudang', 'dept' => 'Gudang', 'people' => ['Dewi Kusuma']],
    ['id' => 10, 'parent' => 3, 'title' => 'Kasir', 'dept' => 'Keuangan', 'people' => ['Citra Santoso']],
    ['id' => 11, 'parent' => 3, 'title' => 'Staf Akuntansi', 'dept' => 'Keuangan', 'people' => []],
]"
    :departments="['Direksi', 'Gudang', 'Keuangan', 'Operasional', 'PPIC', 'Produksi']" />
