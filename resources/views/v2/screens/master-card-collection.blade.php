{{-- Master Card Collection v2 (mockup docs/mockups/master_card_collection_v2.jpg, user 2026-10-01; menu Pro-Dev).
     Tanpa ?id = grid + upload banyak file (Excel sheet "MC" dibaca otomatis, foto = isian manual). ?id = form kartu:
     koreksi hasil baca, Tipe Produk & ukuran dieline (SVG disimpan tiap Save), lalu Make Master Product / Make Inquiry
     lewat dialog yang hanya menanyakan field kunci yang kosong. Logika = App\Services\MasterCard\MasterCardService. --}}
@php
    use App\Models\Master\ProdMasterCard;
    use App\Models\Master\ProdMasterCardFile;
    use App\Services\MasterCard\MasterCardService;
    use App\Services\MasterCard\MasterCardShape;
    use App\Support\V2\Data\MasterCardCollection;
    use App\Support\V2\Data\Sales;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'master-card-collection');
    $isNew = request()->boolean('new');
    $card = request('id') ? ProdMasterCard::with(['files', 'customer'])->find((int) request('id')) : null;
    abort_if(request('id') && ! $card, 404);
    $svc = new MasterCardService(auth()->user()->login);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if (! $card && ! $isNew)
    <x-dl::card title="Upload Master Card" description="Master card Excel (.xlsx, sheet MC) is read automatically + proof image extracted · card photo (.jpg/.png) is stored as an image, its fields completed in the form">
        <form method="post" action="{{ route('v2.mcard.upload') }}" enctype="multipart/form-data">
            @csrf
            <x-dl::form.row cols="1">
                <x-dl::field.upload name="files" :multiple="true" :preview="false" label="File master card (boleh banyak sekaligus)" accept=".xlsx,.xlsm,.xls,.jpg,.jpeg,.png" :source="Src::of('tm2mst.prod_master_card', 'tm2mst.prod_master_card_file')" />
            </x-dl::form.row>
            <x-dl::action-bar><x-dl::button type="submit" icon="fa-cloud-arrow-up" label="Upload" /></x-dl::action-bar>
        </form>
    </x-dl::card>

    <x-dl::grid id="mcard-list" :rows="MasterCardCollection::rows()" search="Search name / design no. / item code / customer..." actions="worklist" :active-key="null"
        :open-url="$home.'?id={id}'" :new-url="$home.'?new=1'" source="{{ Src::of('tm2mst.prod_master_card', 'tm2mst.prod_master_card_file') }}"
        :columns="[
            ['key' => 'photo', 'label' => 'Proof', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Master Card', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'part', 'label' => 'Part'],
            ['key' => 'dims', 'label' => 'Dimensi (P×L×T)'],
            ['key' => 'type', 'label' => 'Product Type'],
            ['key' => 'knife', 'label' => 'Knife / UP'],
            ['key' => 'svg', 'label' => 'SVG Dieline', 'type' => 'badge', 'tones' => ['Ada' => 'success', 'Belum' => 'danger']],
            ['key' => 'source', 'label' => 'Source'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => MasterCardCollection::TONES],
            ['key' => 'updated', 'label' => t('Changed'), 'type' => 'date', 'visible' => false],
        ]"
        :stats="[
            ['label' => 'All'],
            ['label' => 'Incomplete', 'where' => ['status' => 'Incomplete'], 'tone' => 'warn'],
            ['label' => 'Ready', 'where' => ['status' => 'Ready'], 'tone' => 'info'],
            ['label' => 'Master Product', 'where' => ['status' => 'Master Product'], 'tone' => 'success'],
            ['label' => 'Inquiry', 'where' => ['status' => 'Inquiry'], 'tone' => 'accent'],
        ]"
        :filters="['customer' => 'Customer', 'type' => 'Tipe Produk', 'source' => 'Sumber', 'status' => 'Status']" />
