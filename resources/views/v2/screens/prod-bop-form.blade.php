{{--
 REMARK
 Tujuan       : Form aturan Bill of Process v2 (menggantikan halaman v1 pages::production.bop-rule): kartu Step (jenis aturan, finishing, tipe part, Seq, proses, mesin otomatis, kondisi, label routing, waste dan toleransi), Merge, Material (BOM) dan Notes; tab Usage suggestions (takaran dan waste usulan dari data nyata, Apply suggestion, Auto apply, hitung ulang); Deactivate / Activate; tab History. Hanya admin yang boleh mengubah, lainnya baca-saja.
 Sumber hulu  : tm2mst.prod_bop_rule, prod_process, ref_finishing_type; aturan di App\Services\Production\BopRuleEntryService; usulan dari App\Services\ProDev\UsageRateEngine. Engine ProDev (SpecEngine) hanya membaca tabel ini dan tidak disentuh.
 Input        : Query id (aturan) atau new=1; session v2_flash dan errors.
 Output       : POST v2.prod.bop.save (Save / Activate / Deactivate / Apply suggestion / Refresh suggestions).
 Dipakai oleh : Dibuka dari grid prod-bop-rule (Open dan + New Record).
 Status       : Aktif (Master T6b). Panah naik / turun v1 diganti isian Seq. Token label: {machine} {attr} {roll} {pack}.
--}}
@php
    use App\Models\Master\ProdBopRule;
    use App\Services\Production\BopRuleEntryService as Bop;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $home = route('v2.screen', 'prod-bop-rule');
    $svc = app(Bop::class);
    $r = request('id') ? ProdBopRule::with(['process', 'finishing'])->find((int) request('id')) : null;
    abort_if(request('id') && ! $r, 404);
    abort_unless($r || request('new'), 404);
    $new = ! $r;
    $r ??= new ProdBopRule(['activate' => true, 'rule_scope' => 'STANDARD', 'step_seq' => (int) ProdBopRule::max('step_seq') + 10]);
    $can = $svc->canEdit(Auth::user());
    $s = fn (string $col) => Src::of('tm2mst.prod_bop_rule.'.$col);
    $v = fn (string $col) => old($col, $r->{$col});
    $n = fn ($x) => $x === null ? '—' : Locale::number((float) $x, 4);
    $opt = fn (array $a) => array_map('t', $a);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif
@unless ($can)
    <x-dl::alert tone="warning" message="Only an admin can change the BOP rules. You can read them here." />
@endunless

