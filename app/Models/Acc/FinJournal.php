<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;

class FinJournal extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'fin_journal';

    protected $primaryKey = 'journal_id';

    protected $fillable = [
        'journal_code',
        'journal_name',
        'journal_type',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
