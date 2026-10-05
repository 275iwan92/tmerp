{{-- Purchase Requisition v2. Flow & aturan = tmerp pages/pur/pr (index + entry) lewat App\Support\V2\Data\PurchaseRequisition.
     Alur: GRID PR -> + New Record = pilih departemen -> DRAFT (tambah item master / bebas, qty, harga estimasi, tgl butuh) ->
     Confirm (= Submit v1: nomor PR lahir, masuk antrian approval flow engine) -> Approval (Approve / Reject / Hold oleh
     approver tahap berjalan) -> Approved -> Closed otomatis saat seluruh baris sudah menjadi PO (FULLY_ORDERED). --}}
@php
    use App\Models\Pur\PrRequisition;
    use App\Support\V2\Data\PurchaseRequisition;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'purchase-requisition');
    $pr = request('id') ? PrRequisition::with(['lines' => fn ($q) => $q->orderBy('line_no')])->find((int) request('id')) : null;
    abort_if(request('id') && ! $pr, 404);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $pr && ! request('new'))
    {{-- Daftar = objek Grid Transactional App\Support\V2\Grid\Pur\PurchaseRequisitionGrid (DbGrid), tampil lewat view generik _dbgrid. --}}
    @include('v2.screens._dbgrid', ['screen' => (new \App\Support\V2\Grid\Pur\PurchaseRequisitionGrid)->screen()])
@elseif (! $pr)
    <x-dl::action-form action="{{ route('v2.pr.create') }}">
        <x-dl::card title="+ New Record — Purchase Requisition" description="Choose the requesting department. The PR number is created on Confirm (submitted).">
            <x-dl::field.select name="department_id" label="Department" required :options="PurchaseRequisition::departments()" :source="Src::of('tm2mst.org_department')" />
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Create PR" />
            </x-dl::action-bar>
        </x-dl::card>
    </x-dl::action-form>
