<?php

namespace App\Models\Hrr;

use Illuminate\Database\Eloquent\Model;

class RcrTrack extends Model
{
    public $timestamps = false;

    protected $connection = 'tm2hrr';

    protected $table = 'rcr_track';

    protected $primaryKey = 'id_track';

    protected $fillable = ['code', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
