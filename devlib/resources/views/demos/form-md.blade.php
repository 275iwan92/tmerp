@php
    // Form Master-Detail (user 2026-09-30: "lebih baik dari ScriptCase, elegan tapi compact"). Acuan: layar Entry Customer Order
    // ScriptCase lama + pola Odoo Sales Order (status bar, notebook, total kanan bawah), SAP Fiori Object Page (ringkasan kunci
    // + angka besar), Dynamics 365 Sales Order (Line details per baris). Kerangka: panel aksi -> tahapan -> ringkasan kunci ->
    // tab header -> DETAIL selalu di tengah -> footer (catatan kiri, total kanan). Status baru ditentukan "server" (blok ini).
    // Dua contoh di satu halaman (CO & PO): parameter diberi awalan dokumen supaya aksi satu form tidak mengubah form lain.
    $me = 'co';
    $act = request('doc') === $me ? request('_action') : null;
    $status = match ($act) {
        'confirm' => 'confirmed', 'draft', 'save' => 'draft', 'cancel' => 'cancelled', 'delete', 'duplicate' => 'new',
        default => request()->boolean($me.'_new') ? 'new' : request($me.'_status', 'draft'),
    };
    $here = url()->current();
    $isNew = $status === 'new';
    $dup = $act === 'duplicate';
    $blank = $isNew && ! $dup;
    $co = [
        'co_no' => '2602402', 'co_date' => '2026-09-30', 'customer' => 'CV Samudra Kiat Jaya', 'sales' => 'Carrij Soehendra', 'consignee' => 'CV Samudra Kiat Jaya',
        'po_no' => 'PO/SKJ/IX/0187', 'po_date' => '2026-09-28', 'po_recv' => '2026-09-29', 'ship' => 'DELIVER', 'pay' => 'NET30', 'bank' => 'BCA', 'tax' => 'NO',
        'ship_addr' => 'Jl. Raya Waru No. 18, Sidoarjo', 'courier' => 'Armada sendiri',
        'note' => 'IB Coco Tokyo HEXA DC250/M125/K125 EF CS, lam doff, plong, lem samping; OB Coco Tokyo Flat 25 K200/M125/K200 BF cetak, sliter-sloter, stitching.',
        'lines' => [
            ['product' => 'DS001479', 'product_label' => 'IB Coco Tokyo HEXA 1kg', 'qty' => 4000, 'uom' => 'PCS', 'price' => 2250, 'due' => '2026-10-16', 'color' => '4/0', 'min_order' => false, 'order_type' => 'REPEAT', 'ppic' => ''],
            ['product' => 'DS001480', 'product_label' => 'OB Coco Tokyo HEXA 1kg', 'qty' => 400, 'uom' => 'PCS', 'price' => 6200, 'due' => '2026-10-16', 'color' => '4/0', 'min_order' => false, 'order_type' => 'REPEAT', 'ppic' => 'Kirim bersama IB'],
        ],
    ];
    $v = fn ($k) => $blank ? null : ($co[$k] ?? null);
    $customers = ['CV Samudra Kiat Jaya' => 'CV Samudra Kiat Jaya', 'Bu Yuna' => 'Bu Yuna', 'PT Sinar Pangan' => 'PT Sinar Pangan'];
