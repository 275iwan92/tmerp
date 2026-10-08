{{-- Learning Changes v2 (PPIC): the changeover values the learning engine adjusted from real setup times, with the log and a revert (which freezes the value).
     Rules and limits: App\Services\Scheduling\Learning\LearningEngine, Settings > Constants PPIC_LEARN_*. Values are edited by users on Setup and Changeover. --}}
@php
    use App\Models\Master\ProdMachine;
    use App\Models\Master\ProdMachineChangeover;
    use App\Models\Prd\PrdPlanDecision;
    use App\Support\V2\Grid\Prod\ChangeoverLookup;
    use App\Support\V2\Src;
    use Devlib\Locale;

    $src = Src::of('tm2prd.prd_plan_decision', 'tm2mst.prod_machine_changeover');
    $log = PrdPlanDecision::query()->whereIn('event', ['LEARN', 'LEARN_REVERT'])->orderByDesc('decision_id')->limit(60)->get();
    $reverted = $log->where('event', 'LEARN_REVERT')->map(fn ($d) => (int) preg_replace('/.*decision#(\d+).*/', '$1', $d->old_value))->all();
    $open = $log->where('event', 'LEARN')->reject(fn ($d) => in_array($d->decision_id, $reverted, true));
    $machines = ProdMachine::query()->pluck('machine_code', 'machine_id');
    $values = ProdMachineChangeover::query()->where('activate', 1)->orderBy('machine_id')->orderBy('change_kind')->get();
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::card title="Learning Changes" description="The engine corrects Assumed changeover minutes from the setup times operators really needed. It needs a minimum number of finished jobs, moves a value by small steps, and never touches a frozen value. Every change is below and can be put back.">
    <x-dl::action-bar>
        <x-dl::action-form action="{{ route('v2.ppic.learn.run') }}"><x-dl::button icon="fa-graduation-cap" label="Learn now" type="submit" /></x-dl::action-form>
        <x-dl::button variant="secondary" icon="fa-table" label="Setup and Changeover" :href="route('v2.screen', 'prod-changeover')" />
    </x-dl::action-bar>
    <x-dl::action-form action="{{ route('v2.ppic.learn.revert') }}">
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="decision" label="Put a change back" required placeholder="Choose change..." :source="$src"
                :options="$open->mapWithKeys(fn ($d) => [$d->decision_id => Locale::dt($d->created_at).' · '.$d->new_value])->all()" />
            <x-dl::button variant="secondary" icon="fa-rotate-left" label="Put back and freeze" type="submit" />
        </x-dl::form.row>
    </x-dl::action-form>
</x-dl::card>

<x-dl::card title="Changeover values" description="Status Assumed means a start value; Learning and Calibrated were adjusted from real jobs; Frozen values are left alone.">
    <x-dl::table :source="$src" empty="No changeover values yet. They appear here after the first learning run or when typed on Setup and Changeover."
        :columns="['machine' => 'Machine', 'kind' => 'Change', 'minutes' => 'Extra Minutes', 'status' => 'Value Status', 'samples' => 'Samples', 'frozen' => 'Frozen']"
        :rows="$values->map(fn ($v) => ['machine' => raw($machines[$v->machine_id] ?? '#'.$v->machine_id), 'kind' => raw(t(ChangeoverLookup::KINDS[$v->change_kind] ?? $v->change_kind)), 'minutes' => $v->extra_minutes,
            'status' => raw(t(ChangeoverLookup::STATUSES[$v->value_status ?: 'ASSUMED'] ?? 'Assumed')), 'samples' => (int) $v->sample_count, 'frozen' => $v->frozen ? t('Yes') : t('No')])->all()" />
</x-dl::card>

<x-dl::card title="Log" description="Who changed what, from which value to which, and why.">
    <x-dl::table :source="$src" empty="The engine has not changed anything yet."
        :columns="['at' => 'When', 'actor' => 'By', 'event' => 'Event', 'old' => 'Old', 'new' => 'New', 'why' => 'Reason']"
        :rows="$log->map(fn ($d) => ['at' => raw(Locale::dt($d->created_at)), 'actor' => raw($d->actor), 'event' => raw($d->event === 'LEARN' ? t('Adjusted') : t('Put back')), 'old' => raw($d->old_value), 'new' => raw($d->new_value), 'why' => raw($d->reason)])->all()" />
</x-dl::card>
