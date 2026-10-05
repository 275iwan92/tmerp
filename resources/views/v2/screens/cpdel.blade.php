{{-- Customer Plan Delivery v2 = buku janji (user 2026-10-01). Satu baris jadwal kirim memegang DUA janji yang harus cocok TEPAT:
     slot ke customer (tanggal + jam, dipegang Sales) dan siap produksi (PPIC). Produksi tidak sanggup -> PPIC mengisi siap + alasan ->
     Sales nego ke customer (WhatsApp / telepon) lalu mengubah slot (boleh Split) dengan log -> customer menolak = eskalasi ke
     Operational Manager. Janji awal tidak pernah berubah (patokan KPI). Aturan = App\Services\Sales\CpdelPromiseService.
     Post to Production (aturan A.3) = WO dibuat; sesudah itu tanggal & qty terkunci (hanya jam slot yang bisa diubah). --}}
@php
    use App\Services\Sales\CpdelPromiseService as Promise;
    use App\Support\V2\Data\Cpdel;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'cpdel');
    $d = request('id') ? Cpdel::query()->find((int) request('id')) : null;
    abort_if(request('id') && ! $d, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $d)
    {{-- Tanpa kotak KPI (user 2026-10-02, aturan C.22 + C.32): baris berwarna menurut jatuh tempo slot (hari kerja) + kolom Due;
         filter kiri = waktu, kanan = janji. Persen janji kirim ditepati tetap di layar Proof of Delivery. --}}
    <x-dl::grid id="cpdel-list" :rows="Cpdel::rows()" search="Search CO no., customer, product..." actions="worklist" :active-key="null" :new-record="null"
        :open-url="$home.'?id={id}'" group-by="co" row-tone="row_tone"
        source="{{ Src::of('tm2sdi.co_order_delivery', 'tm2sdi.co_order (CONFIRMED)', 'tm2prd.prd_wo.source_delivery_id') }}"
        :columns="[
            ['key' => 'co', 'label' => 'CO No. / Customer', 'type' => 'doc', 'party' => 'customer'],
            ['key' => 'item', 'label' => 'Product'],
            ['key' => 'slot', 'label' => 'Customer slot', 'type' => 'date'],
            ['key' => 'time', 'label' => 'Time'],
            ['key' => 'due', 'label' => 'Due', 'type' => 'badge', 'toneKey' => 'due_tone'],
            ['key' => 'ready', 'label' => 'Production ready'],
            ['key' => 'gap', 'label' => 'Gap (days)', 'type' => 'number', 'visible' => false],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number', 'sum' => true],
            ['key' => 'original', 'label' => 'Original promise', 'type' => 'date', 'visible' => false],
            ['key' => 'production', 'label' => 'Production'],
            ['key' => 'state', 'label' => 'Promise', 'type' => 'badge', 'tones' => Promise::STATES],
        ]"
        :detail="['key' => 'items', 'title' => t('Product'), 'columns' => [
            ['key' => 'product', 'label' => t('Product'), 'strong' => true],
            ['key' => 'spec', 'label' => t('Specification')],
            ['key' => 'order_qty', 'label' => t('Order Qty'), 'type' => 'number'],
            ['key' => 'unit', 'label' => t('Unit')],
            ['key' => 'ship_qty', 'label' => t('This Shipment'), 'type' => 'number'],
            ['key' => 'due', 'label' => t('Due Date'), 'type' => 'date'],
        ]]"
        :stats="[
            ['label' => 'All'],
            ['label' => 'Overdue', 'where' => ['bucket' => 'OVERDUE'], 'tone' => 'danger'],
            ['label' => 'Today', 'where' => ['bucket' => 'TODAY'], 'tone' => 'tangerine'],
            ['label' => 'Due soon', 'where' => ['bucket' => 'SOON'], 'tone' => 'yellow'],
            ['label' => 'Next 7 days', 'where' => ['week' => 1], 'tone' => 'accent'],
        ]"
        :tabs="[
            ['label' => 'All'],
            ['label' => 'Waiting for PPIC', 'where' => ['state' => 'WAITING_PPIC'], 'tone' => 'neutral'],
            ['label' => 'Need negotiation', 'where' => ['state' => 'NEED_NEGOTIATION'], 'tone' => 'warn'],
            ['label' => 'Escalated', 'where' => ['state' => 'ESCALATED'], 'tone' => 'danger'],
            ['label' => 'Aligned', 'where' => ['state' => 'ALIGNED'], 'tone' => 'success'],
        ]"
        :filters="['customer' => 'Customer', 'state' => 'Promise']" />
@else
    {{-- Form = objek form khusus x-mp::sales.promise-book (MockupParts); isi & hak = Cpdel::promise(). --}}
    <x-mp::sales.promise-book :book="Cpdel::promise($d, auth()->user(), $home)" />
@endif
