<x-dl::grid id="grid-md" :rows="\App\Support\DemoData::purchaseOrders()" actions="worklist" :active-key="null" search="Cari no. PO, supplier..."
    :columns="[
            ['key' => 'po_no', 'label' => 'No. PO'],
            ['key' => 'po_date', 'label' => 'Tanggal', 'type' => 'date'],
            ['key' => 'supplier', 'label' => 'Supplier'],
            ['key' => 'items', 'label' => 'Item', 'type' => 'number', 'sum' => true],
            ['key' => 'total', 'label' => 'Total', 'type' => 'money', 'sum' => true],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['Draft' => 'neutral', 'Confirmed' => 'success', 'Cancelled' => 'danger', 'Closed' => 'info']],
        ]"
    :detail="['key' => 'lines', 'title' => 'Item Pesanan', 'columns' => [['key' => 'item', 'label' => 'Kode'], ['key' => 'item_label', 'label' => 'Nama Item'], ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'], ['key' => 'uom', 'label' => 'Satuan'], ['key' => 'price', 'label' => 'Harga', 'type' => 'money'], ['key' => 'subtotal', 'label' => 'Subtotal', 'type' => 'money']]]" />
