{{--
 REMARK
 Tujuan       : Layar ProCost Mode Integrasi (Costing Hub per inquiry): hitung HPP dan harga per tier dari Inquiry + ProDev. Isian officer disimpan dengan Save (kirim hanya field yang berubah ke CostingEntryService); Hitung, Submit ke Direksi, Publish (Direksi), Quotation, Book / Release hold. Rumus dan HPP hanya untuk login berhak (PROCOST_FORMULA_LOGINS).
 Sumber hulu  : App\Support\V2\Data\ProCost::state (iqp_costing + part + tier, ProCostContextService, CostingAdvisor), ProCostFields (katalog field per tipe part), CostingEntryService.
 Input        : Query inquiry (id inquiry), part (nomor part), tier (id price tier), view=1 (lihat saja, tidak membuat draft); tanpa inquiry = daftar Pro-Cost New.
 Output       : x-dl::form (strip, tab General / Cost parameters / ProDev & BOM / Result), x-dl::accordion, x-dl::table, x-mp::prodev.layout-sheet; POST ke v2.procost.save dan v2.procost.accept-zero.
 Dipakai oleh : Route v2.screen slug procost (menu Pro-Cost, grid sales-procost*, rute lama inquiry.costing, ProDev v1).
 Status       : Aktif (T10 tahap 3 pindahan v1 pages::inquiry.costing, 2026-10-21).
--}}
@php
    use App\Services\Costing\CostingAdvisor;
    use App\Services\Costing\CostingEntryService;
    use App\Support\V2\Costing\ProCostFields;
    use App\Support\V2\Data\ProCost;
    use App\Support\V2\Grid\Sales\CostingListGrid;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $inqId = request()->integer('inquiry');
@endphp

@if (! $inqId)
    @include('v2.screens._dbgrid', ['screen' => ['grid' => new CostingListGrid]])
@else
@php
    $st = ProCost::state($inqId, max(1, request()->integer('part', 1)), request()->integer('tier') ?: null, request()->boolean('view'));
    $inq = $st['inquiry'];
    $c = $st['costing'];
    \App\Support\V2\Crumb::set(raw($c?->doc_no ?? $inq->inquiry_no));
    $rp = fn ($v) => $v === null ? '—' : 'IDR '.Locale::number((float) $v, 2);
    $n2 = fn ($v) => $v === null ? '—' : Locale::number((float) $v, 2);
    $src = Src::of('tm2sdi.iqp_costing', 'tm2sdi.iqp_costing_part', 'tm2sdi.iqp_costing_price_tier', 'tm2sdi.iqp_costing_part_coating', 'tm2sdi.iqp_costing_recommendation');
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@foreach ($errors->all() as $e)<x-dl::alert tone="danger" :message="raw($e)" />@endforeach

@if ($st['view'])
    <x-dl::card title="Product Costing (view only)" description="Status and final prices only; the price shows after Direksi publishes (Side A is confidential).">
        @if (! $c)
            <x-dl::alert tone="info" message="Not processed yet — the Costing team has not opened or calculated this inquiry." />
        @elseif (! $c->published_flag)
            <x-dl::alert tone="info" :message="raw(tf('Not processed yet (:status) — the price shows after Direksi publishes.', ['status' => $c->doc_status]))" />
        @else
            <x-dl::table :columns="['qty' => ['Qty', 'num' => true, 'raw' => true], 'price' => ['Price / pcs', 'num' => true, 'raw' => true]]"
                :rows="$c->priceTiers->map(fn ($t) => ['qty' => Locale::number((float) $t->qty_pcs, 0).($t->is_primary ? ' ★' : ''), 'price' => $rp($t->price_per_pcs)])->all()" :source="$src" />
        @endif
        <x-dl::action-bar><x-dl::button variant="secondary" icon="back" label="Back" :href="route('v2.screen', 'sales-procost')" /></x-dl::action-bar>
    </x-dl::card>
@else
@php
    $t = $st['tier'];
    $p = $st['part'];
    $ctx = $st['ctx'];
    $ro = $st['ro'];
    $lock = ! $st['canSee'];
    $lk = $st['lookups'];
    $recs = $st['recs'];
    $qty = $st['qty'];
    $pid = $p?->id_costing_part ?? 0;
    $tid = $t?->id_price_tier ?? 0;
    $geo = $ctx['geometry'] ?? null;
    $hppOrder = (float) ($t?->c_hpp_pc ?? 0) * $qty;
    $typeLabel = ['NEW' => 'New product', 'RECALC' => 'Recalculation', 'REVISE' => 'Revision'][$c->calc_type] ?? $c->calc_type;
    $formStatus = match ($c->doc_status) { 'PENDING_APPROVAL' => 'approval', 'PUBLISHED' => 'approved', 'CANCELLED' => 'cancelled', default => 'draft' };
    $here = route('v2.screen', 'procost').'?inquiry='.$inqId;
    // Penanda sumber saran engine per isian: ✦ saran, ~ perkiraan, ✎ sudah diubah officer.
    $mark = function (?object $rec) {
        if (! $rec || $rec->source === 'OFFICER') { return null; }
        $fmt = rtrim(rtrim(number_format((float) $rec->recommended_value, 4, '.', ','), '0'), '.');
        $sign = $rec->is_override ? '✎' : ($rec->source === 'ESTIMATE' ? '~' : '✦');

        return $sign.' '.t('Engine suggestion').': '.$fmt.' · '.t(CostingAdvisor::SOURCES[$rec->source] ?? $rec->source).($rec->source_note ? ' ('.$rec->source_note.')' : '').($rec->is_override ? ' · '.t('you changed it') : '');
    };
    $opts = fn (?string $k) => $k === null ? [] : (array) ($lk[$k] ?? []);
    $fields = $p ? ProCostFields::integrated($p->part_type_code) : [];
    $geoLock = $ro || $p?->data_source === 'PRODEV';
    $kpiTone = fn ($v) => $v ? 'accent' : 'neutral';
