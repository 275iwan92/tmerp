{{-- Form khusus Material Planning (MRP) dan Material Booking. Layar menyerahkan satu array dan grid-nya lewat slot.
     Di aplikasi: <x-mp::planning.material-plan :plan="MaterialPlanning::planDesk($home, $rows, $m)"> grid </x-mp::planning.material-plan>
                  <x-mp::planning.material-booking :booking="MaterialPlanning::bookingDesk($home, $co)"> grid </x-mp::planning.material-booking> --}}
<div class="dl-demo">
    <x-mp::planning.material-plan :plan="[
        'mode' => 'list', 'home' => '#', 'runAction' => '#',
        'links' => [['fa-boxes-packing', 'Material Booking', '#'], ['fa-calendar-week', 'Demand Forecast (MPS)', '#'], ['fa-fill-drip', 'Material Usage Standard', '#'], ['fa-truck-clock', 'Purchase Lead Time', '#']],
        'warning' => '2 material needs have no stock item yet (Master Product only has the specification) or no layout.',
        'pr' => ['action' => '#', 'picked' => [101], 'options' => [101 => 'Art carton 210gr 65 x 100 · short 1.250,00 SHEET · order by 09-Oct-2026 · PT Kertas Jaya', 204 => 'Ink black · short 12,00 KG · order by 12-Oct-2026 · CV Tinta Abadi']],
    ]">
        <x-dl::empty-state title="The material grid of the screen is placed here (slot)" icon="fa-table" />
    </x-mp::planning.material-plan>

    <x-mp::planning.material-booking :booking="[
        'mode' => 'detail', 'home' => '#', 'action' => '#', 'coNo' => 'CO2600029', 'coStatus' => 'CONFIRMED', 'coTone' => 'success',
        'booking' => 'BK2600007 · DRAFT', 'bookingTone' => 'warn', 'orderUrl' => '#', 'printUrl' => '#',
        'canRun' => true, 'canConfirm' => true, 'canUnlock' => false,
        'facts' => [['Customer', 'PT Contoh Kosmetika', []], ['CO Date', '01-Oct-2026', []], ['Last Calculated', '01-Oct-2026 14:20', []], ['Confirmed By', '—', []]],
        'alert' => ['danger', '2 material lines are not covered yet (short, item not chosen, or layout missing).'],
        'pick' => ['action' => '#', 'lines' => [11 => '2 · COATING · UV gloss · 3,50 KG']], 'needRun' => false,
    ]">
        <x-dl::empty-state title="The material lines grid of the screen is placed here (slot)" icon="fa-table" />
    </x-mp::planning.material-booking>
</div>
