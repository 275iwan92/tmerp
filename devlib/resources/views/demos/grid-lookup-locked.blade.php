{{-- 'lockSaved' => true: kode yang sudah tersimpan terkunci (abu-abu); baris baru (+ Row) kodenya masih bisa diisi. --}}
<x-dl::lookup-grid :rows="[
        ['id' => 1, 'code' => 'BAHAN BAKU', 'description' => 'Paper, board, ink, film', 'active' => true],
        ['id' => 2, 'code' => 'BANK', 'description' => 'Banks (payroll account lookup)', 'active' => true],
        ['id' => 3, 'code' => 'EKSPEDISI', 'description' => 'Forwarders / trucking', 'active' => true],
        ['id' => 4, 'code' => 'JASA', 'description' => 'Services and subcontractors', 'active' => true],
        ['id' => 5, 'code' => 'ATK', 'description' => 'Office supplies', 'active' => false],
    ]"
    :columns="[
        ['key' => 'code', 'label' => 'Category Code', 'required' => true, 'unique' => true, 'lockSaved' => true, 'case' => 'uppercase', 'width' => '200px'],
        ['key' => 'description', 'label' => 'Description'],
        ['key' => 'active', 'label' => 'Active', 'type' => 'check', 'width' => '70px'],
    ]" />
