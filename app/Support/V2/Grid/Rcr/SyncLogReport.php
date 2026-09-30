<?php

namespace App\Support\V2\Grid\Rcr;

use App\Models\Hrr\RcrSyncLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Rekap Sync Log portal kandidat (v1: recruitment.sync-log). Hanya-baca.
class SyncLogReport extends RcrGrid
{
    public function slug(): string
    {
        return 'hrm-rcr-sync-log';
    }

    public function title(): string
    {
        return 'Portal Sync Log';
    }

    public function group(): string
    {
        return 'Recruitment › Report';
    }

    public function family(): string
    {
        return self::REPORT;
    }

    public function icon(): string
    {
        return 'fa-solid fa-rotate';
    }

    protected function models(): array
    {
        return [RcrSyncLog::class];
    }

    public function search(): string
    {
        return 'Search submission, error...';
    }

    protected function query(): Builder
    {
        return RcrSyncLog::query()->orderByDesc('fetched_at');
    }

    public function columns(): array
    {
        return [
            ['key' => 'at', 'label' => 'Fetched', 'type' => 'date'],
            ['key' => 'time', 'label' => 'Time'],
            ['key' => 'backend', 'label' => 'Backend'],
            ['key' => 'uuid', 'label' => 'Submission'],
            ['key' => 'application', 'label' => 'Application ID', 'type' => 'number'],
            ['key' => 'result', 'label' => 'Result', 'type' => 'badge', 'tones' => ['OK' => 'success', 'SUCCESS' => 'success', 'DUPLICATE' => 'neutral', 'ERROR' => 'danger', 'FAILED' => 'danger']],
            ['key' => 'error', 'label' => 'Error'],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'at' => $m->fetched_at?->toDateString(), 'time' => $m->fetched_at?->format('H:i'), 'backend' => $m->backend_used,
            'uuid' => $m->submission_uuid, 'application' => $m->id_application, 'result' => $m->result, 'error' => $m->error_note,
        ];
    }

    public function filters(): array
    {
        return ['backend' => 'Backend', 'result' => 'Result'];
    }

    public function groups(): array
    {
        return ['at' => 'Date', 'result' => 'Result'];
    }
}
