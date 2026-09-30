<x-dl::grid id="grid-group" mode="report" :rows="\App\Support\DemoData::purchaseOrders()" actions="worklist" :active-key="null" group-by="supplier" search="Cari no. PO..."
    :groups="['supplier' => 'Supplier', 'status' => 'Status', 'requester' => 'Pemohon']" :columns="[
            ['key' => 'po_no', 'label' => 'No. PO'],
            ['key' => 'po_date', 'label' => 'Tanggal', 'type' => 'date'],
            ['key' => 'supplier', 'label' => 'Supplier'],
            ['key' => 'items', 'label' => 'Item', 'type' => 'number', 'sum' => true],
            ['key' => 'total', 'label' => 'Total', 'type' => 'money', 'sum' => true],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['Draft' => 'neutral', 'Confirmed' => 'success', 'Cancelled' => 'danger', 'Closed' => 'info']],
        ]" />
