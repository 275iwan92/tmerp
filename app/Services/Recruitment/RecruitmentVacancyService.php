<?php

/*
 * REMARK
 * Tujuan       : Lowongan kerja (vac_vacancy) dari layar v2 hrm-rcr-vacancy-form: Save = terbitkan lowongan dari requisition APPROVED (satu langkah, tampil di portal karir), Close vacancy menutup lowongan (kandidat tidak bisa melamar lagi); lowongan yang ada baca-saja.
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::recruitment.vacancy (diarsipkan di tmerp_unused/recruitment_vacancy_v1_2026-10-19.blade.php): publish = vacancy_code VAC{yy}{nomor 5 digit}, id_requisition, channel, title, description, is_published = 1, published_at = sekarang; Close = closed_at = sekarang. Baru (Jo 2026-10-19): nomor berikutnya diambil dari nomor terbesar tahun itu dalam transaksi (v1 menghitung jumlah baris sehingga bentrok bila ada yang terhapus), requisition wajib APPROVED dicek di server, channel termasuk GLINTS (dipakai 4 lowongan, tidak ada di daftar v1), Close hanya untuk lowongan yang masih terbuka.
 * Input        : id_requisition, channel, title, description; lowongan untuk Close.
 * Output       : tm2hrr.vac_vacancy bertambah atau closed_at terisi; ValidationException (kunci document / field) bila aturan dilanggar.
 * Dipakai oleh : routes/v2_hrm.php (v2.hrm.vacancy.save), layar hrm-rcr-vacancy-form, tes RecruitmentVacancyServiceTest.
 * Status       : Aktif (T7g HRM, 2026-10-19).
 */

namespace App\Services\Recruitment;

use App\Models\Hrr\RcrRequisition;
use App\Models\Hrr\VacVacancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class RecruitmentVacancyService
{
    public const CHANNELS = ['PORTAL_SENDIRI' => 'Own Portal', 'JOBSTREET' => 'Jobstreet', 'LINKEDIN' => 'LinkedIn', 'GLINTS' => 'Glints', 'REFERRAL' => 'Referral', 'LAINNYA' => 'Other'];

    public function publish(array $in): VacVacancy
    {
        $d = Validator::make($in, [
            'id_requisition' => ['required', 'integer', Rule::exists('tm2hrr.rcr_requisition', 'id_requisition')->where('status', 'APPROVED')],
            'channel' => ['required', Rule::in(array_keys(self::CHANNELS))],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
        ], ['id_requisition.exists' => 'Choose an approved requisition.'], ['id_requisition' => 'Requisition', 'title' => 'Vacancy title'])->validate();

        return DB::connection('tm2hrr')->transaction(function () use ($d) {
            $yy = now()->format('y');
            $last = (string) VacVacancy::where('vacancy_code', 'like', "VAC{$yy}%")->lockForUpdate()->max('vacancy_code');
            $next = (int) substr($last, 5) + 1;

            return VacVacancy::create(['vacancy_code' => "VAC{$yy}".str_pad((string) $next, 5, '0', STR_PAD_LEFT), 'id_requisition' => $d['id_requisition'], 'channel' => $d['channel'],
                'title' => trim($d['title']), 'description' => ($d['description'] ?? null) ?: null, 'is_published' => true, 'published_at' => now()]);
        });
    }

    public function close(VacVacancy $v): void
    {
        DB::connection('tm2hrr')->transaction(function () use ($v) {
            $fresh = VacVacancy::whereKey($v->getKey())->lockForUpdate()->firstOrFail();
            if (! $fresh->is_published || $fresh->closed_at) {
                throw ValidationException::withMessages(['document' => 'This vacancy is not open.']);
            }
            $fresh->update(['closed_at' => now()]);
        });
    }

    /** Status tampilan: Open, Closed atau Draft. */
    public static function status(VacVacancy $v): string
    {
        return $v->closed_at ? 'Closed' : ($v->is_published ? 'Open' : 'Draft');
    }

    /** Riwayat tab History: terbaru di atas. */
    public function history(VacVacancy $v): array
    {
        $rows = [['at' => $v->created_at?->toDateTimeString(), 'by' => '', 'action' => 'Created', 'note' => '']];
        if ($v->published_at) {
            $rows[] = ['at' => $v->published_at->toDateTimeString(), 'by' => '', 'action' => 'Published', 'note' => self::CHANNELS[$v->channel] ?? (string) $v->channel];
        }
        if ($v->closed_at) {
            $rows[] = ['at' => $v->closed_at->toDateTimeString(), 'by' => '', 'action' => 'Closed', 'note' => ''];
        }

        return array_reverse($rows);
    }

    /** @return list<array<string, mixed>> requisition APPROVED untuk lookup (memilih mengisi judul lowongan) */
    public static function requisitionSearch(string $q, int $limit = 30): array
    {
        return RcrRequisition::where('status', 'APPROVED')->where(fn ($w) => $w->where('position_title', 'like', "%{$q}%")->orWhere('requisition_no', 'like', "%{$q}%"))
            ->orderByDesc('id_requisition')->limit($limit)->get()->map(fn (RcrRequisition $r) => self::requisitionRow($r))->all();
    }

    /** @return array<string, mixed>|null */
    public static function requisitionFind(mixed $value): ?array
    {
        $r = RcrRequisition::find((int) $value);

        return $r ? self::requisitionRow($r) : null;
    }

    private static function requisitionRow(RcrRequisition $r): array
    {
        return ['id' => (int) $r->id_requisition, 'no' => (string) $r->requisition_no, 'position' => (string) $r->position_title, 'headcount' => (string) $r->headcount,
            'label' => trim($r->requisition_no.' — '.$r->position_title), 'title' => (string) $r->position_title];
    }
}
