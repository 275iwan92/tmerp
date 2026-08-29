<?php

namespace App\Models\Hrr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VacVacancy extends Model
{
    protected $connection = 'tm2hrr';

    protected $table = 'vac_vacancy';

    protected $primaryKey = 'id_vacancy';

    protected $fillable = [
        'vacancy_code', 'id_requisition', 'channel', 'title', 'description',
        'is_published', 'published_at', 'closed_at',
    ];

    protected $casts = ['is_published' => 'boolean', 'published_at' => 'datetime', 'closed_at' => 'datetime'];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(RcrRequisition::class, 'id_requisition', 'id_requisition');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(AppApplication::class, 'id_vacancy', 'id_vacancy');
    }
}
