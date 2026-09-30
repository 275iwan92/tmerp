<x-dl::master title="Purchase Order" name-key="po_no" code-key="supplier" :pipeline="['Draft' => 'Draft', 'Confirmed' => 'Confirmed', 'Closed' => 'Closed']">
    <x-dl::grid id="md-po" :rows="\App\Support\DemoData::purchaseOrders()" actions="worklist" :active-key="null" search="Cari no. PO, supplier..."
        :columns="[
            ['key' => 'po_no', 'label' => 'No. PO'],
            ['key' => 'po_date', 'label' => 'Tanggal', 'type' => 'date'],
            ['key' => 'supplier', 'label' => 'Supplier'],
            ['key' => 'items', 'label' => 'Item', 'type' => 'number'],
            ['key' => 'total', 'label' => 'Total', 'type' => 'money', 'sum' => true],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['Draft' => 'neutral', 'Confirmed' => 'success', 'Cancelled' => 'danger', 'Closed' => 'info']],
        ]"
        :tabs="[['label' => 'Semua'], ['label' => 'Draft', 'where' => ['status' => 'Draft']], ['label' => 'Confirmed', 'where' => ['status' => 'Confirmed']]]" />

    <x-slot:form>
        <x-dl::form.row cols="3">
            <x-dl::field.text name="po_no" label="No. PO" code required />
            <x-dl::field.date name="po_date" label="Tanggal PO" required />
            <x-dl::field.select name="supplier" label="Supplier" :options="['PT Contoh Kertas Jaya', 'CV Tinta Nusantara', 'PT Karton Abadi', 'UD Lem Sejahtera', 'PT Plat Offset Prima']" required />
        </x-dl::form.row>
        <x-dl::form.row cols="3">
            <x-dl::field.text name="requester" label="Pemohon" />
            <x-dl::field.select name="status" label="Status" :options="['Draft' => 'Draft', 'Confirmed' => 'Confirmed', 'Cancelled' => 'Cancelled', 'Closed' => 'Closed']" />
            <x-dl::field.text name="note" label="Catatan" multiline />
        </x-dl::form.row>
        <x-dl::lines name="lines" title="Item Pesanan" total-key="total" count-key="items" :totals="['of' => 'subtotal', 'discount' => true, 'ppn' => 11]"
            :columns="[
                ['key' => 'item', 'label' => 'Kode / Nama Item', 'type' => 'lookup', 'lookup' => 'item', 'required' => true, 'width' => '240px'],
                ['key' => 'spec', 'label' => 'Spesifikasi'],
                ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'required' => true, 'sum' => true, 'width' => '90px'],
                ['key' => 'uom', 'label' => 'Satuan', 'type' => 'select', 'options' => ['PCS', 'LBR', 'RIM', 'KG', 'ROLL'], 'width' => '90px'],
                ['key' => 'price', 'label' => 'Harga/Unit', 'type' => 'money', 'required' => true, 'width' => '130px'],
                ['key' => 'subtotal', 'label' => 'Subtotal', 'type' => 'money', 'formula' => 'qty * price', 'sum' => true],
            ]" />
    </x-slot:form>
</x-dl::master>
