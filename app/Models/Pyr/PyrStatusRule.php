<?php

namespace App\Models\Pyr;

use Illuminate\Database\Eloquent\Model;

class PyrStatusRule extends Model
{
    protected $connection = 'tm2pyr';

    protected $table = 'pyr_status_rule';

    protected $primaryKey = 'id_rule';

    public $timestamps = false;

    protected $fillable = [
        'status_code',
        'component_code',
        'effect',
        'condition_note',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    // Cache in-memory ringan per payroll run -- rule matrix kecil (belasan
    // status x 6 komponen) dan tidak berubah selama 1 run berjalan.
    public static function effectFor(string $statusCode, string $componentCode, \DateTimeInterface $date): string
    {
        $rule = static::query()
            ->where('status_code', $statusCode)
            ->where('component_code', $componentCode)
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            ->first();

        return $rule->effect ?? 'FULL';
    }
}
