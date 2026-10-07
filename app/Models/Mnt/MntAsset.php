<?php

namespace App\Models\Mnt;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Aset yang dirawat: mesin produksi, alat penunjang, kendaraan, fasilitas (AC, genset, bangunan). Mesin dan aset tetap
// ditautkan secara logis lewat machine_id / fixed_asset_id (database lain).
class MntAsset extends Model
{
    protected $connection = 'tm2mnt';

    protected $table = 'mnt_asset';

    protected $primaryKey = 'id_asset';

    protected $guarded = ['id_asset'];

    protected $casts = ['activate' => 'boolean'];

    public const CLASSES = ['MACHINE' => 'Production machine', 'SUPPORT' => 'Support equipment', 'VEHICLE' => 'Vehicle', 'FACILITY' => 'Facility'];

    public const METERS = ['NONE' => 'No meter', 'RUN_HOURS' => 'Run hours', 'IMPRESSIONS' => 'Impressions', 'ODOMETER_KM' => 'Odometer (km)'];

    public const CRITICALITY = ['A' => 'A - stops the line', 'B' => 'B - slows the line', 'C' => 'C - has a replacement'];

    public const CLASS_TONES = ['Production machine' => 'accent', 'Support equipment' => 'info', 'Vehicle' => 'warn', 'Facility' => 'neutral'];

    public function watchPoints(): HasMany
    {
        return $this->hasMany(MntWatchPoint::class, 'asset_id', 'id_asset')->orderBy('sort_order')->orderBy('id_watch_point');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(MntAssetPart::class, 'asset_id', 'id_asset')->orderBy('id_asset_part');
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(MntDocument::class, 'mnt_asset_document', 'asset_id', 'document_id')->orderBy('mnt_document.doc_no');
    }

    // Aset yang tautannya ke Master Mesin: kode, nama dan kritikalitas dibaca dari sana, tidak diubah di Maintenance.
    public function isLinkedMachine(): bool
    {
        return $this->machine_id !== null;
    }
}
