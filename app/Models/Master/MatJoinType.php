<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

// Master legacy HPP (docs/ddl/tmhpp.sql), diimpor 2026-09-18.
class MatJoinType extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'mat_join_type';

    protected $primaryKey = 'id_join_type';

    protected $fillable = ['code', 'label', 'default_rate', 'sort_order', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['activate' => 'boolean'];
}
