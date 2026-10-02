<?php

namespace App\Support\V2\Grid\Hrm;

use App\Models\Master\OrgDepartment;
use App\Models\Master\SopDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// SOP dan Work Instruction, satu register (v1: pages::hrm.sop-documents).
class SopGrid extends HrmGrid
{
    public function slug(): string
    {
        return 'hrm-sop';
    }

    public function title(): string
    {
        return 'SOP & Work Instruction';
    }

    public function family(): string
    {
        return self::MASTER;
    }

    public function group(): string
    {
        return 'Organization';
    }

    protected function v1Route(): ?string
    {
        return 'hrm.sop-documents';
    }

    protected function v1Component(): ?string
    {
        return 'pages::hrm.sop-documents';
    }

    protected function models(): array
    {
        return [SopDocument::class, OrgDepartment::class];
    }

    public function search(): string
    {
        return 'Search title, document code...';
    }

    protected function query(): Builder
    {
        return SopDocument::query()->with('department')->orderBy('title');
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Document', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'type', 'label' => 'Document Type'],
            ['key' => 'dept', 'label' => 'Department Scope'],
            ['key' => 'revision', 'label' => 'Revision'],
            ['key' => 'effective', 'label' => 'Effective', 'type' => 'date'],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'name' => $m->title, 'code' => $m->sop_code, 'type' => SopDocument::TYPES[$m->doc_type] ?? 'SOP', 'dept' => $m->department?->department_name ?? t('All departments'),
            'revision' => $m->revision, 'effective' => $m->effective_date ? substr((string) $m->effective_date, 0, 10) : null,
        ];
    }

    public function filters(): array
    {
        return ['type' => 'Document Type', 'dept' => 'Department'];
    }
}
