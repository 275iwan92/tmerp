<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

// Master legacy HPP (docs/ddl/tmhpp.sql), diimpor 2026-09-18.
class MatFilmRate extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'mat_film_rate';

    protected $primaryKey = 'id_film_rate';

    protected $fillable = ['rate_value', 'label', 'sort_order', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['activate' => 'boolean'];
}
