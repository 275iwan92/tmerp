<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Master\ProdMachine;
use App\Models\Mnt\MntAsset;
use App\Models\Mnt\MntLog;
use App\Models\Mnt\MntWatchPoint;
use Illuminate\Support\Facades\DB;

// Aturan master aset Maintenance (profil, kelengkapan, salin watch point, tarik mesin dari Master Mesin).
// Master hanya berubah lewat tindakan user; sistem tidak mengubah master sendiri.
final class AssetProfileService
{
    // Hal yang dihitung untuk kelengkapan profil. PM Plan ditambahkan di Fase 2 saat tabelnya ada.
    public const ITEMS = ['watch' => 'Watch Points', 'parts' => 'Spare Parts', 'docs' => 'Documents', 'meter' => 'Meter'];

    /** @param array{watch:int, parts:int, docs:int} $counts */
    public static function completeness(array $counts, string $meterType): array
    {
        $items = ['watch' => ($counts['watch'] ?? 0) > 0, 'parts' => ($counts['parts'] ?? 0) > 0,
            'docs' => ($counts['docs'] ?? 0) > 0, 'meter' => $meterType !== 'NONE'];
        $done = count(array_filter($items));

        return ['items' => $items, 'done' => $done, 'total' => count($items), 'pct' => (int) round($done / count($items) * 100)];
    }

    public static function completenessOf(MntAsset $asset): array
    {
        return self::completeness([
            'watch' => $asset->watchPoints()->where('activate', true)->count(),
            'parts' => $asset->parts()->where('activate', true)->count(),
            'docs' => $asset->documents()->count(),
        ], (string) $asset->meter_type);
    }

    // Tarik mesin aktif dari Master Mesin menjadi aset. Hanya kode, nama dan kelas turunan mesin yang diperbarui;
    // kritikalitas, meter dan catatan milik Maintenance tidak disentuh. Mesin yang sudah jadi aset tidak diduplikasi.
    public function syncMachines(): array
    {
        $created = 0;
        $updated = 0;
        DB::connection('tm2mnt')->transaction(function () use (&$created, &$updated) {
            foreach (ProdMachine::where('activate', true)->orderBy('machine_code')->get(['machine_id', 'machine_code', 'machine_name', 'is_process_machine']) as $m) {
                $class = $m->is_process_machine ? 'MACHINE' : 'SUPPORT';
                $asset = MntAsset::where('machine_id', $m->machine_id)->first();
                if ($asset === null) {
                    $asset = MntAsset::create(['asset_code' => $m->machine_code, 'asset_name' => $m->machine_name, 'asset_class' => $class,
                        'machine_id' => $m->machine_id, 'meter_type' => 'NONE', 'activate' => true, 'created_by' => self::login(), 'updated_by' => self::login()]);
                    MntLog::add('ASSET', $asset->id_asset, 'Created', 'From Master Machine');
                    $created++;
                } elseif ($asset->asset_code !== $m->machine_code || $asset->asset_name !== $m->machine_name || $asset->asset_class !== $class) {
                    $asset->update(['asset_code' => $m->machine_code, 'asset_name' => $m->machine_name, 'asset_class' => $class, 'updated_by' => self::login()]);
                    MntLog::add('ASSET', $asset->id_asset, 'Updated', 'From Master Machine');
                    $updated++;
                }
            }
        });

        return ['created' => $created, 'updated' => $updated];
    }

    // Salin watch point aktif dari aset lain sebagai titik awal. Baris yang sudah ada di tujuan (komponen + teks cek sama) dilewati.
    public function copyWatchPoints(MntAsset $from, MntAsset $to): int
    {
        if ($from->is($to)) {
            return 0;
        }
        $copied = 0;
        DB::connection('tm2mnt')->transaction(function () use ($from, $to, &$copied) {
            $have = $to->watchPoints()->get(['component_code', 'check_text'])->map(fn ($w) => $w->component_code.'|'.$w->check_text)->all();
            $sort = (int) $to->watchPoints()->max('sort_order');
            foreach ($from->watchPoints()->where('activate', true)->get() as $w) {
                if (in_array($w->component_code.'|'.$w->check_text, $have, true)) {
                    continue;
                }
                MntWatchPoint::create(['asset_id' => $to->id_asset, 'component_code' => $w->component_code, 'check_text' => $w->check_text,
                    'method_text' => $w->method_text, 'normal_min' => $w->normal_min, 'normal_max' => $w->normal_max, 'measure_uom' => $w->measure_uom,
                    'frequency' => $w->frequency, 'checked_by_role' => $w->checked_by_role, 'sort_order' => ++$sort, 'activate' => true,
                    'created_by' => self::login(), 'updated_by' => self::login()]);
                $copied++;
            }
            MntLog::add('ASSET', $to->id_asset, 'Watch points copied', $copied.' from '.$from->asset_code);
        });

        return $copied;
    }

    private static function login(): string
    {
        return auth()->user()?->login ?? 'system';
    }
}
