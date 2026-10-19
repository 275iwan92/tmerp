<?php

/*
 * REMARK
 * Tujuan       : Grid v2 hrm-sop: daftar SOP dan Work Instruction perusahaan; Open dan + New Record (hanya HR Officer) membuka form v2 hrm-sop-form.
 * Sumber hulu  : tm2mnt.mnt_document (+ mnt_document_line), register SOP / WI tunggal sejak 2026-10-18 (sop_document dilebur).
 * Input        : Pencarian dan filter grid.
 * Output       : Baris dokumen (doc_set kosong); non HR Officer hanya melihat yang Approved.
 * Dipakai oleh : Menu HRM (v2:hrm-sop), layar hrm-sop-form (kembali ke daftar), Grids registry.
 * Status       : Aktif. Halaman v1 pages::hrm.sop-documents sudah dipindah ke tmerp_unused (aturan D.25, T7f HRM).
 */

namespace App\Support\V2\Grid\Hrm;

use App\Models\Master\OrgDepartment;
use App\Models\Mnt\MntDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// SOP dan Work Instruction perusahaan = tm2mnt.mnt_document dengan doc_set kosong. HR Officer menulis; yang lain hanya melihat yang Approved.
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

    protected function canCreate(): bool
    {
        return (bool) auth()->user()?->isHrOfficer();
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'hrm-sop-form').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return $this->canCreate() ? route('v2.screen', 'hrm-sop-form').'?new=1' : null;
    }

    protected function models(): array
    {
        return [MntDocument::class, OrgDepartment::class];
    }

    public function search(): string
    {
        return 'Search title, document code...';
    }

    protected function query(): Builder
    {
        return MntDocument::company()->with('department')
            ->when(! auth()->user()?->isHrOfficer(), fn (Builder $q) => $q->inForce())->orderBy('title');
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Document', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'type', 'label' => 'Document Type'],
            ['key' => 'dept', 'label' => 'Department Scope'],
            ['key' => 'revision', 'label' => 'Revision'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'effective', 'label' => 'Effective', 'type' => 'date'],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'name' => $m->title, 'code' => $m->doc_no, 'type' => MntDocument::COMPANY_KINDS[$m->kind] ?? 'SOP', 'dept' => $m->department?->department_name ?? t('All departments'),
            'revision' => $m->revision, 'status' => $m->isApproved() ? 'Approved' : 'Draft', 'effective' => $m->effective_date ? substr((string) $m->effective_date, 0, 10) : null,
        ];
    }

    public function filters(): array
    {
        return ['type' => 'Document Type', 'dept' => 'Department'];
    }
}
