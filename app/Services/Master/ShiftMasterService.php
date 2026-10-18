<?php

/*
 * REMARK
 * Tujuan       : Master Shift dari layar v2 md-shift-form: simpan shift (nama, departemen, kode, aktif) dan jam kerja tujuh hari (jam masuk, istirahat, jam pulang), aktif / nonaktif.
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::master.shift (diarsipkan di tmerp_unused/master_shift_v1_2026-10-19.blade.php): nama wajib maks 150, departemen wajib, hari tanpa jam masuk dan jam pulang = tidak beroperasi (baris detail dihapus). Disetujui Jo 2026-10-19: kode shift wajib dan unik, menit istirahat dihitung dari Break start dan Break end, jam masuk tanpa jam pulang (atau sebaliknya) ditolak.
 * Input        : Isian form (nama, departemen, kode, start_/bout_/bin_/end_ per nama hari Inggris), user.
 * Output       : tm2mst.emp_shift dan emp_shift_detail berubah; break_minutes = Break end - Break start (menit, lewat tengah malam bila selesai <= mulai); ValidationException bila aturan dilanggar.
 * Dipakai oleh : routes/v2_master.php (v2.md.shift.save), layar md-shift-form, tes ShiftMasterServiceTest.
 * Status       : Aktif. Papan roster (EmpShift::dayPattern) membaca break_minutes, jadi kolom itu kini selalu mengikuti jam istirahat.
 */

namespace App\Services\Master;

use App\Models\Master\EmpShift;
use App\Models\Master\EmpShiftDetail;
use App\Models\Master\OrgDepartment;
use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ShiftMasterService
{
    public function save(?EmpShift $s, array $in, SecUser $user): EmpShift
    {
        $in['shift_code'] = strtoupper(trim((string) ($in['shift_code'] ?? '')));
        $d = Validator::make($in, [
            'shift_name' => ['required', 'string', 'max:150'],
            'department_id' => ['required', 'integer', 'exists:tm2mst.org_department,department_id'],
            'shift_code' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9_\-]+$/', 'unique:tm2mst.emp_shift,shift_code'.($s ? ','.$s->shift_id.',shift_id' : '')],
        ], [], ['shift_name' => 'Shift name', 'department_id' => 'Department', 'shift_code' => 'Shift code'])->validate();

        $days = $this->days($in);

        return DB::connection('tm2mst')->transaction(function () use ($s, $d, $days, $user) {
            $payload = ['shift_name' => $d['shift_name'], 'department_id' => $d['department_id'], 'shift_code' => $d['shift_code'], 'updated_by' => $user->login];
            if ($s) {
                $s->update($payload);
            } else {
                $s = EmpShift::create($payload + ['activate' => true, 'created_by' => $user->login]);
            }

            foreach ($days as $day => $row) {
                if ($row === null) {
                    EmpShiftDetail::where('shift_id', $s->shift_id)->where('day_of_week', $day)->delete();

                    continue;
                }
                EmpShiftDetail::updateOrCreate(['shift_id' => $s->shift_id, 'day_of_week' => $day], $row + ['updated_by' => $user->login]);
            }

            return $s;
        });
    }

    public function setActive(EmpShift $s, bool $active, SecUser $user): void
    {
        $s->update(['activate' => $active, 'updated_by' => $user->login]);
    }

    /**
     * Jam tujuh hari dari isian form: nama hari => null (tidak beroperasi) atau kolom emp_shift_detail.
     *
     * @return array<string, array<string, mixed>|null>
     */
    public function days(array $in): array
    {
        $out = [];
        $errors = [];
        foreach (EmpShift::DAY_ORDER as $day) {
            $t = [];
            foreach (['start' => 'start', 'bout' => 'break_out', 'bin' => 'break_in', 'end' => 'end'] as $key => $col) {
                $raw = trim((string) ($in[$key.'_'.$day] ?? ''));
                $t[$col] = $raw === '' ? null : $this->time($raw);
                if ($raw !== '' && $t[$col] === null) {
                    $errors[$key.'_'.$day] = $day.': '.$raw.' is not a valid time (use HH:MM, 24 hours).';
                }
            }
            if (($t['start'] === null) !== ($t['end'] === null)) {
                $errors['start_'.$day] = $day.': fill both Clock in and Clock out, or leave both empty for a non-operating day.';
            } elseif (($t['break_out'] === null) !== ($t['break_in'] === null)) {
                $errors['bout_'.$day] = $day.': fill both Break start and Break end, or leave both empty.';
            } elseif ($t['start'] === null && $t['break_out'] !== null) {
                $errors['bout_'.$day] = $day.': a break needs Clock in and Clock out.';
            } elseif ($t['break_out'] !== null && $t['break_out'] === $t['break_in']) {
                $errors['bout_'.$day] = $day.': Break start and Break end are the same time.';
            }
            $out[$day] = $t['start'] === null ? null : ['start_time' => $t['start'], 'break_out' => $t['break_out'], 'break_in' => $t['break_in'],
                'break_minutes' => $t['break_out'] === null ? null : $this->minutesBetween($t['break_out'], $t['break_in']), 'end_time' => $t['end']];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    /** "H:MM" / "HH:MM" -> "HH:MM", null bila bukan jam 24 jam yang sah. */
    private function time(string $raw): ?string
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $raw, $m) || (int) $m[1] > 23 || (int) $m[2] > 59) {
            return null;
        }

        return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
    }

    /** Menit dari jam mulai ke jam selesai; selesai <= mulai berarti lewat tengah malam. */
    private function minutesBetween(string $from, string $to): int
    {
        $span = $this->minutes($to) - $this->minutes($from);

        return $span > 0 ? $span : $span + 1440;
    }

    private function minutes(string $hhmm): int
    {
        [$h, $m] = explode(':', $hhmm);

        return (int) $h * 60 + (int) $m;
    }

    /** @return array<int, string> */
    public function departmentOptions(): array
    {
        return OrgDepartment::where('activate', true)->orderBy('department_name')->pluck('department_name', 'department_id')->all();
    }

    /** Riwayat tab History: terbaru di atas. */
    public function history(EmpShift $s): array
    {
        $rows = [['at' => $s->created_at?->toDateTimeString(), 'by' => $s->created_by, 'action' => 'Created', 'note' => '']];
        if ($s->updated_at && $s->updated_at != $s->created_at) {
            $rows[] = ['at' => $s->updated_at->toDateTimeString(), 'by' => $s->updated_by, 'action' => 'Last modified', 'note' => ''];
        }

        return array_reverse($rows);
    }
}
