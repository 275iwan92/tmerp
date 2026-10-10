{{-- Colour Sequence v2 (PPIC): the print process as the press sees it. Per press machine the jobs in planned order with their inks, and the wash before each job.
     Inks come from Product Development; jobs are in the Draft when there is one, else in the confirmed schedule. The order is changed on the PPIC Workspace
     (drag on the Gantt, or die grouping): this screen shows the result and what it costs. Data = App\Support\V2\Data\Ppic::colorSequence(). --}}
@php
    use App\Support\V2\Data\Ppic;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $seq = Ppic::colorSequence();
    $draft = Ppic::draftRun();
    $src = Src::of('tm2prd.prd_wo_operation', 'tm2mst.prod_part_ink', 'tm2mst.prod_machine_changeover');
@endphp

<x-dl::card title="Colour Sequence" :description="$draft ? 'Order of the Draft plan, not yet the schedule in force. Confirm it on the PPIC Workspace.' : 'Order of the confirmed schedule.'">
    <x-dl::action-bar>
        <x-dl::button variant="secondary" icon="back" label="Back" :href="route('v2.screen', 'production-scheduling')" />
        <x-dl::button variant="secondary" icon="fa-sliders" label="Setup and Changeover" :href="route('v2.screen', 'prod-changeover')" />
    </x-dl::action-bar>
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
    </x-dl::card>
@endforeach
