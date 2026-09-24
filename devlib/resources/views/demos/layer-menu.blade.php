@php
    $st = fn (string $wait) => [
        ['label' => 'Draft', 'icon' => 'fa-pencil'], ['label' => 'Waiting '.$wait, 'icon' => 'fa-hourglass-half'],
        ['label' => 'Approved', 'icon' => 'fa-circle-check'], ['label' => 'Cancelled', 'icon' => 'fa-circle-xmark'],
    ];
    $leafs = fn (string $prefix, array $rows) => array_map(fn ($r) => ['key' => $prefix.'.'.$r['label'], ...$r], $rows);
    $tree = [
        ['label' => 'Sales', 'icon' => 'fa-cart-shopping', 'items' => [
            ['label' => 'Inquiry Product', 'icon' => 'fa-file-circle-plus', 'group' => 'Transaction', 'items' => [
                ['label' => 'New', 'icon' => 'fa-wand-magic-sparkles', 'group' => 'Doc type', 'items' => $leafs('inq.new', $st('ProDev'))],
                ['label' => 'Revise', 'icon' => 'fa-pen-to-square', 'group' => 'Doc type', 'items' => $leafs('inq.rev', [
                    ['label' => 'Material', 'icon' => 'fa-layer-group'], ['label' => 'Dimension', 'icon' => 'fa-ruler-combined'],
                    ['label' => 'Artwork', 'icon' => 'fa-palette'], ['label' => 'Color', 'icon' => 'fa-swatchbook'], ['label' => 'Process', 'icon' => 'fa-code-branch']])],
                ['label' => 'Recalc', 'icon' => 'fa-calculator', 'group' => 'Doc type', 'items' => $leafs('inq.rec', [
                    ['label' => 'Pending', 'icon' => 'fa-hourglass-half', 'badge' => '3', 'tone' => 'warn'], ['label' => 'Recalculated', 'icon' => 'fa-rotate'], ['label' => 'Published', 'icon' => 'fa-paper-plane']])],
            ]],
            ['label' => 'Quotation', 'icon' => 'fa-file-invoice', 'group' => 'Transaction', 'items' => [
                ['label' => 'Entry', 'icon' => 'fa-file-circle-plus', 'items' => $leafs('quo.entry', [['label' => 'From Inquiry', 'icon' => 'fa-arrow-turn-down'], ['label' => 'Blank', 'icon' => 'fa-file']])],
                ['label' => 'Worklist', 'icon' => 'fa-list', 'items' => $leafs('quo.list', $st('Customer'))],
            ]],
            ['label' => 'Customer Order', 'icon' => 'fa-clipboard-list', 'group' => 'Transaction', 'items' => [
                ['label' => 'From Quotation', 'icon' => 'fa-arrow-turn-down', 'items' => $leafs('co.quo', [['label' => 'Draft', 'icon' => 'fa-pencil'], ['label' => 'Confirmed', 'icon' => 'fa-circle-check', 'badge' => '4']])],
                ['label' => 'Manual', 'icon' => 'fa-hand-pointer', 'items' => $leafs('co.man', [['label' => 'Draft', 'icon' => 'fa-pencil'], ['label' => 'Confirmed', 'icon' => 'fa-circle-check']])],
            ]],
            ['key' => 'cpdel', 'label' => 'Customer Plan Delivery', 'icon' => 'fa-calendar-check', 'group' => 'Transaction'],
            ['key' => 'r-outstanding', 'label' => 'Outstanding Order', 'icon' => 'fa-file-lines', 'group' => 'Reports'],
            ['key' => 'r-sales', 'label' => 'Sales Summary', 'icon' => 'fa-chart-column', 'group' => 'Reports'],
        ]],
        ['label' => 'Purchasing', 'icon' => 'fa-truck', 'items' => [
            ['label' => 'Purchase Request', 'icon' => 'fa-file-lines', 'group' => 'Transaction', 'items' => [
                ['label' => 'Raw Material', 'icon' => 'fa-box', 'items' => $leafs('pr.rm', $st('Approval'))],
                ['label' => 'Service', 'icon' => 'fa-screwdriver-wrench', 'items' => $leafs('pr.svc', $st('Approval'))],
            ]],
            ['label' => 'Purchase Order', 'icon' => 'fa-file-invoice-dollar', 'group' => 'Transaction', 'items' => [
                ['label' => 'Local', 'icon' => 'fa-location-dot', 'items' => $leafs('po.loc', $st('Approval'))],
                ['label' => 'Import', 'icon' => 'fa-globe', 'items' => $leafs('po.imp', $st('Approval'))],
            ]],
            ['key' => 'pv', 'label' => 'Purchase Receive', 'icon' => 'fa-dolly', 'group' => 'Transaction'],
            ['key' => 'r-po', 'label' => 'Outstanding PO', 'icon' => 'fa-file-lines', 'group' => 'Reports', 'badge' => '7', 'tone' => 'danger'],
        ]],
        ['label' => 'Production', 'icon' => 'fa-industry', 'items' => [
            ['label' => 'ProDev', 'icon' => 'fa-table-cells-large', 'group' => 'Engineering', 'items' => [
                ['label' => 'Mode 1 - From Inquiry', 'icon' => 'fa-link', 'items' => $leafs('prodev.1', $st('Confirm'))],
                ['label' => 'Mode 2 - Manual', 'icon' => 'fa-hand-pointer', 'items' => $leafs('prodev.2', $st('Confirm'))],
            ]],
            ['label' => 'ProCost', 'icon' => 'fa-calculator', 'group' => 'Engineering', 'items' => [
                ['label' => 'Mode 1 - Pick Layout', 'icon' => 'fa-link', 'items' => $leafs('procost.1', $st('Publish'))],
                ['label' => 'Mode 2 - Manual', 'icon' => 'fa-hand-pointer', 'items' => $leafs('procost.2', $st('Publish'))],
            ]],
            ['label' => 'Work Order', 'icon' => 'fa-clipboard-check', 'group' => 'Planning', 'items' => [
                ['label' => 'Worklist', 'icon' => 'fa-list', 'items' => $leafs('wo', [['label' => 'Open', 'icon' => 'fa-play'], ['label' => 'Running', 'icon' => 'fa-spinner'], ['label' => 'Closed', 'icon' => 'fa-lock']])],
                ['key' => 'wo.mon', 'label' => 'Monitoring', 'icon' => 'fa-eye'],
            ]],
            ['key' => 'sched', 'label' => 'Scheduling Board', 'icon' => 'fa-calendar-days', 'group' => 'Planning'],
            ['key' => 'r-output', 'label' => 'Production Output', 'icon' => 'fa-chart-line', 'group' => 'Reports'],
        ]],
        ['label' => 'Inventory', 'icon' => 'fa-boxes-stacked', 'items' => [
            ['label' => 'Stock In', 'icon' => 'fa-arrow-down', 'group' => 'Transaction', 'items' => $leafs('sin', [['label' => 'Raw Material', 'icon' => 'fa-box'], ['label' => 'Finished Goods', 'icon' => 'fa-box-open'], ['label' => 'Semi Finished', 'icon' => 'fa-puzzle-piece']])],
            ['label' => 'Stock Out', 'icon' => 'fa-arrow-up', 'group' => 'Transaction', 'items' => $leafs('sout', [['label' => 'Raw Material', 'icon' => 'fa-box'], ['label' => 'Finished Goods', 'icon' => 'fa-box-open']])],
            ['key' => 'r-card', 'label' => 'Stock Card', 'icon' => 'fa-rectangle-list', 'group' => 'Reports'],
        ]],
        ['label' => 'Finance', 'icon' => 'fa-coins', 'items' => [
            ['label' => 'Cash and Bank', 'icon' => 'fa-money-bill-wave', 'group' => 'Transaction', 'items' => $leafs('cb', [
                ['label' => 'Cash In', 'icon' => 'fa-arrow-down'], ['label' => 'Cash Out', 'icon' => 'fa-arrow-up'],
                ['label' => 'Bank In', 'icon' => 'fa-building-columns'], ['label' => 'Bank Out', 'icon' => 'fa-building-columns']])],
            ['key' => 'r-tb', 'label' => 'Trial Balance', 'icon' => 'fa-scale-balanced', 'group' => 'Reports'],
        ]],
        ['label' => 'HRM', 'icon' => 'fa-users', 'items' => [
            ['key' => 'emp', 'label' => 'Employee Master', 'icon' => 'fa-id-badge', 'group' => 'Employee'],
            ['label' => 'Recruitment', 'icon' => 'fa-user-magnifying-glass', 'group' => 'Recruitment', 'items' => [
                ['label' => 'Candidates', 'icon' => 'fa-users', 'items' => $leafs('cand', [['label' => 'New', 'icon' => 'fa-user-plus'], ['label' => 'Interview', 'icon' => 'fa-comments'], ['label' => 'Offered', 'icon' => 'fa-envelope']])],
                ['key' => 'vac', 'label' => 'Vacancies', 'icon' => 'fa-briefcase'],
            ]],
        ]],
        ['label' => 'Master Data', 'icon' => 'fa-database', 'items' => [
            ['key' => 'm-cust', 'label' => 'Customer', 'icon' => 'fa-circle-user', 'group' => 'Master'],
            ['key' => 'm-prod', 'label' => 'Product', 'icon' => 'fa-box', 'group' => 'Master'],
            ['key' => 'm-mach', 'label' => 'Machine', 'icon' => 'fa-gears', 'group' => 'Master'],
            ['key' => 'm-proc', 'label' => 'Process', 'icon' => 'fa-code-branch', 'group' => 'Master'],
        ]],
    ];
@endphp
<div class="dl-lm-demo">
    <x-dl::layer-menu id="demo-layer" :items="$tree" user="Johannes Iwan" />
    <div class="dl-sm-demo__page"><div><b>Area layar</b><br>Klik modul → layer 2 → layer 3 → layer 4, semua di panel kiri yang sama.<br>Back (atau Esc / ←) naik satu layer. Garis di kanan atas = layer aktif; angka = jumlah layar di bawah cabang.<br>Contoh sampai layer 4: Sales › Inquiry Product › New / Revise / Recalc › …</div></div>
</div>
