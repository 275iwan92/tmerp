{{--
 REMARK
 Tujuan       : Kartu Material substitution di tab Bill of Material layar production-wo, memindahkan fitur substitusi v1 pages::production.wo-entry: baris BOM snapshot Inquiry dengan Qty Kebutuhan / Tersedia / Harus Dibeli, tombol Substitute (dialog dengan pratinjau UP, efisiensi, waste dan selisih biaya sebelum diajukan), status PENDING / AKTIF, Approve / Reject / Undo oleh Manager Operasional ke atas.
 Sumber hulu  : App\Services\Production\WoMaterialSubstitutionService (rows, preview, submit, approve, reject, undo); lookup wo_substitute_material.
 Input        : $wo (PrdWo), $src (Src); query sub (id baris BOM yang dialognya dibuka), rawmat_id, length_mm, width_mm, reason (pratinjau lewat GET).
 Output       : x-dl::card, x-dl::table, x-dl::modal, x-dl::action-form (GET pratinjau, POST v2.production.wo.bom.substitute dan sub.*).
 Dipakai oleh : resources/views/v2/screens/production-wo.blade.php (tab Bill of Material).
 Status       : Aktif (T9 pindahan v1, 2026-10-20).
--}}
@php
    use App\Services\Production\WoMaterialSubstitutionService;
    use Devlib\Locale;

    $svc = app(WoMaterialSubstitutionService::class);
    $woModel = \App\Models\Prd\PrdWo::findOrFail($wo->wo_id);   // $wo da view = objek Ppic, service butuh model
    $lines = $svc->rows($woModel);
    $openLine = (int) request('sub');
    $manager = auth()->user()->isOpsManagerOrAbove();
