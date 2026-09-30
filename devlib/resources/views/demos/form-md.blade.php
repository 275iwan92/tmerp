<x-dl::form title="Purchase Requisition PR-2609-0012" :actions="['Save' => '💾', 'Save & Submit' => '📤']">
    <x-dl::form.row cols="3">
        <x-dl::field.text name="pr_no" label="No. PR" value="PR-2609-0012" code readonly />
        <x-dl::field.date name="pr_date" label="Tanggal" value="2026-09-24" required />
        <x-dl::field.select name="divisi" label="Divisi" :options="['Offset Printing', 'Digital Printing', 'Gudang']" required />
    </x-dl::form.row>
    <x-dl::field.text name="alasan" label="Justifikasi" multiline />
    <x-dl::lines name="items" title="Item Permintaan" :totals="['of' => 'subtotal']"
        :columns="[
            ['key' => 'item', 'label' => 'Kode / Nama Item', 'type' => 'lookup', 'lookup' => 'item', 'required' => true, 'width' => '240px'],
            ['key' => 'spec', 'label' => 'Spesifikasi'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'required' => true, 'width' => '90px'],
            ['key' => 'uom', 'label' => 'Satuan', 'type' => 'select', 'options' => ['PCS', 'LBR', 'RIM', 'KG', 'ROLL'], 'width' => '90px'],
            ['key' => 'price', 'label' => 'Est. Harga', 'type' => 'money', 'width' => '130px'],
            ['key' => 'subtotal', 'label' => 'Est. Subtotal', 'type' => 'money', 'formula' => 'qty * price'],
        ]" />
</x-dl::form>
