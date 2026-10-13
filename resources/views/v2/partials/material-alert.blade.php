{{--
 REMARK
 Tujuan       : Menampilkan peringatan kekurangan bahan di layar pembuka: kartu ringkas dan popup sekali per login atau saat jumlah berubah.
 Sumber hulu  : App\Support\V2\Data\MaterialPlanning::alerts() (tm2inv.rm_booking_d, v_rm_available, tm2pur.po_line, pr_line).
 Input        : Sesi material_alert_seen; tanpa prop.
 Output       : x-dl::alert, x-dl::modal, x-dl::table, x-dl::button menuju route v2.screen material-planning.
 Dipakai oleh : @include dari v2.home (dua tempat: slot dashboard dan tanpa papan).
 Status       : Aktif.
--}}
@php
    use App\Support\V2\Data\MaterialPlanning;
    use Devlib\Locale;

    $mpAlert = MaterialPlanning::alerts();
    $mpCount = (int) ($mpAlert['count'] ?? 0);
    $mpPopup = $mpCount > 0 && session('material_alert_seen') !== $mpCount;
    if ($mpPopup) {
        session(['material_alert_seen' => $mpCount]);
    }
    $mpUrl = route('v2.screen', 'material-planning');
    $mpText = $mpCount ? str_replace([':n', ':s', ':i', ':l'], [$mpCount, $mpAlert['short'], $mpAlert['need_item'], $mpAlert['late']],
        t('Material not enough: :n materials need attention (:s short, :i without item / layout, :l arriving late).')) : '';
@endphp
@if ($mpCount > 0)
    <x-dl::alert tone="danger" :message="raw($mpText)">
        <x-dl::button variant="secondary" size="sm" icon="fa-chart-gantt" :label="t('Open Material Planning')" :href="$mpUrl" />
    </x-dl::alert>
    <x-dl::modal id="material-alert" :title="t('Material Shortage')" icon="fa-triangle-exclamation" tone="danger" size="lg" :open="$mpPopup">
        <x-dl::box as="p"><x-dl::box as="b">{{ $mpText }}</x-dl::box></x-dl::box>
        <x-dl::table :columns="['material' => 'Material', 'short' => 'Short', 'first_need' => 'First Needed', 'order_by' => 'Order By (latest)', 'orders' => 'For Orders', 'status' => 'Coverage']"
            :rows="collect($mpAlert['top'])->map(fn ($r) => ['material' => $r['material'], 'short' => Locale::number((float) $r['short'], 2).' '.$r['uom'],
                'first_need' => Locale::d($r['first_need']) ?? '—', 'order_by' => Locale::d($r['order_by']) ?? '—', 'orders' => $r['orders'], 'status' => t($r['status'])])->all()"
            :source="\App\Support\V2\Src::of('tm2inv.rm_booking_d', 'tm2inv.v_rm_available', 'tm2pur.po_line', 'tm2pur.pr_line')" />
        <x-slot:footer>
            <x-dl::button preset="close" data-dl-close-modal />
            <x-dl::button icon="fa-chart-gantt" :label="t('Open Material Planning')" :href="$mpUrl" />
        </x-slot:footer>
    </x-dl::modal>
@endif
