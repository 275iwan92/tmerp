{{--
 REMARK
 Tujuan       : First Article: catat hasil Pass atau Fail untuk WO dan batch bahan di mesin; Pass melepas batch, Fail menolak batch dan menahan WO yang memakainya.
 Sumber hulu  : App\Services\Inventory\QcDecisionService::firstArticle dan QcAuthority; App\Support\V2\Data\BatchQuality; tabel prd_wo.
 Input        : Query new, wo, batch; session v2_flash dan error validasi.
 Output       : Daftar FirstArticleListGrid lewat _dbgrid, atau form baru x-dl::* yang memposting ke rute v2.iq.first-article; hanya QA atau manajer operasi.
 Dipakai oleh : Layar v2 slug qc-first-article lewat rute v2.screen (view v2.screen memuat berkas ini)
 Status       : Aktif
--}}
@php
    use App\Services\Inventory\QcAuthority;
    use App\Support\V2\Data\BatchQuality;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'qc-first-article');
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! request('new'))
    @include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Inv\FirstArticleListGrid]])
@elseif (! QcAuthority::canDecide(auth()->user()))
    <x-dl::alert tone="info" message="Only Quality Assurance or an operations manager can record a first article." />
    <x-dl::action-bar><x-dl::button variant="secondary" icon="back" label="Back" :href="$home" /></x-dl::action-bar>
@else
    <x-dl::action-form action="{{ route('v2.iq.first-article') }}">
        <x-dl::card title="+ New Record — First Article" description="The first good piece at the machine. Pass releases the batch of material used; Fail rejects it, holds the work orders that used it and opens a supplier claim.">
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="wo_id" label="Work Order" required :options="BatchQuality::workOrders()" :value="request('wo')" :source="Src::of('tm2prd.prd_wo')" />
                <x-dl::field.select name="batch_id" label="Material Batch" required :options="BatchQuality::batchOptions()" :value="request('batch')" :source="Src::of('tm2inv.inv_batch')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="2">
                <x-dl::field.select name="result" label="Result" required :options="['PASS' => 'Pass', 'FAIL' => 'Fail']" :source="Src::of('tm2inv.inv_qc_first_article.result')" />
                <x-dl::field.text name="notes" label="Notes" :case-toggle="false" hint="Required when the result is Fail." :source="Src::of('tm2inv.inv_qc_first_article.notes')" />
            </x-dl::form.row>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Save" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@endif
