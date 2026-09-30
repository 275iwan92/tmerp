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

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if (! $pr && ! request('new'))
    <x-dl::grid id="pr-list" :rows="PurchaseRequisition::rows()" search="Cari no. PR, departemen..." actions="worklist" :active-key="null"
        :new-url="$home.'?new=1'" :open-url="$home.'?id={id}'" source="{{ Src::of('tm2pur.pr_requisition', 'tm2pur.pr_line') }}"
        :columns="[
            ['key' => 'no', 'label' => 'No. PR'],
            ['key' => 'date', 'label' => 'Tanggal', 'type' => 'date'],
            ['key' => 'dept', 'label' => 'Departemen'],
            ['key' => 'need', 'label' => 'Tgl Butuh', 'type' => 'date'],
            ['key' => 'lines', 'label' => 'Item', 'type' => 'number'],
            ['key' => 'total', 'label' => 'Est. Total', 'type' => 'money', 'sum' => true],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => PurchaseRequisition::STATUS_TONES],
        ]"
        :stats="[['label' => 'Semua'], ['label' => 'Draft', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'], ['label' => 'Menunggu approval', 'where' => ['status' => 'SUBMITTED'], 'tone' => 'warn'], ['label' => 'Approved', 'where' => ['status' => ['APPROVED', 'PARTIALLY_ORDERED']], 'tone' => 'success']]"
        :filters="['dept' => 'Departemen', 'status' => 'Status']" />
@elseif (! $pr)
    <form method="post" action="{{ route('v2.pr.create') }}">
        @csrf
        <x-dl::card title="+ New Record — Purchase Requisition" description="Pilih departemen peminta. Nomor PR dibuat saat Confirm (diajukan).">
            <x-dl::field.select name="department_id" label="Departemen" required :options="PurchaseRequisition::departments()" :source="Src::of('tm2mst.org_department')" />
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <x-dl::button preset="save" label="Buat PR" />
            </x-dl::action-bar>
        </x-dl::card>
    </form>
