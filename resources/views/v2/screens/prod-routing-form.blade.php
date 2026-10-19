{{--
 REMARK
 Tujuan       : Form Routing Template v2 (menggantikan halaman v1 pages::production.routing-template): header (kode, nama, tipe produk, referensi bahan, deskripsi, template bawaan), tabel langkah (cabang, nomor urut, proses, mesin pilihan, opsional, qty factor, catatan) yang disimpan bersama Save, hasil pemeriksaan urutan (BLOCK / WARN / catatan) dan usulan mesin per langkah (hanya-baca), Deactivate / Activate, tab History.
 Sumber hulu  : tm2mst.prod_routing_template + prod_routing_step, prod_process, prod_machine, prod_process_precedence, matriks kapabilitas mesin (prod_process_machine); aturan di App\Services\Production\RoutingTemplateEntryService, pemeriksa RoutingTemplateService, MachineCapabilityService.
 Input        : Query id (template) atau new=1; session v2_flash dan errors; old input.
 Output       : POST v2.prod.routing.save (Save / Activate / Deactivate).
 Dipakai oleh : Dibuka dari grid prod-routing (Open dan + New Record).
 Status       : Aktif (Master T6b). Hasil pemeriksaan dan usulan mesin dihitung dari langkah yang terakhir disimpan dan dihitung ulang setiap Save; blok urutan tidak menghentikan Save. Mesin pilihan yang tidak mampu mengerjakan proses ditolak saat Save.
--}}
@php
    use App\Models\Master\ProdRoutingTemplate;
    use App\Services\Production\RoutingTemplateEntryService as Rt;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'prod-routing');
    $svc = app(Rt::class);
    $t = request('id') ? ProdRoutingTemplate::find((int) request('id')) : null;
    abort_if(request('id') && ! $t, 404);
    abort_unless($t || request('new'), 404);
    $new = ! $t;
    $t ??= new ProdRoutingTemplate(['activate' => true, 'is_default' => false]);
    $s = fn (string $col) => Src::of('tm2mst.prod_routing_template.'.$col);
    $v = fn (string $col) => old($col, $t->{$col});
    $oldSteps = old('steps');
    $rows = $svc->rows($new ? null : $t, is_array($oldSteps) ? $oldSteps : null);
    $res = $new ? null : $svc->result($t);
    $check = $res['check'] ?? ['ok' => true, 'blocks' => [], 'warnings' => [], 'notes' => []];
    $nSteps = $new ? 0 : $t->steps()->count();
    $columns = [
        ['key' => 'branch_code', 'label' => 'Branch', 'type' => 'text', 'width' => '90px', 'default' => 'MAIN'],
        ['key' => 'step_seq', 'label' => 'Seq (empty = next of 10)', 'type' => 'number', 'width' => '90px'],
        ['key' => 'process_id', 'label' => 'Process', 'type' => 'lookup', 'lookup' => 'prod_process', 'required' => true, 'width' => '280px'],
        ['key' => 'preferred_machine_id', 'label' => 'Preferred machine', 'type' => 'lookup', 'lookup' => 'prod_machine', 'width' => '220px'],
        ['key' => 'is_optional', 'label' => 'Optional', 'type' => 'bool', 'width' => '90px'],
        ['key' => 'qty_factor', 'label' => 'Qty factor', 'type' => 'number', 'width' => '90px'],
        ['key' => 'note', 'label' => 'Note', 'type' => 'text', 'width' => '220px'],
    ];
    $exp = collect($res['expansion'] ?? [])->map(fn ($e) => ['step' => $e['step_seq'].' · '.$e['branch_code'].' · '.($e['process']->process_name ?? '—'),
        'machine' => $e['suggested_machine'] ? $e['suggested_machine']->machine_code.' — '.$e['suggested_machine']->machine_name : ($e['unresolved'] ? t('No eligible machine') : t('Manual process')),
        'alternatives' => $e['alternatives'], 'blocked' => $e['blocked_machines'], 'note' => $e['fallback_reason'] ?: ($e['is_optional'] ? t('Optional step') : '')])->values()->all();
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::form id="prod-routing-form" :title="$new ? 'New Routing Template' : raw($t->template_name)" :subtitle="$new ? null : raw($t->template_code)"
    panel="top" kind="master" :status="$new ? 'new' : ($t->activate ? 'active' : 'inactive')"
    :action="route('v2.prod.routing.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'"
    :hidden="$new ? [] : ['id' => $t->template_id]" :tabs="['template' => t('Template'), 'steps' => t('Production steps')]" :log="$new ? [] : $svc->history($t)"
    :created-by="$t->created_by" :created-at="$t->created_at?->toDateTimeString()" :updated-by="$t->updated_by" :updated-at="$t->updated_at?->toDateTimeString()">
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Template code" :value="$new ? t('(on Save)') : raw($t->template_code)" />
        <x-dl::strip-kv kind="grand" label="Product type" :value="$new ? '—' : raw($t->prod_type_code ?: '—')" />
        <x-dl::strip-kv kind="grand" label="Steps" :value="$new ? '—' : Locale::number((float) $nSteps, 0)" />
        <x-dl::strip-kv kind="grand" label="Sequence check" :value="$new ? '—' : ($nSteps === 0 ? t('No steps yet') : ($check['ok'] ? t('Valid') : t('Invalid')).' · '.count($check['blocks']).' / '.count($check['warnings']))" />
    </x-slot:strip>

    <x-slot:template>
        <x-dl::card title="Template">
            <x-dl::form.row :cols="4">
                <x-dl::field.text name="template_code" label="Template code" required placeholder="e.g. BOXDPLX" hint="Unique; letters, digits, underscore or hyphen, up to 20." :value="$v('template_code')" :source="$s('template_code')" />
                <x-dl::field.text name="template_name" label="Template name" required :value="$v('template_name')" :source="$s('template_name')" />
                <x-dl::field.text name="prod_type_code" label="Product type (code)" :value="$v('prod_type_code')" :source="$s('prod_type_code')" />
                <x-dl::field.text name="substrate_hint" label="Material reference" placeholder="e.g. DUPLEX 350gsm" :value="$v('substrate_hint')" :source="$s('substrate_hint')" />
            </x-dl::form.row>
            <x-dl::field.text name="description" label="Description" multiline :value="$v('description')" :source="$s('description')" />
            <x-dl::field.checkbox name="is_default" label="Default template for this type / category" hint="Used automatically when the product matches and there is no other choice. With no default, the officer chooses." :value="(bool) $v('is_default')" :source="$s('is_default')" />
        </x-dl::card>
    </x-slot:template>

    <x-slot:steps>
        <x-dl::hint note>Sequence numbers go in multiples of 10 so a step can be inserted in the middle without renumbering everything. The machine must be registered as able to do the process (matrix in Process Master). The check below ignores unsaved edits; it is recomputed at every Save.</x-dl::hint>
        <x-dl::lines name="steps" title="Production steps" add-label="+ Add step" :min-rows="0" :rows="$rows" :columns="$columns" source="{{ Src::of('tm2mst.prod_routing_step') }}" />
        @unless ($new)
            @foreach ($check['blocks'] as $b)
                <x-dl::alert tone="danger" :message="raw($b['message'].($b['reason'] ? ' — '.$b['reason'] : ''))" />
            @endforeach
            @foreach ($check['warnings'] as $w)
                <x-dl::alert tone="warning" :message="raw($w['message'].($w['reason'] ? ' — '.$w['reason'] : ''))" />
            @endforeach
            @foreach ($check['notes'] as $n)
                <x-dl::alert tone="info" :message="raw($n)" />
            @endforeach
            @if ($exp)
                <x-dl::card title="What ProDev would propose">
                    <x-dl::table :columns="['step' => 'Step', 'machine' => 'Suggested machine', 'alternatives' => 'Ready alternatives', 'blocked' => 'Not eligible', 'note' => 'Note']" :rows="$exp" empty="No steps yet." :source="Src::of('tm2mst.prod_routing_step')" />
                </x-dl::card>
            @endif
        @endunless
    </x-slot:steps>
</x-dl::form>
