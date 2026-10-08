<?php

namespace App\Support\V2\Grid\Maint;

use App\Models\Mnt\MntAsset;
use App\Models\Mnt\MntMeterReading;
use App\Support\V2\Data\Maintenance;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

// Meter Readings -- Grid Lookup: pembacaan counter aset (jam jalan, impresi, odometer) diketik di sel. Pembacaan tidak pernah ditimpa:
// baris lama tidak bisa diubah, koreksi = baris baru. Mesin produksi juga diisi otomatis dari output produksi (kolom Source).
class MeterReadingLookup extends DbLookup
{
    public function slug(): string
    {
        return 'maintenance-meter';
    }

    public function title(): string
    {
        return 'Meter Readings';
    }

    public function module(): string
    {
        return 'MAINT';
    }

    public function moduleLabel(): string
    {
        return 'Maintenance';
    }

    protected function query(): Builder
    {
        return MntMeterReading::query()->orderByDesc('reading_at')->orderByDesc('id_meter_reading');
    }

    public function columns(): array
    {
        $meters = array_diff_key(array_map('t', MntAsset::METERS), ['NONE' => 1]);

        return [
            ['key' => 'asset_id', 'label' => 'Asset', 'type' => 'select', 'options' => Maintenance::assetOptions(), 'required' => true, 'width' => '260px'],
            ['key' => 'meter_type', 'label' => 'Meter', 'type' => 'select', 'options' => $meters, 'required' => true, 'width' => '150px'],
            ['key' => 'reading_at', 'label' => 'Date', 'type' => 'date', 'required' => true, 'width' => '140px'],
            ['key' => 'reading_value', 'label' => 'Reading', 'type' => 'number', 'required' => true, 'width' => '130px'],
            ['key' => 'source', 'label' => 'Source', 'readonly' => true, 'width' => '150px'],
            ['key' => 'note', 'label' => 'Note', 'width' => '260px'],
        ];
    }

    protected function defaults(): array
    {
        return ['source' => 'MANUAL'];
    }

    protected function rules(): array
    {
        return ['meter_type' => [Rule::in(['RUN_HOURS', 'IMPRESSIONS', 'ODOMETER_KM'])]];
    }

    // Baris lama tidak diubah; angka baru tidak boleh lebih rendah dari yang terakhir kecuali ada catatan.
    protected function check(array $in, ?int $id): ?string
    {
        if ($id !== null) {
            return t('A meter reading is never overwritten. Add a new row to correct it.');
        }
        $last = MntMeterReading::latestValue((int) ($in['asset_id'] ?? 0), (string) ($in['meter_type'] ?? ''));
        if ($last !== null && (float) ($in['reading_value'] ?? 0) < $last && trim((string) ($in['note'] ?? '')) === '') {
            return t('The reading is lower than the last one. Add a note (for example the counter was replaced).');
        }

        return null;
    }
}
