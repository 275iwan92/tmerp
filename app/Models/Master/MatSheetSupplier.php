<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

// Master legacy HPP (docs/ddl/tmhpp.sql), diimpor 2026-09-18.
class MatSheetSupplier extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'mat_sheet_supplier';

    protected $primaryKey = 'id_sheet_supplier';

    protected $fillable = ['code', 'name', 'sort_order', 'notes', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['activate' => 'boolean'];
}
