<?php

namespace App\Support\V2\Data;

use App\Models\Master\CustCustomer;
use App\Models\Master\CustCustomerContact;
use App\Models\Sdi\CrmActivity;
use App\Models\Sdi\QuoQuotation;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysConstant;
use App\Support\WorkCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

// CRM > Activities (user 2026-10-02): catatan setiap kontak dengan customer + tindak lanjutnya. Satu layar untuk mencatat
// dan untuk daftar "yang harus saya hubungi" (follow-up terbuka, berwarna menurut jatuh tempo).
// Follow-up selesai lewat dua jalan: tombol Follow-up Done, atau mencatat aktivitas baru DARI follow-up itu (Log Result).
// Yang boleh mengubah / menghapus = pembuatnya atau admin.
final class CrmActivities
{
    public const RESULT_TONES = ['POSITIVE' => 'success', 'NEUTRAL' => 'neutral', 'NEGATIVE' => 'danger', 'NO_ANSWER' => 'warn'];

    public static function find(int $id): ?CrmActivity
    {
        return CrmActivity::query()->with(['customer', 'quotation'])->find($id);
    }

    public static function rows(?int $customerId = null): array
    {
        $acts = CrmActivity::query()->with('quotation:id_quotation,quotation_no')->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->orderByDesc('activity_date')->orderByDesc('id_activity')->limit(2000)->get();
        $customers = CustCustomer::query()->whereIn('id_customer', $acts->pluck('customer_id')->unique())->pluck('customer_name', 'id_customer');
        $people = self::people($acts->pluck('created_by'));
        $due = self::dueResolver();

        return $acts->map(fn (CrmActivity $a) => [
            'id' => $a->id_activity, 'type' => t(CrmActivity::TYPES[$a->activity_type] ?? $a->activity_type), 'customer' => $customers[$a->customer_id] ?? '—',
            'date' => $a->activity_date?->toDateString(), 'topic' => t(CrmActivity::TOPICS[$a->topic] ?? $a->topic), 'contact' => $a->contact_person,
            'result' => $a->result ? t(CrmActivity::RESULTS[$a->result] ?? $a->result) : null, 'result_tone' => self::RESULT_TONES[$a->result] ?? 'neutral',
            'quotation' => $a->quotation?->quotation_no, 'notes' => $a->notes, 'next_date' => $a->next_action_date?->toDateString(), 'next' => $a->next_action,
            'by' => $people[$a->created_by] ?? $a->created_by,
        ] + $due($a))->all();
    }

    // Jatuh tempo follow-up untuk warna baris (pola CPDEL, rulebook C.32): lewat = merah, hari ini = oranye, H-1 .. H-n = kuning.
    // Hari dihitung hari kerja; batas kuning = konstanta CRM_FOLLOWUP_SOON_DAYS.
    public static function dueResolver(): \Closure
    {
        $cal = new WorkCalendar;
        $soon = (int) SysConstant::number('CRM_FOLLOWUP_SOON_DAYS', 2);
        $today = CarbonImmutable::today();

        return function (CrmActivity $a) use ($cal, $soon, $today): array {
            if (! $a->next_action_date) {
                return ['bucket' => 'NONE', 'due' => '—', 'due_tone' => 'neutral', 'row_tone' => null];
            }
            if ($a->next_done) {
                return ['bucket' => 'DONE', 'due' => t('Done'), 'due_tone' => 'success', 'row_tone' => null];
            }
            $d = CarbonImmutable::parse($a->next_action_date->toDateString());
            if ($d->lt($today)) {
                return ['bucket' => 'OVERDUE', 'due' => t('Late').' '.max(1, $cal->workdaysBetween($d, $today)).' '.t('days'), 'due_tone' => 'danger', 'row_tone' => 'danger'];
            }
            if ($d->eq($today)) {
                return ['bucket' => 'TODAY', 'due' => t('Today'), 'due_tone' => 'tangerine', 'row_tone' => 'tangerine'];
            }
            $n = max(1, $cal->workdaysBetween($today, $d));

            return $n <= $soon
                ? ['bucket' => 'SOON', 'due' => t('D-').$n, 'due_tone' => 'yellow', 'row_tone' => 'yellow']
                : ['bucket' => 'LATER', 'due' => $n.' '.t('days left'), 'due_tone' => 'neutral', 'row_tone' => null];
        };
    }

    public static function people(Collection $logins): array
    {
        return SecUser::query()->whereIn('login', $logins->filter()->unique()->all())->pluck('name', 'login')->map(fn ($n) => trim((string) $n))->filter()->all();
    }