@else
    {{-- Form Master-Detail + Approval DevLib (disetujui user 2026-09-30). --}}
    @php
        $pending = PurchaseRequisition::pending($pr);
        $log = PurchaseRequisition::log($pr);
        $held = $pr->status === 'SUBMITTED' && strtoupper((string) ($log[0]['action'] ?? '')) === 'HOLD';
        $approved = collect($log)->where('action', 'Approved')->count();
        // Jumlah level ikut jalur flow engine untuk nilai dokumen ini (mis. PO > 1 jt = 3 level) -- sama dgn Approval Center.
        $appr = PurchaseRequisition::approvalState('PR', $pr->pr_id, (float) $pr->c_est_total);
        $formStatus = PurchaseRequisition::formStatus($pr, $held);
        $draft = $pr->status === 'DRAFT';
        $money = fn ($v) => 'IDR '.\Devlib\Locale::number((float) $v, 0);
        $accounts = PurchaseRequisition::accounts();
        $lines = $pr->lines->map(fn ($l) => [
            'pr_line_id' => $l->pr_line_id, 'i_description' => $l->i_description, 'i_qty' => (float) $l->i_qty, 'i_uom' => $l->i_uom,
            'i_est_price' => (float) $l->i_est_price, 'i_need_date' => $l->i_need_date?->toDateString() ?? '', 'i_notes' => $l->i_notes,
            'i_coa_account_id' => $l->i_coa_account_id, 'code' => $l->c_item_code ?: '—', 'source' => $l->i_item_source === 'MASTER_ITEM' ? t('Material master') : t('Free item'), 'stock' => $l->is_stock ? t('Stock') : t('Non-stock / service'),
            'ordered' => \Devlib\Locale::decimal($l->c_qty_ordered, 2),
        ])->values()->all();
    @endphp
    <x-dl::form id="pr-form" :title="'Purchase Requisition · '.raw($pr->doc_no ?: 'Draft #'.$pr->pr_id)" :subtitle="raw(PurchaseRequisition::departments()[$pr->i_department_id] ?? '—')"
        panel="top" kind="document" :status="$formStatus" :pipeline="true" :approval-levels="max(1, $appr['levels'])" :approval-at="$appr['at'] ?: $approved + 1"
        :can-approve="(bool) $pending" :log="$log"
        :action="route('v2.pr.save', $pr->pr_id)" action-field="action" :back="$home" :new-url="$home.'?new=1'" :print-url="\App\Support\V2\Print\PrintDocs::url('purchase-requisition', $pr->pr_id)" :hide="['cancel', 'duplicate']"
        :tabs="['general' => 'General', 'notes' => 'Notes']"
        :created-by="$pr->created_by" :created-at="$pr->created_at?->toDateTimeString()" :updated-by="$pr->updated_by" :updated-at="$pr->updated_at?->toDateTimeString()">
        <x-slot:special>
            {{-- Draft boleh dicetak (user 2026-10-03): lembarnya memakai tanda air DRAFT dari x-dl::print-doc. --}}
        </x-slot:special>
        <x-slot:strip>
            <div class="dl-strip__kv dl-strip__doc"><span>@t('PR No.')</span><b>{{ $pr->doc_no ?: t('(on Confirm)') }}</b></div>
            <x-dl::field.date name="i_doc_date" label="PR Date" :value="$pr->i_doc_date?->toDateString()" :source="Src::of('tm2pur.pr_requisition.i_doc_date')" />
            <x-dl::field.select name="i_department_id" label="Department" required :options="PurchaseRequisition::departments()" :value="$pr->i_department_id" :source="Src::of('tm2mst.org_department')" />
            <div class="dl-strip__kv dl-strip__grand"><span>@t('Est. Total')</span><b data-l-val="lines:grand">{{ $money($pr->c_est_total) }}</b></div>
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="4">
                <x-dl::field.date name="i_need_date" label="Required Date" :value="$pr->i_need_date?->toDateString()" :source="Src::of('tm2pur.pr_requisition.i_need_date')" />
                <x-dl::field.display label="Source" :value="$pr->i_source ?? 'MANUAL'" :source="Src::of('tm2pur.pr_requisition.i_source')" />
                <x-dl::field.display label="Revision no." :value="(string) ($pr->revision_no ?? 0)" :source="Src::of('tm2pur.pr_requisition.revision_no')" />
            </x-dl::form.row>
        </x-slot:general>
        <x-slot:notes>
            <x-dl::field.text name="i_notes" label="Notes / justification" multiline :value="$pr->i_notes" :source="Src::of('tm2pur.pr_requisition.i_notes')" />
        </x-slot:notes>
        <x-slot:detail>
            <x-dl::lines name="lines" title="Details" key-field="pr_line_id" remove-field="remove" :addable="false" :duplicable="false" :min-rows="0" :rows="$lines" :readonly="! $draft"
                :totals="['of' => 'subtotal']" source="{{ Src::of('tm2pur.pr_line') }}"
                :columns="[
                    ['key' => 'i_description', 'label' => 'Item', 'required' => true, 'width' => '320px'],
                    ['key' => 'i_qty', 'label' => 'Qty', 'type' => 'number', 'required' => true, 'sum' => true, 'width' => '100px'],
                    ['key' => 'i_uom', 'label' => t('Unit'), 'width' => '90px'],
                    ['key' => 'i_est_price', 'label' => t('Est. Price'), 'type' => 'money', 'width' => '130px'],
                    ['key' => 'i_need_date', 'label' => t('Need Date'), 'type' => 'date', 'width' => '150px'],
                    ['key' => 'subtotal', 'label' => 'Est. Subtotal', 'type' => 'money', 'formula' => 'i_qty * i_est_price', 'sum' => true, 'width' => '140px'],
                    ['key' => 'i_coa_account_id', 'label' => t('Account'), 'type' => 'select', 'options' => $accounts, 'required' => true, 'more' => true],
                    ['key' => 'code', 'label' => t('Item Code'), 'type' => 'display', 'more' => true],
                    ['key' => 'source', 'label' => 'Item Source', 'type' => 'display', 'more' => true],
                    ['key' => 'stock', 'label' => t('Type'), 'type' => 'display', 'more' => true],
                    ['key' => 'ordered', 'label' => t('Qty already on PO'), 'type' => 'display', 'more' => true],
                    ['key' => 'i_notes', 'label' => t('Line Notes'), 'more' => true],
                ]">
                @if ($draft)
                    <x-slot:tools>
                        <x-dl::field.lookup name="material_id" lookup="material" label="+ Item from Material Master" placeholder="Type at least 2 letters of the item code / name..." :source="Src::of('tm2mst.mat_raw')" />
                        <x-dl::button size="sm" variant="secondary" label="+ Item Master" type="submit" name="action" value="add_material" formnovalidate />
                        <x-dl::field.text name="free_description" label="+ Free item (description)" placeholder="Service / non-master goods..." />
                        <x-dl::field.text name="free_uom" label="Unit" :case-toggle="false" placeholder="PCS" />
                        <x-dl::field.select name="free_account_id" label="Account" placeholder="— choose account —" :options="$accounts" :source="Src::of('tm2acc.fin_account')" />
                        <x-dl::button size="sm" variant="secondary" label="+ Free Item" type="submit" name="action" value="add_free" formnovalidate />
                    </x-slot:tools>
                @endif
            </x-dl::lines>
        </x-slot:detail>
    </x-dl::form>
@endif
