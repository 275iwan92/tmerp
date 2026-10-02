<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

// Satu pertanyaan user + jawaban asisten (tabel paket packages/assistant). Hanya dibaca di sini (Setup > Assistant Log).
class SysAssistantMessage extends Model
{
    public const UPDATED_AT = null;

    protected $connection = 'tm2sys';

    protected $table = 'sys_assistant_message';

    protected $primaryKey = 'id_assistant_message';

    protected $casts = ['answered' => 'boolean', 'model_used' => 'boolean', 'reply' => 'array', 'created_at' => 'datetime'];
}
