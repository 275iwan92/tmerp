{{-- Inquiry Baru (NEW). Flow & field: tmerp pages/inquiry/form.blade.php (JANGAN diubah); ditulis ulang dari objek DevLib + x-mp::sales.*. --}}
@php
    use App\Support\V2\Data\Sales;
    use App\Support\V2\Src;
    use MockupParts\Components\Sales\InquiryForm;

    $doc = request('id') ? Sales::inquiryDoc((int) request('id')) : null;
    $readonly = $doc && ($doc['stat_confirm'] || request('view'));
    $types = Sales::dielineTypes();
    $typeCode = $doc['type_code'] ?? '';
    $finishing = array_column(Sales::masters()['finishing_types'], 'finishing_name', 'id_finishing_type');
    $blankPart = [];
    $chains = Sales::materialChains();
    $parts = $doc['parts'] ?? [$blankPart];
    $tiers = $doc['tiers'] ?? [['qty' => 10000, 'is_primary' => true], ['qty' => 50000, 'is_primary' => false], ['qty' => 200000, 'is_primary' => false]];
    // Kategori Produk -> Detail Kategori -> Ukuran Pasaran = master Lookup Product Market (tm2mst.prod_market, diisi officer ProDev).
    $markets = Sales::productMarkets();
    $i = fn (string $col) => Src::of('tm2sdi.iqp_inquiry.'.$col);
@endphp

