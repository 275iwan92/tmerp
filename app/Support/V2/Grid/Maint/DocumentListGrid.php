<?php

namespace App\Support\V2\Grid\Maint;

use App\Models\Mnt\MntDocument;
use App\Services\Maintenance\MaintenanceAccess;
use App\Support\V2\Data\Maintenance;
use App\Support\V2\Grid\DocListGrid;

// Daftar SOP / WI Maintenance di layar v2 maintenance-documents. Expand = isi dokumen (langkah per bagian).
class DocumentListGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'maintenance-documents';
    }

    public function title(): string
    {
        return 'SOP and Work Instructions';
    }

    public function module(): string
    {
        return 'MAINT';
    }

    protected function listId(): string
    {
        return 'mnt-document-list';
    }

    protected function listRows(): array
    {
        return Maintenance::documentListRows();
    }

    protected function sources(): array
    {
        return ['tm2mnt.mnt_document', 'tm2mnt.mnt_document_line', 'tm2mnt.mnt_asset_document'];
    }

    public function search(): string
    {
        return 'Search document number, title...';
    }

    public function newUrl(): ?string
    {
        return MaintenanceAccess::canManage(auth()->user()) ? parent::newUrl() : null;
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Document', 'type' => 'doc', 'party' => 'title'],
            ['key' => 'kind', 'label' => 'Kind'],
            ['key' => 'set', 'label' => 'Set'],
            ['key' => 'revision', 'label' => 'Rev'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => MntDocument::STATUS_TONES],
            ['key' => 'lines', 'label' => 'Lines', 'type' => 'number'],
            ['key' => 'approved_at', 'label' => 'Approved', 'type' => 'date'],
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Content', 'columns' => [
            ['key' => 'seq', 'label' => 'No.', 'type' => 'number'],
            ['key' => 'section', 'label' => 'Section'],
            ['key' => 'text', 'label' => 'Text', 'strong' => true],
        ]];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Draft', 'where' => ['status' => 'Draft'], 'tone' => 'warn'], ['label' => 'Approved', 'where' => ['status' => 'Approved'], 'tone' => 'success'],
            ['label' => 'SOP', 'where' => ['kind' => 'SOP'], 'tone' => 'accent'], ['label' => 'Work Instruction', 'where' => ['kind' => 'Work Instruction'], 'tone' => 'info']];
    }

    public function filters(): array
    {
        return ['kind' => 'Kind', 'set' => 'Set', 'status' => 'Status'];
    }
}
