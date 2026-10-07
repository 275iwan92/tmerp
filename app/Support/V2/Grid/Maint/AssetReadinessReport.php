<?php

namespace App\Support\V2\Grid\Maint;

use App\Models\Mnt\MntAsset;
use App\Services\Maintenance\AssetProfileService;
use App\Support\V2\Data\Maintenance;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Asset Readiness -- satu baris per aset: seberapa lengkap profil perawatannya. Hanya dibaca; klik membuka profil aset.
// Angka kelengkapan dihitung dari data (AssetProfileService), tidak disimpan.
class AssetReadinessReport extends DbGrid
{
    public function slug(): string
    {
        return 'maintenance-readiness';
    }

    public function title(): string
    {
        return 'Asset Readiness';
    }

    public function module(): string
    {
        return 'MAINT';
    }

    public function moduleLabel(): string
    {
        return 'Maintenance';
    }

    public function family(): string
    {
        return self::REPORT;
    }

    public function guide(): ?string
    {
        return 'One row = one asset. Complete counts how many of Watch Points, Spare Parts, Documents and Meter are filled. Click a row to open the profile and fill what is missing.';
    }

    protected function query(): Builder
    {
        return MntAsset::query()->where('activate', true)->orderBy('asset_code')->withCount([
            'watchPoints as watch_count' => fn ($q) => $q->where('activate', true),
            'parts as parts_count' => fn ($q) => $q->where('activate', true),
            'documents as docs_count',
        ]);
    }

    protected function row(Model $m): array
    {
        $c = AssetProfileService::completeness(['watch' => $m->watch_count, 'parts' => $m->parts_count, 'docs' => $m->docs_count], (string) $m->meter_type);
        $mark = fn (bool $ok) => $ok ? 'Yes' : 'No';

        return ['asset' => $m->asset_code.' · '.$m->asset_name, 'class' => MntAsset::CLASSES[$m->asset_class] ?? $m->asset_class,
            'criticality' => Maintenance::criticalityOf($m), 'watch' => $m->watch_count, 'parts' => $m->parts_count, 'docs' => $m->docs_count,
            'meter' => MntAsset::METERS[$m->meter_type] ?? $m->meter_type, 'has_meter' => $mark($c['items']['meter']),
            'done' => $c['done'], 'pct' => $c['pct'], 'level' => $c['done'] === $c['total'] ? 'Complete' : ($c['done'] === 0 ? 'Empty' : 'Incomplete')];
    }

    public function columns(): array
    {
        return [
            ['key' => 'asset', 'label' => 'Asset'],
            ['key' => 'class', 'label' => 'Class', 'type' => 'badge', 'tones' => MntAsset::CLASS_TONES],
            ['key' => 'criticality', 'label' => 'Criticality'],
            ['key' => 'watch', 'label' => 'Watch Points', 'type' => 'number', 'sum' => true],
            ['key' => 'parts', 'label' => 'Spare Parts', 'type' => 'number', 'sum' => true],
            ['key' => 'docs', 'label' => 'Documents', 'type' => 'number', 'sum' => true],
            ['key' => 'meter', 'label' => 'Meter'],
            ['key' => 'pct', 'label' => 'Complete %', 'type' => 'number'],
            ['key' => 'level', 'label' => 'Level', 'type' => 'badge', 'tones' => ['Complete' => 'success', 'Incomplete' => 'warn', 'Empty' => 'danger']],
        ];
    }

    public function groups(): array
    {
        return ['class' => 'Class', 'level' => 'Level', 'criticality' => 'Criticality'];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Empty', 'where' => ['level' => 'Empty'], 'tone' => 'danger'],
            ['label' => 'Incomplete', 'where' => ['level' => 'Incomplete'], 'tone' => 'warn'], ['label' => 'Complete', 'where' => ['level' => 'Complete'], 'tone' => 'success']];
    }

    public function filters(): array
    {
        return ['class' => 'Class', 'level' => 'Level', 'criticality' => 'Criticality'];
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'maintenance-profile').'?id={id}';
    }
}
