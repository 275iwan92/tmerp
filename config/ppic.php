<?php

// PPIC scheduling start values. Every value here is an ASSUMED starting value that the learning engine corrects; they are
// also listed in Settings > Constants so each one has an input screen (rule: every data needs an input UI).

return [
    // Work calendar
    'day_start' => env('PPIC_DAY_START', '07:00'),
    'default_shifts' => 2,
    'default_hours_per_shift' => 8.0,

    // Lock = next N running shifts after Confirm (set by the officer)
    'lock_shifts' => 1,

    // Priority score weights (urgency / setup / customer tier), similar-urgency window in hours, ageing weight
    'weights' => ['urgency' => 0.50, 'setup' => 0.35, 'tier' => 0.15],
    'similar_window_hours' => 8,
    'horizon_hours' => 72,

    // Jobs on the same die within this many hours of each other are put one after the other (the die is mounted once). ASSUMED.
    'group_due_hours' => 48,

    // A machine that already has the same die is chosen when it finishes within this many hours of the earliest machine (and the due date holds). ASSUMED.
    'group_slack_hours' => 8,

    // Wash minutes are multiplied by this when the next job's inks are darker than the previous (light to dark). ASSUMED, not proven for this plant.
    'color_order_factor' => 0.7,

    // Bottleneck threshold (load percent)
    'bottleneck_load_pct' => 90,

    // Setup start values (minutes and extra waste sheets); machine rows in prod_machine_changeover override them
    'setup' => [
        'base_minutes' => 15,
        'color_add_spot' => ['minutes' => 20, 'waste' => 30],
        'color_full_wash' => ['minutes' => 38, 'waste' => 60],
        'ink_type' => ['minutes' => 55, 'waste' => 80],
        'substrate' => ['minutes' => 15, 'waste' => 20, 'gsm_gap' => 20],
        'size' => ['minutes' => 12, 'waste' => 15],
        'die' => ['minutes' => 15, 'waste' => 0],
        'plate' => ['minutes' => 10, 'waste' => 0],
    ],

    // Minutes to wait after a process before the next one (drying / curing), by process name keyword (upper case)
    'wait_after' => ['CETAK' => 120, 'VARNISH' => 45, 'UV' => 0, 'LAMINASI' => 0],

    // Planning horizon in days for a run
    'horizon_days' => 60,
];
