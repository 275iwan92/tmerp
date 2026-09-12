<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

// Sumber baca-saja untuk engine migrasi (RawMaterialMigrationService,
// ProductMigrationService). tm2old = staging dump mentah ScriptCase V1 —
// JANGAN pernah write ke sini dari aplikasi.
class MstItem extends Model
{
    protected $connection = 'tm2old';

    protected $table = 'mst_item';

    protected $primaryKey = 'item_code';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = ['*'];
}
