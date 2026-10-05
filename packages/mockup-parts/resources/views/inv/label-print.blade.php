{{-- Tampilan objek x-mp::inv.label-print (MockupParts\Components\Inv\LabelPrint). Label ditulis English (diterjemahkan objek
     DevLib); pesan notice sudah diterjemahkan pemilik data -> Lang::raw. --}}
@php
    use Devlib\Lang;
    $f = $form;
    $p = $f['print'];
@endphp
<div {{ $attributes->class('mp-flat') }} {!! $dl() !!}>
    @if ($f['empty'])
        <x-dl::empty-state :title="$f['empty'][0]" :message="$f['empty'][1]" icon="fa-warehouse" />
    @else
        <x-dl::card :title="$f['title']" :icon="$f['icon']" :description="$f['description']">
            @if ($n = $f['notice'])
                <x-dl::alert tone="info" :message="Lang::raw($n['message'])" />
                <x-dl::action-bar align="start">
                    @foreach ($n['buttons'] as [$icon, $label, $url])
                        <x-dl::button variant="secondary" :icon="$icon" :label="$label" :href="$url" />
                    @endforeach
                </x-dl::action-bar>
            @endif
            @if ($q = $f['filter'])
                <x-dl::action-form method="get" action="{{ $q['action'] }}">
                    <x-dl::form.row :cols="3">
                        @foreach ($q['fields'] as [$name, $label, $options, $value, $sources])
                            <x-dl::field.select :name="$name" :label="$label" :options="$options" :value="$value" :source="$src(...$sources)" />
                        @endforeach
                        <x-dl::action-bar align="start"><x-dl::button variant="secondary" icon="fa-rotate" label="Show" type="submit" /></x-dl::action-bar>
                    </x-dl::form.row>
                </x-dl::action-form>
            @endif
            @if ($f['nothing'])
                <x-dl::alert tone="info" :message="$f['nothing']" />
            @elseif ($p)
                <x-dl::action-form action="{{ $p['action'] }}">
                    @foreach ($p['hidden'] as $hk => $hv)<input type="hidden" name="{{ $hk }}" value="{{ $hv }}">@endforeach
                    @if ($stacked())
                        <x-dl::form.row :cols="2">
                            <x-dl::field.multi-select name="keys" :label="$p['keysLabel']" required :options="$p['options']" :value="$p['picked']" empty-text="Choose (0 selected)" noun="selected" :source="$src(...$p['sources'])" />
                            <x-dl::field.number name="copies" label="Copies per label" :value="1" :hint="$p['copiesHint']" />
                        </x-dl::form.row>
                        <x-dl::form.row :cols="2">
                            <x-dl::field.select name="size" label="Label size" required :options="$p['sizes']" value="md" />
                            <x-dl::field.select name="paper" label="Print On" required :options="$p['papers']" value="a4" />
                        </x-dl::form.row>
                    @else
                        <x-dl::form.row :cols="4">
                            <x-dl::field.multi-select name="keys" :label="$p['keysLabel']" required :options="$p['options']" :value="$p['picked']" empty-text="Choose (0 selected)" noun="selected" :source="$src(...$p['sources'])" />
                            <x-dl::field.select name="size" label="Label size" required :options="$p['sizes']" value="md" />
                            <x-dl::field.select name="paper" label="Print On" required :options="$p['papers']" value="a4" />
                            <x-dl::field.number name="copies" label="Copies per label" :value="1" :hint="$p['copiesHint']" />
                        </x-dl::form.row>
                    @endif
                    <x-dl::action-bar>
                        <x-dl::button variant="secondary" icon="fa-clock-rotate-left" label="Print History" :href="$p['historyUrl']" />
                        <x-dl::button icon="print" label="Print" type="submit" />
                    </x-dl::action-bar>
                </x-dl::action-form>
            @endif
            <x-dl::label-guide size="md" :colors="$f['legend']" />
        </x-dl::card>
    @endif
</div>