    public static function customerOptions(): array
    {
        return CustomerOrder::customerOptions();
    }

    public static function contactOptions(int $customerId): array
    {
        return CustCustomerContact::query()->where('customer_id', $customerId)->where('activate', true)->orderByDesc('is_default')->orderBy('contact_name')->get()
            ->mapWithKeys(fn (CustCustomerContact $c) => [$c->id_customer_contact => $c->contact_name.($c->position ? ' · '.$c->position : '')])->all();
    }

    // Quotation customer ini yang masih bisa ditindaklanjuti (tidak dibatalkan), terbaru dulu.
    public static function quotationOptions(int $customerId): array
    {
        return QuoQuotation::query()->where('customer_id', $customerId)->where('stat_cancel', 0)->orderByDesc('id_quotation')->limit(100)
            ->get(['id_quotation', 'quotation_no', 'doc_status'])->mapWithKeys(fn ($q) => [$q->id_quotation => ($q->quotation_no ?: 'Draft #'.$q->id_quotation).' · '.$q->doc_status])->all();
    }

    // Isian awal form baru; ?from=<id> = mencatat hasil sebuah follow-up (customer, quotation dan topik ikut asalnya).
    public static function blank(int $customerId, ?CrmActivity $from = null): CrmActivity
    {
        return new CrmActivity(['customer_id' => $customerId, 'activity_type' => 'CALL', 'topic' => $from?->topic ?? 'RELATION', 'activity_date' => now()->toDateString(),
            'quotation_id' => $from?->quotation_id, 'contact_id' => $from?->contact_id, 'contact_person' => $from?->contact_person]);
    }

    public static function canEdit(CrmActivity $a): bool
    {
        $u = Auth::user();

        return ! $a->exists || $u->is_admin || $a->created_by === $u->login;
    }

    // Simpan baru / ubah. $fromId = follow-up asal yang ditutup karena hasilnya dicatat di aktivitas ini.
    public static function save(CrmActivity $a, array $in, ?int $fromId = null): CrmActivity
    {
        abort_unless(self::canEdit($a), 403, t('Only the person who recorded this activity can change it.'));
        $login = Auth::user()->login;
        $text = fn (string $k, int $max) => mb_substr(trim((string) ($in[$k] ?? '')), 0, $max) ?: null;
        $contacts = self::contactOptions((int) $a->customer_id);
        $contactId = isset($contacts[(int) ($in['contact_id'] ?? 0)]) ? (int) $in['contact_id'] : null;
        $quotations = self::quotationOptions((int) $a->customer_id);
        $next = trim((string) ($in['next_action_date'] ?? '')) ?: null;

        $a->fill([
            'activity_type' => isset(CrmActivity::TYPES[$in['activity_type'] ?? '']) ? $in['activity_type'] : 'OTHER',
            'topic' => isset(CrmActivity::TOPICS[$in['topic'] ?? '']) ? $in['topic'] : 'RELATION',
            'activity_date' => trim((string) ($in['activity_date'] ?? '')) ?: now()->toDateString(),
            'contact_id' => $contactId,
            'contact_person' => $text('contact_person', 150) ?: ($contactId ? CustCustomerContact::query()->whereKey($contactId)->value('contact_name') : null),
            'quotation_id' => isset($quotations[(int) ($in['quotation_id'] ?? 0)]) ? (int) $in['quotation_id'] : null,
            'result' => isset(CrmActivity::RESULTS[$in['result'] ?? '']) ? $in['result'] : null,
            'notes' => trim((string) ($in['notes'] ?? '')) ?: null,
            'next_action_date' => $next, 'next_action' => $next ? $text('next_action', 255) : null,
            'updated_by' => $login,
        ]);
        if (! $next) {
            $a->fill(['next_done' => false, 'next_done_at' => null, 'next_done_by' => null]);
        }
        if (! $a->exists) {
            $a->created_by = $login;
        }
        $a->save();
        if ($fromId && ($from = CrmActivity::query()->where('customer_id', $a->customer_id)->find($fromId)) && $from->followUpOpen()) {
            self::done($from, true);
        }

        return $a;
    }

    public static function done(CrmActivity $a, bool $done): void
    {
        $login = Auth::user()->login;
        $a->forceFill(['next_done' => $done, 'next_done_at' => $done ? now() : null, 'next_done_by' => $done ? $login : null, 'updated_by' => $login])->save();
    }

    public static function delete(CrmActivity $a): void
    {
        abort_unless(self::canEdit($a), 403, t('Only the person who recorded this activity can delete it.'));
        $a->delete();
    }
}
