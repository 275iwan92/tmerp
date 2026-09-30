@php
    // Master-Detail = objek Form yang sama dgn Single Form (panel atas, istilah English, siklus status, tab Riwayat);
    // bedanya isi: header dokumen + tabel baris item (x-dl::lines). Status baru ditentukan "server" (blok ini).
    $act = request('_action');
    $status = match ($act) {
        'confirm' => 'confirmed', 'draft', 'save' => 'draft', 'cancel' => 'cancelled', 'delete', 'duplicate' => 'new',
        default => request()->boolean('new') ? 'new' : request('status', 'draft'),
    };
    $isNew = $status === 'new';
    $dup = $act === 'duplicate';
    $sampleItems = [
        ['item' => 'AP-80-6590', 'spec' => 'Art Paper 80 gsm 65x90', 'qty' => 20, 'uom' => 'RIM', 'price' => 425000],
        ['item' => 'INK-CY-01', 'spec' => 'Tinta Cyan Toyo 1 kg', 'qty' => 4, 'uom' => 'KG', 'price' => 185000],
        ['item' => 'PLT-7490', 'spec' => 'Plat CTP 745x605', 'qty' => 12, 'uom' => 'PCS', 'price' => 95000],
    ];
    $head = ['pr_date' => '2026-09-24', 'divisi' => 'Offset Printing', 'alasan' => 'Stok kertas & tinta untuk order CO-2609-0031.'];
    $h = fn ($k) => $isNew && ! $dup ? null : (! $isNew && request()->has($k) ? request($k) : $head[$k]);
    $items = $isNew && ! $dup ? [] : (! $isNew && is_array(request('items')) ? array_values(request('items')) : $sampleItems);
@endphp
<div class="dl-row" style="margin-bottom:8px">
    <span class="dl-note" style="margin:0">Lihat status:</span>
    @foreach (['draft' => 'Draft', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled', 'closed' => 'Closed'] as $sk => $sl)
        <x-dl::button size="sm" :variant="$sk === $status ? 'primary' : 'secondary'" :label="$sl" :href="url()->current().'?status='.$sk" />
    @endforeach
</div>
<x-dl::form :title="$dup ? 'Purchase Requisition · New (copy of PR-2609-0012)' : ($isNew ? 'Purchase Requisition · New' : 'Purchase Requisition · PR-2609-0012')"
    panel="top" kind="document" :status="$status" :can-reopen="true"
    :action="url()->current()" method="GET" :new-url="url()->current().'?new=1'"
    :created-by="$isNew ? null : 'yuna'" :created-at="$isNew ? null : '2026-09-24 08:12:00'"
    :updated-by="$isNew ? null : ($act ? 'johiwan' : 'yuna')" :updated-at="$isNew ? null : ($act ? now()->format('Y-m-d H:i:s') : '2026-09-24 09:40:00')">
    <x-dl::form.row :cols="3">
        <x-dl::field.text name="pr_no" label="PR No." :value="$isNew ? null : 'PR-2609-0012'" placeholder="(otomatis)" code readonly :case-toggle="false" />
        <x-dl::field.date name="pr_date" label="Tanggal" :value="$h('pr_date')" placeholder="—" required />
        <x-dl::field.select name="divisi" label="Divisi" :options="['Offset Printing', 'Digital Printing', 'Gudang']" :value="$h('divisi')" required />
    </x-dl::form.row>
    <x-dl::field.text name="alasan" label="Justifikasi" multiline :value="$h('alasan')" />
    <x-dl::lines name="items" title="Item Permintaan" :rows="$items" :totals="['of' => 'subtotal']" :readonly="in_array($status, ['confirmed', 'cancelled', 'closed'], true)"
        :columns="[
            ['key' => 'item', 'label' => 'Kode Item', 'required' => true, 'width' => '150px'],
            ['key' => 'spec', 'label' => 'Spesifikasi'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'required' => true, 'width' => '90px'],
            ['key' => 'uom', 'label' => 'Satuan', 'type' => 'select', 'options' => ['PCS', 'LBR', 'RIM', 'KG', 'ROLL'], 'width' => '90px'],
            ['key' => 'price', 'label' => 'Est. Harga', 'type' => 'money', 'width' => '130px'],
            ['key' => 'subtotal', 'label' => 'Est. Subtotal', 'type' => 'money', 'formula' => 'qty * price'],
        ]" />
</x-dl::form>
<p class="dl-note">Master-Detail = objek Form yang sama dengan Single Form: tombol di atas (Draft: Delete · New · Undo · Confirm · Save │ Back), isi terkunci setelah Confirm, tab Riwayat selalu ada. Bagian atas = header dokumen, bawah = tabel baris item dengan total.</p>
