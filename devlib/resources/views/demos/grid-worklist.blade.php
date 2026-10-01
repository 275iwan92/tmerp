<x-dl::grid id="po-worklist" :rows="\App\Support\DemoData::purchaseOrders()" actions="worklist" :active-key="null" search="Cari no. PO, supplier..."
    :columns="[
        ['key' => 'po_no', 'label' => 'No. PO / Supplier', 'type' => 'doc', 'party' => 'supplier'],
        ['key' => 'po_date', 'label' => 'Tanggal', 'type' => 'date'],
        ['key' => 'requester', 'label' => 'Pemohon'],
        ['key' => 'total', 'label' => 'Total', 'type' => 'money', 'sum' => true],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['Draft' => 'neutral', 'Confirmed' => 'success', 'Cancelled' => 'danger', 'Closed' => 'info']],
        ['key' => 'note', 'label' => 'Catatan'],
    ]"
    :tabs="[
        ['label' => 'Semua'],
        ['label' => 'Draft', 'where' => ['status' => 'Draft']],
        ['label' => 'Confirmed', 'where' => ['status' => 'Confirmed']],
        ['label' => 'Closed / Cancelled', 'where' => ['status' => ['Closed', 'Cancelled']]],
    ]"
    :filters="['supplier' => 'Supplier', 'requester' => 'Pemohon']"
    :groups="['supplier' => 'Supplier', 'status' => 'Status']"
    :detail="['key' => 'lines', 'title' => 'Item', 'columns' => [
        ['key' => 'item', 'label' => 'Kode'],
        ['key' => 'item_label', 'label' => 'Nama Item', 'strong' => true],
        ['key' => 'spec', 'label' => 'Spesifikasi'],
        ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
        ['key' => 'uom', 'label' => 'Satuan'],
    ]]" />
