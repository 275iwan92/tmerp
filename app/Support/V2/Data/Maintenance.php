<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Master\MatRaw;
use App\Models\Master\ProdMachine;
use App\Models\Mnt\MntAsset;
use App\Models\Mnt\MntCode;
use App\Models\Mnt\MntDocument;
use App\Services\Maintenance\AssetProfileService;

// Data layar v2 Maintenance Fase 1 (profil aset, kelengkapan, dokumen). Aturan tulis = AssetProfileService.
// Mesin dan bahan ada di tm2mst -> dicari sekali per halaman lalu dicocokkan di PHP (pola 1 CLAUDE.md bagian 2).
final class Maintenance
{
    public const STATUS_LABELS = ['DRAFT' => 'Draft', 'APPROVED' => 'Approved'];

    /** [id_asset => "OFF-02 · Komori GL440"] aset aktif untuk dropdown. */
    public static function assetOptions(): array
    {
        return MntAsset::where('activate', true)->orderBy('asset_code')->get(['id_asset', 'asset_code', 'asset_name'])
            ->mapWithKeys(fn ($a) => [$a->id_asset => $a->asset_code.' · '.$a->asset_name])->all();
    }

    /** [machine_id => criticality] untuk aset yang tertaut ke Master Mesin. */
    private static function machineCriticality(iterable $machineIds): array
    {
        return ProdMachine::whereIn('machine_id', collect($machineIds)->filter()->unique())->pluck('criticality', 'machine_id')->all();
    }

    public static function criticalityOf(MntAsset $a): ?string
    {
        return $a->isLinkedMachine() ? (self::machineCriticality([$a->machine_id])[$a->machine_id] ?? null) : $a->criticality;
    }

    private static function counted()
    {
        return MntAsset::query()->withCount([
            'watchPoints as watch_count' => fn ($q) => $q->where('activate', true),
            'parts as parts_count' => fn ($q) => $q->where('activate', true),
            'documents as docs_count',
        ]);
    }

    /** Baris daftar profil aset. */
    public static function assetRows(): array
    {
        $assets = self::counted()->orderBy('asset_code')->get();
        $crit = self::machineCriticality($assets->pluck('machine_id'));

        return $assets->map(function (MntAsset $a) use ($crit) {
            $c = AssetProfileService::completeness(['watch' => $a->watch_count, 'parts' => $a->parts_count, 'docs' => $a->docs_count], (string) $a->meter_type);

            return ['id' => $a->id_asset, 'code' => $a->asset_code, 'name' => $a->asset_name, 'class' => MntAsset::CLASSES[$a->asset_class] ?? $a->asset_class,
                'criticality' => $a->isLinkedMachine() ? ($crit[$a->machine_id] ?? null) : $a->criticality, 'location' => $a->location,
                'meter' => MntAsset::METERS[$a->meter_type] ?? $a->meter_type, 'watch' => $a->watch_count, 'parts' => $a->parts_count, 'docs' => $a->docs_count,
                'complete' => $c['done'].' / '.$c['total'], 'pct' => $c['pct'], 'status' => $a->activate ? 'Active' : 'Inactive'];
        })->all();
    }

    /** Baris x-dl::lines tab Watch Points. */
    public static function watchRows(MntAsset $asset): array
    {
        return $asset->watchPoints->map(fn ($w) => ['id_watch_point' => $w->id_watch_point, 'component_code' => $w->component_code, 'check_text' => $w->check_text,
            'method_text' => $w->method_text, 'normal_min' => $w->normal_min, 'normal_max' => $w->normal_max, 'measure_uom' => $w->measure_uom,
            'frequency' => $w->frequency, 'checked_by_role' => $w->checked_by_role, 'activate' => $w->activate])->values()->all();
    }

    /** Baris x-dl::lines tab Spare Parts (nama bahan dari tm2mst, satu query). */
    public static function partRows(MntAsset $asset): array
    {
        $mats = MatRaw::whereIn('material_id', $asset->parts->pluck('material_id'))->get(['material_id', 'material_code', 'material_name'])->keyBy('material_id');

        return $asset->parts->map(fn ($p) => ['id_asset_part' => $p->id_asset_part,
            'item' => ($mats[$p->material_id]->material_code ?? '#'.$p->material_id).' · '.($mats[$p->material_id]->material_name ?? ''),
            'component_code' => $p->component_code, 'qty_per_use' => $p->qty_per_use, 'min_stock_suggested' => $p->min_stock_suggested,
            'expected_life_days' => $p->expected_life_days, 'is_critical' => $p->is_critical])->values()->all();
    }

    /** Dokumen terhubung ke aset (tampil saja; dibuka di layar Documents). */
    public static function documentRows(MntAsset $asset): array
    {
        return $asset->documents->map(fn ($d) => ['id_document' => $d->id_document, 'doc_no' => $d->doc_no, 'kind' => MntDocument::KINDS[$d->kind] ?? $d->kind,
            'title' => $d->title, 'revision' => $d->revision, 'status' => self::STATUS_LABELS[$d->status] ?? $d->status])->values()->all();
    }

    /** [id_document => "WI-MNT-OFF-01 · Daily check of ..."] dokumen yang belum terhubung ke aset ini. */
    public static function linkableDocuments(MntAsset $asset): array
    {
        $linked = $asset->documents->pluck('id_document');

        return MntDocument::where('activate', true)->whereNotIn('id_document', $linked)->orderBy('doc_no')->get(['id_document', 'doc_no', 'title'])
            ->mapWithKeys(fn ($d) => [$d->id_document => $d->doc_no.' · '.$d->title])->all();
    }

    public static function componentOptions(): array
    {
        return ['' => '—'] + array_map('t', MntCode::options('COMPONENT'));
    }

    /** Baris daftar dokumen SOP / WI. */
    public static function documentListRows(): array
    {
        return MntDocument::query()->withCount('lines')->orderBy('doc_no')->get()->map(fn ($d) => ['id' => $d->id_document, 'code' => $d->doc_no,
            'title' => $d->title, 'kind' => MntDocument::KINDS[$d->kind] ?? $d->kind, 'set' => $d->doc_set, 'revision' => $d->revision,
            'status' => self::STATUS_LABELS[$d->status] ?? $d->status, 'lines' => $d->lines_count, 'approved_at' => $d->approved_at?->toDateString(),
            'items' => $d->lines()->orderBy('seq_no')->get()->map(fn ($l) => ['seq' => $l->seq_no, 'section' => $l->section, 'text' => $l->line_text])->all()])->all();
    }
}
