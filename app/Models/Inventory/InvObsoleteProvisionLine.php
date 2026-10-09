<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

// Baris provisi stok usang: item x bin, umur tanpa pergerakan, kelas, persen, provisi.
class InvObsoleteProvisionLine extends Model
{
    protected $connection = 'tm2inv';

    protected $table = 'inv_obsolete_provision_line';

    protected $primaryKey = 'id_provision_line';

    public $timestamps = false;

    protected $guarded = [];
}
