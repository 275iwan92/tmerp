<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

// Seri nomor dokumen (format = data, user 2026-10-02): nomor terbit lewat App\Support\Docs\DocSeries, tidak diketik.
class SysDocSeries extends Model
{
    public const RESETS = ['MONTH' => 'Every month', 'YEAR' => 'Every year', 'NONE' => 'Never'];

    protected $connection = 'tm2sys';

    protected $table = 'sys_doc_series';

    protected $primaryKey = 'id_doc_series';

    protected $fillable = ['series_code', 'series_name', 'number_format', 'reset_period', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['activate' => 'boolean'];
}
