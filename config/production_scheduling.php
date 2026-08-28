<?php

// Sumber: docs/production_scheduling_tier3.md §11 (Threshold & Konstanta).
// Isi di sini, JANGAN hardcode angka ini di dalam service class manapun.

return [

    // §5A.6 Tier1 — minimum data point sebelum Rate Band historis dianggap reliable
    'rate_band_min_datapoint' => env('PRD_RATEBAND_MIN_DATAPOINT', 5),

    // §9.5 Tier1 — minimum data point per kombinasi supplier+material sebelum lead time
    // historis dipakai (di luar scope file ini, dicatat untuk konsistensi lintas modul)
    'leadtime_min_datapoint' => env('PRD_LEADTIME_MIN_DATAPOINT', 5),

    // Jam kerja default per hari (fallback bila resource tak punya kalender spesifik).
    // Machine Calendar sesungguhnya (per shift/roster) harus dibaca dari modul HRM
    // (emp_shift_roster) — lihat catatan MachineCalendarService::subtractWorkingTime().
    'default_shift_start' => env('PRD_SHIFT_START', '08:00'),
    'default_shift_end' => env('PRD_SHIFT_END', '20:00'),

    // Snap waktu saat drag/resize di Scheduling Board (menit)
    'drag_snap_minutes' => env('PRD_DRAG_SNAP_MINUTES', 15),

    // Durasi minimum task hasil resize (menit) — Tier4 mockup pakai 30 menit
    'min_task_duration_minutes' => env('PRD_MIN_TASK_DURATION_MINUTES', 30),

    // §9.6 Tier1 — kadensi kalibrasi bobot Decision Feedback Loop
    'feedback_calibration_cycle' => env('PRD_FEEDBACK_CALIBRATION_CYCLE', 'weekly'),

    // §5A.10 Tier1 — ambang Dead Stock (hari tanpa OUT)
    'dead_stock_days' => env('PRD_DEAD_STOCK_DAYS', 180),

];
