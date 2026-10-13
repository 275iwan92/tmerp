{{--
 REMARK
 Tujuan       : Urutan proses cetak seperti dilihat mesin: per mesin cetak, job berurutan beserta tinta dan pencucian sebelum tiap job; urutan diubah di PPIC Workspace.
 Sumber hulu  : App\Support\V2\Data\Ppic::colorSequence dan draftRun; prd_wo_operation, prod_part_ink (tinta dari Product Development), prod_machine_changeover, prd_plan_decision.
 Input        : Tanpa parameter; hak jadwal dari PpicAccess::canSchedule; session v2_flash.
 Output       : x-dl::card per mesin dengan urutan job, tinta dan menit cuci; tombol Back ke production-scheduling dan Setup and Changeover (prod-changeover).
 Dipakai oleh : Layar v2 slug ppic-color-sequence lewat rute v2.screen (view v2.screen memuat berkas ini)
 Status       : Aktif
--}}
@php
    use App\Support\V2\Data\Ppic;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $seq = Ppic::colorSequence();
    $draft = Ppic::draftRun();
    $src = Src::of('tm2prd.prd_wo_operation', 'tm2mst.prod_part_ink', 'tm2mst.prod_machine_changeover', 'tm2prd.prd_plan_decision');
    $isOfficer = \App\Services\Scheduling\Plan\PpicAccess::canSchedule(auth()->user());
@endphp

<x-dl::card title="Colour Sequence" :description="$draft ? 'Order of the Draft plan, not yet the schedule in force. Confirm it on the PPIC Workspace.' : 'Order of the confirmed schedule.'">
    <x-dl::action-bar>
        <x-dl::button variant="secondary" icon="back" label="Back" :href="route('v2.screen', 'production-scheduling')" />
        <x-dl::button variant="secondary" icon="fa-sliders" label="Setup and Changeover" :href="route('v2.screen', 'prod-changeover')" />
    </x-dl::action-bar>
    @if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
    @if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif
    @if (! $seq)
        <x-dl::alert tone="info" message="No print operation is planned yet." />
    @endif
</x-dl::card>

@foreach ($seq as $m)
    <x-dl::card :title="raw($m['machine'])" :description="raw(tf(':n jobs · :w min of colour wash in this order', ['n' => count($m['jobs']), 'w' => $m['wash_minutes']]))">
        <x-dl::table :source="$src" empty="No jobs."
            :columns="['n' => '#', 'start' => 'Start', 'wo' => 'Work Order', 'product' => 'Product', 'inks' => 'Inks (unit order)', 'wash' => 'Wash before (min)', 'why' => 'Why', 'plan' => 'Plan']"
            :rows="array_map(fn ($j) => ['n' => $j['n'], 'start' => raw(Locale::dt($j['start'])), 'wo' => raw($j['wo']), 'product' => raw($j['product'] ?? '—'),
                'inks' => raw($j['known'] ? implode(' · ', $j['inks']) : ($j['colours'] ? $j['colours'].' '.t('colours (inks not in Product Development)') : '—')),
                'wash' => $j['n'] === 1 || $j['unknown'] ? '—' : $j['wash'], 'why' => raw($j['wash_label']), 'plan' => raw($j['plan'] === 'DRAFT' ? t('Draft') : t('Confirmed'))], $m['jobs'])" />
        @if (count($m['jobs']) > 1)
            {{-- Option C: the engine proposes the order (light to dark); the officer overrides it, the operator asks. A reason is always required. --}}
            <x-dl::action-form action="{{ route($isOfficer ? 'v2.ppic.color.move' : 'v2.ppic.color.request') }}">
                <x-dl::form.row :cols="4">
                    <x-dl::field.select name="op" label="Job" required :options="collect($m['jobs'])->mapWithKeys(fn ($j) => [$j['op_id'] => $j['n'].' · '.$j['wo']])->all()" placeholder="Choose job..." :source="$src" />
                    <x-dl::field.select name="before" label="Run before" required :options="collect($m['jobs'])->mapWithKeys(fn ($j) => [$j['op_id'] => $j['n'].' · '.$j['wo']])->all()" placeholder="Choose job..." :source="$src" />
                    <x-dl::field.text name="reason" label="Reason" required hint="Required. Written to the decision log with your name." :source="Src::of('tm2prd.prd_plan_decision.reason')" />
                    <x-dl::button icon="fa-arrows-up-down" :label="$isOfficer ? 'Change order' : 'Ask the officer'" type="submit" />
                </x-dl::form.row>
            </x-dl::action-form>
        @endif
        {{-- The first job of the press: its dieline and artwork are the reference for the people at the machine. --}}
        @include('v2.screens._job-visual', ['woId' => $m['jobs'][0]['wo_id'], 'height' => '170px'])
    </x-dl::card>
@endforeach
