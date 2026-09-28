{{-- Inquiry Revisi (REVISE). Flow & field: tmerp pages/inquiry/revise.blade.php (JANGAN diubah); ditulis ulang dari objek DevLib + x-mp::sales.*.
     ?source_id = buat versi baru dari inkuiri asal; ?id = buka dokumen REVISE tersimpan. --}}
@php
    use App\Support\V2\Data\Sales;
    use App\Support\V2\Src;

    $doc = request('id') ? Sales::inquiryDoc((int) request('id')) : null;
    // Sama dgn v1: sumber = inkuiri induk, bila tautannya kosong = dokumen itu sendiri (spec sudah disalin utuh); tanpa id/source_id = 404.
    $source = $doc ? (Sales::parentDoc((int) $doc['id']) ?? $doc) : (request('source_id') ? Sales::inquiryDoc((int) request('source_id')) : null);
    abort_if(request('id') ? ! $doc : ! $source, 404);
    $readonly = $doc && ($doc['stat_confirm'] || request('view'));
    $scope = $doc['revise_scope'] ?? [];
    $parts = ($doc ?? $source)['parts'];
    $types = Sales::dielineTypes();
    $typeCode = $source['type_code'];
    $version = 'v'.($source['revision_no'] + 1);
    $finishing = array_column(Sales::masters()['finishing_types'], 'finishing_name', 'id_finishing_type');
    $colors = ['4/4', '4/1', '4/0', '2/0', '1/1', '1/0'];
    $label = fn ($p, $n) => $p['part_name'] ?: 'Part '.($n + 1);
@endphp

