<?php

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;

// Katalog kode Maintenance (gaya SAP PM catalog): Component / Problem / Cause / Action. Nama English, tampil lewat kamus.
class MntCode extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'mnt_code';

    protected $primaryKey = 'id_code';

    protected $guarded = ['id_code'];

    protected $casts = ['activate' => 'boolean'];

    public const TYPES = ['COMPONENT' => 'Component', 'PROBLEM' => 'Problem', 'CAUSE' => 'Cause', 'ACTION' => 'Action'];

    // [code => name] aktif untuk satu jenis (dropdown form WO).
    public static function options(string $type): array
    {
        return static::query()->where('code_type', $type)->where('activate', true)->orderBy('sort_order')->orderBy('name')
            ->pluck('name', 'code')->all();
    }
}
