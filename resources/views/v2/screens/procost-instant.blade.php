{{--
 REMARK
 Tujuan       : Layar ProCost Mode Instant (kalkulator HPP standalone, tanpa Inquiry): identitas dokumen, customer dan produk, dimensi, parameter (qty, PPN, margin, toleransi, pembulatan), part dengan input per tipe part dan output per pcs / per order, coating. Setiap Save menghitung ulang; Confirm = ajukan ke Direksi; Reverse = buat Inquiry + ProDev draft dan jadikan Integrated.
 Sumber hulu  : App\Support\V2\Data\ProCostInstant::state (iqp_costing mode INSTANT + part + tier), ProCostFields::instant (field per tipe part), InstantCostingService (aturan tulis + CostingEngine).
 Input        : Query id (id costing), part (nomor part), tab (parts | info); tanpa id = daftar Pro-Cost Instant.
 Output       : x-dl::form (strip, tab General / Parts / Info), x-dl::table, x-dl::accordion; POST ke v2.procost-instant.save.
 Dipakai oleh : Route v2.screen slug procost-instant (menu Pro-Cost Instant, grid sales-procost-instant, rute lama inquiry.costing.instant); rute v2.procost-instant.new membuat draft baru.
 Status       : Aktif (T10 tahap 3 pindahan v1 pages::inquiry.costing-instant, 2026-10-21).
--}}
@php
    use App\Support\V2\Costing\ProCostFields;
    use App\Support\V2\Data\ProCostInstant;
    use App\Support\V2\Grid\Sales\CostingInstantGrid;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $costingId = request()->integer('id');
@endphp

@if (! $costingId)
    @include('v2.screens._dbgrid', ['screen' => ['grid' => new CostingInstantGrid]])
@else
@php
    $st = ProCostInstant::state($costingId, max(1, request()->integer('part', 1)));
    $c = $st['costing'];
    $t = $st['tier'];
    $p = $st['part'];
    $lk = $st['lookups'];
    $ro = $st['ro'];
    $lock = ! $st['canSee'];
    $qty = $st['qty'];
    \App\Support\V2\Crumb::set(raw($c->doc_no ?? 'Draft #'.$c->id_costing));
    $n4 = fn ($v) => $v === null ? '—' : Locale::number((float) $v, 4);
    $n2 = fn ($v) => $v === null ? '—' : Locale::number((float) $v, 2);
    $src = Src::of('tm2sdi.iqp_costing', 'tm2sdi.iqp_costing_part', 'tm2sdi.iqp_costing_price_tier', 'tm2sdi.iqp_costing_part_coating');
    $formStatus = match ($c->doc_status) { 'PENDING_APPROVAL' => 'approval', 'PUBLISHED' => 'approved', 'CANCELLED' => 'cancelled', default => 'draft' };
    $fields = $p ? ProCostFields::instant($p->part_type_code) : [];
    $opts = fn (?string $k) => $k === null ? [] : (array) ($lk[$k] ?? []);
    $toolPc = $p && $qty > 0 ? (float) $p->c_tooling_cost_order / $qty : 0;
    $here = route('v2.screen', 'procost-instant').'?id='.$costingId;
    $dcFamily = $p && in_array($p->part_type_code, ['DC', 'DC+SF', 'DC+SHEET'], true);
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@foreach ($errors->all() as $e)<x-dl::alert tone="danger" :message="raw($e)" />@endforeach

<x-dl::kpi-row>
    <x-dl::kpi label="Price per pcs" :value="$n4($t->c_after_round_pc)" icon="fa-tag" tone="accent" />
    <x-dl::kpi label="Total HPP" :value="$lock ? '🔒' : $n4($t->c_hpp_pc)" icon="fa-coins" tone="neutral" />
    <x-dl::kpi label="Total material cost" :value="$lock ? '🔒' : $n4($c->total_material_cost_pc)" icon="fa-boxes-stacked" tone="neutral" />
    <x-dl::kpi label="Total process cost" :value="$lock ? '🔒' : $n4((float) $c->total_process_cost_pc + (float) $c->total_finishing_cost_pc)" icon="fa-gears" tone="neutral" />
    <x-dl::kpi label="Total tooling cost" :value="$lock ? '🔒' : $n4($t->c_tooling_pc)" icon="fa-screwdriver-wrench" tone="neutral" />
    <x-dl::kpi label="Total after margin" :value="$n2($t->c_after_margin_pc)" icon="fa-chart-line" tone="success" />
    <x-dl::kpi label="Total after VAT" :value="$n2($t->c_after_ppn_pc)" icon="fa-check" tone="accent" />
