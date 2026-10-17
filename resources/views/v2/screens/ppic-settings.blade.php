{{--
 REMARK
 Tujuan       : Satu pintu semua pengaturan PPIC dalam delapan kategori dengan kotak cari; tiap baris menyatakan apa yang ada, perlu dirapikan atau belum dibangun.
 Sumber hulu  : Daftar kategori ditulis di view ini dari audit kode 10 Okt 2026; tiap baris menunjuk layar master atau lookup yang sudah ada.
 Input        : Query q (kata kunci pencarian).
 Output       : Kartu per kategori berisi tautan ke layar slug (prod-machine, prod-process, md-shift, dan seterusnya) beserta status ADA, RAPIKAN atau BELUM.
 Dipakai oleh : Layar v2 slug ppic-settings lewat rute v2.screen (view v2.screen memuat berkas ini)
 Status       : Aktif
--}}
@php
    use App\Support\V2\Src;

    $q = trim((string) request('q', ''));
    // category => [[label, slug or null, status, note]]; status: ADA exists | RAPIKAN exists but needs tidying | BELUM not built
    $cats = [
        'Machines and capacity' => [['Master Machine', 'prod-machine', 'ADA', 'Hours per shift and shifts per day are in the machine form'], ['Machine Model Catalog', 'prod-machine-model', 'ADA', '']],
        'Processes and routing' => [['Master Process', 'prod-process', 'ADA', 'Subcontract flag (inhouse) is read; the bottleneck flag is not read by the planner yet'], ['Routing Template', 'prod-routing', 'ADA', ''], ['BOP Rule Setting', 'prod-bop-rule', 'ADA', '']],
        'Tooling and setup' => [['Tooling Master', 'prod-tooling', 'ADA', 'Status gates the job; first and repeat mounting minutes'], ['Setup and Changeover', 'prod-changeover', 'ADA', 'Wash and change minutes per machine; the learning engine corrects Assumed values']],
        'Calendar' => [['Master Shift', 'md-shift', 'ADA', ''], ['Holiday Calendar', 'holiday-calendar', 'ADA', ''], ['Office Hours', 'office-hours', 'ADA', ''], ['Shift Schedule', 'hrm-roster', 'ADA', ''],
            ['Preventive maintenance windows in the plan', null, 'BELUM', 'Only the present machine status is read, not the PM calendar']],
        'Rules and priority' => [['Wait Rules', 'prod-wait-rule', 'ADA', 'Drying and curing minutes after a process'], ['Die grouping', 'production-scheduling', 'ADA', 'On the PPIC Workspace: take a Work Order out of its die group, with a reason']],
        'Engine parameters' => [['Planning Parameters', 'setup-constants', 'ADA', 'PPIC_* constants: lock shifts, score weights, windows, grouping tolerance, wash factor, learning limits']],
        'People' => [['Operator skills per process', null, 'BELUM', 'No screen and no data model yet']],
        'Materials' => [['Material Planning', 'material-planning', 'ADA', ''], ['Material Booking', 'material-booking', 'ADA', ''], ['Material Usage Standard', 'usage-standard', 'ADA', ''], ['Purchase Lead Time', 'purchase-lead-time', 'ADA', ''],
            ['Automatic booking confirmation', null, 'BELUM', 'Booking is not confirmed automatically when stock covers it']],
    ];
    $tone = ['ADA' => 'success', 'RAPIKAN' => 'warn', 'BELUM' => 'danger'];
    $label = ['ADA' => t('Exists'), 'RAPIKAN' => t('Needs tidying'), 'BELUM' => t('Not built')];
    $home = route('v2.screen', 'ppic-settings');
@endphp

<x-dl::card title="PPIC Settings" description="Everything that is changed now and then, in eight categories. The daily work is on the PPIC Workspace.">
    <x-dl::action-form method="get" action="{{ $home }}">
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="q" label="Search" :value="$q" :case-toggle="false" hint="Part of a screen name, a category or a note." :source="Src::of('tm2sys.sys_app_menu')" />
            <x-dl::button variant="secondary" icon="fa-magnifying-glass" label="Search" type="submit" />
        </x-dl::form.row>
    </x-dl::action-form>
</x-dl::card>

@foreach ($cats as $cat => $items)
    @php
        $rows = array_values(array_filter($items, fn ($i) => $q === '' || stripos($i[0].' '.t($cat).' '.$cat.' '.$i[3], $q) !== false));
    @endphp
    @if ($rows)
        <x-dl::card :title="$cat">
            @foreach ($rows as [$name, $slug, $status, $note])
                <x-dl::form.row :cols="3">
                    @if ($slug)
                        <x-dl::button variant="secondary" icon="fa-arrow-right" :label="$name" :href="route('v2.screen', $slug)" />
                    @else
                        <x-dl::field.display label="Screen" :value="raw(t($name))" :source="Src::of('tm2sys.sys_app_menu')" />
                    @endif
                    <x-dl::badge :label="$label[$status]" :tone="$tone[$status]" />
                    <x-dl::field.display label="Note" :value="raw($note !== '' ? t($note) : '—')" :source="Src::of('tm2sys.sys_app_menu')" />
                </x-dl::form.row>
            @endforeach
        </x-dl::card>
    @endif
@endforeach
