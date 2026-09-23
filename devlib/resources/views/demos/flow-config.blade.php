<x-dl::flow-config
    :docs="['PR' => 'Purchase Requisition', 'PO' => 'Purchase Order', 'AD' => 'Stock Adjustment']"
    :roles="['HEAD_PPIC' => 'Kepala PPIC', 'HEAD_PUR' => 'Kepala Pembelian', 'OPS_MGR' => 'Operational Manager', 'HEAD_WH' => 'Kepala Gudang', 'FIN_MGR' => 'Finance Manager', 'DIREKSI' => 'Direksi']"
    :flows="[
        'PR' => [['name' => 'Kepala divisi', 'role' => 'HEAD_PPIC', 'action' => 'KNOW', 'min' => ''], ['name' => 'Persetujuan Direksi', 'role' => 'DIREKSI', 'action' => 'APPROVE', 'min' => 50000000]],
        'PO' => [['name' => 'Kepala Pembelian', 'role' => 'HEAD_PUR', 'action' => 'KNOW', 'min' => ''], ['name' => 'Operational Manager', 'role' => 'OPS_MGR', 'action' => 'APPROVE', 'min' => ''], ['name' => 'Direksi', 'role' => 'DIREKSI', 'action' => 'APPROVE', 'min' => 50000000]],
        'AD' => [['name' => 'Kepala Gudang', 'role' => 'HEAD_WH', 'action' => 'APPROVE', 'min' => ''], ['name' => 'Finance Manager', 'role' => 'FIN_MGR', 'action' => 'APPROVE', 'min' => 5000000]],
    ]" />