<x-mp::sales.inquiry-derived mode="REVISE" :doc="$doc ?? []" :readonly="(bool) $readonly" :version="$version" :back-url="route('v2.screen', 'inquiry')" :submit-label="'Submit ke Costing ('.$version.')'">
    <x-dl::card title="1️⃣ Produk Asal & Jejak Relasi">
        <x-mp::sales.source-trace from-label="Inkuiri asal" :from="$source['inquiry_no'].' (v'.$source['revision_no'].')'" :to-label="'Versi baru ('.$version.')'" :to="$doc['inquiry_no'] ?? null" />
        <x-dl::alert tone="info">🔗 REVISE membuat <b>versi baru</b>. Dokumen hilir (quotation/CO) yang sudah pakai versi lama tetap memakai snapshotnya — tak terpengaruh. Versi lama akan ditandai <b>CANCELLED · SUPERSEDED</b> begitu versi baru ini disimpan.</x-dl::alert>
        <x-dl::form.row cols="3">
            <x-dl::field.display label="Customer" :value="$source['customer']" :source="Src::of('tm2sdi.iqp_inquiry.customer_id', 'tm2mst.cust_customer.customer_name')" />
            <x-dl::field.display label="Produk" :value="$source['product_draft_name']" :source="Src::of('tm2sdi.iqp_inquiry.product_draft_name')" />
            <x-dl::field.display label="Versi baru" :value="$version" :source="Src::of('tm2sdi.iqp_inquiry.revision_no')" />
        </x-dl::form.row>
    </x-dl::card>

    <x-dl::card title="2️⃣ Scope Revisi" description="pilih yang berubah (boleh >1)">
        <x-dl::alert tone="warn">Scope menentukan <b>apa yang wajib diproses ulang</b>. Pilih manual — sistem tak menebak, agar tak memicu proof/imposisi mahal yang tak perlu.</x-dl::alert>
        <x-dl::alert tone="danger" data-scope-err />
        <x-mp::sales.revise-scope :value="$scope" />
        <x-dl::field.text name="revise_reason" label="Alasan Revisi" required placeholder="mis. Tambah logo Halal Indonesia (spot ink) — permintaan customer" :value="$doc['revise_reason'] ?? ''" :source="Src::of('tm2sdi.iqp_inquiry.revise_reason')" />
    </x-dl::card>

    <x-dl::card title="3️⃣ Konsekuensi (otomatis dari scope)">
        <x-mp::sales.revise-impact :scope="$scope" />
    </x-dl::card>

    <x-dl::card title="🎨 Artwork — Identitas Desain Baru" class="mp-scopeblock" data-scope-block="ARTWORK" :hidden="! in_array('ARTWORK', $scope, true)">
        <x-dl::field.text name="design_number" label="No. Design / Proof" placeholder="mis. DS003480" :value="$doc['design_number'] ?? $source['design_number']" code :source="Src::of('tm2sdi.iqp_inquiry.design_number')" />
        @foreach ($parts as $n => $p)
            <div class="mp-subtitle">{{ $label($p, $n) }}</div>
            <div class="mp-thumbs">
                <x-mp::image-thumb :label="'Artwork Lama (v'.$source['revision_no'].')'" empty="tak ada file contoh" />
                <x-dl::field.upload :name="'parts['.$n.'][artwork]'" label="Artwork Baru" accept=".jpg,.jpeg,.png" :source="Src::of('tm2sdi.iqp_part.artwork_path', 'tm2sdi.iqp_part.artwork_original_filename')" />
            </div>
        @endforeach
        <x-dl::alert tone="info">Artwork baru akan melalui proof ulang (modul Proof/Prove) sebelum quotation — di luar layar ini.</x-dl::alert>
    </x-dl::card>

    <x-dl::card title="🖌️ Warna — Konfigurasi Cetak per Part" class="mp-scopeblock" data-scope-block="COLOR" :hidden="! in_array('COLOR', $scope, true)">
        @foreach ($parts as $n => $p)
            <x-dl::form.row cols="2">
                <x-dl::field.select :name="'parts['.$n.'][cfg_color]'" :label="$label($p, $n).' — Warna (mis. 4/4)'" :options="$colors" :value="$p['cfg_color']" :source="Src::of('tm2sdi.iqp_part.cfg_color')" />
                <x-dl::field.text :name="'parts['.$n.'][cfg_ink]'" label="Catatan tinta/plate" placeholder="mis. +1 spot Pantone 348C" :value="$p['cfg_ink']" :source="Src::of('tm2sdi.iqp_part.cfg_ink')" />
            </x-dl::form.row>
        @endforeach
        <x-dl::alert tone="warn">⚠️ Penambahan spot/plate menaikkan HPP walau ukuran &amp; bahan tetap — dihitung ulang oleh Costing.</x-dl::alert>
    </x-dl::card>

    <x-dl::card title="📐 Dimensi — Ukuran Baru" class="mp-scopeblock" data-scope-block="DIMENSION" :hidden="! in_array('DIMENSION', $scope, true)">
        @foreach ($parts as $n => $p)
            <div class="mp-subtitle">{{ $label($p, $n) }}</div>
            <x-mp::dieline-v3 :types="$types" :type="$typeCode" :dims="$p['dims']" :name="'parts['.$n.'][dieline]'" :picker="false" height="360px"
                :source="Src::of('tm2sdi.iqp_part.dieline_spec', 'tm2sdi.iqp_part.flat_w', 'tm2sdi.iqp_part.flat_h', 'tm2sdi.iqp_part.flat_area')" />
        @endforeach
        <x-dl::alert tone="warn">Ukuran berubah → up per plano bisa berubah → imposisi WAJIB diulang (di luar layar ini).</x-dl::alert>
    </x-dl::card>

    <x-dl::card title="🧱 Material — Substitusi" class="mp-scopeblock" data-scope-block="MATERIAL" :hidden="! in_array('MATERIAL', $scope, true)">
        @foreach ($parts as $n => $p)
            <x-dl::lines :name="'parts['.$n.'][materials]'" :title="$label($p, $n)" add-label="+ baris bahan" :rows="$p['materials']" :source="Src::of('tm2sdi.iqp_part_material')"
                :columns="[
                    ['key' => 'material', 'label' => 'Bahan', 'type' => 'lookup', 'lookup' => 'paper', 'required' => true, 'src' => Src::of('tm2sdi.iqp_part_material.rawmat_id', 'tm2mst.mat_raw.material_name')],
                    ['key' => 'gram_weight', 'label' => 'Gramasi', 'type' => 'number', 'width' => '110px', 'src' => Src::of('tm2sdi.iqp_part_material.gram_weight')],
                ]" />
        @endforeach
        <x-dl::alert tone="info">Gramasi/bahan naik → harga bahan berubah → costing ulang. Imposisi tetap (ukuran sama) kecuali scope Dimensi juga dipilih.</x-dl::alert>
    </x-dl::card>

    <x-dl::card title="⚙️ Finishing (routing dihitung ulang di ProDev)" class="mp-scopeblock" data-scope-block="PROCESS" :hidden="! in_array('PROCESS', $scope, true)">
        @foreach ($parts as $n => $p)
            <x-dl::lines :name="'parts['.$n.'][finishings]'" :title="$label($p, $n)" add-label="+ finishing" :min-rows="0" :rows="$p['finishings']" :source="Src::of('tm2sdi.iqp_part_finishing')"
                :columns="[
                    ['key' => 'finishing_type_id', 'label' => 'Jenis Finishing', 'type' => 'select', 'options' => $finishing, 'required' => true, 'src' => Src::of('tm2sdi.iqp_part_finishing.finishing_type_id')],
                    ['key' => 'attribute', 'label' => 'Atribut', 'placeholder' => 'atribut, mis. Gold Puyeh', 'src' => Src::of('tm2sdi.iqp_part_finishing.attribute')],
                ]" />
        @endforeach
        <x-dl::alert tone="info">Urutan/finishing berubah → waktu &amp; biaya proses berubah → costing ulang.</x-dl::alert>
    </x-dl::card>
</x-mp::sales.inquiry-derived>
