<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

// Katalog pertanyaan angka asisten (tabel paket packages/assistant). Kelas hitungnya = config('assistant.metrics').
class SysAssistantMetric extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_assistant_metric';

    protected $primaryKey = 'id_assistant_metric';

    protected $fillable = ['metric_key', 'title', 'phrases', 'module_code', 'min_level', 'sort_order', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['activate' => 'boolean'];
}
