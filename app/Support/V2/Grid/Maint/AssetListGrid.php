<?php

namespace App\Support\V2\Grid\Maint;

use App\Models\Mnt\MntAsset;
use App\Services\Maintenance\MaintenanceAccess;
use App\Support\V2\Data\Maintenance;
use App\Support\V2\Grid\DocListGrid;

// Daftar aset di layar v2 maintenance-profile: Open / + New Record (aset non-mesin) = form di layar yang sama.
class AssetListGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'maintenance-profile';
    }

    public function title(): string
    {
        return 'Asset Maintenance Profile';
    }

    public function module(): string
    {
        return 'MAINT';
    }

    protected function listId(): string
    {
        return 'mnt-asset-list';
    }

    protected function listRows(): array
    {
        return Maintenance::assetRows();
    }

    protected function sources(): array
    {
        return ['tm2mnt.mnt_asset', 'tm2mnt.mnt_watch_point', 'tm2mnt.mnt_asset_part', 'tm2mst.prod_machine'];
    }

    public function search(): string
    {
        return 'Search asset code, name...';
    }

    public function newUrl(): ?string
    {
        return MaintenanceAccess::canManage(auth()->user()) ? parent::newUrl() : null;
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Asset', 'type' => 'doc', 'party' => 'name'],
            ['key' => 'class', 'label' => 'Class', 'type' => 'badge', 'tones' => MntAsset::CLASS_TONES],
            ['key' => 'criticality', 'label' => 'Criticality'],
            ['key' => 'location', 'label' => 'Location'],
            ['key' => 'meter', 'label' => 'Meter'],
            ['key' => 'watch', 'label' => 'Watch Points', 'type' => 'number'],
            ['key' => 'parts', 'label' => 'Spare Parts', 'type' => 'number'],
            ['key' => 'docs', 'label' => 'Documents', 'type' => 'number'],
            ['key' => 'complete', 'label' => 'Complete'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['Active' => 'success', 'Inactive' => 'neutral']],
        ];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Production machine', 'where' => ['class' => 'Production machine'], 'tone' => 'accent'],
            ['label' => 'Support equipment', 'where' => ['class' => 'Support equipment'], 'tone' => 'info'], ['label' => 'Vehicle', 'where' => ['class' => 'Vehicle'], 'tone' => 'warn'],
            ['label' => 'Facility', 'where' => ['class' => 'Facility'], 'tone' => 'neutral']];
    }

    public function filters(): array
    {
        return ['class' => 'Class', 'criticality' => 'Criticality', 'meter' => 'Meter', 'status' => 'Status'];
    }
}
