{{-- Inquiry Rekalkulasi (RECALC). Flow & field: tmerp pages/inquiry/recalc.blade.php (JANGAN diubah); ditulis ulang dari objek DevLib + x-mp::sales.*.
     ?source_id = buat RECALC dari inkuiri baku; ?id = buka dokumen RECALC tersimpan. Spec 100% reuse, yang diisi hanya Qty & Deadline. --}}
@php
    use App\Support\V2\Data\Sales;
    use App\Support\V2\Src;

    $doc = request('id') ? Sales::inquiryDoc((int) request('id')) : null;
    $source = $doc ? Sales::parentDoc((int) $doc['id']) : Sales::inquiryDoc((int) request('source_id', 140));
    $readonly = $doc && ($doc['stat_confirm'] || request('view'));
    $types = Sales::dielineTypes();
    $type = $types[$source['type_code']];
    $finishing = array_column(Sales::masters()['finishing_types'], 'finishing_name', 'id_finishing_type');
    $materials = collect(Sales::masters()['materials'])->keyBy('material_id');
    $rp = fn ($v, $uom) => $v !== null ? 'Rp '.number_format((float) $v, 0, ',', '.').'/'.$uom : '—';
    $priceRows = collect($source['parts'])->flatMap(fn ($p) => array_column($p['materials'], 'material'))->unique()->map(function ($id) use ($materials, $rp) {
        $m = $materials[$id];
        $baku = $m['avg_unitprice'];
        $kini = $m['last_unitprice'] ?? $baku;
        $delta = $baku > 0 && $kini !== null ? ($kini - $baku) / $baku * 100 : null;

        return ['name' => $m['material_name'], 'baku' => $rp($baku, $m['uom_purchase']), 'kini' => $rp($kini, $m['uom_purchase']),
            'delta' => $delta === null ? '—' : (abs($delta) < 0.05 ? '0%' : ($delta > 0 ? '▲ +' : '▼ ').number_format($delta, 1).'%')];
    })->values()->all();
@endphp

<x-mp::sales.inquiry-derived mode="RECALC" :doc="$doc ?? []" :readonly="(bool) $readonly" :back-url="route('v2.screen', 'inquiry')">
    <x-dl::card title="1️⃣ Produk Asal & Jejak Relasi">
        <x-mp::sales.source-trace from-label="Inkuiri baku" :from="$source['inquiry_no']" to-label="RECALC" :to="$doc['inquiry_no'] ?? null" />
        <x-dl::alert tone="warn">RECALC = <b>repeat order harga baru</b>. Spec, dimensi, artwork, warna, dan proses <b>tidak berubah</b> — semua di-reuse dari inkuiri baku. Yang di-refresh hanya <b>harga bahan terkini</b>.</x-dl::alert>
        <x-dl::form.row cols="3">
            <x-dl::field.display label="Customer" :value="$source['customer']" :source="Src::of('tm2sdi.iqp_inquiry.customer_id', 'tm2mst.cust_customer.customer_name')" />
            <x-dl::field.display label="Produk" :value="$source['product_draft_name']" :source="Src::of('tm2sdi.iqp_inquiry.product_draft_name')" />
            <x-dl::field.decimal name="qty" label="Qty Order (PCS)" required :value="$doc['qty'] ?? $source['qty']" :source="Src::of('tm2sdi.iqp_tier.qty').' (tier utama, is_primary = 1)'" />
        </x-dl::form.row>
        <x-dl::form.row cols="3">
            <x-dl::field.date name="deadline_quotation" label="Deadline Quotation" :value="$doc['deadline_quotation'] ?? $source['deadline_quotation']" :source="Src::of('tm2sdi.iqp_inquiry.deadline_quotation')" />
        </x-dl::form.row>
    </x-dl::card>

    <x-dl::card title="2️⃣ Spec & Layout (reuse dari baku)" description="read-only">
        <x-dl::alert tone="warn">🔒 <b>TERKUNCI</b> — Untuk mengubah salah satu ini, gunakan <b>REVISE</b>, bukan RECALC.</x-dl::alert>
        @foreach ($source['parts'] as $p)
            <div class="mp-subtitle">{{ $p['part_name'] }} ({{ $p['part_type'] }})</div>
            <div class="mp-thumbs">
                <x-mp::image-thumb label="Artwork" />
                <x-mp::diecut-thumb :spec="$type['spec']" :dims="$p['dims']" />
            </div>
            <x-mp::sales.reuse-summary :source="Src::of('tm2sdi.iqp_part')" :items="[
                ['label' => 'Dimensi (bentangan)', 'flat' => ['spec' => $type['spec'], 'dims' => $p['dims']]],
                ['label' => 'Dieline / Tipe', 'value' => $source['type_code']],
                ['label' => 'Warna', 'value' => $p['cfg_color'].($p['cfg_ink'] ? ' · '.$p['cfg_ink'] : '')],
                ['label' => 'Qty/SKU', 'value' => $p['qty_per_sku']],
                ['label' => 'Material', 'wide' => true, 'value' => collect($p['materials'])->pluck('material_label')->filter()->implode(', ') ?: '—'],
                ['label' => 'Proses', 'wide' => true, 'value' => collect($p['finishings'])->map(fn ($f) => $finishing[$f['finishing_type_id']] ?? null)->filter()->implode(' → ') ?: 'belum ada data (modul routing proses belum digarap)'],
            ]" />
        @endforeach
    </x-dl::card>

    <x-dl::card title="3️⃣ Refresh Harga Bahan" description="yang berubah hanya ini">
        <x-dl::alert tone="info">Harga bahan ditarik dari <code>mat_raw</code> — kolom <code>avg_unitprice</code> (baku) dibandingkan <code>last_unitprice</code> (kini). Struktur &amp; kuantitas bahan identik, hanya unit price yang diperbarui.</x-dl::alert>
        <x-dl::table :columns="['name' => 'Material', 'baku' => 'Harga Baku', 'kini' => 'Harga Kini', 'delta' => 'Δ']" :rows="$priceRows"
            source="{{ Src::of('tm2mst.mat_raw.avg_unitprice', 'tm2mst.mat_raw.last_unitprice', 'tm2mst.mat_raw.uom_purchase') }}" />
        <x-dl::alert tone="warn">💰 HPP &amp; harga jual RECALC dihitung oleh <b>Costing</b> setelah disubmit — belum tersedia di layar Sales ini (Sisi A).</x-dl::alert>
    </x-dl::card>
</x-mp::sales.inquiry-derived>
