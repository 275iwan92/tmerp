@php
    $tone = ['Approved' => ['success', 'fa-circle-check'], 'Draft' => ['warn', 'fa-pen-to-square'], 'Revision' => ['info', 'fa-code-branch']];
    $cell = fn ($s) => ['label' => $s, 'tone' => $tone[$s][0], 'icon' => $tone[$s][1], 'url' => '#'];
    $set = fn ($code, $title, $machines, $st) => [
        'code' => $code, 'title' => $title, 'sub' => count($machines).' · '.implode(', ', array_slice($machines, 0, 3)).(count($machines) > 3 ? ' +'.(count($machines) - 3) : ''),
        'cells' => array_combine(['sop', 'daily', 'weekly', 'monthly', 'breakdown'], array_map($cell, $st)),
        'flags' => array_intersect(['Draft', 'Revision'], $st) ? ['pending'] : [],
        'detail' => [['title' => 'Machines', 'items' => $machines], ['title' => 'Steps (SOP)', 'items' => ['Daily check by the operator', 'Weekly and monthly care by the technician', 'Breakdown first response']]],
    ];
    $rows = [
        $set('OFF', 'Offset Printing Press', ['OFF-01', 'OFF-02'], ['Approved', 'Approved', 'Approved', 'Approved', 'Draft']),
        $set('DIE', 'Die Cutting Machine', ['DIE-01', 'DIE-02', 'DIE-03', 'DIE-04'], ['Approved', 'Approved', 'Approved', 'Draft', 'Draft']),
        $set('CUT', 'Paper Cutting Machine', ['CUT-01', 'CUT-02', 'CUT-03'], ['Approved', 'Revision', 'Approved', 'Approved', 'Approved']),
        $set('CTP', 'CTP Plate Setter', ['CTP-01'], ['Approved', 'Approved', 'Approved', 'Approved', 'Approved']),
        $set('PLT', 'Cutting Plotter', ['PLT-01', 'PLT-02', 'PLT-03', 'PLT-04', 'PLT-05'], ['Draft', 'Draft', 'Draft', 'Draft', 'Draft']),
    ];
@endphp
<x-dl::coverage head="Set" sub-head="Machines" search="Search set, machine…"
    :columns="['sop' => 'SOP', 'daily' => 'WI Daily', 'weekly' => 'WI Weekly', 'monthly' => 'WI Monthly', 'breakdown' => 'WI Breakdown']"
    :filters="['pending' => 'Not fully approved']" :rows="$rows" />