</x-dl::kpi-row>

<x-dl::form id="procost-instant-form" :title="'Update HPP — Instant mode · '.raw($c->doc_no ?? 'Draft #'.$c->id_costing)" :subtitle="raw($c->doc_status)" panel="top" kind="document" :status="$formStatus" :pipeline="false"
    :action="route('v2.procost-instant.save', $costingId)" action-field="action" :back="route('v2.screen', 'sales-procost-instant')" :new-url="route('v2.procost-instant.new')"
    :hide="['print', 'email', 'duplicate', 'cancel']"
    :tabs="['general' => 'General', 'parts' => 'Parts details', 'info' => 'Info']"
    :created-by="$c->created_by" :created-at="$c->created_at?->toDateTimeString()" :updated-by="$c->updated_by" :updated-at="$c->updated_at?->toDateTimeString()">
    <x-slot:special>
        <x-dl::button size="sm" variant="secondary" icon="fa-calculator" label="Calculate HPP" type="submit" name="action" value="recalc" :disabled="$ro" formnovalidate />
        <x-dl::button size="sm" variant="danger" icon="cancel" label="Cancel document" type="submit" name="action" value="cancel" :disabled="in_array($c->doc_status, ['CANCELLED', 'PUBLISHED'], true)" formnovalidate onclick="return confirm('{{ t('Cancel this document?') }}')" />
        <x-dl::button size="sm" variant="secondary" icon="fa-right-left" label="Reverse" type="submit" name="action" value="reverse" :disabled="$ro" formnovalidate onclick="return confirm('{{ t('Reverse: create a NEW Inquiry Product + a DRAFT ProDev + a draft Master Product from this data; this document becomes Integrated. Continue?') }}')" />
    </x-slot:special>
    <x-slot:strip>
        <x-dl::strip-kv kind="doc" label="Doc No." :value="$c->doc_no ?? 'Draft #'.$c->id_costing" />
        <x-dl::field.date name="doc_date" label="Doc date" :value="$c->doc_date?->format('Y-m-d')" :readonly="$ro" :source="$src" />
        <x-dl::field.select name="calc_type" label="Calculation type" :options="['NEW' => 'New product', 'RECALC' => 'Recalculation', 'REVISE' => 'Product revision']" :value="$c->calc_type" :readonly="$ro" :source="$src" />
        <x-dl::strip-kv kind="grand" label="Price per pcs" :value="$n4($t->c_after_round_pc)" />
        <input type="hidden" name="part_no" value="{{ $st['partNo'] }}"><input type="hidden" name="part_id" value="{{ $p?->id_costing_part ?? 0 }}">
    </x-slot:strip>

    <x-slot:general>
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="customer_id" label="Customer" :options="$lk['customers']" :value="$c->customer_id" :readonly="$ro" :source="Src::of('tm2mst.cust_customer')" />
            <x-dl::field.text name="customer_name_draft" label="Prospect / lead name" :value="$c->customer_name_draft" :readonly="$ro" :case-toggle="false" :source="$src" />
            <x-dl::field.text name="product_name_draft" label="Product name" :value="$c->product_name_draft" :readonly="$ro" :case-toggle="false" :source="$src" />
        </x-dl::form.row>
        <x-dl::divider label="Dimensions" />
        <x-dl::form.row :cols="4">
            <x-dl::field.decimal name="i_dim_panjang_mm" label="Length (mm)" :value="$c->i_dim_panjang_mm !== null ? (float) $c->i_dim_panjang_mm : null" :readonly="$ro" :source="$src" />
            <x-dl::field.decimal name="i_dim_lebar_mm" label="Width (mm)" :value="$c->i_dim_lebar_mm !== null ? (float) $c->i_dim_lebar_mm : null" :readonly="$ro" :source="$src" />
            <x-dl::field.decimal name="i_dim_tinggi_mm" label="Height (mm)" :value="$c->i_dim_tinggi_mm !== null ? (float) $c->i_dim_tinggi_mm : null" :readonly="$ro" :source="$src" />
            <x-dl::field.decimal name="i_lidah_lem_mm" label="Glue flap (mm)" :value="$c->i_lidah_lem_mm !== null ? (float) $c->i_lidah_lem_mm : null" :readonly="$ro" :source="$src" />
        </x-dl::form.row>
        <x-dl::divider label="Parameters" />
        <x-dl::form.row :cols="3">
            <x-dl::field.decimal name="tier[qty_pcs]" label="Order qty (pcs)" required :value="(float) $t->qty_pcs" :readonly="$ro" :source="$src" />
            <x-dl::field.select name="ppn_mode" label="VAT" :options="['PPN' => 'Include VAT', 'NON_PPN' => 'Exclude VAT']" :value="$c->ppn_mode === 'NON_PPN' ? 'NON_PPN' : 'PPN'" :readonly="$ro" :source="$src" />
            <x-dl::field.decimal name="ppn_pct" label="VAT percent" :value="(float) $c->ppn_pct" :readonly="$ro" :source="$src" />
            <x-dl::field.decimal name="tier[margin_pct]" label="Margin percent" required :value="(float) $t->margin_pct" :readonly="$ro" :source="$src" />
            <x-dl::field.decimal name="tier[tolerance_pct]" label="Tolerance percent" required :value="(float) $t->tolerance_pct" :readonly="$ro" :source="$src" />
            <x-dl::field.select name="tier[rounding_mode]" label="Rounding mode" :options="ProCostFields::ROUNDING" :value="$t->rounding_mode" :readonly="$ro" :source="$src" />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="currency_code" label="Currency" :options="['IDR' => 'IDR', 'USD' => 'USD', 'EUR' => 'EUR']" :value="$c->currency_code" :readonly="$ro" hint="No exchange-rate conversion yet — all master rates are IDR" :source="$src" />
            <x-dl::field.text name="calc_notes" label="Notes" multiline :value="$c->calc_notes" :readonly="$ro" :case-toggle="false" :source="$src" />
        </x-dl::form.row>
    </x-slot:general>

    <x-slot:parts>
        <x-dl::action-bar align="start">
            @for ($i = 1; $i <= $st['partCount']; $i++)
                <x-dl::button size="sm" :variant="$i === $st['partNo'] ? 'primary' : 'secondary'" :label="'Part '.$i" :href="$here.'&part='.$i" />
            @endfor
            @if (! $ro)
                <x-dl::button size="sm" variant="secondary" icon="plus" label="+ Part" type="submit" name="action" value="add_part" formnovalidate />
                @if ($p)<x-dl::button size="sm" variant="danger" icon="fa-trash" label="Delete this part" type="submit" name="action" value="remove_part" formnovalidate onclick="return confirm('{{ t('Delete this part?') }}')" />@endif
            @endif
            <x-dl::hint>{{ $st['partCount'] ? tf('Part :n of :t', ['n' => $st['partNo'], 't' => $st['partCount']]) : t('No part yet. Click + Part to add one.') }}</x-dl::hint>
        </x-dl::action-bar>
        @if ($p)
            <x-dl::field.display label="Part qty" :value="Locale::number($qty * max(1, (float) $p->qty_per_set), 2)" :source="$src" />
            @foreach (ProCostFields::groups() as $g => $title)
                @php $gf = array_values(array_filter($fields, fn ($f) => $f['group'] === $g)); @endphp
                @if ($gf)
                    <x-dl::divider :label="$title" />
                    <x-dl::form.row :cols="4">
                        @foreach ($gf as $f)
                            @php $name = 'part['.$p->id_costing_part.']['.$f['key'].']'; $val = $p->{$f['key']}; @endphp
                            @if ($f['type'] === 's')
                                <x-dl::field.select :name="$name" :label="$f['label']" :options="$opts($f['opt'])" :value="$f['key'] === 'i_film_rate' ? ($val !== null ? (string) (float) $val : '') : $val" :readonly="$ro" :source="$src" />
                            @elseif ($f['type'] === 'b')
                                <x-dl::field.select :name="$name" :label="$f['label']" :options="ProCostFields::YES_NO" :value="$val ? '1' : '0'" :readonly="$ro" :source="$src" />
                            @elseif ($f['type'] === 't')
                                <x-dl::field.text :name="$name" :label="$f['label']" :value="$val" :readonly="$ro" :case-toggle="false" :source="$src" />
                            @else
                                <x-dl::field.decimal :name="$name" :label="$f['label']" :value="$val !== null ? (float) $val : null" :readonly="$ro" :source="$src" />
                            @endif
                        @endforeach
                    </x-dl::form.row>
                @endif
            @endforeach
            <x-dl::field.text :name="'part['.$p->id_costing_part.'][calc_notes]'" label="Calculation notes" multiline :value="$p->calc_notes" :readonly="$ro" :case-toggle="false" :source="$src" />

            <x-dl::divider label="Output" />
            <x-dl::form.row :cols="3">
                <x-dl::field.display label="Cost price (HPP) / pc" :value="$lock ? '🔒' : $n4((float) $p->c_total_part_cost_pc + $toolPc)" :source="$src" />
                <x-dl::field.display label="Print quantity (LC)" :value="$n4($p->c_jumlah_cetak_lc)" :source="$src" />
                <x-dl::field.display label="Calculation description" :value="$lock ? '🔒 only designated logins' : ($p->description_calc ?: '—')" :source="$src" />
            </x-dl::form.row>
            @if ($lock)
                <x-dl::alert tone="info" message="The calculation detail (breakdown per pc / order) is only for the designated logins (constant PROCOST_FORMULA_LOGINS)." />
            @else
                <x-dl::table :columns="['label' => 'Detailed breakdown per pc', 'val' => ['Value', 'num' => true, 'raw' => true]]" :rows="collect($st['cells'])->map(fn ($c2) => ['label' => raw($c2[0]), 'val' => raw($n4($c2[1]))])->all()" :source="$src" />
                <x-dl::hint>{{ t('Supplier price info') }}: {{ $p->c_sheet_compare_info ?: '—' }}</x-dl::hint>
                <x-dl::accordion key="per-order" icon="fa-receipt" :title="'Detail per order (× qty '.Locale::number($st['breakdown']['qty'], 0).')'">
                    <x-dl::table :columns="['label' => 'Item / order', 'val' => ['Value', 'num' => true, 'raw' => true]]" :rows="collect($st['breakdown']['pc'])->map(fn ($v, $k) => ['label' => raw($k), 'val' => raw(Locale::number($v * $st['breakdown']['qty'], 2))])->values()->all()" :source="$src" />
                </x-dl::accordion>
            @endif

            @if ($dcFamily)
                <x-dl::divider label="Coating" />
                @forelse ($p->coatings as $co)
                    <x-dl::form.row :cols="4">
                        <x-dl::field.select :name="'coating['.$p->id_costing_part.']['.$co->id_coating.'][coating_type]'" label="Coating type" :options="$lk['coatings']" :value="$co->coating_type" :readonly="$ro" :source="$src" />
                        <x-dl::field.decimal :name="'coating['.$p->id_costing_part.']['.$co->id_coating.'][index_coating]'" label="Index coating" :value="(float) $co->index_coating" :readonly="$ro" :source="$src" />
                        <x-dl::field.text :name="'coating['.$p->id_costing_part.']['.$co->id_coating.'][notes]'" label="Coating notes" :value="$co->notes_coating" :readonly="$ro" :case-toggle="false" :source="$src" />
                        <x-dl::field.display label="Cost per print sheet / per pcs" :value="$lock ? '🔒' : Locale::number((float) $co->c_cost_per_lc, 6).' / '.$n4($co->c_cost_per_pc)" :source="$src" />
                        @if (! $ro)<x-dl::button size="sm" variant="danger" icon="fa-xmark" label="Remove" type="submit" name="action" :value="'remove_coating_'.$co->id_coating" formnovalidate />@endif
                    </x-dl::form.row>
                @empty
                    <x-dl::hint>@t('No records found')</x-dl::hint>
                @endforelse
                @if (! $ro)<x-dl::button size="sm" variant="secondary" icon="plus" label="+ Coating" type="submit" name="action" value="add_coating" formnovalidate />@endif
            @endif
        @endif
    </x-slot:parts>

    <x-slot:info>
        <x-dl::form.row :cols="4">
            <x-dl::field.display label="HPP ID" :value="(string) $c->id_costing" :source="$src" />
            <x-dl::field.display label="Create log" :value="(string) $c->created_at" :source="$src" />
            <x-dl::field.display label="Created by" :value="$c->created_by ?: '—'" :source="$src" />
            <x-dl::field.display label="Update log" :value="(string) $c->updated_at" :source="$src" />
            <x-dl::field.display label="Updated by" :value="$c->updated_by ?: '—'" :source="$src" />
            <x-dl::field.display label="Mode" :value="$c->mode" :source="$src" />
            <x-dl::field.display label="Reversed at" :value="(string) ($c->reversed_at ?: '—')" :source="$src" />
            <x-dl::field.display label="Reversed by" :value="$c->reversed_by ?: '—'" :source="$src" />
        </x-dl::form.row>
    </x-slot:info>
</x-dl::form>
@endif
