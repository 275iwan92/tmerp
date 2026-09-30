@php
    // Contoh acuan ke-2 Form Master-Detail: Purchase Order. Kerangka sama persis dgn Customer Order (panel aksi -> tahapan ->
    // ringkasan disorot -> tab header -> Details di tengah -> footer); hanya pihak (Supplier), field header & kolom baris beda.
    $me = 'po';
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
        ['at' => '2026-09-02 09:40:00', 'by' => 'siti', 'action' => 'Saved', 'note' => 'Ubah harga baris 2'],
        ['at' => '2026-09-02 08:40:00', 'by' => 'siti', 'action' => 'Saved', 'note' => null],
        ['at' => '2026-09-02 08:12:00', 'by' => 'siti', 'action' => 'Created', 'note' => null],
    ]));
    $po = [
        'po_no' => 'PO-2609-0001', 'po_date' => '2026-09-02', 'supplier' => 'UD Lem Sejahtera', 'buyer' => 'Siti Rahma', 'pr_ref' => 'PR-2609-0012',
        'eta' => '2026-09-09', 'warehouse' => 'GD-BB', 'term' => 'NET30', 'currency' => 'IDR', 'ship_to' => 'Gudang Bahan Baku, Jl. Contoh Industri 42',
        'note' => 'Barang dikirim pagi (07.00–11.00). Sertakan COA untuk tinta.',
        'lines' => [
            ['item' => 'RM-IVR-0310', 'item_label' => 'Ivory 310 gsm', 'spec' => 'Plano 65x100', 'qty' => 70, 'uom' => 'LBR', 'price' => 4200, 'eta' => '2026-09-09', 'disc' => '', 'wh' => 'GD-BB', 'rem' => ''],
            ['item' => 'RM-PLT-0072', 'item_label' => 'Plat CTP 72x103', 'spec' => 'Thermal, 0,30 mm', 'qty' => 105, 'uom' => 'PCS', 'price' => 96000, 'eta' => '2026-09-09', 'disc' => '', 'wh' => 'GD-BB', 'rem' => ''],
            ['item' => 'RM-KRT-0012', 'item_label' => 'Kertas HVS 210 gsm', 'spec' => 'Plano 79x109', 'qty' => 140, 'uom' => 'RIM', 'price' => 21500, 'eta' => '2026-09-12', 'disc' => '', 'wh' => 'GD-BB', 'rem' => 'Kirim bertahap'],
            ['item' => 'RM-TNT-0045', 'item_label' => 'Tinta Offset Magenta', 'spec' => '5 kg/kaleng', 'qty' => 175, 'uom' => 'KG', 'price' => 192000, 'eta' => '2026-09-09', 'disc' => '', 'wh' => 'GD-TNT', 'rem' => 'Sertakan COA'],
        ],
    ];
    $v = fn ($k) => $blank ? null : ($po[$k] ?? null);
    $suppliers = ['PT Contoh Kertas Jaya' => 'PT Contoh Kertas Jaya', 'CV Tinta Nusantara' => 'CV Tinta Nusantara', 'PT Karton Abadi' => 'PT Karton Abadi', 'UD Lem Sejahtera' => 'UD Lem Sejahtera', 'PT Plat Offset Prima' => 'PT Plat Offset Prima'];
    $warehouses = ['GD-BB' => 'GD-BB · Bahan Baku', 'GD-TNT' => 'GD-TNT · Tinta & Kimia', 'GD-SP' => 'GD-SP · Sparepart'];
