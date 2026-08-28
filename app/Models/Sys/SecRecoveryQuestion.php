<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

// Daftar pertanyaan keamanan untuk fitur Lupa Password 4-pertanyaan
// (keputusan user 2026-08-27: tabel, bukan hardcode array -- ikut prefix
// sec_ krn ini tabel security, sejalur dgn sec_user/sec_group/sec_module).
class SecRecoveryQuestion extends Model
{
    public $timestamps = true;

    protected $connection = 'tm2sys';

    protected $table = 'sec_recovery_question';

    protected $primaryKey = 'id_question';

    protected $fillable = [
        'question_text', 'activate', 'sort_order', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
    ];
}