<x-mp::sales.inquiry-form mode="NEW" :doc="$doc ?? []" :readonly="(bool) $readonly" :back-url="route('v2.screen', 'inquiry')"
    :links="['🔀 Imposition' => route('v2.screen', 'prodev'), '🖨 Cetak Nota' => route('v2.screen', 'nota-inquiry').($doc ? '?id='.$doc['id'] : '')]"
    :prodev-url="route('v2.screen', 'prodev').'?part={part}&inquiry={inquiry}'"
    :urls="['save' => route('v2.inquiry.save'), 'end' => route('v2.inquiry.end', '__ID__'), 'artwork' => route('v2.inquiry.artwork', '__ID__'),
        'prodev' => route('v2.inquiry.prodev', '__ID__'), 'customer' => route('v2.inquiry.customer')]">

    <x-slot:header>
        <x-dl::divider label="Header Inkuiri" />
        <x-dl::form.row cols="5">
            <x-dl::field.display label="No. Inkuiri" :value="$doc['inquiry_no'] ?? 'Otomatis saat disimpan'" format="mono" :source="$i('inquiry_no')" />
            <x-dl::field.date name="inquiry_date" label="Tanggal" required :value="$doc['inquiry_date'] ?? date('Y-m-d')" :source="$i('inquiry_date')" />
            <x-dl::field.date name="deadline_quotation" label="Tgl Kebutuhan Pelanggan" :value="$doc['deadline_quotation'] ?? date('Y-m-d', strtotime('+14 days'))" :source="$i('deadline_quotation')" />
            <x-dl::field.lookup name="pic_sales" label="PIC Sales" lookup="sales_person" :value="$doc['pic_sales'] ?? auth()->user()->name" :source="$i('pic_sales').' ← tm2mst.sales_person'" />
            <x-dl::field.select name="source" label="Sumber" required :options="['EMAIL', 'WA', 'PHONE', 'VISIT', 'MARKETPLACE']" :value="$doc['source'] ?? 'EMAIL'" :source="$i('source')" />
        </x-dl::form.row>
        <x-dl::form.row cols="3">
            <x-dl::field.lookup name="customer_id" label="Customer" lookup="customer" create required placeholder="Ketik utk cari, atau scroll pilih…" :value="$doc['customer_id'] ?? null"
                :source="Src::of('tm2sdi.iqp_inquiry.customer_id', 'tm2mst.cust_customer.customer_name')" />
            <x-dl::field.text name="product_draft_name" label="Nama Produk (draft)" multiline required :maxlength="150" placeholder="mis. Box Kosmetik Serum 30ml"
                :hint="'Nama sementara selama quotation. Karakter khusus (/@#%'.chr(34).chr(39).';) tidak diperbolehkan.'" :value="$doc['product_draft_name'] ?? ''" :source="$i('product_draft_name')" />
        </x-dl::form.row>
        {{-- Kategori -> Detail -> Ukuran bersambung (user 2026-09-26); Tipe Produk = nama dieline internasional, terisi otomatis
             dari Detail & terkunci (tetap bisa dipilih manual bila tidak memakai kategori, mis. produk custom). --}}
        <script type="application/json" data-inq-markets>@json($markets)</script>
        <x-dl::form.row cols="4">
            {{-- Wajib diisi sampai Ukuran (user 2026-09-26); produk yang belum ada di daftar lewat pilihan Custom. --}}
            <x-dl::field.select name="product_group" label="Kategori Produk" required placeholder="— pilih kategori —"
                :options="collect($markets)->pluck('label', 'code')->all() + ['__CUSTOM__' => '➕ Custom (belum ada di daftar)']"
                hint="Wajib — pilih Custom bila produk belum ada di daftar." :source="'tm2mst.prod_market (induk), tidak disimpan'" />
            <x-dl::field.select name="product_detail" label="Detail Kategori" required placeholder="— pilih kategori dulu —" :options="[]" disabled
                :source="'tm2mst.prod_market (detail) → Tipe Produk, tidak disimpan'" />
            <x-dl::field.select name="market_size" label="Ukuran Pasaran" required placeholder="— pilih detail dulu —" :options="[]" disabled
                :source="'tm2mst.prod_type_size (milik detail) → dimensi semua Part'" />
            <x-dl::field.select name="type_code" label="Tipe Produk (dieline internasional)" placeholder="— pilih tipe produk —" :value="$typeCode"
                :options="collect($types)->map(fn ($t) => $t['label'])->all()" hint="Otomatis dari Detail Kategori — nama ini yang dicari di Pacdora. Custom: pilih dieline terdekat."
                :source="Src::of('tm2sdi.iqp_part.part_type', 'tm2mst.prod_type.type_code')" />
        </x-dl::form.row>
        <div data-inq-custom hidden>
            <x-dl::form.row cols="2">
                <x-dl::field.text name="product_detail_custom" label="Nama Produk Custom" required placeholder="mis. Stand Smartphone / Dus Telur / Dus Pot Tanaman"
                    :source="'catatan detail custom (belum ada di Lookup Product Market)'" />
                <x-dl::alert tone="info">Produk belum ada di daftar: pilih <b>Tipe Produk</b> (dieline) yang paling mirip dan isi dimensi manual di part.
                    Bila sering dipesan, minta officer ProDev menambahkannya di <b>Lookup Product Market</b>.</x-dl::alert>
            </x-dl::form.row>
        </div>
        <x-dl::form.row cols="2">
            <x-dl::alert tone="info" data-inq-template-note><span data-inq-template-text></span><x-dl::button size="sm" variant="secondary" label="Tambahkan Otomatis" data-inq-template /></x-dl::alert>
        </x-dl::form.row>
        <x-dl::form.row cols="2">
            <x-dl::field.text name="sales_note" label="Catatan Sales" placeholder="opsional" multiline :value="$doc['sales_note'] ?? ''" :source="$i('sales_note')" />
            <x-dl::field.checkbox name="material_source" label="Sumber Bahan" text="Customer bawa bahan baku sendiri" :value="($doc['material_source'] ?? 'OWN') === 'CUSTOMER'"
                hint="Tidak dicentang → bahan dari perusahaan, order bersifat PRODUCT." :source="Src::of('tm2sdi.iqp_inquiry.material_source', 'tm2sdi.iqp_inquiry.job_nature', 'tm2sdi.iqp_inquiry.pph_applicable')" />
        </x-dl::form.row>
        <div data-inq-custmat hidden>
            <x-dl::divider label="Spesifikasi Bahan Customer (wajib bila bahan dari customer)" />
            <x-dl::form.row cols="2">
                <x-dl::field.text name="cust_material_spec" label="Deskripsi bahan customer" required placeholder="mis. Art Carton 260gsm, 65×100cm, brand APP, 50 rim" :source="$i('cust_material_spec')" />
                <x-dl::field.date name="cust_material_eta" label="ETA bahan tiba" :source="$i('cust_material_eta')" />
            </x-dl::form.row>
            <x-dl::alert tone="info">Bahan customer → HPP material = 0; masuk via Receiving non-costed (consignment). Handling &amp; spoilage dihitung Costing.</x-dl::alert>
        </div>
    </x-slot:header>

    <x-dl::card title="Qty Order Berjenjang" description="Costing hitung HPP per tier — tepat 1 UTAMA">
        <x-dl::alert tone="warn" data-inq-tier-err />
        <div class="mp-narrow">
            <x-dl::lines name="tiers" title="Tier" add-label="+ Tambah Tier" :rows="$tiers" :source="Src::of('tm2sdi.iqp_tier')"
                :columns="[
                    ['key' => 'qty', 'label' => 'Qty (pcs)', 'type' => 'number', 'required' => true, 'placeholder' => 'mis. 10000', 'src' => Src::of('tm2sdi.iqp_tier.qty')],
                    ['key' => 'is_primary', 'label' => 'Utama', 'type' => 'primary', 'width' => '100px', 'src' => Src::of('tm2sdi.iqp_tier.is_primary')],
                ]" />
        </div>
    </x-dl::card>

    <x-dl::card title="Part & Dieline" description="dimensi produk & preview dieline live">
        <div data-inq-parts>
            @foreach ($parts as $n => $part)
                <x-mp::sales.inquiry-part :index="(string) $n" :part="$part + ['saved' => (bool) $doc]" :types="$types" :type-code="$typeCode" :finishing-types="$finishing" :material-chains="$chains" />
            @endforeach
        </div>
        <template data-inq-part-tpl><x-mp::sales.inquiry-part index="__I__" :part="$blankPart" :types="$types" :type-code="$typeCode" :finishing-types="$finishing" :material-chains="$chains" /></template>
        <script type="application/json" data-inq-matchains>@json($chains)</script>
        <div><x-dl::button variant="secondary" icon="＋" label="Tambah Part" data-inq-add-part /></div>
    </x-dl::card>

    <x-dl::card title="Status Tooling" description="plat/pisau/film — cek aus saat repeat order">
        <x-dl::alert tone="info">🔧 Tooling baru (plat cetak, pisau/matras, film) → biaya masuk sebagai line terpisah. Master aset tooling (cek otomatis aus) menyusul di iterasi berikutnya — isi manual dulu.</x-dl::alert>
        <x-dl::lines name="toolings" title="Tooling" add-label="+ Tambah Tooling" :min-rows="0" :rows="$doc['toolings'] ?? []" :source="Src::of('tm2sdi.iqp_tooling')"
            :columns="[
                ['key' => 'tooling_kind', 'label' => 'Jenis', 'type' => 'select', 'options' => ['PLATE' => 'Plat cetak', 'PISAU' => 'Pisau/matras', 'MATRAS' => 'Matras', 'FILM' => 'Film'], 'default' => 'PLATE', 'width' => '150px', 'src' => Src::of('tm2sdi.iqp_tooling.tooling_kind')],
                ['key' => 'tooling_status', 'label' => 'Status', 'type' => 'select', 'options' => ['ADA' => 'Ada', 'ADA_WARNING' => 'Ada (warning)', 'AUS' => 'Aus', 'TIDAK_ADA' => 'Tidak ada'], 'default' => 'ADA', 'width' => '160px', 'src' => Src::of('tm2sdi.iqp_tooling.tooling_status')],
                ['key' => 'is_new_charge', 'label' => 'Biaya Baru?', 'type' => 'bool', 'width' => '110px', 'src' => Src::of('tm2sdi.iqp_tooling.is_new_charge')],
                ['key' => 'cost', 'label' => 'Biaya (Rp)', 'type' => 'money', 'readonlyUnless' => 'is_new_charge', 'width' => '150px', 'src' => Src::of('tm2sdi.iqp_tooling.cost')],
                ['key' => 'remark', 'label' => 'Catatan', 'placeholder' => 'opsional', 'src' => Src::of('tm2sdi.iqp_tooling.remark')],
            ]" />
    </x-dl::card>

    <x-dl::card title="Biaya Eksternal / Pihak Ketiga" description="bila sudah ditentukan sejak awal">
        <x-dl::alert tone="info">🚚 Biaya pihak ketiga yang sudah pasti (trucking, ekspedisi, jasa luar) dicatat sejak inkuiri → mengalir ke quotation &amp; CO.</x-dl::alert>
        <x-dl::lines name="external_costs" title="Biaya" add-label="+ Tambah Biaya" :min-rows="0" :rows="$doc['external_costs'] ?? []" :source="Src::of('tm2sdi.iqp_external_cost')"
            :columns="[
                ['key' => 'description', 'label' => 'Deskripsi', 'placeholder' => 'mis. trucking ke gudang customer', 'src' => Src::of('tm2sdi.iqp_external_cost.description')],
                ['key' => 'cost_kind', 'label' => 'Jenis', 'type' => 'select', 'options' => ['TRUCKING' => 'Trucking', 'EKSPEDISI' => 'Ekspedisi', 'JASA_LUAR' => 'Jasa Luar', 'LAINNYA' => 'Lainnya'], 'default' => 'TRUCKING', 'width' => '150px', 'src' => Src::of('tm2sdi.iqp_external_cost.cost_kind')],
                ['key' => 'amount', 'label' => 'Jumlah (Rp)', 'type' => 'money', 'width' => '150px', 'src' => Src::of('tm2sdi.iqp_external_cost.amount')],
                ['key' => 'tax_treatment', 'label' => 'Perlakuan Pajak', 'type' => 'select', 'options' => ['TAXABLE' => 'Taxable (masuk DPP)', 'REIMBURSEMENT' => 'Reimbursement'], 'default' => 'TAXABLE', 'width' => '180px', 'src' => Src::of('tm2sdi.iqp_external_cost.tax_treatment')],
            ]" />
    </x-dl::card>
</x-mp::sales.inquiry-form>
