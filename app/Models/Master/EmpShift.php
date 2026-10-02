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
        'shift_code',
        'shift_name',
        'color_hex',
        'capacity_per_wc',
        'description',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
        'capacity_per_wc' => 'integer',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(OrgDepartment::class, 'department_id', 'department_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(EmpShiftDetail::class, 'shift_id', 'shift_id');
    }

    public const DAY_ORDER = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public const DAY_LABELS = ['Monday' => 'Mon', 'Tuesday' => 'Tue', 'Wednesday' => 'Wed', 'Thursday' => 'Thu', 'Friday' => 'Fri', 'Saturday' => 'Sat', 'Sunday' => 'Sun'];

    public const DEFAULT_COLOR = '#2f6fed';

    public static function dayLabel(string $dayName): string
    {
        return t(self::DAY_LABELS[$dayName]);
    }

    // Pola jam 7 hari (index 0=Senin..6=Minggu) dalam bentuk array datar --
    // dipakai papan roster & editor Master Shift. Hari tanpa baris
    // emp_shift_detail / tanpa jam = libur.
    public function dayPattern(): array
    {
        $byDay = $this->details->keyBy('day_of_week');
        $pattern = [];

        foreach (self::DAY_ORDER as $i => $dayName) {
            $detail = $byDay->get($dayName);
            $on = $detail && $detail->start_time && $detail->end_time;

            $pattern[$i] = [
                'day' => $dayName,
                'label' => self::dayLabel($dayName),
                'on' => $on,
                'in' => $on ? $detail->start_time->format('H:i') : '',
                'out' => $on ? $detail->end_time->format('H:i') : '',
                'brk' => (int) ($detail->break_minutes ?? 0),
            ];
        }

        return $pattern;
    }

    // Menit kerja bersih 1 hari. Jam pulang <= jam masuk berarti lewat tengah
    // malam (mis. 21:15-05:00) -> tambah 1 hari sebelum dikurangi istirahat.
    public static function dayMinutes(array $day): int
    {
        if (! $day['on']) {
            return 0;
        }

        $in = self::toMinutes($day['in']);
        $out = self::toMinutes($day['out']);
        $span = $out - $in;

        if ($span <= 0) {
            $span += 1440;
        }

        return max(0, $span - $day['brk']);
    }

    public static function isNight(array $day): bool
    {
        return $day['on'] && self::toMinutes($day['out']) <= self::toMinutes($day['in']);
    }

    private static function toMinutes(string $time): int
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');

        return ((int) $h * 60) + (int) $m;
    }

    public function weekMinutes(): int
    {
        return array_sum(array_map(fn ($d) => self::dayMinutes($d), $this->dayPattern()));
    }

    public function activeDayCount(): int
    {
        return count(array_filter($this->dayPattern(), fn ($d) => $d['on']));
    }

    public static function formatMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '-';
        }

        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $h.'j'.($m ? ' '.$m.'m' : '');
    }

    // Ringkasan padat utk kolom kiri papan roster: hari berurutan dengan jam
    // sama digabung jadi 1 baris ("Sen-Jum 07:30-16:30", "Sab 07:30-15:30").
    public function summaryLines(): array
    {
        $pattern = $this->dayPattern();
        $lines = [];
        $i = 0;

        while ($i < 7) {
            if (! $pattern[$i]['on']) {
                $i++;

                continue;
            }

            $signature = fn (array $d) => $d['on'] ? $d['in'].'|'.$d['out'] : 'x';
            $j = $i;

            while ($j + 1 < 7 && $signature($pattern[$j + 1]) === $signature($pattern[$i])) {
                $j++;
            }

            $range = $i === $j ? $pattern[$i]['label'] : $pattern[$i]['label'].'-'.$pattern[$j]['label'];
            $lines[] = $range.' '.$pattern[$i]['in'].'-'.$pattern[$i]['out'];
            $i = $j + 1;
        }

        return $lines ?: [t('No active days yet')];
    }

    public function color(): string
    {
        return $this->color_hex ?: self::DEFAULT_COLOR;
    }

    public function capacity(): int
    {
        return max(1, (int) ($this->capacity_per_wc ?: 4));
    }

    // Ringkasan jam untuk grid (mis. "05:00-13:15 (Sen-Sab)") — pola sama
    // dengan implementasi ProdShift sebelumnya, mockup_master_produksi.html.
    public function scheduleSummary(): string
    {
        $working = $this->details
            ->filter(fn (EmpShiftDetail $d) => $d->start_time && $d->end_time)
            ->sortBy(fn (EmpShiftDetail $d) => array_search($d->day_of_week, self::DAY_ORDER))
            ->values();

        if ($working->isEmpty()) {
            return t('Hours not filled in yet');
        }

        $first = $working->first();
        $sameHours = $working->every(
            fn (EmpShiftDetail $d) => $d->start_time?->format('H:i') === $first->start_time?->format('H:i')
                && $d->end_time?->format('H:i') === $first->end_time?->format('H:i')
        );

        $indices = $working->map(fn (EmpShiftDetail $d) => array_search($d->day_of_week, self::DAY_ORDER))->values();
        $isConsecutive = $indices->count() > 1 && $indices->last() - $indices->first() === $indices->count() - 1;

        $dayRange = $isConsecutive
            ? self::dayLabel($working->first()->day_of_week).'-'.self::dayLabel($working->last()->day_of_week)
            : $working->map(fn (EmpShiftDetail $d) => self::dayLabel($d->day_of_week))->implode(', ');

        if (! $sameHours) {
            return str_replace(':days', $dayRange, t(':days (hours differ per day)'));
        }

        return $first->start_time->format('H:i').'-'.$first->end_time->format('H:i').' ('.$dayRange.')';
    }
}
