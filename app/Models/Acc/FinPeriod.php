<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinPeriod extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'fin_period';

    protected $primaryKey = 'period_id';

    protected $guarded = ['period_id'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'closed_at' => 'datetime', 'is_adjustment' => 'boolean'];

    public function logs(): HasMany
    {
        return $this->hasMany(FinPeriodLog::class, 'period_id', 'period_id')->orderByDesc('log_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(FinCloseTask::class, 'period_id', 'period_id')->orderBy('step_no');
    }
}
