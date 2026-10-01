<?php

namespace App\Support\V2\Print;

use App\Models\Prd\MntWo;
use App\Support\V2\Data\Maintenance;
use Devlib\Locale;
use Devlib\PrintDoc\Document;

// Maintenance Work Order (format A, user 2026-10-02): perintah kerja perawatan, tanpa kolom jumlah. Baris = tugas checklist; Hasil / Terukur /
// Catatan diisi teknisi (sudah terisi bila sudah di-input). Spare part dan Masalah / Sebab / Tindakan di bawah tabel.
// Sumber: tm2prd.mnt_wo + mnt_wo_task + mnt_wo_part.
final class MaintenanceWoPrint extends Document
{
    public string $type = 'maintenance-wo';

    public string $title = 'Maintenance Work Order';

    public ?string $subtitle = 'Maintenance Job Order · Internal';

    public string $numberLabel = 'WO No.';

    public array $refs = [
        ['key' => 'machine', 'label' => 'Machine'],
        ['key' => 'wo_type', 'label' => 'WO Kind'],
        ['key' => 'priority', 'label' => 'Priority'],
        ['key' => 'technician', 'label' => 'Technician'],
        ['key' => 'planned', 'label' => 'Planned'],
    ];

    public string $partyLabel = 'Source';

    public ?array $side = ['key' => 'machine_stop', 'label' => 'Machine Stopped'];

    public string $nb = 'RESULT: OK / NG. MEASURED is filled in for measuring tasks. Spare parts and working hours are recorded in the system when done.';

    public string $nameLabel = 'Task (Checklist)';

    public bool $qtyCols = false;

    public array $extra = [
        ['key' => 'kind', 'label' => 'Kind', 'width' => 20],
        ['key' => 'range', 'label' => 'Min - Max', 'width' => 26],
        ['key' => 'result', 'label' => 'Result', 'width' => 20, 'hand' => true],
        ['key' => 'measured', 'label' => 'Measured', 'width' => 22, 'hand' => true],
        ['key' => 'note', 'label' => 'Notes', 'width' => 32, 'hand' => true],
    ];

    public array $receivers = [['Production', 'Takes the machine back']];

    public static function load(int $id, array $opt = []): static
    {
        $wo = MntWo::with(['machine', 'request', 'plan', 'tasks' => fn ($q) => $q->orderBy('seq_no'), 'parts'])->findOrFail($id);
        $parts = collect(Maintenance::parts($wo))->map(fn ($p) => $p['item'].' × '.Locale::decimal($p['qty_used'] ?? $p['qty_plan'], 2).' '.$p['uom'])->implode(' · ');
        $pca = implode(' / ', array_filter([$wo->problem_code, $wo->cause_code, $wo->action_code]));
        $sameDay = $wo->planned_start && $wo->planned_end && $wo->planned_start->isSameDay($wo->planned_end);
        $planned = $wo->planned_start ? Locale::stamp($wo->planned_start).($wo->planned_end ? ' – '.($sameDay ? $wo->planned_end->format('H:i') : Locale::stamp($wo->planned_end)) : '') : null;

        return new self([
            'number' => $wo->doc_no ?: 'Draft #'.$wo->id_wo, 'date' => ($wo->planned_start ?? $wo->created_at)?->toDateString(), 'status' => strtoupper(Maintenance::workLabel($wo)),
            'printedBy' => $opt['printedBy'] ?? null,
            'machine' => $wo->machine ? $wo->machine->machine_code.' · '.$wo->machine->machine_name : null,
            'wo_type' => MntWo::TYPES[$wo->wo_type] ?? $wo->wo_type, 'priority' => MntWo::PRIORITIES[$wo->priority] ?? $wo->priority,
            'technician' => $wo->assigned_to ? Maintenance::employeeName($wo->assigned_to) : null, 'planned' => $planned,
            'machine_stop' => self::t($wo->needs_machine_stop ? 'Yes, downtime recorded' : 'No'),
            'party' => ['name' => $wo->request?->doc_no ?? $wo->plan?->plan_code ?? ucfirst(strtolower((string) $wo->source)), 'lines' => PrintDocs::address($wo->description)],
            'notes' => array_values(array_filter([
                $parts ? ['Spare Parts', $parts] : null,
                ['Problem / Cause / Action', trim($pca.($wo->findings ? ' — '.$wo->findings : '')) ?: str_repeat('.', 90)],
            ])),
            'rows' => $wo->tasks->map(fn ($t) => ['name' => $t->task_text, 'kind' => ucfirst(strtolower((string) $t->task_kind)),
                'range' => $t->task_kind === 'MEASURE' ? trim(Locale::decimal($t->min_value, 2).' – '.Locale::decimal($t->max_value, 2).' '.$t->measure_uom) : null,
                'result' => $t->result, 'measured' => $t->measured_value !== null ? Locale::decimal($t->measured_value, 2) : null, 'note' => $t->note])->all(),
        ]);
    }
}
