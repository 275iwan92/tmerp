<?php

namespace App\Support\V2\Data;

use App\Models\Master\SysHoliday;
use App\Models\Master\SysOfficeHour;
use App\Support\Localization\Dictionary;
use Illuminate\Validation\Rule;

// Kalender kerja Telasih: hari libur (sys_holiday) + jam kantor (sys_office_hour). Layar: Master Data > Holiday Calendar & Office Hours.
final class WorkCalendar
{
    public const TYPES = ['NATIONAL' => 'National holiday', 'COLLECTIVE_LEAVE' => 'Collective leave', 'COMPANY' => 'Company holiday'];

    public const TONES = ['NATIONAL' => 'danger', 'COLLECTIVE_LEAVE' => 'warn', 'COMPANY' => 'info'];

    public static function year(): int
    {
        return max(2000, min(2100, (int) request('year', now()->year)));
    }

    public static function typeLabel(?string $code): string
    {
        return Dictionary::t(self::TYPES[$code] ?? (string) $code, self::context('holiday_type'));
    }

    public static function name(?string $en): string
    {
        return Dictionary::t($en, self::context('holiday_name'));
    }

    // Konteks kamus nilai database = <koneksi>.<tabel>.<kolom> (nama koneksi, sama di semua situs).
    private static function context(string $column): string
    {
        $m = new SysHoliday;

        return $m->getConnectionName().'.'.$m->getTable().'.'.$column;
    }

    // Tanda kalender tahunan: tanggal libur aktif -> warna per jenis, klik = buka form.
    public static function marks(int $year, string $openUrl): array
    {
        return SysHoliday::query()->whereYear('holiday_date', $year)->where('activate', true)->orderBy('holiday_date')->get()
            ->mapWithKeys(fn (SysHoliday $h) => [$h->holiday_date->toDateString() => ['label' => self::name($h->holiday_name),
                'tone' => self::TONES[$h->holiday_type] ?? 'neutral', 'url' => str_replace('{id}', (string) $h->id_holiday, $openUrl)]])->all();
    }

    // Hari non-kerja menurut jam kantor, format Carbon dayOfWeek (0 = Minggu).
    public static function offDays(): array
    {
        return SysOfficeHour::query()->where('activate', true)->where('is_workday', false)->pluck('day_of_week')
            ->map(fn ($iso) => (int) $iso % 7)->all();
    }

    public static function saveHoliday(?SysHoliday $h, array $in): SysHoliday
    {
        $data = validator($in, [
            'holiday_date' => ['required', 'date', Rule::unique(SysHoliday::class, 'holiday_date')->ignore($h?->id_holiday, 'id_holiday')],
            'holiday_name' => ['required', 'string', 'max:150'],
            'holiday_type' => ['required', Rule::in(array_keys(self::TYPES))],
        ], [], ['holiday_date' => t('Date'), 'holiday_name' => t('Holiday Name'), 'holiday_type' => t('Type')])->validate();
        $login = auth()->user()?->login;
        $h ??= new SysHoliday(['activate' => true, 'created_by' => $login]);
        $h->fill($data + ['updated_by' => $login])->save();

        return $h;
    }
}
