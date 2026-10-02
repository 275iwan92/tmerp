{{-- Inquiry Revisi (REVISE) dari Master Product (docs/flow_product_master.md §4, mockup disetujui user 2026-09-29).
     Alur layar: GRID dokumen REVISE -> + New Record = pilih product (grid) -> form -> Simpan (DRAFT) -> Confirm (terkunci, lanjut
     ProDev -> ProCost; product turunan baru lahir saat dikirim ke ProDev). Titik berat: PERBEDAAN sebelum -> sesudah per field
     (iqp_revise_change) yang ikut tampil di ProDev & ProCost. Hanya blok scope terpilih yang bisa diubah. --}}
@php
    use App\Models\Master\MatJoinType;
    use App\Models\Sdi\IqpInquiry;
    use App\Services\Sales\InquiryDerivedWriter;
    use App\Support\V2\Data\ProductMaster;
    use App\Support\V2\Data\Sales;
    use App\Support\V2\Src;

    $inq = request('id') ? IqpInquiry::where('inquiry_type', 'REVISE')->find((int) request('id')) : null;
    abort_if(request('id') && ! $inq, 404);
    // Produk induk: dokumen tersimpan -> product inquiry asalnya; wizard worklist Inquiry (?source_id) -> product inquiry itu.
    $productId = $inq ? $inq->parentInquiry?->product_id : ((int) request('product') ?: (request('source_id') ? IqpInquiry::whereKey((int) request('source_id'))->value('product_id') : null));
    $noProduct = request('source_id') && ! $productId;
    $doc = $productId ? ProductMaster::doc($productId) : null;
    abort_if($productId && ! $doc, 404);
    $source = $doc ? InquiryDerivedWriter::sourceOf(\App\Models\Master\ProdProduct::find($doc['id'])) : null;
    $parts = $inq ? ProductMaster::inquiryParts($inq) : ($source ? ProductMaster::inquiryParts($source) : []);
    $locked = (bool) $inq?->stat_confirm_sales;
    $scope = old('revise_scope', $inq?->reviseScopeArray() ?? []);
    $on = fn ($s) => in_array($s, $scope, true);
    $matOptions = $doc ? ProductMaster::materialOptions() : [];
    $finOptions = array_column(Sales::masters()['finishing_types'], 'finishing_name', 'id_finishing_type');
    $joinOptions = MatJoinType::where('activate', true)->orderBy('sort_order')->pluck('label', 'id_join_type')->all();
    $colors = [];
    foreach (range(1, 6) as $f) { foreach (range(0, 6) as $b) { $colors["$f/$b"] = "$f/$b"; } }
    $changes = $inq ? $inq->reviseChanges()->with('part:id_part,part_name')->get() : collect();
    $home = route('v2.screen', 'inquiry-revise');
    $dim = fn ($p, $k) => isset($p['dims'][$k]) ? rtrim(rtrim((string) $p['dims'][$k], '0'), '.') : '';
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if ($noProduct)<x-dl::alert tone="warn" message="The selected inquiry is not a Master Product yet (Quotation not WON). Choose a product from the list." />@endif

@if (! $doc && ! request('pick') && ! $noProduct)
    {{-- Inquiry > Revise: meja Sales untuk jenis REVISE (satu tabel iqp_inquiry, saringan jenis + tanda; rulebook A.20, C.28). --}}
    @php $g = (new \App\Support\V2\Grid\Sales\InquiryReviseGrid)->props(); @endphp
    <x-dl::grid id="revise-docs" :rows="$g['rows']" :columns="$g['columns']" :stats="$g['stats']" :filters="$g['filters']" :detail="$g['detail']" :search="$g['search']"
        actions="worklist" :active-key="null" :new-url="$g['newUrl']" :open-url="$g['openUrl']" :source="$g['source']" />