<x-dl::form id="prod-bop-form" :title="$new ? 'New BOP Rule' : raw($r->step_label ?: ($r->process?->process_name ?: $r->material_role ?: 'Material rule'))" :subtitle="$new ? null : raw('Seq '.$r->step_seq.' · '.t(Bop::SCOPES[$r->rule_scope] ?? $r->rule_scope))"
    panel="top" kind="master" :status="$new ? 'new' : ($r->activate ? 'active' : 'inactive')" :can-input="$can"
    :action="route('v2.prod.bop.save')" action-field="action" :back="$home" :new-url="$home.'?new=1'"
    :hidden="$new ? [] : ['id' => $r->id_bop_rule]" :tabs="['rule' => t('Rule'), 'usage' => t('Usage suggestions')]" :log="$new ? [] : $svc->history($r)"
    :created-by="$r->created_by" :created-at="$r->created_at?->toDateTimeString()" :updated-by="$r->updated_by" :updated-at="$r->updated_at?->toDateTimeString()">
    <x-slot:special>
        @if ($can)
            <x-dl::button type="submit" name="action" value="refresh" variant="secondary" icon="fa-chart-line" label="Refresh suggestions" formnovalidate data-dl-confirm="{{ t('Calculate usage-rate and waste suggestions from the work orders completed in the last 3 months? Rules with Auto apply on take the new value.') }}" />
        @endif
    </x-slot:special>
    <x-slot:strip>
        <x-dl::strip-kv kind="grand" label="Seq" :value="$new ? '—' : (string) $r->step_seq" />
        <x-dl::strip-kv kind="grand" label="Rule type" :value="$new ? '—' : raw(t(Bop::SCOPES[$r->rule_scope] ?? $r->rule_scope).($r->finishing ? ' · '.$r->finishing->finishing_name : ''))" />
        <x-dl::strip-kv kind="grand" label="Process" :value="$new ? '—' : raw($r->process?->process_name ?: t('Material only'))" />
        <x-dl::strip-kv kind="grand" label="Rate source" :value="$new ? '—' : raw($r->rate_source ?: '—')" />
    </x-slot:strip>

    <x-slot:rule>
        <x-dl::card title="Step">
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="rule_scope" label="Rule type" required :options="$opt(Bop::SCOPES)" :value="$v('rule_scope')" :source="$s('rule_scope')" />
                <x-dl::field.select name="finishing_type_id" label="Finishing (finishing insert only)" placeholder="—" :options="$svc->finishingOptions()" :value="$v('finishing_type_id')" :source="$s('finishing_type_id')" />
                <x-dl::field.select name="part_type" label="Part type only" placeholder="All part types" :options="Bop::PART_TYPES" :value="$v('part_type')" :source="$s('part_type')" />
                <x-dl::field.number name="step_seq" label="Seq" required hint="Lower numbers run first. Adjacent steps with the same merge group are merged." :value="$v('step_seq')" :source="$s('step_seq')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="process_id" label="Process (empty = material rule only)" placeholder="—" :options="$svc->processOptions()" :value="$v('process_id')" :source="$s('process_id')" />
                <x-dl::field.select name="machine_role" label="Auto machine" placeholder="No machine" :options="$opt(Bop::ROLES)" :value="$v('machine_role')" :source="$s('machine_role')" />
                <x-dl::field.select name="condition_code" label="Condition (standard step only)" placeholder="Always" :options="$opt(Bop::CONDITIONS)" :value="$v('condition_code')" :source="$s('condition_code')" />
            </x-dl::form.row>
            <x-dl::field.text name="step_label" label="Routing label" placeholder="e.g. Cetak di mesin: {machine}" hint="Tokens: {machine} {attr} {roll} {pack}. Required when a process is chosen." :value="$v('step_label')" :source="$s('step_label')" />
            <x-dl::form.row :cols="4">
                <x-dl::field.number name="waste_pct" label="Default waste (%)" :value="$v('waste_pct')" :source="$s('waste_pct')" />
                <x-dl::field.number name="tolerance_pct" label="Default tolerance (%)" :value="$v('tolerance_pct')" :source="$s('tolerance_pct')" />
            </x-dl::form.row>
        </x-dl::card>
        <x-dl::card title="Merge">
            <x-dl::form.row :cols="4">
                <x-dl::field.text name="merge_group" label="Merge group" placeholder="e.g. PLONG (empty = not merged)" :value="$v('merge_group')" :source="$s('merge_group')" />
                <x-dl::field.text name="merge_label" label="Joined text when merged" placeholder="e.g. + Emboss" :value="$v('merge_label')" :source="$s('merge_label')" />
            </x-dl::form.row>
        </x-dl::card>
        <x-dl::card title="Material (BOM)">
            <x-dl::form.row :cols="4">
                <x-dl::field.text name="material_pattern" label="Material pattern (mat_raw, separate with |)" placeholder="e.g. PLASTIKLAMDOFF%" :value="$v('material_pattern')" :source="$s('material_pattern')" />
                <x-dl::field.select name="material_rule" label="Material selection method" placeholder="No material" :options="$opt(Bop::MATERIAL_RULES)" :value="$v('material_rule')" :source="$s('material_rule')" />
                <x-dl::field.text name="material_role" label="BOM line name" placeholder="e.g. LEM LAMINASI" hint="Required when a pattern is filled in." :value="$v('material_role')" :source="$s('material_role')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.select name="usage_basis" label="Usage basis" placeholder="Not calculated" :options="$opt(Bop::USAGE)" :value="$v('usage_basis')" :source="$s('usage_basis')" />
                <x-dl::field.number name="usage_rate" label="Usage rate" :value="$v('usage_rate')" :source="$s('usage_rate')" />
            </x-dl::form.row>
        </x-dl::card>
        <x-dl::field.text name="note" label="Notes" :value="$v('note')" :source="$s('note')" />
    </x-slot:rule>

    <x-slot:usage>
        <x-dl::card title="Usage suggestions">
            <x-dl::hint note>The suggestions come from work orders completed in the last 3 months. They change a rule only when you click Apply suggestion, or automatically while Auto apply is on.</x-dl::hint>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Usage rate now" :value="$new ? '—' : $n($r->usage_rate)" :source="$s('usage_rate')" />
                <x-dl::field.display label="Suggested rate" :value="$new ? '—' : $n($r->rate_suggested)" :source="$s('rate_suggested')" />
                <x-dl::field.display label="Waste now (%)" :value="$new ? '—' : $n($r->waste_pct)" :source="$s('waste_pct')" />
                <x-dl::field.display label="Suggested waste (%)" :value="$new ? '—' : $n($r->waste_suggested)" :source="$s('waste_suggested')" />
            </x-dl::form.row>
            <x-dl::form.row :cols="4">
                <x-dl::field.display label="Sample (completed WOs)" :value="$new ? '—' : Locale::number((float) ($r->suggest_sample ?? 0), 0)" :source="$s('suggest_sample')" />
                <x-dl::field.display label="Calculated at" :value="$new || ! $r->suggest_at ? '—' : Locale::dt($r->suggest_at)" :source="$s('suggest_at')" />
                <x-dl::field.display label="Rate source" :value="$new ? '—' : raw($r->rate_source ?: '—')" :source="$s('rate_source')" />
                <x-dl::field.checkbox name="auto_apply" label="Auto apply" hint="Take the suggestion automatically at every refresh." :value="(bool) $v('auto_apply')" :source="$s('auto_apply')" />
            </x-dl::form.row>
            @if ($can && ! $new)
                <x-dl::action-bar>
                    <x-dl::button type="submit" name="action" value="apply" variant="secondary" icon="fa-check" label="Apply suggestion" formnovalidate />
                </x-dl::action-bar>
            @endif
        </x-dl::card>
    </x-slot:usage>
</x-dl::form>
