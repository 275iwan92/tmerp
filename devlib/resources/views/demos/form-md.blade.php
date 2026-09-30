@php
    // Form Master-Detail (user 2026-09-30): isi = bagian FORM dari Documents > Master-Detail (Purchase Order) yang dinilai benar
    // -- header 3 kolom + bar tahapan + baris item (lookup, jumlah, diskon, PPN, total) -- di dalam objek Form yang sama dgn
    // Single Form (panel atas, istilah English, siklus status, tab Riwayat). Status baru ditentukan "server" (blok ini).
    $act = request('_action');
    $status = match ($act) {
        'confirm' => 'confirmed', 'draft', 'save' => 'draft', 'cancel' => 'cancelled', 'delete', 'duplicate' => 'new',
        default => request()->boolean('new') ? 'new' : request('status', 'draft'),
    };
    $isNew = $status === 'new';
    $dup = $act === 'duplicate';
    $po = \App\Support\DemoData::purchaseOrders()[0];
    $rec = $isNew && ! $dup ? ['lines' => []] : ($dup ? ['po_no' => null] + $po : $po);
    $locked = in_array($status, ['confirmed', 'cancelled', 'closed'], true);
@endphp
<div class="dl-row" style="margin-bottom:8px">
    <span class="dl-note" style="margin:0">Lihat status:</span>
    @foreach (['draft' => 'Draft', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled', 'closed' => 'Closed'] as $sk => $sl)
        <x-dl::button size="sm" :variant="$sk === $status ? 'primary' : 'secondary'" :label="$sl" :href="url()->current().'?status='.$sk" />
    @endforeach
</div>
<x-dl::form :title="$isNew ? 'Purchase Order · New'.($dup ? ' (copy of '.$po['po_no'].')' : '') : 'Purchase Order · '.$po['po_no']" :subtitle="$rec['supplier'] ?? null"
    panel="top" kind="document" :status="$status" :pipeline="true" :can-reopen="true"
    :action="url()->current()" method="GET" :new-url="url()->current().'?new=1'"
    :created-by="$isNew ? null : 'siti'" :created-at="$isNew ? null : $po['po_date'].' 08:12:00'"
    :updated-by="$isNew ? null : ($act ? 'johiwan' : 'siti')" :updated-at="$isNew ? null : ($act ? now()->format('Y-m-d H:i:s') : $po['po_date'].' 09:40:00')">
    <x-dl::form.row :cols="3">
        <x-dl::field.text name="po_no" label="No. PO" :value="$rec['po_no'] ?? null" placeholder="(otomatis)" code readonly :case-toggle="false" />
        <x-dl::field.date name="po_date" label="Tanggal PO" :value="$rec['po_date'] ?? null" placeholder="—" required />
        <x-dl::field.select name="supplier" label="Supplier" :options="['PT Contoh Kertas Jaya', 'CV Tinta Nusantara', 'PT Karton Abadi', 'UD Lem Sejahtera', 'PT Plat Offset Prima']" :value="$rec['supplier'] ?? null" required />
    </x-dl::form.row>
    <x-dl::form.row :cols="3">
        <x-dl::field.text name="requester" label="Pemohon" :value="$rec['requester'] ?? null" />
        <x-dl::field.text name="note" label="Catatan" multiline :value="$rec['note'] ?? null" />
    </x-dl::form.row>
    <x-dl::lines name="lines" title="Item Pesanan" :rows="$rec['lines']" :readonly="$locked" :totals="['of' => 'subtotal', 'discount' => true, 'ppn' => 11]"
        :columns="[
            ['key' => 'item', 'label' => 'Kode / Nama Item', 'type' => 'lookup', 'lookup' => 'item', 'required' => true, 'width' => '240px'],
            ['key' => 'spec', 'label' => 'Spesifikasi'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'required' => true, 'sum' => true, 'width' => '90px'],
            ['key' => 'uom', 'label' => 'Satuan', 'type' => 'select', 'options' => ['PCS', 'LBR', 'RIM', 'KG', 'ROLL'], 'width' => '90px'],
            ['key' => 'price', 'label' => 'Harga/Unit', 'type' => 'money', 'required' => true, 'width' => '130px'],
            ['key' => 'subtotal', 'label' => 'Subtotal', 'type' => 'money', 'formula' => 'qty * price', 'sum' => true],
        ]" />
</x-dl::form>
<p class="dl-note">Form Master-Detail = form Purchase Order dari Documents › Master-Detail, dipasang di objek Form yang sama dengan Single Form: panel tombol di atas, bar tahapan Draft › Confirmed › Closed (Cancelled merah), header 3 kolom, baris item dengan lookup, jumlah, diskon, PPN 11% dan total. Setelah Confirm header & baris terkunci. Tab Riwayat selalu ada.</p>
