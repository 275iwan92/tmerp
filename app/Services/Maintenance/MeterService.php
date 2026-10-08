<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Mnt\MntAsset;
use App\Models\Mnt\MntMeterReading;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Meter aset (jam jalan, impresi, odometer). Pembacaan tidak pernah ditimpa; koreksi counter fisik = baris baru.
// Mesin produksi bisa diakumulasi otomatis dari output produksi, mulai dari pembacaan awal yang diketik user.
final class MeterService
{
    public function __construct(private ?string $login = null)
    {
        $this->login ??= auth()->user()?->login ?? 'system';
    }

    // Catatan wajib bila angka turun (counter diganti atau dikoreksi); selain itu meter hanya naik.
    public function record(MntAsset $asset, string $type, float $value, ?CarbonImmutable $at = null, string $source = 'MANUAL', ?string $note = null): MntMeterReading
    {
        if (! array_key_exists($type, MntAsset::METERS) || $type === 'NONE') {
            throw ValidationException::withMessages(['meter' => t('Pick a meter type.')]);
        }
        $last = MntMeterReading::latestValue($asset->id_asset, $type);
        if ($last !== null && $value < $last && trim((string) $note) === '') {
            throw ValidationException::withMessages(['meter' => t('The reading is lower than the last one. Add a note (for example the counter was replaced).')]);
        }

        return MntMeterReading::create(['asset_id' => $asset->id_asset, 'meter_type' => $type, 'reading_at' => $at ?? now(), 'reading_value' => $value,
            'source' => $source, 'note' => $note, 'created_by' => $this->login, 'updated_by' => $this->login]);
    }

    // Tambahkan output produksi sejak pembacaan terakhir ke meter mesin. Tanpa pembacaan awal tidak ada dasar angka, jadi dilewati.
    // RUN_HOURS = jam dari operasi WO yang selesai di mesin itu; IMPRESSIONS = jumlah output bagus operasi tersebut.
    // @return array<string, float> kode aset => penambahan
    public function accumulateFromProduction(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $added = [];
        $assets = MntAsset::where('activate', true)->whereNotNull('machine_id')->whereIn('meter_type', ['RUN_HOURS', 'IMPRESSIONS'])->get();
        foreach ($assets as $asset) {
            $last = MntMeterReading::where('asset_id', $asset->id_asset)->where('meter_type', $asset->meter_type)->orderByDesc('reading_at')->orderByDesc('id_meter_reading')->first();
            if (! $last) {
                continue;
            }
            $ops = DB::connection('tm2prd')->table('prd_wo_operation')->where('machine_id', $asset->machine_id)->whereNotNull('actual_start')
                ->where('actual_finish', '>', $last->reading_at)->where('actual_finish', '<=', $now)->get(['operation_id', 'actual_start', 'actual_finish']);
            if ($ops->isEmpty()) {
                continue;
            }
            $delta = $asset->meter_type === 'RUN_HOURS'
                ? $ops->sum(fn ($o) => CarbonImmutable::parse($o->actual_start)->diffInMinutes(CarbonImmutable::parse($o->actual_finish), true)) / 60
                : (float) DB::connection('tm2prd')->table('prd_wo_operation_output')->whereIn('operation_id', $ops->pluck('operation_id'))->sum('qty_good');
            if ($delta <= 0) {
                continue;
            }
            MntMeterReading::create(['asset_id' => $asset->id_asset, 'meter_type' => $asset->meter_type, 'reading_at' => $now, 'reading_value' => round($last->reading_value + $delta, 2),
                'source' => 'PRODUCTION', 'note' => $ops->count().' production operations', 'created_by' => $this->login, 'updated_by' => $this->login]);
            $added[$asset->asset_code] = round($delta, 2);
        }

        return $added;
    }
}