@endphp
<div class="dl-row" style="margin-bottom:8px">
    <span class="dl-note" style="margin:0">Lihat status:</span>
    @foreach (['draft' => 'Draft', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled', 'closed' => 'Closed'] as $sk => $sl)
        <x-dl::button size="sm" :variant="$sk === $status ? 'primary' : 'secondary'" :label="$sl" :href="$here.'?'.$me.'_status='.$sk.'#form-md'" />
    @endforeach
</div>
<x-dl::form :title="$isNew ? 'Customer Order · New'.($dup ? ' (copy of '.$co['co_no'].')' : '') : 'Customer Order · '.$co['co_no']" :subtitle="$blank ? null : $co['customer']"
    panel="top" kind="document" :status="$status" :pipeline="true" :can-reopen="true"
    :action="$here.'#form-md'" method="GET" :new-url="$here.'?'.$me.'_new=1#form-md'" :hidden="['doc' => $me]"
    :tabs="['basic' => 'Basic', 'kirim' => 'Pengiriman', 'pajak' => 'Pajak & Bayar', 'lampiran' => 'Lampiran & Catatan']"
    :created-by="$isNew ? null : 'carrij'" :created-at="$isNew ? null : '2026-09-30 08:12:00'"
    :updated-by="$isNew ? null : ($act ? 'johiwan' : 'carrij')" :updated-at="$isNew ? null : ($act ? now()->format('Y-m-d H:i:s') : '2026-09-30 09:40:00')">
    <x-slot:strip>
        {{-- Ringkasan: No. dokumen & nominal penting di atas, disorot; nominal ikut hidup dari baris Details. --}}
        <div class="dl-strip__kv dl-strip__doc"><span>No. Pesanan</span><b>{{ $blank || $dup ? '(otomatis)' : $co['co_no'] }}</b></div>
        <x-dl::field.date name="co_date" label="Tanggal Pesan" :value="$v('co_date') ?? now()->format('Y-m-d')" required />
        <x-dl::field.select name="customer" label="Customer" :options="$customers" :value="$v('customer')" required />
        <div class="dl-strip__kv dl-strip__grand"><span>Grand Total</span><b data-l-val="lines:grand">Rp 0</b></div>
    </x-slot:strip>
    <x-slot:basic>
        <x-dl::form.row :cols="4">
            <x-dl::field.select name="sales" label="Sales By" :options="['Carrij Soehendra' => 'Carrij Soehendra', 'Firman' => 'Firman']" :value="$v('sales')" required />
            <x-dl::field.select name="consignee" label="Penerima Barang (Consignee)" :options="$customers" :value="$v('consignee')" required />
            <x-dl::field.text name="po_no" label="No. PO Customer" :value="$v('po_no')" :case-toggle="false" />
            <x-dl::field.date name="po_date" label="Tgl PO Customer" :value="$v('po_date')" placeholder="—" />
        </x-dl::form.row>
        <x-dl::form.row :cols="4">
            <x-dl::field.date name="po_recv" label="Tgl PO Diterima" :value="$v('po_recv')" placeholder="—" />
            <x-dl::field.select name="ship" label="Metode Pengiriman" :options="['DELIVER' => 'Deliver / Kirim', 'PICKUP' => 'Diambil customer']" :value="$v('ship')" />
            <x-dl::field.select name="pay" label="Status Pembayaran" :options="['CASH' => 'Tunai', 'NET14' => 'Bayar 14 hari', 'NET30' => 'Bayar 30 hari']" :value="$v('pay')" />
        </x-dl::form.row>
    </x-slot:basic>
    <x-slot:kirim>
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="ship_addr" label="Alamat Kirim" :value="$v('ship_addr')" />
            <x-dl::field.select name="courier" label="Ekspedisi" :options="['Armada sendiri' => 'Armada sendiri', 'JNE Trucking' => 'JNE Trucking', 'Diambil' => 'Diambil customer']" :value="$v('courier')" />
        </x-dl::form.row>
    </x-slot:kirim>
    <x-slot:pajak>
        <x-dl::form.row :cols="4">
            <x-dl::field.select name="tax" label="Tax Enable" :options="['NO' => 'No', 'YES' => 'Yes']" :value="$v('tax')" />
            <x-dl::field.select name="bank" label="Pembayaran pada" :options="['BCA' => 'BCA 0881-xxx', 'MANDIRI' => 'Mandiri 1400-xxx']" :value="$v('bank')" />
        </x-dl::form.row>
    </x-slot:pajak>
    <x-slot:lampiran>
        <x-dl::form.row>
            <x-dl::field.upload name="po_file" label="Lampiran PO (PDF)" accept="application/pdf" />
            <x-dl::field.text name="note" label="Note" multiline :value="$v('note')" />
        </x-dl::form.row>
    </x-slot:lampiran>
    <x-slot:detail>
        <x-dl::lines name="lines" title="Details" add-label="+ Add Line" :rows="$blank ? [] : $co['lines']" :min-rows="$blank ? 1 : 0" :readonly="in_array($status, ['confirmed', 'cancelled', 'closed'], true)"
            :totals="['of' => 'subtotal', 'discount' => true, 'ppn' => 11]"
            :columns="[
                ['key' => 'product', 'label' => 'Product', 'type' => 'lookup', 'lookup' => 'product', 'required' => true, 'width' => '260px'],
                ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'required' => true, 'sum' => true, 'width' => '100px'],
                ['key' => 'uom', 'label' => 'Satuan', 'type' => 'select', 'options' => ['PCS', 'LBR', 'SET', 'BOX'], 'required' => true, 'width' => '90px'],
                ['key' => 'price', 'label' => 'Unit Price', 'type' => 'money', 'required' => true, 'width' => '120px'],
                ['key' => 'due', 'label' => 'Due Date', 'type' => 'date', 'required' => true, 'width' => '140px'],
                ['key' => 'subtotal', 'label' => 'Subtotal', 'type' => 'money', 'formula' => 'qty * price', 'sum' => true, 'width' => '130px'],
                ['key' => 'color', 'label' => 'Cfg Color', 'more' => true, 'placeholder' => '4/0', 'required' => true],
                ['key' => 'order_type', 'label' => 'Order Type', 'type' => 'select', 'options' => ['NEW' => 'Baru', 'REPEAT' => 'Repeat Tanpa Perubahan', 'REVISE' => 'Repeat Dengan Perubahan'], 'more' => true, 'required' => true],
                ['key' => 'qty_min', 'label' => 'Qty Limit Min (-10%)', 'type' => 'number', 'formula' => 'qty * 0.9', 'more' => true],
                ['key' => 'qty_max', 'label' => 'Qty Limit Max (+10%)', 'type' => 'number', 'formula' => 'qty * 1.1', 'more' => true],
                ['key' => 'min_order', 'label' => 'Qty Minim', 'type' => 'bool', 'more' => true],
                ['key' => 'ppic', 'label' => 'Catatan u/ PPIC', 'more' => true],
            ]">
            <x-slot:note>
                <x-dl::field.text name="customer_note" label="Catatan untuk Customer (tercetak di nota)" multiline :value="$blank ? null : 'Harga belum termasuk ongkos kirim luar kota.'" />
            </x-slot:note>
        </x-dl::lines>
    </x-slot:detail>
</x-dl::form>
<p class="dl-note">Form Master-Detail: <b>1</b> panel aksi (tombol baku sesuai status) · <b>2</b> tahapan Draft › Confirmed › Closed · <b>3</b> ringkasan di atas: No. dokumen &amp; Grand Total disorot (Grand Total ikut hidup) · <b>4</b> tab header · <b>5</b> Details selalu di tengah: kolom inti di tabel, kolom lain lewat <b>›</b> per baris (titik biru = sudah terisi); kolom Product &amp; Subtotal diam saat tabel digeser · <b>6</b> footer: catatan kiri, Subtotal/Diskon/DPP/PPN/Total kanan.</p>
