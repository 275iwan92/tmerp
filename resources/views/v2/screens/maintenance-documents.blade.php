{{--
 REMARK
 Tujuan       : Master SOP dan Work Instruction maintenance: dokumen bernomor, revisi, status Draft atau Approved; dokumen disetujui terkunci, Back to Draft membuat revisi baru; satu dokumen dipakai banyak aset.
 Sumber hulu  : Model MntDocument (tm2mnt), Maintenance::coverageRows, MaintenanceAccess::canManage; daftar = grid Maint\DocumentListGrid.
 Input        : Query id (dokumen), new (form baru); sesi v2_flash dan errors.
 Output       : x-dl::tabs (Coverage dan All list), x-dl::coverage, grid atau form; posting ke route v2.mnt.document.save.
 Dipakai oleh : Route v2.screen slug maintenance-documents (App\Support\V2\Screens, menu sys_app_menu target v2:maintenance-documents) di menu Maintenance > Master Data
 Status       : Aktif (Fase 1 Master Data)
--}}
@php
    use App\Models\Mnt\MntDocument;
    use App\Models\Mnt\MntLog;
    use App\Services\Maintenance\MaintenanceAccess;
    use App\Support\V2\Data\Maintenance;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'maintenance-documents');
    $document = request('id') ? MntDocument::with('lines')->find((int) request('id')) : null;
    abort_if(request('id') && ! $document, 404);
    $head = MaintenanceAccess::canManageDocuments(auth()->user());
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $document && ! request('new'))
    {{-- Coverage = objek x-dl::coverage (satu baris per set mesin, kolom SOP + 4 WI). All list = objek Grid Transactional
         App\Support\V2\Grid\Maint\DocumentListGrid (DocListGrid), tampil lewat view generik _dbgrid. --}}
    <x-dl::tabs :tabs="['coverage' => 'Coverage', 'list' => 'All list']">
        <x-slot:coverage>
            <x-dl::coverage head="Set" sub-head="Assets" search="Search set, asset…" :columns="Maintenance::COVERAGE_COLUMNS" :filters="['pending' => 'Not fully approved']" :rows="Maintenance::coverageRows()" />
        </x-slot:coverage>
        <x-slot:list>
            @include('v2.screens._dbgrid', ['screen' => ['grid' => new \App\Support\V2\Grid\Maint\DocumentListGrid]])
        </x-slot:list>
    </x-dl::tabs>
