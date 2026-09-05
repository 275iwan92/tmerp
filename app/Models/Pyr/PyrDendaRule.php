<?php

namespace App\Models\Pyr;

use Illuminate\Database\Eloquent\Model;

class PyrDendaRule extends Model
{
    protected $connection = 'tm2pyr';

    protected $table = 'pyr_denda_rule';

    protected $primaryKey = 'id_denda';

    public $timestamps = false;

    protected $fillable = [
        'status_code',
        'denda_amount',
        'per_event',
        'condition_note',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'denda_amount' => 'decimal:2',
        'per_event' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public static function forStatus(string $statusCode, \DateTimeInterface $date): ?self
    {
        return static::query()
            ->where('status_code', $statusCode)
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            ->first();
    }
}
