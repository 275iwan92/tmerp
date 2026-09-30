<x-dl::drilldown link="supplier" title="Ringkasan per Supplier">
    <x-slot:summary>
        <x-dl::grid id="grid-sum" mode="report" :rows="\App\Support\DemoData::supplierSummary()" actions="worklist" :active-key="null" :new-record="false" search="Cari supplier..."
            :columns="[['key' => 'name', 'label' => 'Supplier', 'type' => 'identity'], ['key' => 'po', 'label' => 'Jumlah PO', 'type' => 'number', 'sum' => true], ['key' => 'open', 'label' => 'PO Terbuka', 'type' => 'number', 'sum' => true], ['key' => 'items', 'label' => 'Item', 'type' => 'number', 'sum' => true], ['key' => 'total', 'label' => 'Nilai PO', 'type' => 'money', 'sum' => true]]" />
    </x-slot:summary>
    <x-dl::grid id="grid-sum-det" mode="report" :rows="\App\Support\DemoData::purchaseOrders()" actions="worklist" :active-key="null" :new-record="false" search="Cari no. PO..." :columns="[
            ['key' => 'po_no', 'label' => 'No. PO'],
            ['key' => 'po_date', 'label' => 'Tanggal', 'type' => 'date'],
            ['key' => 'supplier', 'label' => 'Supplier'],
            ['key' => 'items', 'label' => 'Item', 'type' => 'number', 'sum' => true],
            ['key' => 'total', 'label' => 'Total', 'type' => 'money', 'sum' => true],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['Draft' => 'neutral', 'Confirmed' => 'success', 'Cancelled' => 'danger', 'Closed' => 'info']],
        ]" />
</x-dl::drilldown>
