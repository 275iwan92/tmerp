<?php

/*
 * REMARK
 * Tujuan       : Jadwal interview (app_interview_schedule) dari layar v2 hrm-rcr-interview-form: jadwalkan lamaran yang menunggu di tahap Interview HR / User, tandai Done, atau Cancel; interviewer diberi notifikasi.
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::recruitment.interview-schedule (diarsipkan di tmerp_unused/recruitment_interview_schedule_v1_2026-10-19.blade.php): antrean = lamaran IN_PROGRESS yang tahap sekarang (track requisition, stage_seq) berkode INTERVIEW_HR / INTERVIEW_USER dan belum punya jadwal SCHEDULED untuk tahap itu; simpan = id_application, stage_code, scheduled_at, interviewer_id, location, status SCHEDULED + notifikasi ke login interviewer; Mark Done / Cancel mengubah status. Baru (Jo 2026-10-19): antrean dicek server (v1 hanya menyaring sidebar dan jatuh ke INTERVIEW_HR bila tahap tak ada), interviewer harus user aktif, tanggal tidak boleh lampau, location maks 150, Done / Cancel hanya dari SCHEDULED, teks notifikasi English dengan tautan ke form.
 * Input        : id_application, date (Y-m-d), time (H:i), interviewer_id, location; jadwal untuk Done / Cancel.
 * Output       : tm2hrr.app_interview_schedule bertambah / berubah status, tm2sys.sys_notifications bertambah satu baris; ValidationException (kunci id_application / field / document) bila aturan dilanggar.
 * Dipakai oleh : routes/v2_hrm.php (v2.hrm.interview.save), layar hrm-rcr-interview-form, lookup rcr_interview_awaiting, tes InterviewScheduleServiceTest.
 * Status       : Aktif (T7k HRM, 2026-10-19).
 */

namespace App\Services\Recruitment;

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\AppInterviewSchedule;
use App\Models\Hrr\AppPipelineStageConfig;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class InterviewScheduleService
{
    public const STAGES = ['INTERVIEW_HR', 'INTERVIEW_USER'];

    /** Tahap interview lamaran ini sekarang, atau null bila lamaran tidak menunggu jadwal. */
    public static function pendingStage(AppApplication $a): ?AppPipelineStageConfig
    {
        if ($a->status !== 'IN_PROGRESS') {
            return null;
        }
        $a->loadMissing('vacancy.requisition');
        $stage = AppPipelineStageConfig::where('id_track', $a->vacancy?->requisition?->id_track)->where('stage_seq', $a->current_stage_seq)->whereIn('stage_code', self::STAGES)->first();
        if (! $stage || AppInterviewSchedule::where('id_application', $a->getKey())->where('stage_code', $stage->stage_code)->where('status', 'SCHEDULED')->exists()) {
            return null;
        }

        return $stage;
    }

    /** @return array<int, string> user aktif untuk pilihan interviewer */
    public static function interviewers(): array
    {
        return SecUser::where('activate', true)->orderBy('name')->pluck('name', 'id_user')->all();
    }

    public function schedule(array $in): AppInterviewSchedule
    {
        $d = Validator::make($in, [
            'id_application' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
            'interviewer_id' => ['required', 'integer', Rule::exists('tm2sys.sec_user', 'id_user')->where('activate', 1)],
            'location' => ['nullable', 'string', 'max:150'],
        ], ['id_application.required' => 'Choose the application.', 'date.after_or_equal' => 'The interview date cannot be in the past.'], ['date' => 'Date', 'time' => 'Time', 'interviewer_id' => 'Interviewer', 'location' => 'Location'])->validate();

        return DB::connection('tm2hrr')->transaction(function () use ($d) {
            $a = AppApplication::with('candidate')->whereKey((int) $d['id_application'])->lockForUpdate()->first();
            $stage = $a ? self::pendingStage($a) : null;
            if (! $stage) {
                throw ValidationException::withMessages(['id_application' => 'This application is not waiting for an interview schedule.']);
            }
            $at = Carbon::parse($d['date'].' '.$d['time']);
            $s = AppInterviewSchedule::create(['id_application' => $a->getKey(), 'stage_code' => $stage->stage_code, 'scheduled_at' => $at,
                'interviewer_id' => (int) $d['interviewer_id'], 'location' => trim((string) ($d['location'] ?? '')) ?: null, 'status' => 'SCHEDULED']);
            SysNotification::create(['user_id' => SecUser::whereKey($s->interviewer_id)->value('login'), 'title' => 'New interview schedule',
                'message' => 'You are scheduled to interview '.($a->candidate?->name ?? '').' on '.$at->format('d-M-Y H:i').'.', 'link' => route('v2.screen', 'hrm-rcr-interview-form').'?id='.$s->getKey(), 'is_read' => false]);

            return $s;
        });
    }

    public function markDone(AppInterviewSchedule $s): void
    {
        $this->close($s, 'DONE');
    }

    public function cancel(AppInterviewSchedule $s): void
    {
        $this->close($s, 'CANCELLED');
    }

    private function close(AppInterviewSchedule $s, string $to): void
    {
        DB::connection('tm2hrr')->transaction(function () use ($s, $to) {
            $fresh = AppInterviewSchedule::whereKey($s->getKey())->lockForUpdate()->firstOrFail();
            if ($fresh->status !== 'SCHEDULED') {
                throw ValidationException::withMessages(['document' => 'Only a scheduled interview can be changed.']);
            }
            $fresh->update(['status' => $to]);
        });
    }

    /** Riwayat tab History: satu baris jadwal dan statusnya. */
    public function history(AppInterviewSchedule $s): array
    {
        $who = SecUser::whereKey($s->interviewer_id)->value('name') ?? '';

        return [['at' => $s->scheduled_at?->toDateTimeString(), 'by' => (string) $who, 'action' => ucfirst(strtolower($s->status)), 'note' => trim($s->stage_code.' · '.($s->location ?? ''), ' ·')]];
    }

    /** @return list<array<string, mixed>> lamaran menunggu jadwal, untuk lookup */
    public static function awaitingSearch(string $q, int $limit = 30): array
    {
        return AppApplication::with(['candidate', 'vacancy.requisition'])->where('status', 'IN_PROGRESS')
            ->when($q !== '', fn ($w) => $w->whereHas('candidate', fn ($c) => $c->where('name', 'like', "%{$q}%")))
            ->orderByDesc('id_application')->get()
            ->map(fn (AppApplication $a) => ($s = self::pendingStage($a)) ? self::row($a, $s) : null)->filter()->take($limit)->values()->all();
    }

    /** @return array<string, mixed>|null */
    public static function awaitingFind(mixed $value): ?array
    {
        $a = AppApplication::with(['candidate', 'vacancy.requisition'])->find((int) $value);
        $s = $a ? self::pendingStage($a) : null;

        return $s ? self::row($a, $s) : null;
    }

    private static function row(AppApplication $a, AppPipelineStageConfig $s): array
    {
        $name = (string) ($a->candidate?->name ?? '—');

        return ['id' => (int) $a->id_application, 'candidate' => $name, 'stage' => (string) $s->stage_name, 'vacancy' => (string) ($a->vacancy?->title ?? ''), 'label' => $name.' — '.$s->stage_name];
    }
}