@endphp
@if ($lines->isNotEmpty())
    <x-dl::card title="Material substitution" description="Material lines of the Product Development BOM (Inquiry snapshot). A substitution only replaces the displayed line after approval; the snapshot itself never changes.">
        <x-dl::table :source="$src" empty="No material lines."
            :columns="['name' => 'Material & specification', 'need' => ['Required Qty', 'num' => true, 'raw' => true], 'have' => ['Available Qty (Warehouse)', 'num' => true, 'raw' => true], 'to_buy' => ['Qty to buy', 'num' => true, 'raw' => true]]"
            :rows="$lines->map(fn ($l) => ['name' => raw($l->name.($l->spec !== '' ? ' · '.$l->spec : '')), 'need' => Locale::number($l->need).' '.$l->unit, 'have' => Locale::number($l->have).' '.$l->unit, 'to_buy' => Locale::number($l->to_buy).' '.$l->unit])->all()" />
        @foreach ($lines as $l)
            <x-dl::action-bar align="start">
                <x-dl::hint>{{ $l->name }}</x-dl::hint>
                @if ($l->pending)
                    <x-dl::badge label="PENDING" tone="warn" />
                    <x-dl::hint>{{ tf('Proposed: :name (:l x :w mm), UP :up, efficiency :eff%, cost change :delta IDR', ['name' => $l->pending->substitute_rawmat_name, 'l' => Locale::number((float) $l->pending->substitute_length_mm), 'w' => Locale::number((float) $l->pending->substitute_width_mm), 'up' => $l->pending->computed_up_count, 'eff' => Locale::number((float) $l->pending->computed_efficiency_pct, 1), 'delta' => $l->pending->total_cost_delta !== null ? Locale::number((float) $l->pending->total_cost_delta) : '—']) }}</x-dl::hint>
                    @if ($manager)
                        <x-dl::action-form action="{{ route('v2.production.wo.sub.approve', ['wo' => $wo->wo_id, 'sub' => $l->pending->id]) }}"><x-dl::button size="sm" icon="fa-check" label="Approve" type="submit" /></x-dl::action-form>
                        <x-dl::button size="sm" variant="secondary" icon="fa-xmark" label="Reject" type="button" :data-dl-open="'wo-sub-rej-'.$l->pending->id" />
                    @else
                        <x-dl::hint>@t('Waiting for Operations Manager approval or above')</x-dl::hint>
                    @endif
                @else
                    @if ($l->active)
                        <x-dl::badge label="ACTIVE" tone="info" />
                        <x-dl::hint>{{ tf('Approved by :by · :at · estimated output :n pcs', ['by' => $l->active->approved_by, 'at' => $l->active->approved_at ? Locale::dt($l->active->approved_at) : '—', 'n' => Locale::number((float) $l->active->computed_qty_output_pcs)]) }}</x-dl::hint>
                        @if ($manager)
                            <x-dl::action-form action="{{ route('v2.production.wo.sub.undo', ['wo' => $wo->wo_id, 'sub' => $l->active->id]) }}"><x-dl::button size="sm" variant="secondary" icon="fa-rotate-left" label="Undo" type="submit" /></x-dl::action-form>
                        @endif
                    @endif
                    <x-dl::button size="sm" variant="secondary" icon="fa-right-left" label="Substitute" type="button" :data-dl-open="'wo-sub-'.$l->id" />
                @endif
            </x-dl::action-bar>

            @if (! $l->pending)
                @php
                    $mine = $openLine === $l->id;
                    $rawId = $mine ? (int) request('rawmat_id') : 0;
                    $len = $mine ? (float) Locale::parse(request('length_mm')) : 0;
                    $wid = $mine ? (float) Locale::parse(request('width_mm')) : 0;
                    $pv = $mine && $rawId ? $svc->preview($woModel, $l->id, $rawId, $len, $wid) : null;
                    $subUrl = route('v2.production.wo.bom.substitute', ['wo' => $wo->wo_id, 'line' => $l->id]);
                    $previewUrl = route('v2.screen', 'production-wo').'?id='.$wo->wo_id.'&tab=bom&sub='.$l->id;
                @endphp
                <x-dl::modal :id="'wo-sub-'.$l->id" title="Substitute material" size="lg" icon="fa-right-left" :open="$mine">
                    <x-dl::action-form action="{{ $subUrl }}" :id="'wo-sub-form-'.$l->id">
                        <x-dl::field.lookup name="rawmat_id" lookup="wo_substitute_material" label="Substitute material" :value="$mine ? $rawId : null" required placeholder="Type 2+ letters of name / code..." :source="$src" />
                        <x-dl::form.row :cols="2">
                            <x-dl::field.decimal name="length_mm" label="Length (mm)" :value="$mine ? $len : null" required :source="$src" />
                            <x-dl::field.decimal name="width_mm" label="Width (mm)" :value="$mine ? $wid : null" required :source="$src" />
                        </x-dl::form.row>
                        <x-dl::hint>@t('The size is filled by hand: the physical size is not complete in Raw Material Master yet.')</x-dl::hint>
                        <x-dl::field.text name="reason" label="Reason" :value="$mine ? request('reason') : null" :case-toggle="false" placeholder="e.g. The original stock is empty, a similar one is available..." :source="$src" />
                        @if ($pv && isset($pv['error']))
                            <x-dl::alert tone="warn" :message="raw(t($pv['error']))" />
                        @elseif ($pv)
                            <x-dl::kpi-row>
                                <x-dl::kpi label="UP per sheet" :value="$pv['up_count']" icon="fa-table-cells" tone="accent" />
                                <x-dl::kpi label="Efficiency" :value="$pv['efficiency_pct']" format="percent" icon="fa-gauge-high" tone="success" />
                                <x-dl::kpi label="Waste" :value="$pv['waste_pct']" format="percent" icon="fa-recycle" tone="warn" />
                                <x-dl::kpi label="Cost change (IDR)" :value="$pv['cost_delta'] ?? 0" format="money" icon="fa-coins" :tone="($pv['cost_delta'] ?? 0) > 0 ? 'danger' : 'success'" :note="$pv['cost_delta'] === null ? t('unit cost missing') : null" />
                            </x-dl::kpi-row>
                            <x-dl::hint>{{ tf('Required sheets: :need · available of the substitute: :have · output from stock: :out pcs', ['need' => Locale::number($pv['need_new']), 'have' => Locale::number($pv['have']), 'out' => Locale::number($pv['output_pcs'])]) }}</x-dl::hint>
                        @endif
                    </x-dl::action-form>
                    <x-slot:footer>
                        <x-dl::button preset="cancel" data-dl-close-modal />
                        <x-dl::button variant="secondary" icon="fa-calculator" label="Preview" type="submit" :form="'wo-sub-form-'.$l->id" formaction="{{ $previewUrl }}" formmethod="get" />
                        <x-dl::button icon="fa-paper-plane" label="Submit for approval" type="submit" :form="'wo-sub-form-'.$l->id" />
                    </x-slot:footer>
                </x-dl::modal>
            @else
                <x-dl::modal :id="'wo-sub-rej-'.$l->pending->id" title="Reject substitution" size="md" icon="fa-xmark" tone="danger">
                    <x-dl::action-form action="{{ route('v2.production.wo.sub.reject', ['wo' => $wo->wo_id, 'sub' => $l->pending->id]) }}" :id="'wo-sub-rej-form-'.$l->pending->id">
                        <x-dl::field.text name="reason" label="Rejection reason" required :case-toggle="false" :source="$src" />
                    </x-dl::action-form>
                    <x-slot:footer>
                        <x-dl::button preset="cancel" data-dl-close-modal />
                        <x-dl::button variant="danger" icon="fa-xmark" label="Reject" type="submit" :form="'wo-sub-rej-form-'.$l->pending->id" />
                    </x-slot:footer>
                </x-dl::modal>
            @endif
        @endforeach
    </x-dl::card>
@endif