@else
    {{-- Form Master-Detail + Approval DevLib (disetujui user 2026-09-30). --}}
    @php
        $pending = PurchaseRequisition::pending($pr);
        $log = PurchaseRequisition::log($pr);
        $held = $pr->status === 'SUBMITTED' && strtoupper((string) ($log[0]['action'] ?? '')) === 'HOLD';
        $approved = collect($log)->where('action', 'Approved')->count();
        $formStatus = PurchaseRequisition::formStatus($pr, $held);
        $draft = $pr->status === 'DRAFT';
        $money = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
        $lines = $pr->lines->map(fn ($l) => [
            'pr_line_id' => $l->pr_line_id, 'i_description' => $l->i_description, 'i_qty' => (float) $l->i_qty, 'i_uom' => $l->i_uom,
            'i_est_price' => (float) $l->i_est_price, 'i_need_date' => $l->i_need_date?->toDateString() ?? '', 'i_notes' => $l->i_notes,
            'code' => $l->c_item_code ?: '—', 'source' => $l->i_item_source === 'MASTER_ITEM' ? 'Master bahan' : 'Item bebas', 'stock' => $l->is_stock ? 'Stok' : 'Non-stok / jasa',
            'ordered' => rtrim(rtrim(number_format((float) $l->c_qty_ordered, 2, '.', ''), '0'), '.') ?: '0',
        ])->values()->all();
    @endphp
    <x-dl::form id="pr-form" :title="'Purchase Requisition · '.($pr->doc_no ?: 'Draft #'.$pr->pr_id)" :subtitle="PurchaseRequisition::departments()[$pr->i_department_id] ?? '—'"
        panel="top" kind="document" :status="$formStatus" :pipeline="true" :approval-levels="max(1, $approved + ($pr->status === 'SUBMITTED' ? 1 : 0))" :approval-at="$approved + 1"
        :can-approve="(bool) $pending" :log="$log"
        :action="route('v2.pr.save', $pr->pr_id)" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hide="['cancel', 'duplicate', 'email', 'print']"
        :tabs="['general' => 'General', 'notes' => 'Notes']"
        :created-by="$pr->created_by" :created-at="$pr->created_at?->toDateTimeString()" :updated-by="$pr->updated_by" :updated-at="$pr->updated_at?->toDateTimeString()">
        <x-slot:special>
            @unless (in_array($pr->status, ['DRAFT', 'SUBMITTED'], true))<x-dl::button size="sm" preset="print" label="Print" :href="route('pur.pr.print', ['pr' => $pr->pr_id])" target="_blank" />@endunless
        </x-slot:special>
        <x-slot:strip>
            <div class="dl-strip__kv dl-strip__doc"><span>No. PR</span><b>{{ $pr->doc_no ?: '(saat Confirm)' }}</b></div>
            <x-dl::field.date name="i_doc_date" label="Tanggal PR" :value="$pr->i_doc_date?->toDateString()" :source="Src::of('tm2pur.pr_requisition.i_doc_date')" />
            <x-dl::field.select name="i_department_id" label="Departemen" required :options="PurchaseRequisition::departments()" :value="$pr->i_department_id" :source="Src::of('tm2mst.org_department')" />
            <div class="dl-strip__kv dl-strip__grand"><span>Est. Total</span><b data-l-val="lines:grand">{{ $money($pr->c_est_total) }}</b></div>
        </x-slot:strip>
        <x-slot:general>
            <x-dl::form.row :cols="4">
                <x-dl::field.date name="i_need_date" label="Tanggal Dibutuhkan" :value="$pr->i_need_date?->toDateString()" :source="Src::of('tm2pur.pr_requisition.i_need_date')" />
                <x-dl::field.display label="Sumber" :value="$pr->i_source ?? 'MANUAL'" :source="Src::of('tm2pur.pr_requisition.i_source')" />
                <x-dl::field.display label="Revisi ke" :value="(string) ($pr->revision_no ?? 0)" :source="Src::of('tm2pur.pr_requisition.revision_no')" />
            </x-dl::form.row>
        </x-slot:general>
        <x-slot:notes>
            <x-dl::field.text name="i_notes" label="Catatan / justifikasi" multiline :value="$pr->i_notes" :source="Src::of('tm2pur.pr_requisition.i_notes')" />
        </x-slot:notes>
        <x-slot:detail>
            <x-dl::lines name="lines" title="Details" key-field="pr_line_id" remove-field="remove" :addable="false" :duplicable="false" :min-rows="0" :rows="$lines" :readonly="! $draft"
                :totals="['of' => 'subtotal']" source="{{ Src::of('tm2pur.pr_line') }}"
                :columns="[
                    ['key' => 'i_description', 'label' => 'Item', 'required' => true, 'width' => '320px'],
                    ['key' => 'i_qty', 'label' => 'Qty', 'type' => 'number', 'required' => true, 'sum' => true, 'width' => '100px'],
                    ['key' => 'i_uom', 'label' => 'Satuan', 'width' => '90px'],
                    ['key' => 'i_est_price', 'label' => 'Est. Harga', 'type' => 'money', 'width' => '130px'],
                    ['key' => 'i_need_date', 'label' => 'Tgl Butuh', 'type' => 'date', 'width' => '150px'],
                    ['key' => 'subtotal', 'label' => 'Est. Subtotal', 'type' => 'money', 'formula' => 'i_qty * i_est_price', 'sum' => true, 'width' => '140px'],
                    ['key' => 'code', 'label' => 'Kode Item', 'type' => 'display', 'more' => true],
                    ['key' => 'source', 'label' => 'Sumber Item', 'type' => 'display', 'more' => true],
                    ['key' => 'stock', 'label' => 'Jenis', 'type' => 'display', 'more' => true],
                    ['key' => 'ordered', 'label' => 'Qty sudah di-PO', 'type' => 'display', 'more' => true],
                    ['key' => 'i_notes', 'label' => 'Catatan Baris', 'more' => true],
                ]">
                @if ($draft)
                    <x-slot:tools>
                        <x-dl::field.lookup name="material_id" lookup="material" label="+ Item dari Master Bahan" placeholder="Ketik min. 2 huruf kode / nama item..." :source="Src::of('tm2mst.mat_raw')" />
                        <x-dl::button size="sm" variant="secondary" label="+ Item Master" type="submit" name="action" value="add_material" formnovalidate />
                        <x-dl::field.text name="free_description" label="+ Item bebas (uraian)" placeholder="Jasa / barang non-master..." />
                        <x-dl::field.text name="free_uom" label="Satuan" :case-toggle="false" placeholder="PCS" />
                        <x-dl::button size="sm" variant="secondary" label="+ Item Bebas" type="submit" name="action" value="add_free" formnovalidate />
                    </x-slot:tools>
                @endif
            </x-dl::lines>
        </x-slot:detail>
    </x-dl::form>
@endif
