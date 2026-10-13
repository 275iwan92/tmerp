{{--
 REMARK
 Tujuan       : Kalender bulanan Preventive Maintenance Order (Draft dan Confirmed) per aset, hanya baca, dengan daftar order berikutnya dan tautan Open ke PMO.
 Sumber hulu  : Maintenance::calendarEvents dan Maintenance::assetOptions; model MntOrder (tm2mnt.mnt_order, jenis PMO, belum cancel / close).
 Input        : Tanpa parameter; membaca 15 order terdekat yang punya planned_start.
 Output       : x-dl::calendar tampilan bulan dan x-dl::table; tidak ada posting (jadwal diubah di form PMO).
 Dipakai oleh : Route v2.screen slug maintenance-pm-calendar (App\Support\V2\Screens, menu sys_app_menu target v2:maintenance-pm-calendar) di menu Maintenance
 Status       : Aktif (mockup maintenance_v2_flow.jpg bagian 4)
--}}
@php
    use App\Models\Mnt\MntOrder;
    use App\Support\V2\Data\Maintenance;

    $events = Maintenance::calendarEvents();
    $upcoming = MntOrder::whereIn('order_type', MntOrder::typesOf('PMO'))->where('stat_cancel', false)->where('stat_close', false)->whereNotNull('planned_start')->orderBy('planned_start')->limit(15)->get();
    $assets = Maintenance::assetOptions();
    $rows = $upcoming->map(fn ($o) => ['order' => $o->doc_no ?: t('Draft'), 'asset' => raw($assets[$o->asset_id] ?? '#'.$o->asset_id), 'planned' => Maintenance::stamp($o->planned_start),
        'status' => $o->statusLabel(), 'open' => route('v2.screen', 'maintenance-pmo').'?id='.$o->id_order])->all();
@endphp

<x-dl::calendar :events="$events" display="month" :sidebar="false" source="tm2mnt.mnt_order (PMO)" />

<x-dl::card title="Next orders" description="Open Preventive Maintenance Orders in date order. Click Open to change the schedule.">
    <x-dl::table :columns="['order' => 'Order', 'asset' => 'Asset', 'planned' => 'Planned', 'status' => 'Status']" :rows="$rows" />
</x-dl::card>