@endphp

@if ($notices = session('procost_notices'))
    @foreach ($notices as $n)
        <x-dl::action-form action="{{ route('v2.procost.accept-zero', $inqId) }}">
            <input type="hidden" name="part" value="{{ $n['part'] }}"><input type="hidden" name="tier" value="{{ $n['tier'] }}"><input type="hidden" name="field" value="{{ $n['field'] }}">
            <input type="hidden" name="part_no" value="{{ $st['partNo'] }}"><input type="hidden" name="tier_id" value="{{ $tid }}">
            <x-dl::alert tone="warn" :message="raw(tf(':label was emptied and restored to :value.', ['label' => t($n['label']), 'value' => rtrim(rtrim(number_format((float) $n['value'], 4, '.', ','), '0'), '.')]))" />
            <x-dl::action-bar><x-dl::button variant="secondary" size="sm" label="Really not used" type="submit" /></x-dl::action-bar>
        </x-dl::action-form>
    @endforeach
@endif

@if ($st['adv']['filled'] > 0 || $st['adv']['missing'])
    <x-dl::alert tone="info" :message="raw(trim(
        ($st['adv']['filled'] > 0 ? tf('✦ The engine filled :n boxes from Inquiry, ProDev, templates, constants and masters.', ['n' => $st['adv']['filled']]).' '
            .($st['adv']['estimate'] > 0 ? tf(':n estimated (blue) because the ProDev layout does not exist yet.', ['n' => $st['adv']['estimate']]).' ' : '')
            .($st['adv']['override'] > 0 ? tf(':n already changed by you.', ['n' => $st['adv']['override']]).' ' : '')
            .t('Hover over the hint under a box to see its source; any value can be changed.') : '')
        .($st['adv']['missing'] ? ' '.t('Not filled yet and worth checking').': '.implode(', ', array_map('t', $st['adv']['missing'])).'.' : '')))" />
@endif

@if ($st['revise']->isNotEmpty())
    <x-dl::card title="Revision: what changed from the parent product (must be recalculated)" icon="fa-pen">
        <x-dl::table :columns="['part' => 'Part', 'what' => 'Changed', 'old' => 'Before', 'new' => 'After', 'impact' => 'Repeated in']"
            :rows="$st['revise']->map(fn ($r) => ['part' => raw($r->part?->part_name ?? t('Document')), 'what' => raw($r->field_label), 'old' => raw($r->old_value ?? '—'), 'new' => raw($r->new_value ?? '—'), 'impact' => raw($r->impact)])->all()" :source="Src::of('tm2sdi.iqp_revise_change')" />
    </x-dl::card>
@endif

<x-dl::kpi-row>
    <x-dl::kpi label="Total COGS (HPP)" :value="$lock ? '🔒' : $rp($hppOrder)" :hint="$lock ? null : $rp($t?->c_hpp_pc).' / pcs'" icon="fa-coins" tone="neutral" />
    <x-dl::kpi :label="'Margin (+'.(float) $t?->margin_pct.'%) · Tol '.(float) $t?->tolerance_pct.'%'" :value="$lock ? '🔒' : $rp(((float) $t?->price_per_pcs - (float) $t?->c_hpp_pc) * $qty)" hint="Profit contribution" icon="fa-chart-line" tone="success" />
    <x-dl::kpi :label="'VAT (+'.($c->ppn_mode === 'NON_PPN' ? 0 : (float) $c->ppn_pct).'%)'" :value="$rp(((float) $t?->c_after_ppn_pc - (float) $t?->price_per_pcs) * $qty)" hint="Tax" icon="fa-percent" tone="warn" />
    <x-dl::kpi label="Final selling price / pcs" :value="$rp($t?->c_after_ppn_pc)" :hint="'Excl VAT '.$rp($t?->price_per_pcs).' · Grand total '.$rp((float) $t?->c_after_ppn_pc * $qty)" icon="fa-check" tone="accent" />
</x-dl::kpi-row>

