<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmpShift extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_shift';

    protected $primaryKey = 'shift_id';

    protected $fillable = [
        'department_id',
        'shift_name',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(OrgDepartment::class, 'department_id', 'department_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(EmpShiftDetail::class, 'shift_id', 'shift_id');
    }

    private const DAY_ORDER = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    private const DAY_LABELS = ['Monday' => 'Sen', 'Tuesday' => 'Sel', 'Wednesday' => 'Rab', 'Thursday' => 'Kam', 'Friday' => 'Jum', 'Saturday' => 'Sab', 'Sunday' => 'Min'];

    // Ringkasan jam untuk grid (mis. "05:00-13:15 (Sen-Sab)") — pola sama
    // dengan implementasi ProdShift sebelumnya, mockup_master_produksi.html.
    public function scheduleSummary(): string
    {
        $working = $this->details
            ->filter(fn (EmpShiftDetail $d) => $d->start_time && $d->end_time)
            ->sortBy(fn (EmpShiftDetail $d) => array_search($d->day_of_week, self::DAY_ORDER))
            ->values();

        if ($working->isEmpty()) {
            return 'Jam belum diisi';
        }

        $first = $working->first();
        $sameHours = $working->every(
            fn (EmpShiftDetail $d) => $d->start_time?->format('H:i') === $first->start_time?->format('H:i')
                && $d->end_time?->format('H:i') === $first->end_time?->format('H:i')
        );

        $indices = $working->map(fn (EmpShiftDetail $d) => array_search($d->day_of_week, self::DAY_ORDER))->values();
        $isConsecutive = $indices->count() > 1 && $indices->last() - $indices->first() === $indices->count() - 1;

        $dayRange = $isConsecutive
            ? self::DAY_LABELS[$working->first()->day_of_week].'-'.self::DAY_LABELS[$working->last()->day_of_week]
            : $working->map(fn (EmpShiftDetail $d) => self::DAY_LABELS[$d->day_of_week])->implode(', ');

        if (! $sameHours) {
            return $dayRange.' (jam beda per hari)';
        }

        return $first->start_time->format('H:i').'-'.$first->end_time->format('H:i').' ('.$dayRange.')';
    }
}
