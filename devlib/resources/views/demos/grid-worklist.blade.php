<x-dl::grid id="po-worklist" :rows="\App\Support\DemoData::purchaseOrders()" actions="worklist" :active-key="null" search="Cari no. PO, supplier..."
    :columns="[
        ['key' => 'po_no', 'label' => 'No. PO'],
        ['key' => 'po_date', 'label' => 'Tanggal', 'type' => 'date'],
        ['key' => 'supplier', 'label' => 'Supplier'],
        ['key' => 'items', 'label' => 'Item', 'type' => 'number', 'sum' => true],
        ['key' => 'total', 'label' => 'Total', 'type' => 'money', 'sum' => true],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['DRAFT' => 'neutral', 'SUBMITTED' => 'warn', 'APPROVED' => 'success', 'CLOSED' => 'info', 'CANCELLED' => 'danger']],
        ['key' => 'note', 'label' => 'Catatan'],
    ]"
    :tabs="[
        ['label' => 'Semua'],
        ['label' => 'Perlu Tindakan', 'where' => ['status' => ['DRAFT', 'SUBMITTED']]],
        ['label' => 'Disetujui', 'where' => ['status' => 'APPROVED']],
        ['label' => 'Selesai / Batal', 'where' => ['status' => ['CLOSED', 'CANCELLED']]],
    ]"
    :filters="['supplier' => 'Supplier', 'requester' => 'Pemohon']"
    :groups="['supplier' => 'Supplier', 'status' => 'Status']"
    :expand="['requester', 'note']" />