@else
    @php
        $new = ! $document;
        $m = $document ?? new MntDocument(['kind' => 'WI', 'revision' => '00', 'status' => 'DRAFT', 'activate' => true]);
        $locked = ! $new && $m->isApproved();
        $editable = $head && ! $locked;
        $used = $document ? \App\Models\Mnt\MntAsset::whereIn('id_asset', \Illuminate\Support\Facades\DB::connection('tm2mnt')->table('mnt_asset_document')->where('document_id', $document->id_document)->pluck('asset_id'))->orderBy('asset_code')->get(['asset_code', 'asset_name']) : collect();
    @endphp
    <x-dl::form id="mnt-document-form" :title="'SOP and Work Instructions · '.($m->doc_no ? raw($m->doc_no) : 'New')" :subtitle="$new ? null : raw($m->title)"
        panel="top" kind="master" :status="$new ? 'new' : ($m->activate ? 'active' : 'inactive')" :stage="$new ? null : t(Maintenance::STATUS_LABELS[$m->status] ?? $m->status)" :can-input="$editable" :log="$document ? MntLog::forDoc('DOCUMENT', $document->id_document) : []"
        :action="route('v2.mnt.document.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'" :hidden="$document ? ['id' => $document->id_document] : []"
        :tabs="['general' => 'Document']"
        :created-by="$m->created_by" :created-at="$m->created_at?->toDateTimeString()" :updated-by="$m->updated_by" :updated-at="$m->updated_at?->toDateTimeString()">
        <x-slot:strip>
            <x-dl::strip-kv kind="doc" label="Document" :value="$m->doc_no ?: t('(new)')" />
            <x-dl::strip-kv label="Revision" :value="$m->revision" />
            <x-dl::strip-kv label="Status" :value="t(Maintenance::STATUS_LABELS[$m->status] ?? $m->status)" />
            <x-dl::strip-kv kind="grand" label="Used by" :value="$used->count().' '.t('assets')" />
        </x-slot:strip>
        @if ($document && $head)
            <x-slot:special>
                @if ($locked)
                    <x-dl::button size="sm" variant="secondary" icon="fa-rotate-left" label="Back to Draft" type="submit" name="action" value="to_draft" formnovalidate />
                @else
                    <x-dl::button size="sm" variant="success" icon="fa-check" label="Approve" type="submit" name="action" value="approve" formnovalidate />
                @endif
            </x-slot:special>
        @endif
        <x-slot:general>
            <x-dl::form.row :cols="4">
                <x-dl::field.text name="doc_no" label="Document No." required :case-toggle="false" :readonly="! $editable" :value="$m->doc_no" :source="Src::of('tm2mnt.mnt_document.doc_no')" />
                <x-dl::field.select name="kind" label="Kind" required :readonly="! $editable" :options="array_map('t', MntDocument::KINDS)" :value="$m->kind" :source="Src::of('tm2mnt.mnt_document.kind')" />
                <x-dl::field.text name="doc_set" label="Set" :readonly="! $editable" :value="$m->doc_set" placeholder="OFF" :source="Src::of('tm2mnt.mnt_document.doc_set')" />
                <x-dl::field.text name="file_location" label="File Location (manual, drawing)" :readonly="! $editable" :value="$m->file_location" :source="Src::of('tm2mnt.mnt_document.file_location')" />
            </x-dl::form.row>
            <x-dl::field.text name="title" label="Title" required :readonly="! $editable" :value="$m->title" :source="Src::of('tm2mnt.mnt_document.title')" />
            @if ($locked)
                <x-dl::hint>@t('This document is approved and locked. Use Back to Draft to change it; that starts a new revision.')</x-dl::hint>
            @endif
        </x-slot:general>
        @if ($document)
            <x-slot:detail>
                <x-dl::tabs :tabs="['read' => 'Read', 'content' => 'Content ('.raw($document->lines->count()).')', 'assets' => 'Assets ('.raw($used->count()).')']">
                    <x-slot:read>
                        <x-dl::hint>@t('The text below is shown in your language. Edit the English base text in the Content tab; translations are kept in Setup, Translation Dictionary.')</x-dl::hint>
                        <x-dl::table :columns="['seq' => '#', 'section' => 'Section', 'text' => 'Text']" :rows="array_map(fn ($r) => ['seq' => $r['seq'], 'section' => raw($r['section']), 'text' => raw($r['text'])], Maintenance::readRows($document))" />
                    </x-slot:read>
                    <x-slot:content>
                        <x-dl::lines name="lines" title="Content" key-field="id_document_line" remove-field="remove" :addable="false" :duplicable="false" :min-rows="0"
                            :rows="$document->lines->map(fn ($l) => ['id_document_line' => $l->id_document_line, 'seq_no' => $l->seq_no, 'section' => $l->section, 'line_text' => $l->line_text])->all()"
                            :readonly="! $editable" source="{{ Src::of('tm2mnt.mnt_document_line') }}"
                            :columns="[
                                ['key' => 'section', 'label' => 'Section', 'width' => '150px'],
                                ['key' => 'line_text', 'label' => 'Text', 'required' => true, 'width' => '620px'],
                            ]">
                            @if ($editable)
                                <x-slot:tools>
                                    <x-dl::field.text name="new_section" label="Section" value="Steps" />
                                    <x-dl::field.text name="new_line" label="+ Line" placeholder="Step or sentence..." />
                                    <x-dl::button size="sm" variant="secondary" label="+ Line" type="submit" name="action" value="add_line" formnovalidate />
                                </x-slot:tools>
                            @endif
                        </x-dl::lines>
                    </x-slot:content>
                    <x-slot:assets>
                        <x-dl::table :columns="['asset_code' => 'Asset', 'asset_name' => 'Name']" :rows="$used->map(fn ($u) => ['asset_code' => raw($u->asset_code), 'asset_name' => raw($u->asset_name)])->all()" />
                    </x-slot:assets>
                </x-dl::tabs>
            </x-slot:detail>
        @endif
    </x-dl::form>
@endif
