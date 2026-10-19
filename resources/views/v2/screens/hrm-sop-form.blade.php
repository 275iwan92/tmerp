{{--
 REMARK
 Tujuan       : Form SOP / Work Instruction perusahaan (menggantikan halaman v1 pages::hrm.sop-documents): HR Officer menulis dokumen (nomor, jenis SOP atau WI, judul, revisi, tanggal berlaku, lingkup departemen, tujuan, baris isi), Approve (dokumen terkunci), Back to Draft (revisi naik), Activate / Deactivate; yang lain hanya membaca dokumen Approved yang aktif. Catatan perubahan di tab History.
 Sumber hulu  : tm2mnt.mnt_document + mnt_document_line + MntLog, tm2mst.org_department; aturan di App\Services\Maintenance\MntDocumentService (dipakai juga layar Maintenance).
 Input        : Query id (dokumen perusahaan) atau new=1 (hanya HR Officer); session v2_flash dan errors.
 Output       : POST v2.hrm.sop.save (Save, Approve, Back to Draft, Activate / Deactivate).
 Dipakai oleh : Dibuka dari grid hrm-sop (Open dan + New Record).
 Status       : Aktif (T7f HRM).
--}}
@php
    use App\Models\Master\OrgDepartment;
    use App\Models\Mnt\MntDocument;
    use App\Models\Mnt\MntLog;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'hrm-sop');
    $user = Auth::user();
    $hr = $user->isHrOfficer();
    $m = request('id') ? MntDocument::company()->with('lines')->find((int) request('id')) : null;
    abort_if(request('id') && (! $m || (! $hr && ! ($m->isApproved() && $m->activate))), 404);
    abort_unless($m || ($hr && request('new')), 404);
    $new = ! $m;
    $m ??= new MntDocument(['kind' => 'SOP', 'revision' => '00', 'status' => 'DRAFT', 'activate' => true]);
    $locked = ! $new && $m->isApproved();
    $editable = $hr && ! $locked;
    $s = fn (string $col) => Src::of('tm2mnt.mnt_document.'.$col);
    $v = fn (string $col) => old($col, $m->{$col});
    $rows = old('lines') ?: ($new ? [['section' => 'Steps', 'text' => '']] : $m->lines->map(fn ($l) => ['section' => $l->section, 'text' => $l->line_text])->all());
    $rows = $rows ?: [['section' => 'Steps', 'text' => '']];
    $departments = OrgDepartment::where('activate', true)->orderBy('department_name')->pluck('department_name', 'department_id')->all();
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="hrm-sop-form" :title="$new ? 'New SOP / Work Instruction' : raw($m->title)" :subtitle="$new ? null : raw($m->doc_no)"
    panel="top" kind="master" :status="$new ? 'new' : ($m->activate ? 'active' : 'inactive')" :stage="$new ? null : t($m->isApproved() ? 'Approved' : 'Draft')" :can-input="$editable"
    :action="route('v2.hrm.sop.save')" action-field="action" :back="$home" :new-url="$hr ? $home.'?new=1' : null" :hidden="$new ? [] : ['id' => $m->id_document]"
    :tabs="['general' => t('Document'), 'content' => t('Steps')]" :log="$new ? [] : MntLog::forDoc('DOCUMENT', $m->id_document)"
    :created-by="$m->created_by" :created-at="$m->created_at?->toDateTimeString()" :updated-by="$m->updated_by" :updated-at="$m->updated_at?->toDateTimeString()">
    @if ($hr && ! $new)
        <x-slot:special>
            @if ($locked)
                <x-dl::button variant="secondary" icon="fa-rotate-left" label="Back to Draft" type="submit" name="action" value="to_draft" formnovalidate />
            @else
                <x-dl::button variant="success" icon="fa-check" label="Approve" type="submit" name="action" value="approve" formnovalidate />
            @endif
        </x-slot:special>
    @endif
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Document" :value="$new ? t('(new)') : raw($m->doc_no)" />
        <x-dl::strip-kv kind="grand" label="Revision" :value="raw((string) $m->revision)" />
        <x-dl::strip-kv kind="grand" label="Status" :value="t($m->isApproved() ? 'Approved' : 'Draft')" />
        <x-dl::strip-kv kind="grand" label="Department scope" :value="raw($m->department?->department_name ?? t('All departments'))" />
    </x-slot:strip>

    <x-slot:general>
        <x-dl::card title="Document">
            <x-dl::form.row :cols="4">
                <x-dl::field.text name="doc_no" label="Document No." required :case-toggle="false" placeholder="e.g. 001/SOP/TM/V/2026" :value="$v('doc_no')" :source="$s('doc_no')" />
                <x-dl::field.select name="kind" label="Document type" required :options="array_map('t', MntDocument::COMPANY_KINDS)" :value="$v('kind')" :placeholder="null" :source="$s('kind')" />
                <x-dl::field.text name="revision" label="Revision" required :case-toggle="false" :value="$v('revision')" :source="$s('revision')" />
                <x-dl::field.date name="effective_date" label="Effective from" :value="old('effective_date', $m->effective_date?->toDateString())" :source="$s('effective_date')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="2">
                <x-dl::field.text name="title" label="Title" required :value="$v('title')" :source="$s('title')" />
                <x-dl::field.select name="department_id" label="Department scope" placeholder="— All departments —" :options="$departments" :value="$v('department_id')" :source="$s('department_id')" />
            </x-dl::form.row>
            <x-dl::field.text name="purpose" label="Purpose" multiline :value="$v('purpose')" :source="$s('purpose')" />
            @if ($locked)
                <x-dl::hint note>This document is approved and locked. Use Back to Draft to change it; that starts a new revision.</x-dl::hint>
            @endif
        </x-dl::card>
    </x-slot:general>

    <x-slot:content>
        <x-dl::lines name="lines" title="Steps" add-label="+ Add line" :min-rows="1" :duplicable="false" :rows="$rows" :readonly="! $editable" source="{{ Src::of('tm2mnt.mnt_document_line') }}"
            :columns="[['key' => 'section', 'label' => 'Section', 'width' => '150px', 'default' => 'Steps'], ['key' => 'text', 'label' => 'Step text', 'width' => '620px']]" />
    </x-slot:content>
</x-dl::form>