@endphp
<div class="dl-row" style="margin-bottom:8px">
    <span class="dl-note" style="margin:0">Lihat status:</span>
    @foreach (['draft' => 'Draft', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled', 'closed' => 'Closed'] as $sk => $sl)
        <x-dl::button size="sm" :variant="$sk === $status ? 'primary' : 'secondary'" :label="$sl" :href="$here.'?'.$me.'_status='.$sk.'#form-md-po'" />
    @endforeach
</div>
<x-dl::form :title="$isNew ? 'Purchase Order · New'.($dup ? ' (copy of '.$po['po_no'].')' : '') : 'Purchase Order · '.$po['po_no']" :subtitle="$blank ? null : $po['supplier']"
    panel="top" kind="document" :status="$status" :pipeline="true" :can-reopen="true" :log="$log"
    :action="$here.'#form-md-po'" method="GET" :new-url="$here.'?'.$me.'_new=1#form-md-po'" :hidden="['doc' => $me]"
    :tabs="['basic' => 'Basic', 'kirim' => 'Pengiriman', 'bayar' => 'Pembayaran', 'lampiran' => 'Lampiran & Catatan']"
    :created-by="$isNew ? null : 'siti'" :created-at="$isNew ? null : '2026-09-02 08:12:00'"
    :updated-by="$isNew ? null : ($act ? 'johiwan' : 'siti')" :updated-at="$isNew ? null : ($act ? now()->format('Y-m-d H:i:s') : '2026-09-02 09:40:00')">
    <x-slot:strip>
        <div class="dl-strip__kv dl-strip__doc"><span>No. PO</span><b>{{ $blank || $dup ? '(otomatis)' : $po['po_no'] }}</b></div>
        <x-dl::field.date name="po_date" label="Tanggal PO" :value="$v('po_date') ?? now()->format('Y-m-d')" required />
        <x-dl::field.select name="supplier" label="Supplier" :options="$suppliers" :value="$v('supplier')" required />
        <div class="dl-strip__kv dl-strip__grand"><span>Grand Total</span><b data-l-val="po_lines:grand">Rp 0</b></div>
    </x-slot:strip>
    <x-slot:basic>
        <x-dl::form.row :cols="4">
            <x-dl::field.select name="buyer" label="Buyer / Pemohon" :options="['Siti Rahma' => 'Siti Rahma', 'Andi Pratama' => 'Andi Pratama', 'Joko Santoso' => 'Joko Santoso']" :value="$v('buyer')" required />
            <x-dl::field.select name="pr_ref" label="Referensi PR" :options="['PR-2609-0012' => 'PR-2609-0012', 'PR-2609-0015' => 'PR-2609-0015']" :value="$v('pr_ref')" />
            <x-dl::field.date name="eta" label="Tgl Kirim Diharapkan" :value="$v('eta')" placeholder="—" required />
            <x-dl::field.select name="currency" label="Mata Uang" :options="['IDR' => 'IDR · Rupiah', 'USD' => 'USD · Dollar']" :value="$v('currency')" />
        </x-dl::form.row>
    </x-slot:basic>
    <x-slot:kirim>
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="warehouse" label="Gudang Tujuan" :options="$warehouses" :value="$v('warehouse')" required />
            <x-dl::field.text name="ship_to" label="Alamat Kirim" :value="$v('ship_to')" />
        </x-dl::form.row>
    </x-slot:kirim>
    <x-slot:bayar>
        <x-dl::form.row :cols="4">
            <x-dl::field.select name="term" label="Syarat Bayar" :options="['CASH' => 'Tunai', 'NET14' => 'Bayar 14 hari', 'NET30' => 'Bayar 30 hari', 'NET45' => 'Bayar 45 hari']" :value="$v('term')" required />
        </x-dl::form.row>
    </x-slot:bayar>
    <x-slot:lampiran>
        <x-dl::form.row>
            <x-dl::field.upload name="quote_file" label="Penawaran Supplier (PDF)" accept="application/pdf" />
            <x-dl::field.text name="po_internal" label="Catatan Internal" multiline />
        </x-dl::form.row>
    </x-slot:lampiran>
    <x-slot:detail>
        <x-dl::lines name="po_lines" title="Details" add-label="+ Add Line" :rows="$blank ? [] : $po['lines']" :min-rows="$blank ? 1 : 0" :readonly="in_array($status, ['confirmed', 'cancelled', 'closed'], true)"
            :totals="['of' => 'subtotal', 'discount' => true, 'ppn' => 11, 'costs' => ['freight' => 'Ongkos Kirim', 'handling' => 'Bongkar Muat', 'other' => 'Biaya Lain']]"
            :columns="[
                ['key' => 'item', 'label' => 'Item', 'type' => 'lookup', 'lookup' => 'item', 'required' => true, 'width' => '240px'],
                ['key' => 'spec', 'label' => 'Spesifikasi', 'width' => '170px'],
                ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'required' => true, 'sum' => true, 'width' => '90px'],
                ['key' => 'uom', 'label' => 'Satuan', 'type' => 'select', 'options' => ['PCS', 'LBR', 'RIM', 'KG', 'ROLL'], 'required' => true, 'width' => '85px'],
                ['key' => 'price', 'label' => 'Harga/Unit', 'type' => 'money', 'required' => true, 'width' => '120px'],
                ['key' => 'subtotal', 'label' => 'Subtotal', 'type' => 'money', 'formula' => 'qty * price', 'sum' => true, 'width' => '130px'],
                ['key' => 'eta', 'label' => 'Tgl Kirim', 'type' => 'date', 'more' => true],
                ['key' => 'wh', 'label' => 'Gudang', 'type' => 'select', 'options' => $warehouses, 'more' => true],
                ['key' => 'disc', 'label' => 'Diskon Baris (Rp)', 'type' => 'money', 'more' => true],
                ['key' => 'rem', 'label' => 'Catatan Baris', 'more' => true],
            ]">
            <x-slot:note>
                <x-dl::field.text name="note" label="Catatan untuk Supplier (tercetak di PO)" multiline :value="$v('note')" />
            </x-slot:note>
        </x-dl::lines>
    </x-slot:detail>
</x-dl::form>
<p class="dl-note">Purchase Order memakai kerangka yang sama dengan Customer Order — beda hanya pihak (Supplier), field header dan kolom baris. Kolom Tgl Kirim, Gudang, Diskon Baris dan Catatan Baris ada di panel <b>›</b> tiap baris.</p>