@elseif (! $doc)
    <x-dl::card title="+ New Record — choose the product to revise" description="Only products from Master Product (born from a WON Quotation). Double-click a row.">
        <x-dl::grid id="revise-pick" :rows="ProductMaster::rows()" search="Search product, design no., customer..." :new-record="null" actions="worklist" :active-key="null"
            :open-url="$home.'?product={id}'" source="{{ Src::of('tm2mst.prod_product') }}"
            :columns="[
                ['key' => 'photo', 'label' => 'Artwork', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
                ['key' => 'name', 'label' => 'Product', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
                ['key' => 'customer', 'label' => 'Customer'],
                ['key' => 'revision', 'label' => 'Cabang'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['ACTIVE' => 'success', 'DRAFT' => 'neutral']],
            ]" />
        <x-dl::action-bar><x-dl::button variant="secondary" icon="back" label="Back" :href="$home" /></x-dl::action-bar>
    </x-dl::card>
@else
    <x-dl::action-form action="{{ route('v2.derived.save', 'revise') }}" enctype="multipart/form-data" id="revise-form" data-dl-guard data-rv-form data-locked="{{ $locked ? 1 : 0 }}">
        <input type="hidden" name="product_id" value="{{ $doc['id'] }}">
        @if ($inq)<input type="hidden" name="id" value="{{ $inq->id_inquiry }}">@endif

        <x-dl::card>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <span class="mp-chips">
                    <x-dl::badge :label="$inq ? $inq->inquiry_no : 'Belum tersimpan'" />
                    <x-dl::badge :label="$inq?->sub_state ?? 'DRAFT'" :tone="Sales::STATE_TONES[$inq?->sub_state ?? 'DRAFT'] ?? 'neutral'" />
                </span>
                @if ($inq)<x-dl::button preset="print" :href="\App\Support\V2\Print\PrintDocs::url('inquiry', $inq->id_inquiry, ['from' => 'inquiry-revise'])" target="_blank" />@endif
            </x-dl::action-bar>
            <h2 class="mp-bigname">{{ $doc['name'] }} <small class="mp-hint">→ Rev.{{ $doc['revision_no'] + 1 }}</small></h2>
            <p class="mp-hint">Induk: {{ $doc['code'] }} · No. Desain {{ $doc['design_no'] ?? '—' }} · {{ $doc['customer'] }} · inquiry asal {{ $source?->inquiry_no }}. Product induk tetap utuh.</p>
            <x-dl::form.row cols="3">
                <x-dl::field.text name="revise_reason" label="Revision reason" required :value="old('revise_reason', $inq?->revise_reason)" placeholder="e.g. change the long-side artwork — customer request" :disabled="$locked" :source="Src::of('tm2sdi.iqp_inquiry.revise_reason')" />
                <x-dl::field.text name="design_number" label="New Design No." :value="old('design_number', $inq?->design_number ?? $doc['design_no'])" :disabled="$locked" :source="Src::of('tm2sdi.iqp_inquiry.design_number')" />
            </x-dl::form.row>
            <div data-rv-scope><x-dl::field.checkboxes name="revise_scope" label="Revision scope (more than one allowed) — only the selected blocks can be changed" :options="InquiryDerivedWriter::SCOPES" :value="$scope" :disabled="$locked" :source="Src::of('tm2sdi.iqp_inquiry.revise_scope')" /></div>
        </x-dl::card>

        <x-dl::card title="Revision differences (before → after)" description="Recorded automatically on Save; also shown on the ProDev and ProCost screens so they know what must be redone.">
            <x-dl::table :columns="['part' => 'Part', 'field' => 'Yang berubah', 'old' => 'Sebelum (induk)', 'new' => 'Sesudah', 'impact' => 'Diulang di']"
                :rows="$changes->map(fn ($c) => ['part' => $c->part?->part_name ?? 'Dokumen', 'field' => $c->field_label, 'old' => $c->old_value ?? '—', 'new' => $c->new_value ?? '—', 'impact' => $c->impact])->all()"
                empty="Belum ada perubahan tersimpan — pilih scope, ubah isian, lalu Simpan." source="{{ Src::of('tm2sdi.iqp_revise_change') }}" />
        </x-dl::card>

        @foreach ($parts as $p)
            @php
                $n = 'parts['.$p['seq'].']';
                $master = collect($doc['components'])->firstWhere('name', $p['name']) ?? ['id' => $p['id'], 'name' => $p['name'], 'artwork' => $p['artwork'], 'artwork_inner' => $p['artwork_inner'], 'has_spec' => false, 'type_code' => null, 'dims' => $p['dims']];
            @endphp
            <x-dl::card :title="raw($p['name'])" :description="$p['kind'] === 'ASSEMBLY' ? 'Rakitan' : 'Komponen'">
                @if ($p['kind'] === 'ASSEMBLY')
                    <fieldset class="mp-rv" data-rv-block="JOIN" @disabled($locked || ! $on('JOIN'))>
                        <legend>Struktur / Sambungan</legend>
                        <x-dl::field.select :name="$n.'[join_type_id]'" label="Join type" :options="$joinOptions" :value="$p['join_type_id']" :source="Src::of('tm2sdi.iqp_part.join_type_id', 'tm2mst.mat_join_type')" />
                    </fieldset>
                @else
                    <div class="mp-grid2">
                        <div>
                            <x-mp::sales.part-visual :part="$master + ['artwork' => $p['artwork'], 'artwork_inner' => $p['artwork_inner']]" :types="Sales::dielineTypes()" height="300px" />
                            <fieldset class="mp-rv" data-rv-block="ARTWORK" @disabled($locked || ! $on('ARTWORK'))>
                                <legend>Artwork</legend>
                                <x-dl::form.row cols="2">
                                    <x-dl::field.upload :name="$n.'[artwork]'" label="New artwork (outside)" accept=".jpg,.jpeg,.png" :source="Src::of('tm2sdi.iqp_part.artwork_path')" />
                                    <x-dl::field.upload :name="$n.'[artwork_inner]'" label="New artwork (inside)" accept=".jpg,.jpeg,.png" :source="Src::of('tm2sdi.iqp_part.artwork_inner_path')" />
                                </x-dl::form.row>
                            </fieldset>
                        </div>
                        <div>
                            <fieldset class="mp-rv" data-rv-block="DIMENSION" @disabled($locked || ! $on('DIMENSION'))>
                                <legend>Dimensi (mm)</legend>
                                <x-dl::form.row cols="3">
                                    <x-dl::field.decimal :name="$n.'[dims][product_length]'" label="P" :value="$dim($p, 'product_length')" :source="Src::of('tm2sdi.iqp_part.dieline_spec.dims')" />
                                    <x-dl::field.decimal :name="$n.'[dims][product_width]'" label="L" :value="$dim($p, 'product_width')" />
                                    <x-dl::field.decimal :name="$n.'[dims][product_height]'" label="T" :value="$dim($p, 'product_height')" />
                                </x-dl::form.row>
                            </fieldset>
                            <fieldset class="mp-rv" data-rv-block="MATERIAL" @disabled($locked || ! $on('MATERIAL'))>
                                <legend>Bahan</legend>
                                @foreach ($p['materials'] as $m)
                                    <x-dl::field.select :name="$n.'[materials]['.$m['seq'].']'" :label="'Bahan #'.$m['seq']" :options="$matOptions" :value="$m['category_id']" :hint="$m['category_id'] ? null : 'Data lama: '.raw($m['label'])" :source="Src::of('tm2sdi.iqp_part_material.category_id')" />
                                @endforeach
                            </fieldset>
                            <fieldset class="mp-rv" data-rv-block="COLOR" @disabled($locked || ! $on('COLOR'))>
                                <legend>Warna</legend>
                                <x-dl::form.row cols="2">
                                    <x-dl::field.select :name="$n.'[cfg_color]'" label="Colour notation" :options="$colors" :value="$p['cfg_color']" :placeholder="null" :source="Src::of('tm2sdi.iqp_part.cfg_color')" />
                                    <x-dl::field.text :name="$n.'[cfg_ink]'" label="Tinta" :value="$p['cfg_ink']" placeholder="mis. CMYK + spot Pantone 348C" :source="Src::of('tm2sdi.iqp_part.cfg_ink')" />
                                </x-dl::form.row>
                            </fieldset>
                            <fieldset class="mp-rv" data-rv-block="PROCESS" @disabled($locked || ! $on('PROCESS'))>
                                <legend>Proses / Finishing</legend>
                                <input type="hidden" name="{{ $n }}[finishings][]" value="">
                                <x-dl::field.checkboxes :name="$n.'[finishings]'" label="Finishing" :options="$finOptions" :value="$p['finishings']" :source="Src::of('tm2sdi.iqp_part_finishing')" />
                            </fieldset>
                        </div>
                    </div>
                @endif
            </x-dl::card>
        @endforeach

        <x-dl::action-bar>
            @if ($inq)<x-dl::button variant="secondary" icon="copy" label="Duplicate" type="button" data-dl-open="dup-modal" />@endif
            @if (! $locked)
                @if ($inq)<x-dl::button preset="delete" type="submit" :formaction="route('v2.derived.delete', $inq->id_inquiry)" onclick="return confirm('Hapus draft {{ $inq->inquiry_no }}?')" formnovalidate />@endif
                <x-dl::button preset="save" name="action" value="save" />
                <x-dl::button preset="confirm" type="submit" name="action" value="confirm" onclick="return confirm('Confirm revisi ini? Form akan terkunci dan dikirim ke Pro-Dev, lalu Pro-Cost.')" />
            @elseif (! $inq->stat_cancel)
                <x-dl::button preset="cancel" data-dl-open="revise-cancel" />
            @endif
        </x-dl::action-bar>
    </x-dl::action-form>

    @if ($inq)
        @include('v2.screens._inquiry-duplicate', ['source' => $inq->id_inquiry, 'label' => $inq->inquiry_no.' — '.$doc['name'].' · '.$doc['customer']])
    @endif

    @if ($locked && ! $inq->stat_cancel)
        <x-dl::alert tone="info" message="Confirmed by Sales: this revision is now in Pro-Dev › Revise. After Pro-Dev confirms every part it moves to Pro-Cost › Revise, then back here ready for Quotation." />
        <x-dl::modal id="revise-cancel" title="Cancel revision" icon="cancel">
            <x-dl::action-form action="{{ route('v2.derived.cancel', $inq->id_inquiry) }}">
                <x-dl::field.text name="cancel_reason" label="Cancellation reason" required />
                <x-dl::action-bar><x-dl::button preset="save" label="Cancel document" variant="danger" /></x-dl::action-bar>
            </x-dl::action-form>
        </x-dl::modal>
    @endif
@endif