@else
    @php
        $c = $card ?? new ProdMasterCard(['source_kind' => 'MANUAL', 'status' => 'INCOMPLETE']);
        $types = Sales::dielineTypes();
        $missing = $card ? $svc->missing($card) : [];
        $check = $card && $card->type_code && $card->dims_json ? MasterCardShape::check($card->toArray(), $card->type_code, $card->dims_json) : null;
        $parts = MasterCardShape::parts($c->toArray());
        $file = fn (string $kind, ?string $part = null) => $card?->files->first(fn ($f) => $f->file_kind === $kind && ($part === null || (string) $f->part_label === $part));
        $artwork = $file('ARTWORK', $parts[0]);
        $locked = $card?->converted() ?? false;
        $cats = MasterCardCollection::categoryOptions();
        $mm = fn ($v) => $v === null ? null : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
        $warn = collect($missing)->values()->implode(' · ');
        $status = $locked ? 'closed' : ($card ? 'draft' : 'new');
    @endphp

    <x-dl::form id="mcard-form" :title="$c->product_name ? raw($c->product_name) : 'Master Card baru'"
        :subtitle="$card ? 'Master Card · '.raw((['XLSX' => 'Excel', 'PHOTO' => 'Foto', 'MANUAL' => 'Manual'][$c->source_kind] ?? $c->source_kind).' · '.ProdMasterCard::STATUS[$c->status].($c->customer_text ? ' · kartu: '.$c->customer_text : '')) : 'Isi manual (mis. dari foto kartu)'"
        panel="top" kind="document" :status="$status" :action="$card ? route('v2.mcard.save', $card->id_master_card) : route('v2.mcard.new')"
        :back="$home" :new-url="$home.'?new=1'" enctype="multipart/form-data" :hide="['confirm', 'cancel', 'draft', 'duplicate', 'email', 'print']"
        :tabs="['main' => 'Data Kartu', 'bom' => 'Bahan & Proses', 'files' => 'Gambar']"
        :created-by="$c->created_by" :created-at="$c->created_at?->toDateTimeString()" :updated-by="$c->updated_by" :updated-at="$c->updated_at?->toDateTimeString()">
        @if ($card && ! $locked)
            <x-slot:special>
                <x-dl::button size="sm" variant="success" icon="fa-box" label="Make Master Product" data-dl-open="mcard-product" />
                <x-dl::button size="sm" variant="secondary" icon="fa-file-circle-plus" label="Make Inquiry" data-dl-open="mcard-inquiry" />
            </x-slot:special>
        @endif
        <x-slot:strip>
            <div class="dl-strip__kv dl-strip__doc"><span>Kode Item</span><b>{{ $c->card_code ?: '—' }}</b></div>
            <x-dl::field.display label="No. Desain" :value="$c->design_no ?: '—'" :source="Src::of('tm2mst.prod_master_card.design_no')" />
            <x-dl::field.display label="Customer" :value="$c->customer?->customer_name ?? ($c->customer_text ? $c->customer_text.' (belum cocok master)' : '—')" :source="Src::of('tm2mst.prod_master_card.customer_id', 'tm2mst.cust_customer.customer_name')" />
            <div class="dl-strip__kv dl-strip__grand"><span>Status</span><b>{{ ProdMasterCard::STATUS[$c->status] }}</b></div>
        </x-slot:strip>

        <x-slot:main>
            @if ($locked)
                <x-dl::alert tone="success" :message="$c->status === 'PRODUCT' ? 'Sudah ada di Master Product — kartu terkunci (dibuat dari kartu ini atau ditautkan karena no. desain / kode item sama).' : 'Sudah menjadi Inquiry — kartu terkunci.'" />
                <x-dl::action-bar>
                    @if ($c->product_id)<x-dl::button size="sm" variant="secondary" icon="fa-box" label="Buka Master Product" :href="route('v2.screen', 'master-product').'?id='.$c->product_id" />@endif
                    @if ($c->inquiry_id)<x-dl::button size="sm" variant="secondary" icon="fa-inbox" label="Buka Inquiry" :href="route('v2.screen', 'inquiry-new').'?id='.$c->inquiry_id" />@endif
                </x-dl::action-bar>
            @elseif ($missing)
                <x-dl::alert tone="warn" :message="count($missing).' field kunci belum ada: '.$warn.'. Kolom lain terisi dari kartu (bisa dikoreksi).'" />
            @elseif ($card)
                <x-dl::alert tone="info" message="All key fields complete — ready to become a Master Product or Inquiry." />
            @endif
            <x-dl::divider label="Identitas" />
            <x-dl::form.row cols="4">
                <x-dl::field.text name="card_code" label="Item Code" :value="old('card_code', $c->card_code)" :source="Src::of('tm2mst.prod_master_card.card_code')" />
                <x-dl::field.text name="design_no" label="No. Desain" :value="old('design_no', $c->design_no)" :error="isset($missing['design_no']) ? 'kosong di kartu' : null" :source="Src::of('tm2mst.prod_master_card.design_no')" />
                <x-dl::field.text name="product_name" label="Item Name" required :value="old('product_name', $c->product_name)" :source="Src::of('tm2mst.prod_master_card.product_name')" />
                <x-dl::field.date name="card_date" label="Card Date" :value="old('card_date', $c->card_date?->toDateString())" :source="Src::of('tm2mst.prod_master_card.card_date')" />
            </x-dl::form.row>
            <x-dl::form.row cols="4">
                <x-dl::field.lookup name="customer_id" lookup="customer" label="Customer (master)" placeholder="Search customer..." :value="old('customer_id', $c->customer_id)"
                    :hint="$c->customer_text ? 'Teks kartu: '.raw($c->customer_text) : null" :error="isset($missing['customer_id']) ? 'belum dipilih' : null" :source="Src::of('tm2mst.prod_master_card.customer_id', 'tm2mst.cust_customer')" />
                <x-dl::field.select name="part_label" label="Part" :options="['' => 'Kartu utuh (Double Join = A + B)', 'A' => 'Part A saja', 'B' => 'Part B saja']" :value="old('part_label', $c->part_label ?? '')" :placeholder="null"
                    hint="Cards per part with the same Design No. are merged into 1 product" :source="Src::of('tm2mst.prod_master_card.part_label')" />
                <x-dl::field.select name="join_type_id" label="Sambungan" :options="MasterCardCollection::joinOptions()" :value="old('join_type_id', $c->join_type_id)" placeholder="— tanpa sambungan —"
                    :hint="$c->join_text ? 'Teks kartu: '.raw($c->join_text) : null" :source="Src::of('tm2mst.prod_master_card.join_type_id', 'tm2mst.mat_join_type')" />
                <x-dl::field.number name="pack_qty" label="Isi per Pack (pcs)" :min="1" :value="old('pack_qty', $c->pack_qty)" :source="Src::of('tm2mst.prod_master_card.pack_qty')" />
            </x-dl::form.row>
            <x-dl::divider label="Size & Shape" />
            <x-dl::form.row cols="4">
                <x-dl::field.decimal name="dim_l" label="Length L (mm)" :value="old('dim_l', $mm($c->dim_l))" :source="Src::of('tm2mst.prod_master_card.dim_l')" />
                <x-dl::field.decimal name="dim_w" label="Width W (mm)" :value="old('dim_w', $mm($c->dim_w))" :source="Src::of('tm2mst.prod_master_card.dim_w')" />
                <x-dl::field.decimal name="dim_h" label="Height H (mm)" :value="old('dim_h', $mm($c->dim_h))" :source="Src::of('tm2mst.prod_master_card.dim_h')" />
                <x-dl::field.text name="up_text" label="Isi 1 Cetakan" placeholder="mis. 8 UP / 1/2 UP / 1 SET" :value="old('up_text', $c->up_text)" :source="Src::of('tm2mst.prod_master_card.up_text')" />
            </x-dl::form.row>
            <x-dl::form.row cols="4">
                <x-dl::field.text name="knife_code" label="Die Code" :value="old('knife_code', $c->knife_code)" :source="Src::of('tm2mst.prod_master_card.knife_code')" />
                <x-dl::field.decimal name="knife_w" label="Die width (mm)" :value="old('knife_w', $mm($c->knife_w))" :source="Src::of('tm2mst.prod_master_card.knife_w')" />
                <x-dl::field.decimal name="knife_h" label="Die height (mm)" :value="old('knife_h', $mm($c->knife_h))" :source="Src::of('tm2mst.prod_master_card.knife_h')" />
                <x-dl::field.select name="type_code" label="Product Type (dieline)" :options="MasterCardCollection::typeOptions()" :value="old('type_code', $c->type_code)" placeholder="— choose —"
                    hint="System suggestion; change it and Save = size recalculated from the die" :error="isset($missing['type_code']) ? 'belum dipilih' : null" :source="Src::of('tm2mst.prod_master_card.type_code', 'tm2mst.prod_type')" />
            </x-dl::form.row>
            @if ($check)
                <x-dl::alert :tone="$check['ok'] ? 'success' : 'warn'"
                    :message="'Cek bentangan: rumus '.$mm($check['flat']['w']).' × '.$mm($check['flat']['h']).' mm per potong'.($check['piece'] ? ' vs pisau '.$mm($check['piece'][0]).' × '.$mm($check['piece'][1]).' mm ('.$c->up_text.')' : '').($check['ok'] ? ' — cocok.' : ' — selisih, sesuaikan parameter dieline lalu Save.')" />
            @endif
            @if ($c->type_code && isset($types[$c->type_code]))
                <x-mp::dieline-v3 :data-artwork="$artwork?->url()" :types="$types" :type="$c->type_code" :dims="$c->dims_json ?? []" name="dieline" :picker="false" height="420px"
                    :source="Src::of('tm2mst.prod_master_card.dims_json', 'tm2mst.prod_master_card.dieline_svg', 'tm2mst.prod_type.dieline_spec')" />
                <p class="mp-hint">SVG dieline disimpan di kartu setiap Save ({{ $c->dieline_svg ? \Devlib\Locale::number(strlen($c->dieline_svg)).' karakter' : 'belum ada' }}) dan ikut ke Master Product / Inquiry.</p>
            @endif
            <x-dl::form.row cols="1">
                <x-dl::field.text :multiline="true" name="notes" label="Description (card)" :value="old('notes', $c->notes)" :source="Src::of('tm2mst.prod_master_card.notes')" />
            </x-dl::form.row>
        </x-slot:main>

        <x-slot:bom>
            <x-dl::divider label="Material — check the master category" />
            @forelse ($c->materials_json ?? [] as $i => $m)
                <x-dl::form.row cols="3">
                    <x-dl::field.display :label="'Bahan #'.($i + 1).' di kartu'" :value="trim($m['name'].' · '.($m['quality'] ?? '').' · '.($m['size_text'] ?? ''), ' ·')" :source="Src::of('tm2mst.prod_master_card.materials_json')" />
                    <x-dl::field.select :name="'materials['.$i.']'" :label="'Kategori master #'.($i + 1)" :options="$cats" :value="$m['category_id'] ?? null" placeholder="— choose a category —"
                        :error="isset($missing['materials.'.$i]) ? 'belum cocok master' : null" :source="Src::of('tm2mst.mat_category')" />
                    <x-dl::field.display label="Material size" :value="! empty($m['size_p']) ? $mm($m['size_p']).' × '.$mm($m['size_l']).' mm' : '—'" />
                </x-dl::form.row>
            @empty
                <x-dl::empty-state title="No material yet" message="Card from a photo: material is filled when the Excel card is available, or complete it in the Inquiry after Make Inquiry." />
            @endforelse
            <x-dl::divider label="Finishing" />
            <x-dl::table :columns="['finishing_name' => 'Finishing', 'attribute' => 'Atribut', 'text' => 'Teks kartu', 'master' => 'Master']"
                :rows="array_map(fn ($f) => $f + ['master' => $f['finishing_type_id'] ? 'cocok' : 'tidak ada di master (tidak ikut)'], $c->finishing_json ?? [])" empty="Tanpa finishing." :source="Src::of('tm2mst.prod_master_card.finishing_json', 'tm2mst.ref_finishing_type')" />
            <x-dl::divider label="Urutan pekerjaan (BOP)" />
            <x-dl::table :columns="['no' => '#', 'text' => 'Teks kartu', 'process_name' => 'Proses master', 'scope' => 'Masuk ke']"
                :rows="collect($c->process_json ?? [])->values()->map(fn ($p, $i) => ['no' => $i + 1, 'text' => $p['text'], 'process_name' => $p['process_name'] ?? '— tidak cocok —', 'scope' => $p['scope'] === 'BODY' ? 'Body (rakitan)' : 'Part'])->all()"
                empty="Belum ada urutan pekerjaan." :source="Src::of('tm2mst.prod_master_card.process_json', 'tm2mst.prod_process')" />
            <x-dl::divider label="Colour & ACC" />
            <x-dl::form.row cols="3">
                <x-dl::field.display label="First colour" :value="implode(', ', $c->colors_json['first'] ?? []) ?: '—'" />
                <x-dl::field.display label="Second colour" :value="implode(', ', $c->colors_json['second'] ?? []) ?: '—'" />
                <x-dl::field.display label="ACC" :value="collect($c->acc_json ?? [])->map(fn ($v, $k) => str_replace('_', ' ', $k).': '.$v)->implode(' · ') ?: '—'" :source="Src::of('tm2mst.prod_master_card.acc_json')" />
            </x-dl::form.row>
        </x-slot:bom>

        <x-slot:files>
            <x-dl::divider label="Artwork per part (placed on the dieline & carried to Inquiry / Master Product)" />
            <x-dl::form.row cols="3">
                @foreach ($parts as $p)
                    @php($art = $file('ARTWORK', $p))
                    <x-dl::field.upload :name="'artwork['.($p === '' ? '_' : $p).']'" :label="'Artwork '.($p === '' ? 'produk' : raw('Part '.$p))" accept=".jpg,.jpeg,.png" :disabled="$locked"
                        :value="$art ? ['url' => $art->url(), 'name' => $art->original_name] : null" :source="Src::of('tm2mst.prod_master_card_file (ARTWORK)')" />
                @endforeach
            </x-dl::form.row>
            <x-dl::divider label="Image from card" />
            <div class="mp-thumbs">
                @forelse ($card?->files->whereIn('file_kind', ['PROOF', 'CUT_PLAN', 'PHOTO']) ?? [] as $f)
                    <x-mp::image-thumb :label="ProdMasterCardFile::KINDS[$f->file_kind]" :src="$f->url()" />
                @empty
                    <x-dl::empty-state title="No image yet" message="Upload a master card Excel or a card photo." />
                @endforelse
            </div>
            @if ($src = $file('SOURCE'))
                <x-dl::action-bar><x-dl::button size="sm" variant="secondary" icon="download" :label="'File asli: '.$src->original_name" :href="$src->url()" /></x-dl::action-bar>
            @endif
        </x-slot:files>
    </x-dl::form>

    @if ($card && ! $locked)
        @foreach (['product' => ['mcard-product', 'Make Master Product', 'fa-box', 'success'], 'inquiry' => ['mcard-inquiry', 'Make Inquiry', 'fa-file-circle-plus', 'primary']] as $target => [$mid, $title, $icon, $variant])
            @php($need = $svc->missing($card, $target))
            <x-dl::modal :id="$mid" :title="$title.' — '.raw($c->product_name)" :icon="$icon" size="lg" :open="session('mcard_dialog') === $target">
                <form method="post" action="{{ route('v2.mcard.convert', $card->id_master_card) }}" id="{{ $mid }}-form">
                    @csrf
                    <input type="hidden" name="target" value="{{ $target }}">
                    @if ($errors->has('dialog'))<x-dl::alert tone="danger" :message="$errors->first('dialog')" />@endif
                    <x-dl::alert tone="info" :message="$need ? 'Lengkapi '.count($need).' isian ini dulu. Yang lain sudah terisi dari kartu.' : 'Field kunci kartu sudah lengkap.'" />
                    <x-dl::form.row cols="2">
                        @if (isset($need['design_no']))<x-dl::field.text name="design_no" label="No. Desain" required placeholder="mis. DS003xxx" :value="old('design_no')" hint="Empty on the card." />@endif
                        @if (isset($need['customer_id']))<x-dl::field.lookup name="customer_id" lookup="customer" label="Customer (master)" required placeholder="Search customer..." :hint="$c->customer_text ? 'Teks kartu: '.raw($c->customer_text) : null" />@endif
                        @if (isset($need['type_code']))<x-dl::field.select name="type_code" label="Product Type (dieline)" required :options="MasterCardCollection::typeOptions()" placeholder="— choose —" />@endif
                        @foreach ($c->materials_json ?? [] as $i => $m)
                            @if (isset($need['materials.'.$i]))<x-dl::field.select :name="'materials['.$i.']'" :label="'Bahan #'.($i + 1).' (kategori master)'" required :options="$cats" placeholder="— choose —" :hint="'Teks kartu: '.raw($m['name'].' · '.($m['quality'] ?? ''))" />@endif
                        @endforeach
                        @if ($target === 'inquiry')
                            <x-dl::field.number name="qty" label="Qty order (tier 1)" required :min="1" :value="old('qty')" />
                            <x-dl::field.date name="deadline" label="Deadline quotation" required :value="old('deadline', now()->addDays(3)->toDateString())" />
                            <x-dl::field.lookup name="pic_sales" lookup="sales_person" label="PIC Sales" required placeholder="Search sales..." :value="old('pic_sales')" />
                        @endif
                    </x-dl::form.row>
                    @foreach (array_diff_key($need, array_flip(['design_no', 'customer_id', 'type_code'])) as $k => $label)
                        @if (! str_starts_with($k, 'materials.'))<x-dl::alert tone="warn" :message="$label.' masih kosong — isi di form kartu lalu Save dulu.'" />@endif
                    @endforeach
                    <x-dl::alert tone="info" :message="$target === 'product'
                        ? 'Akan dibuat: Master Product “'.$c->product_name.'” · part '.implode(' + ', array_map(fn ($p) => $p === '' ? 'tunggal' : $p, $parts)).(count($parts) > 1 ? ' + Body rakitan' : '').' · '.($types[$c->type_code]['label'] ?? '—').' · '.count($c->process_json ?? []).' langkah proses · dieline (tipe + ukuran) ikut, SVG tetap tersimpan di kartu. Jalurnya lewat Inquiry sumber “MASTER CARD” (aturan Master Product tetap satu).'
                        : ($c->product_id ? 'Produk sudah ada → dibuka sebagai Inquiry Rekalkulasi.' : 'Akan dibuat: Inquiry BARU (DRAFT) berisi semua part, bahan, proses, artwork & dieline dari kartu — lanjutkan di layar Inquiry lalu Confirm.')" />
                </form>
                <x-slot:footer>
                    <x-dl::button variant="secondary" label="Cancel" data-dl-close-modal />
                    <x-dl::button :variant="$variant" :icon="$icon" :label="$title" type="submit" form="{{ $mid }}-form" />
                </x-slot:footer>
            </x-dl::modal>
        @endforeach
    @endif
@endif
