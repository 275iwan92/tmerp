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
    // Log riwayat (History): contoh isi -- di tmerp dari tm2sys.doc_state_log + audit perubahan. Terbaru di atas.
    $log = $isNew ? [] : array_values(array_filter([
        in_array($status, ['confirmed', 'cancelled', 'closed'], true) ? ['at' => now()->format('Y-m-d H:i:s'), 'by' => 'johiwan', 'action' => ucfirst($status), 'note' => $status === 'cancelled' ? 'Salah customer, dibuat ulang.' : null] : null,
        $status === 'draft' && $act === 'draft' ? ['at' => now()->format('Y-m-d H:i:s'), 'by' => 'johiwan', 'action' => 'Back to Draft', 'note' => 'Revisi qty sesuai PO customer.'] : null,
        ['at' => '2026-09-30 09:40:00', 'by' => 'carrij', 'action' => 'Saved', 'note' => 'Ubah harga baris 2'],
        ['at' => '2026-09-30 08:40:00', 'by' => 'carrij', 'action' => 'Saved', 'note' => null],
        ['at' => '2026-09-30 08:12:00', 'by' => 'carrij', 'action' => 'Created', 'note' => null],
    ]));
    $co = [
        'co_no' => '2602402', 'co_date' => '2026-09-30', 'customer' => 'CV Samudra Kiat Jaya', 'sales' => 'Carrij Soehendra', 'consignee' => 'CV Samudra Kiat Jaya',
        'po_no' => 'PO/SKJ/IX/0187', 'po_date' => '2026-09-28', 'po_recv' => '2026-09-29', 'ship' => 'DELIVER', 'pay' => 'NET30', 'bank' => 'BCA', 'tax' => 'NO',
        'ship_addr' => 'Jl. Raya Waru No. 18, Sidoarjo', 'courier' => 'Armada sendiri', 'ship_date' => '2026-10-16',
        'bill_addr' => 'Jl. Raya Waru No. 18, Sidoarjo', 'contact' => 'Bpk. Hendra', 'contact_phone' => '+6281231234567', 'contact_email' => 'purchasing@samudrakiat.co.id', 'npwp' => '01.234.567.8-609.000', 'currency' => 'IDR', 'rate' => 1,
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
    panel="top" kind="document" :status="$status" :pipeline="true" :can-reopen="true" :log="$log"
    :action="$here.'#form-md'" method="GET" :new-url="$here.'?'.$me.'_new=1#form-md'" :hidden="['doc' => $me]"
    :tabs="['general' => 'General', 'party' => 'Customer', 'finance' => 'Finance', 'delivery' => 'Delivery', 'files' => 'Attachments & Notes']"
    :created-by="$isNew ? null : 'carrij'" :created-at="$isNew ? null : '2026-09-30 08:12:00'"
    :updated-by="$isNew ? null : ($act ? 'johiwan' : 'carrij')" :updated-at="$isNew ? null : ($act ? now()->format('Y-m-d H:i:s') : '2026-09-30 09:40:00')">
    <x-slot:strip>
        {{-- Ringkasan: No. dokumen & nominal penting di atas, disorot; nominal ikut hidup dari baris Details. --}}
        <div class="dl-strip__kv dl-strip__doc"><span>No. Pesanan</span><b>{{ $blank || $dup ? '(otomatis)' : $co['co_no'] }}</b></div>
        <x-dl::field.date name="co_date" label="Tanggal Pesan" :value="$v('co_date') ?? now()->format('Y-m-d')" required />
        <x-dl::field.select name="customer" label="Customer" :options="$customers" :value="$v('customer')" required />
        <div class="dl-strip__kv dl-strip__grand"><span>Grand Total</span><b data-l-val="lines:grand">IDR 0</b></div>
    </x-slot:strip>
    <x-slot:general>
        <x-dl::form.row :cols="4">
            <x-dl::field.select name="sales" label="Sales By" :options="['Carrij Soehendra' => 'Carrij Soehendra', 'Firman' => 'Firman']" :value="$v('sales')" required />
            <x-dl::field.text name="po_no" label="No. PO Customer" :value="$v('po_no')" :case-toggle="false" />
            <x-dl::field.date name="po_date" label="Tgl PO Customer" :value="$v('po_date')" placeholder="—" />
            <x-dl::field.date name="po_recv" label="Tgl PO Diterima" :value="$v('po_recv')" placeholder="—" />
        </x-dl::form.row>
    </x-slot:general>
    <x-slot:party>
        <x-dl::form.row :cols="4">
            <x-dl::field.text name="bill_addr" label="Alamat Tagihan" :value="$v('bill_addr')" />
            <x-dl::field.text name="contact" label="Contact Person" :value="$v('contact')" />
            <x-dl::field.phone name="contact_phone" label="No. HP Kontak" :value="$v('contact_phone')" />
            <x-dl::field.text name="contact_email" label="Email" type="email" :value="$v('contact_email')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="4">
            <x-dl::field.masked name="npwp" label="NPWP Customer" mask="99.999.999.9-999.999" :value="$v('npwp')" />
        </x-dl::form.row>
    </x-slot:party>
    <x-slot:finance>
        <x-dl::form.row :cols="4">
            <x-dl::field.select name="currency" label="Currency" :options="['IDR' => 'IDR · Rupiah', 'USD' => 'USD · US Dollar', 'SGD' => 'SGD · Singapore Dollar']" :value="$v('currency') ?? 'IDR'" required />
            <x-dl::field.decimal name="rate" label="Rate (Kurs ke IDR)" :decimals="2" :value="$v('rate') ?? 1" hint="IDR = 1. Diisi bila mata uang asing." />
            <x-dl::field.select name="pay" label="Payment Terms" :options="['CASH' => 'Tunai (COD)', 'NET14' => 'Net 14 hari', 'NET30' => 'Net 30 hari', 'NET45' => 'Net 45 hari']" :value="$v('pay')" required />
            <x-dl::field.select name="bank" label="Pembayaran ke Rekening" :options="['BCA' => 'BCA 0881-xxx', 'MANDIRI' => 'Mandiri 1400-xxx']" :value="$v('bank')" />
        </x-dl::form.row>
    </x-slot:finance>
    <x-slot:delivery>
        <x-dl::form.row :cols="4">
            <x-dl::field.select name="consignee" label="Penerima Barang (Consignee)" :options="$customers" :value="$v('consignee')" required />
            <x-dl::field.select name="ship" label="Metode Pengiriman" :options="['DELIVER' => 'Deliver / Kirim', 'PICKUP' => 'Diambil customer']" :value="$v('ship')" />
            <x-dl::field.select name="courier" label="Ekspedisi" :options="['Armada sendiri' => 'Armada sendiri', 'JNE Trucking' => 'JNE Trucking', 'Diambil' => 'Diambil customer']" :value="$v('courier')" />
            <x-dl::field.date name="ship_date" label="Rencana Kirim" :value="$v('ship_date')" placeholder="—" />
        </x-dl::form.row>
        <x-dl::form.row :cols="2">
            <x-dl::field.text name="ship_addr" label="Alamat Kirim" :value="$v('ship_addr')" />
        </x-dl::form.row>
    </x-slot:delivery>
    <x-slot:files>
        <x-dl::form.row>
            <x-dl::field.upload name="po_file" label="Lampiran PO Customer (PDF)" accept="application/pdf" />
            <x-dl::field.text name="note" label="Note Internal" multiline :value="$v('note')" />
        </x-dl::form.row>
        <x-dl::form.row>
            <x-dl::field.text name="customer_note" label="Catatan untuk Customer (tercetak di nota)" multiline :value="$blank ? null : 'Harga belum termasuk ongkos kirim luar kota.'" />
        </x-dl::form.row>
    </x-slot:files>
    <x-slot:detail>
        <x-dl::lines name="lines" title="Details" add-label="+ Add Line" :rows="$blank ? [] : $co['lines']" :min-rows="$blank ? 1 : 0" :readonly="in_array($status, ['confirmed', 'cancelled', 'closed'], true)"
            :totals="['of' => 'subtotal', 'discount' => true, 'ppn' => 11, 'costs' => ['trucking' => 'Trucking / Ongkos Angkut', 'packing' => 'Packing / Palet', 'plate' => 'Plate & Pisau (Tooling)', 'insurance' => 'Asuransi Pengiriman', 'admin' => 'Administrasi / Materai', 'other' => 'Biaya Lain']]"
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
        </x-dl::lines>
    </x-slot:detail>
</x-dl::form>
<p class="dl-note">Form Master-Detail: <b>1</b> panel aksi (tombol baku sesuai status) · <b>2</b> tahapan Draft › Confirmed › Closed · <b>3</b> ringkasan di atas: No. dokumen &amp; Grand Total disorot (Grand Total ikut hidup) · <b>4</b> tab header · <b>5</b> Details selalu di tengah: kolom inti di tabel, kolom lain lewat <b>›</b> per baris (titik biru = sudah terisi); kolom Product &amp; Subtotal diam saat tabel digeser · <b>6</b> footer: catatan kiri, Subtotal/Diskon/DPP/PPN/Total kanan.</p>
