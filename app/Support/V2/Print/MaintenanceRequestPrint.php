<?php

namespace App\Support\V2\Print;

use App\Models\Prd\MntRequest;
use App\Models\Prd\MntWo;
use App\Support\V2\Data\Maintenance;
use Devlib\Locale;
use Devlib\PrintDoc\Document;

// Maintenance Request (belum A): laporan kerusakan, tanpa kolom jumlah. Baris = gejala yang dilaporkan; kolom tindakan dan
// catatan diisi tangan oleh teknisi saat memeriksa. Sumber: tm2prd.mnt_request (+ mnt_wo bila sudah diterima).
final class MaintenanceRequestPrint extends Document
{
    public string $type = 'maintenance-request';

    public string $title = 'Maintenance Request';

    public ?string $subtitle = 'Breakdown Report · Internal';

    public string $numberLabel = 'Request No.';

    public array $refs = [
        ['key' => 'machine', 'label' => 'Machine'],
        ['key' => 'kind', 'label' => 'Kind'],
        ['key' => 'priority', 'label' => 'Priority'],
        ['key' => 'stopped', 'label' => 'Machine Stopped'],
        ['key' => 'reported_by', 'label' => 'Reported By'],
    ];

    public string $partyLabel = 'Decision';

    public ?array $side = ['key' => 'wo_no', 'label' => 'Work Order'];

    public string $nb = 'One row per symptom. FIRST ACTION and TECHNICIAN NOTES are filled in by the technician during inspection.';

    public string $nameLabel = 'What Happened';

    public bool $qtyCols = false;

    public array $extra = [
        ['key' => 'component', 'label' => 'Component', 'width' => 34],
        ['key' => 'first_action', 'label' => 'First Action', 'width' => 43, 'hand' => true],
        ['key' => 'tech_note', 'label' => 'Technician Notes', 'width' => 43, 'hand' => true],
    ];

    public array $receivers = [['Reporter', 'Production']];

    public static function load(int $id, array $opt = []): static
    {
        $req = MntRequest::with(['machine', 'wo'])->findOrFail($id);
        $wo = $req->wo;
        $decision = array_values(array_filter([
            $req->reject_reason,
            $wo?->assigned_to ? self::t('Technician').': '.Maintenance::employeeName($wo->assigned_to) : null,
            $wo?->planned_start ? self::t('Planned start').': '.Locale::stamp($wo->planned_start) : null,
        ]));

        return new self([
            'number' => $req->doc_no, 'date' => $req->request_date?->toDateString(), 'status' => $req->status, 'printedBy' => $opt['printedBy'] ?? null,
            'machine' => $req->machine ? $req->machine->machine_code.' · '.$req->machine->machine_name : null,
            'kind' => MntRequest::KINDS[$req->request_kind] ?? $req->request_kind, 'priority' => MntWo::PRIORITIES[$req->priority] ?? $req->priority,
            'stopped' => $req->machine_stopped ? self::t('Yes, since').' '.Locale::stamp($req->stopped_at) : self::t('No'),
            'reported_by' => $req->reported_by ?: PrintDocs::person($req->created_by), 'wo_no' => $wo?->doc_no,
            'party' => ['name' => $req->status, 'lines' => $decision],
            'rows' => [['name' => $req->symptom, 'component' => $wo?->component_code]],
        ]);
    }
}
