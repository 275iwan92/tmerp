{{--
 REMARK
 Tujuan       : Master Card Collection (menu Pro-Dev): upload banyak berkas master card (Excel sheet MC dibaca otomatis, foto = isian manual), koreksi hasil baca, tentukan tipe produk dan ukuran dieline, lalu Make Master Product atau Make Inquiry.
 Sumber hulu  : App\Services\MasterCard\MasterCardService dan MasterCardShape; App\Support\V2\Data\MasterCardCollection dan Sales; model ProdMasterCard dan ProdMasterCardFile (tm2mst).
 Input        : Query id (kartu), new (kartu manual); sesi v2_flash dan errors.
 Output       : x-dl::card, x-mp::dieline-v dan x-mp::image-thumb; posting ke route v2.mcard.upload, v2.mcard.new, v2.mcard.save, v2.mcard.convert (SVG dieline disimpan tiap Save).
 Dipakai oleh : Route v2.screen slug master-card-collection (App\Support\V2\Screens, menu sys_app_menu target v2:master-card-collection); konversi menuju inquiry-new atau inquiry-recalc
 Status       : Aktif (mockup master_card_collection_v2.jpg)
--}}
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

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $card && ! $isNew)
    <x-dl::card title="Upload Master Card" description="Master card Excel (.xlsx, sheet MC) is read automatically + proof image extracted · card photo (.jpg/.png) is stored as an image, its fields completed in the form">
        <x-dl::action-form action="{{ route('v2.mcard.upload') }}" enctype="multipart/form-data">
            <x-dl::form.row cols="1">
                <x-dl::field.upload name="files" :multiple="true" :preview="false" label="Master card files (several at once allowed)" accept=".xlsx,.xlsm,.xls,.jpg,.jpeg,.png" :source="Src::of('tm2mst.prod_master_card', 'tm2mst.prod_master_card_file')" />
            </x-dl::form.row>
            <x-dl::action-bar><x-dl::button type="submit" icon="fa-cloud-arrow-up" label="Upload" /></x-dl::action-bar>
        </x-dl::action-form>
    </x-dl::card>

    <x-dl::grid id="mcard-list" :rows="MasterCardCollection::rows()" search="Search name / design no. / item code / customer..." actions="worklist" :active-key="null"
        :open-url="$home.'?id={id}'" :new-url="$home.'?new=1'" source="{{ Src::of('tm2mst.prod_master_card', 'tm2mst.prod_master_card_file') }}"
        :columns="[
            ['key' => 'photo', 'label' => 'Proof', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Master Card', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'part', 'label' => 'Part'],
            ['key' => 'dims', 'label' => 'Dimension (L×W×H)'],
            ['key' => 'type', 'label' => 'Product Type'],
            ['key' => 'knife', 'label' => 'Knife / UP'],
            ['key' => 'svg', 'label' => 'SVG Dieline', 'type' => 'badge', 'tones' => [t('Available') => 'success', t('Not yet') => 'danger']],
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
        :filters="['customer' => 'Customer', 'type' => 'Product Type', 'source' => 'Source', 'status' => 'Status']" />
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

    <x-dl::form id="mcard-form" :title="$c->product_name ? raw($c->product_name) : 'New Master Card'"
        :subtitle="$card ? 'Master Card · '.raw((['XLSX' => 'Excel', 'PHOTO' => t('Photo'), 'MANUAL' => t('Manual')][$c->source_kind] ?? $c->source_kind).' · '.ProdMasterCard::STATUS[$c->status].($c->customer_text ? ' · '.t('card').': '.$c->customer_text : '')) : 'Manual entry (e.g. from a card photo)'"
        panel="top" kind="document" :status="$status" :action="$card ? route('v2.mcard.save', $card->id_master_card) : route('v2.mcard.new')"
        :back="$home" :new-url="$home.'?new=1'" enctype="multipart/form-data" :hide="['confirm', 'cancel', 'draft', 'duplicate', 'email', 'print']"
        :tabs="['main' => 'Card Data', 'bom' => 'Material & Process', 'files' => 'Images']"
        :created-by="$c->created_by" :created-at="$c->created_at?->toDateTimeString()" :updated-by="$c->updated_by" :updated-at="$c->updated_at?->toDateTimeString()">
        @if ($card && ! $locked)
            <x-slot:special>
                <x-dl::button size="sm" variant="success" icon="fa-box" label="Make Master Product" data-dl-open="mcard-product" />
                <x-dl::button size="sm" variant="secondary" icon="fa-file-circle-plus" label="Make Inquiry" data-dl-open="mcard-inquiry" />
            </x-slot:special>
        @endif
        <x-slot:strip>
            <x-dl::strip-kv kind="doc" label="Item Code" :value="$c->card_code ?: '—'" />
            <x-dl::field.display label="Design No." :value="$c->design_no ?: '—'" :source="Src::of('tm2mst.prod_master_card.design_no')" />
            <x-dl::field.display label="Customer" :value="$c->customer?->customer_name ?? ($c->customer_text ? $c->customer_text.' ('.t('not matched to master yet').')' : '—')" :source="Src::of('tm2mst.prod_master_card.customer_id', 'tm2mst.cust_customer.customer_name')" />
            <x-dl::strip-kv kind="grand" label="Status" :value="ProdMasterCard::STATUS[$c->status]" />
        </x-slot:strip>

        <x-slot:main>
            @if ($locked)
                <x-dl::alert tone="success" :message="$c->status === 'PRODUCT' ? 'Already in Master Product — the card is locked (created from this card, or linked because the design no. / item code is the same).' : 'Already an Inquiry — the card is locked.'" />
                <x-dl::action-bar>
                    @if ($c->product_id)<x-dl::button size="sm" variant="secondary" icon="fa-box" label="Open Master Product" :href="route('v2.screen', 'master-product').'?id='.$c->product_id" />@endif
                    @if ($c->inquiry_id)<x-dl::button size="sm" variant="secondary" icon="fa-inbox" label="Open Inquiry" :href="route('v2.screen', 'inquiry-new').'?id='.$c->inquiry_id" />@endif
                </x-dl::action-bar>
            @elseif ($missing)
                <x-dl::alert tone="warn" :message="raw(tf(':n key fields are missing: :list.', ['n' => count($missing), 'list' => $warn]).' '.t('Other columns are filled from the card (can be corrected).'))" />
            @elseif ($card)
                <x-dl::alert tone="info" message="All key fields complete — ready to become a Master Product or Inquiry." />
            @endif
            <x-dl::divider label="Identity" />
            <x-dl::form.row cols="4">
                <x-dl::field.text name="card_code" label="Item Code" :value="old('card_code', $c->card_code)" :source="Src::of('tm2mst.prod_master_card.card_code')" />
                <x-dl::field.text name="design_no" label="Design No." :value="old('design_no', $c->design_no)" :error="isset($missing['design_no']) ? t('empty on the card') : null" :source="Src::of('tm2mst.prod_master_card.design_no')" />
                <x-dl::field.text name="product_name" label="Item Name" required :value="old('product_name', $c->product_name)" :source="Src::of('tm2mst.prod_master_card.product_name')" />
                <x-dl::field.date name="card_date" label="Card Date" :value="old('card_date', $c->card_date?->toDateString())" :source="Src::of('tm2mst.prod_master_card.card_date')" />
            </x-dl::form.row>
            <x-dl::form.row cols="4">
                <x-dl::field.lookup name="customer_id" lookup="customer" label="Customer (master)" placeholder="Search customer..." :value="old('customer_id', $c->customer_id)"
                    :hint="$c->customer_text ? 'Card text: '.raw($c->customer_text) : null" :error="isset($missing['customer_id']) ? t('not chosen yet') : null" :source="Src::of('tm2mst.prod_master_card.customer_id', 'tm2mst.cust_customer')" />
                <x-dl::field.select name="part_label" label="Part" :options="['' => t('Whole card (Double Join = A + B)'), 'A' => t('Part A only'), 'B' => t('Part B only')]" :value="old('part_label', $c->part_label ?? '')" :placeholder="null"
                    hint="Cards per part with the same Design No. are merged into 1 product" :source="Src::of('tm2mst.prod_master_card.part_label')" />
                <x-dl::field.select name="join_type_id" label="Joint" :options="MasterCardCollection::joinOptions()" :value="old('join_type_id', $c->join_type_id)" placeholder="— no joint —"
                    :hint="$c->join_text ? 'Card text: '.raw($c->join_text) : null" :source="Src::of('tm2mst.prod_master_card.join_type_id', 'tm2mst.mat_join_type')" />
                <x-dl::field.number name="pack_qty" label="Qty per Pack (pcs)" :min="1" :value="old('pack_qty', $c->pack_qty)" :source="Src::of('tm2mst.prod_master_card.pack_qty')" />
            </x-dl::form.row>
            <x-dl::divider label="Size & Shape" />
            <x-dl::form.row cols="4">
                <x-dl::field.decimal name="dim_l" label="Length L (mm)" :value="old('dim_l', $mm($c->dim_l))" :source="Src::of('tm2mst.prod_master_card.dim_l')" />
                <x-dl::field.decimal name="dim_w" label="Width W (mm)" :value="old('dim_w', $mm($c->dim_w))" :source="Src::of('tm2mst.prod_master_card.dim_w')" />
                <x-dl::field.decimal name="dim_h" label="Height H (mm)" :value="old('dim_h', $mm($c->dim_h))" :source="Src::of('tm2mst.prod_master_card.dim_h')" />
                <x-dl::field.text name="up_text" label="UP per Sheet" placeholder="e.g. 8 UP / 1/2 UP / 1 SET" :value="old('up_text', $c->up_text)" :source="Src::of('tm2mst.prod_master_card.up_text')" />
            </x-dl::form.row>
            <x-dl::form.row cols="4">
                <x-dl::field.text name="knife_code" label="Die Code" :value="old('knife_code', $c->knife_code)" :source="Src::of('tm2mst.prod_master_card.knife_code')" />
                <x-dl::field.decimal name="knife_w" label="Die width (mm)" :value="old('knife_w', $mm($c->knife_w))" :source="Src::of('tm2mst.prod_master_card.knife_w')" />
                <x-dl::field.decimal name="knife_h" label="Die height (mm)" :value="old('knife_h', $mm($c->knife_h))" :source="Src::of('tm2mst.prod_master_card.knife_h')" />
                <x-dl::field.select name="type_code" label="Product Type (dieline)" :options="MasterCardCollection::typeOptions()" :value="old('type_code', $c->type_code)" placeholder="— choose —"
                    hint="System suggestion; change it and Save = size recalculated from the die" :error="isset($missing['type_code']) ? t('not chosen yet') : null" :source="Src::of('tm2mst.prod_master_card.type_code', 'tm2mst.prod_type')" />
            </x-dl::form.row>
            @if ($check)
                <x-dl::alert :tone="$check['ok'] ? 'success' : 'warn'"
                    :message="raw(tf('Flat size check: formula :w × :h mm per piece', ['w' => $mm($check['flat']['w']), 'h' => $mm($check['flat']['h'])]).($check['piece'] ? ' '.tf('vs die :w × :h mm (:up)', ['w' => $mm($check['piece'][0]), 'h' => $mm($check['piece'][1]), 'up' => $c->up_text]) : '').' — '.($check['ok'] ? t('matches.') : t('differs, adjust the dieline parameters then Save.')))" />
            @endif
            @if ($c->type_code && isset($types[$c->type_code]))
                <x-mp::dieline-v3 :data-artwork="$artwork?->url()" :types="$types" :type="$c->type_code" :dims="$c->dims_json ?? []" name="dieline" :picker="false" height="420px"
                    :source="Src::of('tm2mst.prod_master_card.dims_json', 'tm2mst.prod_master_card.dieline_svg', 'tm2mst.prod_type.dieline_spec')" />
                <x-dl::hint note>{{ tf('The dieline SVG is stored on the card at every Save (:size) and is carried to Master Product / Inquiry.', ['size' => $c->dieline_svg ? \Devlib\Locale::number(strlen($c->dieline_svg)).' '.t('characters') : t('none yet')]) }}</x-dl::hint>
            @endif
            <x-dl::form.row cols="1">
                <x-dl::field.text :multiline="true" name="notes" label="Description (card)" :value="old('notes', $c->notes)" :source="Src::of('tm2mst.prod_master_card.notes')" />
            </x-dl::form.row>
        </x-slot:main>

        <x-slot:bom>
            <x-dl::divider label="Material — check the master category" />
            @forelse ($c->materials_json ?? [] as $i => $m)
                <x-dl::form.row cols="3">
                    <x-dl::field.display :label="raw(tf('Material #:n on the card', ['n' => $i + 1]))" :value="trim($m['name'].' · '.($m['quality'] ?? '').' · '.($m['size_text'] ?? ''), ' ·')" :source="Src::of('tm2mst.prod_master_card.materials_json')" />
                    <x-dl::field.select :name="'materials['.$i.']'" :label="'Master category #'.raw($i + 1)" :options="$cats" :value="$m['category_id'] ?? null" placeholder="— choose a category —"
                        :error="isset($missing['materials.'.$i]) ? t('not matched to master yet') : null" :source="Src::of('tm2mst.mat_category')" />
                    <x-dl::field.display label="Material size" :value="! empty($m['size_p']) ? $mm($m['size_p']).' × '.$mm($m['size_l']).' mm' : '—'" />
                </x-dl::form.row>
            @empty
                <x-dl::empty-state title="No material yet" message="Card from a photo: material is filled when the Excel card is available, or complete it in the Inquiry after Make Inquiry." />
            @endforelse
            <x-dl::divider label="Finishing" />
            <x-dl::table :columns="['finishing_name' => 'Finishing', 'attribute' => 'Attribute', 'text' => 'Card text', 'master' => 'Master']"
                :rows="array_map(fn ($f) => $f + ['master' => $f['finishing_type_id'] ? t('matched') : t('not in master (not carried over)')], $c->finishing_json ?? [])" empty="No finishing." :source="Src::of('tm2mst.prod_master_card.finishing_json', 'tm2mst.ref_finishing_type')" />
            <x-dl::divider label="Work sequence (BOP)" />
            <x-dl::table :columns="['no' => '#', 'text' => 'Card text', 'process_name' => 'Master process', 'scope' => 'Goes to']"
                :rows="collect($c->process_json ?? [])->values()->map(fn ($p, $i) => ['no' => $i + 1, 'text' => $p['text'], 'process_name' => $p['process_name'] ?? t('— no match —'), 'scope' => $p['scope'] === 'BODY' ? t('Body (assembly)') : 'Part'])->all()"
                empty="No work sequence yet." :source="Src::of('tm2mst.prod_master_card.process_json', 'tm2mst.prod_process')" />
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
                    <x-dl::field.upload :name="'artwork['.($p === '' ? '_' : $p).']'" :label="$p === '' ? 'Product artwork' : 'Artwork '.raw('Part '.$p)" accept=".jpg,.jpeg,.png" :disabled="$locked"
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
                <x-dl::action-bar><x-dl::button size="sm" variant="secondary" icon="download" :label="'Original file: '.$src->original_name" :href="$src->url()" /></x-dl::action-bar>
            @endif
        </x-slot:files>
    </x-dl::form>

    @if ($card && ! $locked)
        @foreach (['product' => ['mcard-product', 'Make Master Product', 'fa-box', 'success'], 'inquiry' => ['mcard-inquiry', 'Make Inquiry', 'fa-file-circle-plus', 'primary']] as $target => [$mid, $title, $icon, $variant])
            @php($need = $svc->missing($card, $target))
            <x-dl::modal :id="$mid" :title="$title.' — '.raw($c->product_name)" :icon="$icon" size="lg" :open="session('mcard_dialog') === $target">
                <x-dl::action-form action="{{ route('v2.mcard.convert', $card->id_master_card) }}" id="{{ $mid }}-form">
                    <input type="hidden" name="target" value="{{ $target }}">
                    @if ($errors->has('dialog'))<x-dl::alert tone="danger" :message="raw($errors->first('dialog'))" />@endif
                    <x-dl::alert tone="info" :message="$need ? raw(tf('Complete these :n fields first.', ['n' => count($need)]).' '.t('The rest is already filled from the card.')) : 'The key fields of the card are complete.'" />
                    <x-dl::form.row cols="2">
                        @if (isset($need['design_no']))<x-dl::field.text name="design_no" label="Design No." required placeholder="e.g. DS003xxx" :value="old('design_no')" hint="Empty on the card." />@endif
                        @if (isset($need['customer_id']))<x-dl::field.lookup name="customer_id" lookup="customer" label="Customer (master)" required placeholder="Search customer..." :hint="$c->customer_text ? 'Card text: '.raw($c->customer_text) : null" />@endif
                        @if (isset($need['type_code']))<x-dl::field.select name="type_code" label="Product Type (dieline)" required :options="MasterCardCollection::typeOptions()" placeholder="— choose —" />@endif
                        @foreach ($c->materials_json ?? [] as $i => $m)
                            @if (isset($need['materials.'.$i]))<x-dl::field.select :name="'materials['.$i.']'" :label="raw(tf('Material #:n (master category)', ['n' => $i + 1]))" required :options="$cats" placeholder="— choose —" :hint="'Card text: '.raw($m['name'].' · '.($m['quality'] ?? ''))" />@endif
                        @endforeach
                        @if ($target === 'inquiry')
                            <x-dl::field.number name="qty" label="Order qty (tier 1)" required :min="1" :value="old('qty')" />
                            <x-dl::field.date name="deadline" label="Quotation deadline" required :value="old('deadline', now()->addDays(3)->toDateString())" />
                            <x-dl::field.lookup name="pic_sales" lookup="sales_person" label="Sales PIC" required placeholder="Search sales..." :value="old('pic_sales')" />
                        @endif
                    </x-dl::form.row>
                    @foreach (array_diff_key($need, array_flip(['design_no', 'customer_id', 'type_code'])) as $k => $label)
                        @if (! str_starts_with($k, 'materials.'))<x-dl::alert tone="warn" :message="raw(tf(':field is still empty — fill it in the card form then Save first.', ['field' => $label]))" />@endif
                    @endforeach
                    <x-dl::alert tone="info" :message="$target === 'product'
                        ? raw(tf('Will be created: Master Product “:name” · part :parts · :type · :n process steps · dieline (type + size) is carried over, the SVG stays on the card.', ['name' => $c->product_name, 'parts' => implode(' + ', array_map(fn ($p) => $p === '' ? t('single') : $p, $parts)).(count($parts) > 1 ? ' + '.t('Body assembly') : ''), 'type' => $types[$c->type_code]['label'] ?? '—', 'n' => count($c->process_json ?? [])]).' '.t('It goes through an Inquiry with source “MASTER CARD” (Master Product keeps one rule).'))
                        : ($c->product_id ? 'The product already exists → opened as a Recalculation Inquiry.' : 'Will be created: a NEW Inquiry (DRAFT) with all parts, material, process, artwork & dieline from the card — continue on the Inquiry screen then Confirm.')" />
                </x-dl::action-form>
                <x-slot:footer>
                    <x-dl::button variant="secondary" label="Cancel" data-dl-close-modal />
                    <x-dl::button :variant="$variant" :icon="$icon" :label="$title" type="submit" form="{{ $mid }}-form" />
                </x-slot:footer>
            </x-dl::modal>
        @endforeach
    @endif
@endif
