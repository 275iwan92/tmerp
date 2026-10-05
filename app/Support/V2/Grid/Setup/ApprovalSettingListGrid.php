<?php

namespace App\Support\V2\Grid\Setup;

use App\Support\V2\Data\ApprovalSetting;
use App\Support\V2\Grid\DocListGrid;

// Daftar Approval Levels (Grid Transactional) di layar v2 approval-setting: Open / + New Record = form di layar yang sama.
class ApprovalSettingListGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'approval-setting';
    }

    public function title(): string
    {
        return 'Approval Levels';
    }

    public function module(): string
    {
        return 'SETUP';
    }

    protected function listRows(): array
    {
        return ApprovalSetting::rows();
    }

    protected function sources(): array
    {
        return ['tm2mst.flow_config', 'tm2mst.flow_config_step'];
    }

    public function search(): string
    {
        return 'Search module, position...';
    }

    public function columns(): array
    {
        return [
            ['key' => 'module', 'label' => 'Document Code / Name', 'type' => 'doc', 'party' => 'name'],
            ['key' => 'levels', 'label' => t('Number of Levels'), 'type' => 'number'],
            ['key' => 'approvers', 'label' => t('Approver per Level (position)')],
            ['key' => 'final', 'label' => t('Final Status')],
            ['key' => 'active', 'label' => 'Status', 'type' => 'badge', 'tones' => [t('Active') => 'success', t('Inactive') => 'neutral']],
        ];
    }

    public function stats(): array
    {
        return [['label' => t('All')], ['label' => t('Active'), 'where' => ['active' => t('Active')], 'tone' => 'success'], ['label' => t('Inactive'), 'where' => ['active' => t('Inactive')], 'tone' => 'neutral']];
    }
}