<x-dl::form id="procost-form" :title="'Pro-Cost · '.raw($c->doc_no ?? $inq->inquiry_no)" :subtitle="raw(ProCost::customerName($inq).' · '.$c->doc_status)" panel="top" kind="document" :status="$formStatus" :pipeline="false"
    :action="route('v2.procost.save', $inqId)" action-field="action" :back="route('v2.screen', 'sales-procost')"
    :hide="['delete', 'new', 'print', 'email', 'confirm', 'cancel', 'duplicate']"
    :tabs="['general' => 'General', 'params' => 'Cost parameters', 'prodev' => 'ProDev & BOM', 'result' => 'Result']"
    :created-by="$c->created_by" :created-at="$c->created_at?->toDateTimeString()" :updated-by="$c->updated_by" :updated-at="$c->updated_at?->toDateTimeString()">
    <x-slot:special>
        <x-dl::button size="sm" variant="secondary" icon="fa-gears" label="Calculate" type="submit" name="action" value="hitung" :disabled="$ro" formnovalidate />
        <x-dl::button size="sm" variant="success" icon="approve" label="Submit to Direksi" type="submit" name="action" value="submit" :disabled="$c->doc_status !== 'CALCULATED'" formnovalidate />
        @if ($st['canPublish'])
            <x-dl::button size="sm" icon="fa-bullhorn" label="Publish (Direksi)" type="submit" name="action" value="publish" :disabled="$c->doc_status !== 'PENDING_APPROVAL'" formnovalidate onclick="return confirm('{{ t('Publish this price to Sales? After publish the selling price is open for Quotation.') }}')" />
        @endif
        <x-dl::button size="sm" variant="secondary" icon="plus" label="+ Quotation" type="submit" name="action" value="quotation" formnovalidate />
    </x-slot:special>
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Doc No." :value="$c->doc_no ?? '—'" />
        <x-dl::field.display label="Inquiry" :value="$inq->inquiry_no.' · '.$inq->product_draft_name" :source="Src::of('tm2sdi.iqp_inquiry')" />
        <x-dl::field.display label="Customer" :value="ProCost::customerName($inq)" :source="Src::of('tm2mst.cust_customer')" />
        <x-dl::strip-kv kind="grand" label="Final selling price / pcs" :value="$rp($t?->c_after_ppn_pc)" />
        <input type="hidden" name="part_no" value="{{ $st['partNo'] }}"><input type="hidden" name="part_id" value="{{ $pid }}"><input type="hidden" name="tier_id" value="{{ $tid }}">
    </x-slot:strip>

    <x-slot:general>
        <x-dl::action-bar align="start">
            <x-dl::button size="sm" variant="secondary" icon="fa-file-lines" label="Inquiry" :href="route('v2.screen', 'inquiry-new').'?id='.$inqId.'&view=1'" />
            <x-dl::button size="sm" variant="secondary" icon="fa-table-cells" label="ProDev" :href="route('v2.prodev.open', $inqId)" />
            @foreach ($c->priceTiers as $pt)
                <x-dl::button size="sm" :variant="$pt->id_price_tier === $tid ? 'primary' : 'secondary'" :label="'Qty '.Locale::number((float) $pt->qty_pcs, 0).($pt->is_primary ? ' ★' : '')" :href="$here.'&part='.$st['partNo'].'&tier='.$pt->id_price_tier" />
            @endforeach
        </x-dl::action-bar>
        @if ($t && ! $t->tier_id)<x-dl::hint>@t('The inquiry has no qty tier yet — default 1,000')</x-dl::hint>@endif
        <x-dl::form.row :cols="3">
            <x-dl::field.decimal :name="'tier['.$tid.'][margin_pct]'" label="Margin (%)" :value="(float) $t?->margin_pct" :readonly="$ro" :hint="$mark($recs->get('0|'.$tid.'|margin_pct'))" :source="$src" />
            <x-dl::field.decimal :name="'tier['.$tid.'][tolerance_pct]'" label="Tolerance (%)" :value="(float) $t?->tolerance_pct" :readonly="$ro" :hint="$mark($recs->get('0|'.$tid.'|tolerance_pct'))" :source="$src" />
            <x-dl::field.select :name="'tier['.$tid.'][rounding_mode]'" label="Rounding" :options="ProCostFields::ROUNDING" :value="$t?->rounding_mode" :readonly="$ro" :source="$src" />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.decimal name="ppn_pct" label="VAT (%)" :value="(float) $c->ppn_pct" :readonly="$ro" :source="$src" />
            <x-dl::field.decimal name="paper_index" label="Paper index (IDR / ream)" :value="$p?->i_paper_price_override !== null ? (float) $p->i_paper_price_override : null" placeholder="master" :readonly="$ro || ! $p" :source="$src" />
            <x-dl::field.display label="Document" :value="$typeLabel.' (Rev '.($inq->revision_no ?? 0).') · '.($inq->pic_sales ?: '—')" :source="Src::of('tm2sdi.iqp_inquiry')" />
        </x-dl::form.row>

        <x-dl::divider label="Parts" />
        <x-dl::action-bar align="start">
            @for ($i = 1; $i <= $st['partCount']; $i++)
                <x-dl::button size="sm" :variant="$i === $st['partNo'] ? 'primary' : 'secondary'" :label="'Part '.$i" :href="$here.'&part='.$i.'&tier='.$tid" />
            @endfor
            @if (! $ro)
                @if ($st['inquiryParts']->isNotEmpty())
                    <x-dl::field.select name="new_inq_part" label="Add a part from the Inquiry" :options="$st['inquiryParts']->mapWithKeys(fn ($ip) => [$ip->id_part => ($ip->part_name ?: 'Part #'.$ip->part_seq).($ip->prod_part_id ? ' · ProDev' : '')])->all()" :source="Src::of('tm2sdi.iqp_part')" />
                    <x-dl::button size="sm" variant="secondary" icon="plus" label="+ Part" type="submit" name="action" value="add_part_inq" formnovalidate />
                @endif
                <x-dl::button size="sm" variant="secondary" icon="plus" label="+ Manual part" type="submit" name="action" value="add_part" formnovalidate />
                @if ($p)<x-dl::button size="sm" variant="danger" icon="fa-trash" label="Delete this part" type="submit" name="action" value="remove_part" formnovalidate onclick="return confirm('{{ t('Delete this part from the costing?') }}')" />@endif
            @endif
        </x-dl::action-bar>
        @if ($p)
            <x-dl::form.row :cols="3">
                <x-dl::field.display label="Part" :value="($p->part_name ?: '—').' ('.$p->part_type_code.')'" :source="$src" />
                <x-dl::field.display label="Dimensions L × W × H (mm)" :value="$n2($p->i_dim_panjang_mm ?: $c->i_dim_panjang_mm).' × '.$n2($p->i_dim_lebar_mm ?: $c->i_dim_lebar_mm).' × '.$n2($p->i_dim_tinggi_mm ?: $c->i_dim_tinggi_mm)" :source="$src" />
                <x-dl::field.display label="Material" :value="ProCost::materialLabel($p, $ctx)" :source="Src::of('tm2mst.mat_grammage')" />
            </x-dl::form.row>
            @if ($ctx)
                <x-dl::form.row :cols="3">
                    <x-dl::field.display label="Colour" :value="$ctx['iqpPart']?->cfg_color ?: ($ctx['prodPart']?->cfg_color ?: '—')" :source="$src" />
                    <x-dl::field.display label="Ink" :value="$ctx['iqpPart']?->cfg_ink ?: count($ctx['inks']).' pass'" :source="$src" />
                    <x-dl::field.display label="Finishing" :value="collect($ctx['finishings'])->pluck('name')->unique()->implode(', ') ?: '—'" :source="$src" />
                </x-dl::form.row>
                <x-dl::form.row :cols="3">
                    <x-dl::field.display label="Qty per set" :value="(float) $p->qty_per_set.' pcs'" :source="$src" />
                    <x-dl::field.display label="Source" :value="$inq->inquiry_no.($geo ? ' · ProDev '.$geo['mc'].' ('.$geo['status'].')' : ' · no ProDev layout yet')" :source="$src" />
                    @if ($ctx['artworkUrl'])<x-mp::image-thumb label="Artwork" :src="$ctx['artworkUrl']" />@else<x-mp::image-thumb label="Artwork" empty="No artwork" />@endif
                </x-dl::form.row>
            @endif
        @else
            <x-dl::alert tone="info" message="There is no costing part yet. Inquiry parts that already have a ProDev layout appear here automatically; otherwise add one with + Part." />
        @endif
    </x-slot:general>

    <x-slot:params>
        @if ($p)
            @foreach (ProCostFields::groups() as $g => $title)
                @php $gf = array_values(array_filter($fields, fn ($f) => $f['group'] === $g)); @endphp
                @if ($gf)
                    <x-dl::divider :label="$title" />
                    <x-dl::form.row :cols="4">
                        @foreach ($gf as $f)
                            @php
                                $name = 'part['.$pid.']['.$f['key'].']';
                                $val = $p->{$f['key']};
                                $ronly = $ro || ($f['geo'] && $geoLock);
                                $hint = $mark($recs->get($pid.'|0|'.$f['key']));
                            @endphp
                            @if ($f['type'] === 's')
                                <x-dl::field.select :name="$name" :label="$f['label']" :options="$opts($f['opt'])" :value="$f['key'] === 'i_film_rate' ? ($val !== null ? (string) (float) $val : '') : $val" :readonly="$ronly" :hint="$hint" :source="$src" />
                            @elseif ($f['type'] === 'b')
                                <x-dl::field.select :name="$name" :label="$f['label']" :options="ProCostFields::YES_NO" :value="$val ? '1' : '0'" :readonly="$ronly" :hint="$hint" :source="$src" />
                            @elseif ($f['type'] === 't')
                                <x-dl::field.text :name="$name" :label="$f['label']" :value="$val" :readonly="$ronly" :hint="$hint" :case-toggle="false" :source="$src" />
                            @else
                                <x-dl::field.decimal :name="$name" :label="$f['label']" :value="$val !== null ? (float) $val : null" :readonly="$ronly" :hint="$hint" :source="$src" />
                            @endif
                        @endforeach
                    </x-dl::form.row>
                @endif
            @endforeach
            @if (! in_array($p->part_type_code, ProCostFields::SHEET, true) && $p->part_type_code !== 'KRAFT')
                <x-dl::divider label="Coating layers" />
                @foreach ($p->coatings as $co)
                    <x-dl::form.row :cols="4">
                        <x-dl::field.select :name="'coating['.$co->id_coating.'][coating_type]'" label="Coating" :options="$lk['coatings']" :value="$co->coating_type" :readonly="$ro" :source="$src" />
                        <x-dl::field.decimal :name="'coating['.$co->id_coating.'][index_coating]'" label="Index coating (IDR / cm²)" :value="(float) $co->index_coating" :readonly="$ro" :source="$src" />
                        @if (! $ro)<x-dl::button size="sm" variant="danger" icon="fa-xmark" label="Remove" type="submit" name="action" :value="'remove_coating_'.$co->id_coating" formnovalidate />@endif
                    </x-dl::form.row>
                @endforeach
                @if (! $ro)<x-dl::button size="sm" variant="secondary" icon="plus" label="+ Layer" type="submit" name="action" value="add_coating" formnovalidate />@endif
            @endif
        @else
            <x-dl::alert tone="info" message="Add a part first (General tab)." />
        @endif
    </x-slot:params>

    <x-slot:prodev>
        @if ($p)
            <x-dl::accordion-group :multiple="true">
                <x-dl::accordion key="layout" icon="fa-table-cells" title="Visual Imposition Layout (ProDev)" :summary="raw($p->data_source === 'PRODEV' ? t('Take data (ProDev)') : t('Manual'))" :open="true">
                    @if (! $ro)
                        <x-dl::button size="sm" variant="secondary" :icon="$p->data_source === 'PRODEV' ? 'fa-link' : 'edit'" :label="$p->data_source === 'PRODEV' ? 'Switch to manual' : 'Take data from ProDev'" type="submit" name="action" value="toggle_source" formnovalidate />
                    @endif
                    @if ($p->data_source === 'PRODEV' && ! $ro)
                        <x-dl::form.row :cols="3">
                            <x-dl::field.select name="layout_group" label="Change ProDev layout (CONFIRMED)" :options="$st['layoutGroups']->mapWithKeys(fn ($g) => [$g->id_layout_group => $g->mastercard_code.' · '.$g->product?->product_name.' · '.$g->up_count.' UP'])->all()" :value="$p->prod_layout_group_id" :source="Src::of('tm2mst.prod_layout_group')" />
                            <x-dl::button size="sm" variant="secondary" label="Apply layout" type="submit" name="action" value="pick_layout" formnovalidate />
                        </x-dl::form.row>
                    @endif
                    @if ($geo)
                        <x-dl::table :columns="['k' => 'Item', 'v' => 'Value']" :rows="[
                            ['k' => 'Plano size', 'v' => raw((float) $geo['plano_l'].' × '.(float) $geo['plano_w'].' cm'.($geo['plano_name'] ? ' ('.$geo['plano_name'].')' : ''))],
                            ['k' => 'Print area', 'v' => raw((float) $geo['print_l'].' × '.(float) $geo['print_w'].' cm')],
                            ['k' => 'Imposition', 'v' => raw(rtrim(rtrim(number_format($geo['cut'], 2), '0'), '.').' sheet / plano ('.$geo['isi'].' up / sheet)')],
                            ['k' => 'Method · UP', 'v' => raw(($geo['method'] ?? '—').' · '.$geo['up'])],
                            ['k' => 'Efficiency / waste', 'v' => raw($n2($geo['efficiency']).'% / '.$n2($geo['waste']).'%')],
                            ['k' => 'Knife', 'v' => raw(($geo['knife'] ?: '—').($geo['knife_size'] ? ' ('.$geo['knife_size'].' cm)' : '').' · '.($geo['die_meter'] !== null ? $n2($geo['die_meter']).' m' : '—'))],
                            ['k' => 'Layout status', 'v' => raw($geo['status'])],
                        ]" :source="Src::of('tm2mst.prod_layout_group')" />
                        <x-mp::prodev.layout-sheet :detail="(array) ($ctx['group']->layout_detail ?? [])" :plano-l="(float) $geo['plano_l']" :plano-w="(float) $geo['plano_w']" />
                    @else
                        <x-dl::alert tone="info" message="This part has no ProDev layout yet — the illustration and print size appear after the layout is made in ProDev. Meanwhile fill the geometry in Cost parameters." />
                    @endif
                </x-dl::accordion>

                @if ($ctx['bomLive'])
                    <x-dl::accordion key="bom" icon="fa-boxes-stacked" title="BOM and availability" :summary="raw('BOM: ProDev · Availability: MRP · qty '.Locale::number($qty, 0))" :badge="(string) count($ctx['bomLive'])" :open="true">
                        <x-dl::action-bar align="end">
                            @if ($st['booking']?->doc_status === 'HELD')
                                <x-dl::badge :label="raw('Held by '.$st['booking']->hold_by.' until '.$st['booking']->hold_until?->format('d-M-Y'))" tone="success" />
                                <x-dl::button size="sm" variant="secondary" label="Release hold" type="submit" name="action" value="release_book" formnovalidate />
                            @else
                                @if ($c->doc_status !== 'PUBLISHED')<x-dl::badge label="Book is available after Publish" tone="warn" />@endif
                                <x-dl::button size="sm" variant="secondary" label="Book" type="submit" name="action" value="book" :disabled="$c->doc_status !== 'PUBLISHED'" formnovalidate />
                            @endif
                            <x-dl::button size="sm" variant="secondary" label="Recalculate" :href="$here.'&part='.$st['partNo'].'&tier='.$tid" />
                        </x-dl::action-bar>
                        <x-dl::table :columns="['role' => 'Role', 'material' => 'Material', 'need' => ['Need', 'num' => true, 'raw' => true], 'stock' => ['Free stock', 'num' => true, 'raw' => true], 'incoming' => ['Incoming PO/PR', 'num' => true, 'raw' => true], 'status' => 'Status']"
                            :rows="collect($ctx['bomLive'])->map(fn ($b) => ['role' => raw($b['role']), 'material' => raw($b['material']), 'need' => raw($b['need'] !== null ? Locale::number((float) $b['need'], 2).' '.$b['uom'].($b['estimated'] ? ' (est.)' : '') : '—'),
                                'stock' => raw($b['stock'] !== null ? Locale::number((float) $b['stock'], 2).' '.$b['stock_uom'] : '—'), 'incoming' => raw($b['incoming'] !== null ? Locale::number((float) $b['incoming'], 2) : '—'), 'status' => raw($b['status'])])->all()" :source="Src::of('tm2prd.prd_wo_material', 'tm2inv.v_rm_available')" />
                    </x-dl::accordion>
                @else
                    <x-dl::accordion key="bom" icon="fa-boxes-stacked" title="BOM — Bill of Material (InqProd)" :badge="(string) count($ctx['bom'])" :open="true">
                        <x-dl::table :columns="['layer' => 'Layer', 'material' => 'Material', 'brand' => 'Brand', 'spec' => 'Grammage / flute', 'size' => 'Size (cm)', 'qty' => 'Qty / sheet', 'source' => 'Source']" empty="There is no BOM in the Inquiry for this part yet."
                            :rows="collect($ctx['bom'])->map(fn ($b) => ['layer' => raw($b['layer'].($b['primary'] ? ' ★' : '')), 'material' => raw($b['material']), 'brand' => raw($b['brand'] ?: '—'), 'spec' => raw($b['spec'] ?: '—'), 'size' => raw($b['size'] ?: '—'), 'qty' => raw($b['qty'] ?: '—'), 'source' => raw($b['source'].($b['owner'] === 'CUSTOMER' ? ' · customer material' : ''))])->all()" :source="Src::of('tm2sdi.iqp_part_material')" />
                    </x-dl::accordion>
                @endif

                <x-dl::accordion key="bop" icon="fa-route" :title="'BOP — Routing ('.($ctx['bop'][0]['origin'] ?? 'ProDev').')'" :badge="(string) count($ctx['bop'])" :open="true">
                    <x-dl::table :columns="['step' => '#', 'process' => 'Process', 'machine' => 'Machine', 'where' => 'In-house / Subcon', 'target' => ['Target / hour', 'num' => true, 'raw' => true], 'factor' => ['Factor', 'num' => true, 'raw' => true], 'waste' => ['Waste', 'num' => true, 'raw' => true]]" empty="There is no routing in ProDev / Inquiry for this part yet."
                        :rows="collect($ctx['bop'])->map(fn ($r) => ['step' => raw(($r['branch'] ? $r['branch'].'-' : '').$r['step']), 'process' => raw($r['process']), 'machine' => raw($r['machine'] ?: '—'), 'where' => raw($r['subcon'] ? t('Subcon').': '.$r['subcon'] : t('In-house')),
                            'target' => raw($r['target'] ? Locale::number((float) $r['target'], 0) : '—'), 'factor' => raw($r['factor'] !== null ? (string) (float) $r['factor'] : '—'), 'waste' => raw($r['waste'] !== null ? (float) $r['waste'].'%' : '—')])->all()" :source="Src::of('tm2mst.prod_part_process')" />
                    @foreach ($st['routingWarnings'] as $w)<x-dl::alert tone="warn" :message="raw($w)" />@endforeach
                </x-dl::accordion>

                <x-dl::accordion key="ink" icon="fa-droplet" title="Ink" :badge="(string) count($ctx['inks'])">
                    <x-dl::table :columns="['pass' => 'Pass', 'unit' => 'Unit', 'ink' => 'Ink', 'cov' => ['Coverage', 'num' => true, 'raw' => true]]" empty="There is no ink data yet."
                        :rows="collect($ctx['inks'])->map(fn ($i) => ['pass' => raw($i->pass_label ?: $i->pass_seq), 'unit' => raw($i->unit_no), 'ink' => raw($i->ink_name), 'cov' => raw($i->coverage_pct !== null ? (float) $i->coverage_pct.'%' : '—')])->all()" :source="Src::of('tm2sdi.iqp_part_ink')" />
                </x-dl::accordion>

                <x-dl::accordion key="fin" icon="fa-wand-magic-sparkles" title="Finishing & ProDev part detail" :badge="(string) count($ctx['finishings'])">
                    <x-dl::table :columns="['name' => 'Finishing', 'attr' => 'Attribute', 'notes' => 'Notes', 'cost' => ['Cost', 'num' => true, 'raw' => true], 'origin' => 'Origin']" empty="There is no finishing yet."
                        :rows="collect($ctx['finishings'])->map(fn ($f) => ['name' => raw($f['name']), 'attr' => raw($f['attr'] ?: '—'), 'notes' => raw($f['notes'] ?: ''), 'cost' => raw($f['cost'] !== null ? $rp($f['cost']) : ''), 'origin' => raw($f['origin'])])->all()" :source="Src::of('tm2sdi.iqp_part_finishing')" />
                    @if ($pp = $ctx['prodPart'])
                        <x-dl::form.row :cols="3">
                            <x-dl::field.display label="Knife code" :value="$pp->die_code ?: '—'" :source="Src::of('tm2mst.prod_part')" />
                            <x-dl::field.display label="Knife size" :value="$pp->die_w_cm ? (float) $pp->die_w_cm.' × '.(float) $pp->die_h_cm.' cm' : '—'" :source="Src::of('tm2mst.prod_part')" />
                            <x-dl::field.display label="Raw material" :value="$pp->sheet_raw_w_cm ? (float) $pp->sheet_raw_w_cm.' × '.(float) $pp->sheet_raw_h_cm.' cm' : '—'" :source="Src::of('tm2mst.prod_part')" />
                            <x-dl::field.display label="Joint" :value="$pp->joint_type ?: '—'" :source="Src::of('tm2mst.prod_part')" />
                            <x-dl::field.display label="ECMA" :value="$pp->ecma_code ?: '—'" :source="Src::of('tm2mst.prod_part')" />
                            <x-dl::field.display label="Flap" :value="$pp->flap_side ? $pp->flap_side.' '.(float) $pp->flap_length_mm.' mm' : '—'" :source="Src::of('tm2mst.prod_part')" />
                        </x-dl::form.row>
                    @endif
                </x-dl::accordion>
            </x-dl::accordion-group>
        @else
            <x-dl::alert tone="info" message="Add a part first (General tab)." />
        @endif
    </x-slot:prodev>

    <x-slot:result>
        <x-dl::accordion-group :multiple="true">
            <x-dl::accordion key="breakdown" icon="fa-receipt" title="Granular Cost Breakdown" :summary="raw('Qty '.Locale::number($qty * max(1, (float) ($p?->qty_per_set ?? 1)), 0))" :open="true">
                @if ($lock)
                    <x-dl::alert tone="info" message="The cost breakdown is only for the designated logins (constant PROCOST_FORMULA_LOGINS). The final selling price stays visible above." />
                @elseif ($p)
                    @php
                        $mult = $qty * max(1, (float) $p->qty_per_set);
                        $tooling = ProCost::toolingInfo($p);
                        $toolOrder = (float) $p->c_tooling_cost_order;
                        $isSheet = in_array($p->part_type_code, ProCostFields::SHEET, true);
                    @endphp
                    <x-dl::table :columns="['k' => 'Materials', 'v' => ['Value', 'num' => true, 'raw' => true]]" :rows="array_values(array_filter([
                        $isSheet ? ['k' => 'Sheet (trim × length)', 'v' => raw($n2($p->c_lebar_trim_cm).' × '.$n2($p->c_panjang_sheet_cm).' cm')] : ['k' => 'Plano sheets needed', 'v' => raw(Locale::number($mult / max(0.0001, (float) $p->i_cut_per_plano * max(1, (float) $p->i_isi_per_lb)), 0).' sheets')],
                        $isSheet ? ['k' => 'Running meter / discount', 'v' => raw($n2($p->c_running_meter).' m / '.$n2((float) $p->c_discount_rm * 100).'%')] : ['k' => 'Cost per plano', 'v' => raw($rp((float) $p->c_paper_cost_pc * (float) $p->i_cut_per_plano * max(1, (float) $p->i_isi_per_lb)))],
                        ['k' => 'Total material cost', 'v' => raw($rp(((float) $p->c_paper_cost_pc + (float) $p->c_sheet_cost_pc) * $mult))],
                    ]))" :source="$src" />
                    <x-dl::table :columns="['name' => 'Process & finishing', 'rate' => ['Rate', 'num' => true, 'raw' => true], 'basis' => ['Basis', 'num' => true, 'raw' => true], 'sub' => ['Subtotal', 'num' => true, 'raw' => true]]"
                        :rows="array_merge(collect(ProCost::processRows($p, $qty))->map(fn ($r) => ['name' => raw($r[0]), 'rate' => raw($r[1]), 'basis' => raw($r[2]), 'sub' => raw($rp($r[3]))])->all(),
                            $p->coatings->map(fn ($co) => ['name' => raw($co->coating_type), 'rate' => raw('Index × Area'), 'basis' => raw(Locale::number($mult / max(1, (float) $p->i_isi_per_lb), 0).' sht'), 'sub' => raw($rp((float) $co->c_cost_per_pc * $mult))])->all(),
                            [['name' => raw(t('Total process')), 'rate' => '', 'basis' => '', 'sub' => raw($rp(((float) $p->c_process_cost_pc + (float) $p->c_total_coating_pc) * $mult))]])" :source="$src" />
                    @if ($toolOrder > 0)
                        <x-dl::table :columns="['name' => 'Tooling (film · plate · knife)', 'basis' => ['Basis', 'num' => true, 'raw' => true], 'sub' => ['Cost', 'num' => true, 'raw' => true]]" :rows="[
                            ['name' => 'Film', 'basis' => raw($tooling['film_ff'].'² × '.$n2($p->c_area_cetak_cm2).' cm² × '.$tooling['film_rate']), 'sub' => raw($rp($p->c_film_cost_order))],
                            ['name' => 'Plate', 'basis' => raw($n2($tooling['plate']).' × '.$p->i_jumlah_warna.' '.t('colours')), 'sub' => raw($rp($p->c_plate_cost_order))],
                            ['name' => 'Knife', 'basis' => raw($tooling['knife_ff'].' × '.$n2($tooling['knife_rate'])), 'sub' => raw($rp($p->c_knife_cost_order))],
                            ['name' => raw(t('Total tooling')), 'basis' => '', 'sub' => raw($rp($toolOrder))],
                        ]" :source="$src" />
                    @endif
                    @if ($p->description_calc)<x-dl::field.text name="description_calc_view" label="Calculation description" multiline readonly :value="$p->description_calc" :source="$src" />@endif
                @endif
            </x-dl::accordion>

            @if ($st['report'] && ! $lock)
                @php
                    $rep = $st['report'];
                    $cmp = $rep['compare'] ?? ['n' => 0, 'median' => null, 'min' => null, 'max' => null, 'pct' => null];
                    $idr = fn ($v) => 'IDR '.number_format((float) $v, 2, '.', ',');
                    $srcLabels = CostingAdvisor::SOURCES;
                @endphp
                <x-dl::accordion key="report" icon="fa-clipboard-check" title="Calculation report" :badge="count($rep['notes']).' '.t('notes')" :tone="collect($rep['notes'])->contains(fn ($n) => $n[0] === 'warn') ? 'warn' : 'ok'" :open="true"
                    :summary="raw(tf('Selling price :p per pcs (:o per order)', ['p' => $idr($rep['final']), 'o' => $idr($rep['per_order'])]).($cmp['pct'] !== null ? ', '.number_format(abs($cmp['pct']), 1).'% '.($cmp['pct'] >= 0 ? t('above') : t('below')).' '.t('the median of similar costings') : ''))">
                    <x-dl::table :columns="['k' => '1. Price breakdown, per pcs', 'v' => ['Value', 'num' => true, 'raw' => true]]" :rows="array_merge(
                        collect($rep['split'])->map(fn ($val, $lbl) => ['k' => raw($lbl), 'v' => raw($idr($val))])->values()->all(),
                        $rep['ok'] ? collect($rep['steps'])->map(fn ($s) => ['k' => raw($s[0]), 'v' => raw($s[2] === 'sum' ? $idr($s[1]) : ($s[1] < 0 ? '- ' : '+ ').number_format(abs($s[1]), 2, '.', ','))])->all() : [['k' => raw(t('The step breakdown is not shown: the trace differs from the stored engine result. Recalculate or report it.')), 'v' => '']])" :source="$src" />
                    <x-dl::table :columns="['tag' => '2. Worth your attention', 'txt' => 'Note']" empty="No notes: every core box is filled and nothing is estimated."
                        :rows="collect($rep['notes'])->map(fn ($n) => ['tag' => raw($n[1]), 'txt' => raw($n[2])])->all()" :source="$src" />
                    <x-dl::hint>{{ $cmp['median'] !== null
                        ? tf('3. Compared with similar costings (:n tiers): median :m, lowest :lo, highest :hi.', ['n' => $cmp['n'], 'm' => $idr($cmp['median']), 'lo' => $idr($cmp['min']), 'hi' => $idr($cmp['max'])])
                        : tf('3. Only :n comparisons so far (minimum 3). The comparison appears after more costings are calculated.', ['n' => $cmp['n']]) }}</x-dl::hint>
                    <x-dl::hint>{{ tf('4. Source of the filled boxes: :s. Boxes you changed: :o. Statistics are used from 5 similar costings and light machine learning from 30 (COSTING constants). Templates only fill rates; product geometry stays from Inquiry and ProDev.', ['s' => collect($rep['sources'])->map(fn ($n, $s) => ($srcLabels[$s] ?? $s).' '.$n)->implode(', ') ?: '-', 'o' => $rep['overrides']]) }}</x-dl::hint>
                    <x-dl::table :columns="['k' => '5. Quick simulation', 'v' => ['Price', 'num' => true, 'raw' => true], 'd' => ['Change', 'num' => true, 'raw' => true]]" :rows="collect($rep['sim'])->map(fn ($s) => ['k' => raw($s[0]), 'v' => raw($idr($s[1])), 'd' => raw(($s[1] >= $rep['final'] ? '+' : '-').number_format(abs($s[1] - $rep['final']) / max(0.0001, $rep['final']) * 100, 1).'%')])->all()" :source="$src" />
                    <x-dl::hint>{{ tf('This calculation is recorded as learning material for the engine: :n boxes with their context (part type, qty).', ['n' => $rep['learned']]) }}</x-dl::hint>
                </x-dl::accordion>
            @endif
        </x-dl::accordion-group>
    </x-slot:result>
</x-dl::form>
@endif
@endif
