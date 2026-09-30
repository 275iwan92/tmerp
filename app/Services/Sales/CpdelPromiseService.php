<?php

namespace App\Services\Sales;

use App\Models\Master\SysHoliday;
use App\Models\Master\SysOfficeHour;
use App\Models\Sdi\CoDelayReason;
use App\Models\Sdi\CoDeliveryNegotiation;
use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sys\SecUser;
use App\Support\V2\Data\Cpdel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// CPDEL dua janji (user 2026-10-01): janji ke customer = slot (ship_date + ship_time, Sales); janji produksi = ready_at (PPIC).
// Keduanya harus cocok TEPAT (hari yang sama, jam di dalam jam kantor Telasih & jam terima customer). Produksi tidak sanggup
// -> PPIC mengisi tanggal siap + alasan -> Sales nego ke customer (WhatsApp / telepon) lalu langsung mengubah slot (boleh Split)
// dengan log jelas -> customer menolak = eskalasi ke Operational Manager (lembur / prioritas / subkon / tawaran baru).
// Semua aksi tercatat di co_delivery_negotiation; janji awal (original_date) tidak pernah berubah.
final class CpdelPromiseService
{
    public const STATES = ['SHIPPED' => 'success', 'LATE' => 'danger', 'ESCALATED' => 'danger', 'NEED_NEGOTIATION' => 'warn',
        'WAITING_PPIC' => 'neutral', 'ALIGNED' => 'success'];

    public const CHANNELS = ['WHATSAPP' => 'WhatsApp', 'CALL' => 'Phone call', 'EMAIL' => 'Email', 'MEETING' => 'Meeting'];

    public const DECISIONS = ['OVERTIME' => 'Overtime', 'PRIORITY' => 'Re-prioritize production', 'SUBCON' => 'Subcontract', 'NEW_OFFER' => 'Make customer a new offer'];

    // Slot kirim; tanpa jam = akhir hari (jam belum disepakati).
    public static function slot(CoOrderDelivery $d): ?CarbonImmutable
    {
        return $d->ship_date ? CarbonImmutable::parse($d->ship_date->toDateString().' '.($d->ship_time ?? '23:59:00')) : null;
    }

    public static function state(CoOrderDelivery $d): string
    {
        $slot = self::slot($d);

        return match (true) {
            $d->status === 'SHIPPED' => 'SHIPPED',
            $slot && $d->ship_date->lt(CarbonImmutable::today()) => 'LATE',
            (bool) $d->escalated_at => 'ESCALATED',
            ! $d->ready_at => 'WAITING_PPIC',
            $slot && $d->ready_at->gt($slot) => 'NEED_NEGOTIATION',
            default => 'ALIGNED',
        };
    }

    // Selisih janji produksi vs slot dalam hari (positif = produksi terlambat dari janji customer).
    public static function gapDays(CoOrderDelivery $d): ?int
    {
        return $d->ready_at && $d->ship_date ? (int) $d->ship_date->startOfDay()->diffInDays($d->ready_at->startOfDay(), false) : null;
    }

    public static function canPlan(?SecUser $u): bool
    {
        return (bool) $u && ($u->is_admin || ($u->accessibleModules()->get('PROD')?->can_input ?? false));
    }

    public static function canSell(?SecUser $u): bool
    {
        return (bool) $u && ($u->is_admin || ($u->accessibleModules()->get('SALES')?->can_input ?? false));
    }

    // Keputusan eskalasi = level manager ke atas (level hanya untuk keputusan, model akses 2026-09-28).
    public static function canDecide(?SecUser $u): bool
    {
        return (bool) $u && ($u->is_admin || $u->maxMgmtLevel() >= 4);
    }

    public static function reasons(): array
    {
        return CoDelayReason::query()->where('activate', true)->orderBy('id_delay_reason')->get()
            ->mapWithKeys(fn ($r) => [$r->reason_code => t($r->reason_name, $r->getConnectionName().'.'.$r->getTable().'.reason_name').' ('.t($r->department).')'])->all();
    }

    // Masalah slot: kantor Telasih tutup / libur / di luar jam kantor / di luar jam terima customer. Kosong = slot sah.
    public static function slotProblems(?int $customerId, string $date, ?string $time): array
    {
        $day = CarbonImmutable::parse($date);
        $office = SysOfficeHour::query()->where('activate', true)->where('day_of_week', $day->dayOfWeekIso)->first();
        $problems = [];
        if (SysHoliday::isHoliday($day->toDateString())) {
            $problems[] = t('The date is a holiday (Holiday Calendar).');
        }
        if (! $office || ! $office->is_workday) {
            $problems[] = t('Telasih office is closed on that day (Office Hours).');
        } elseif ($time && ($time < substr($office->start_time, 0, 5) || $time > substr($office->end_time, 0, 5))) {
            $problems[] = t('The time is outside Telasih office hours').' ('.substr($office->start_time, 0, 5).'-'.substr($office->end_time, 0, 5).').';
        }
        $recv = DeliveryPromiseService::receivingHours($customerId, $day->toDateString());
        if (! $recv || ! $recv->is_open) {
            $problems[] = t('The customer does not receive goods on that day (Customer Receiving Hours).');
        } elseif ($time && ! DeliveryPromiseService::withinHours($recv, CarbonImmutable::parse($day->toDateString().' '.$time))) {
            $problems[] = t('The time is outside the customer receiving hours').' ('.DeliveryPromiseService::receivingText($recv).').';
        }

        return $problems;
    }

    // PPIC: janji produksi (barang siap). Lewat dari slot -> wajib alasan; status jadi Need negotiation untuk Sales.
    public static function commitReady(CoOrderDelivery $d, array $in, string $login): void
    {
        self::assertOpen($d);
        $v = validator($in, ['ready_date' => ['required', 'date'], 'ready_time' => ['required', 'date_format:H:i'],
            'reason_code' => ['nullable', Rule::exists(CoDelayReason::class, 'reason_code')], 'note' => ['nullable', 'string', 'max:500']],
            [], ['ready_date' => 'Ready date', 'ready_time' => 'Ready time', 'reason_code' => 'Reason'])->validate();
        $ready = CarbonImmutable::parse($v['ready_date'].' '.$v['ready_time']);
        $slot = self::slot($d);
        if ($slot && $ready->gt($slot) && empty($v['reason_code'])) {
            throw ValidationException::withMessages(['reason_code' => t('Ready after the agreed slot: choose the reason so Sales can negotiate.')]);
        }
        DB::connection('tm2sdi')->transaction(function () use ($d, $ready, $v, $login) {
            $d->update(['ready_at' => $ready, 'ready_source' => 'MANUAL', 'ready_by' => $login, 'updated_by' => $login]);
            self::log($d, 'READY_COMMITTED', 'PPIC', $login, ['ready_at' => $ready, 'reason_code' => $v['reason_code'] ?? null, 'note' => $v['note'] ?? null]);
        });
    }

    // Sales: slot baru hasil kesepakatan dengan customer (WhatsApp / telepon), boleh Split sebagian qty ke slot baru.
    public static function reschedule(CoOrderDelivery $d, array $in, string $login): ?CoOrderDelivery
    {
        self::assertOpen($d);
        $v = validator($in, ['ship_date' => ['required', 'date'], 'ship_time' => ['required', 'date_format:H:i'], 'split_qty' => ['nullable', 'numeric', 'gt:0'],
            'channel' => ['required', Rule::in(array_keys(self::CHANNELS))], 'customer_contact' => ['required', 'string', 'max:100'],
            'reason_code' => ['nullable', Rule::exists(CoDelayReason::class, 'reason_code')], 'note' => ['nullable', 'string', 'max:500']],
            [], ['ship_date' => 'Slot date', 'ship_time' => 'Slot time', 'split_qty' => 'Split qty', 'channel' => 'Channel',
                'customer_contact' => 'Customer contact', 'reason_code' => 'Reason'])->validate();
        if ($problems = self::slotProblems($d->order?->customer_id, $v['ship_date'], $v['ship_time'])) {
            throw ValidationException::withMessages(['ship_date' => implode(' ', $problems)]);
        }
        $split = ! empty($v['split_qty']);
        $dateChanged = $v['ship_date'] !== $d->ship_date?->toDateString();
        // Jam slot pertama kali disepakati (tanggal tetap) = bukan perubahan janji -> tanpa alasan.
        $firstTime = ! $split && ! $dateChanged && ! $d->ship_time;
        if (! $firstTime && empty($v['reason_code'])) {
            throw ValidationException::withMessages(['reason_code' => t('Changing an agreed slot needs a reason.')]);
        }
        if ($d->posted_at && ($split || $dateChanged)) {
            throw ValidationException::withMessages(['ship_date' => t('Posted to Production: only the slot time can change here; the date and qty are locked.')]);
        }
        $old = ['old_date' => $d->ship_date, 'old_time' => $d->ship_time, 'old_qty' => $d->ship_qty];
        $original = $d->original_date ?? $d->ship_date;

        return DB::connection('tm2sdi')->transaction(function () use ($d, $v, $login, $split, $dateChanged, $firstTime, $old, $original) {
            $d->update(['original_date' => $original]);
            $before = CoOrderDelivery::where('order_id', $d->order_id)->where('item_id', $d->item_id)->pluck('id_delivery')->all();
            if ($split || $dateChanged) {
                if ($error = Cpdel::split($d->id_delivery, $split ? (string) $v['split_qty'] : null, $v['ship_date'])) {
                    throw ValidationException::withMessages(['split_qty' => $error]);
                }
            }
            $target = $split ? CoOrderDelivery::where('order_id', $d->order_id)->where('item_id', $d->item_id)->whereNotIn('id_delivery', $before)->first() : $d->fresh();
            if (! $target) {
                throw ValidationException::withMessages(['split_qty' => t('The split line was not created.')]);
            }
            $target->update(['ship_time' => $v['ship_time'], 'original_date' => $original, 'escalated_at' => null, 'updated_by' => $login]);
            if (! $split) {
                $d->update(['escalated_at' => null]);
            }
            self::log($d, $split ? 'SPLIT' : ($firstTime ? 'SLOT_TIME_SET' : 'RESCHEDULED'), 'SALES', $login, $old + ['new_date' => $v['ship_date'], 'new_time' => $v['ship_time'],
                'new_qty' => $split ? $v['split_qty'] : $d->ship_qty, 'channel' => $v['channel'],
                'customer_contact' => $v['customer_contact'], 'reason_code' => $v['reason_code'] ?? null, 'note' => $v['note'] ?? null]);
            if ($split) {
                self::log($target, 'SPLIT', 'SALES', $login, $old + ['new_date' => $v['ship_date'], 'new_time' => $v['ship_time'], 'new_qty' => $v['split_qty'],
                    'reason_code' => $v['reason_code'] ?? null, 'channel' => $v['channel'], 'customer_contact' => $v['customer_contact'],
                    'note' => t('Split from line').' #'.$d->id_delivery.(empty($v['note']) ? '' : ' · '.$v['note'])]);
            }

            return $target;
        });
    }

    // Sales: customer menolak tawaran -> eskalasi ke Operational Manager.
    public static function customerRejected(CoOrderDelivery $d, array $in, string $login): void
    {
        self::assertOpen($d);
        $v = validator($in, ['channel' => ['required', Rule::in(array_keys(self::CHANNELS))], 'customer_contact' => ['required', 'string', 'max:100'],
            'note' => ['required', 'string', 'max:500']], [], ['channel' => 'Channel', 'customer_contact' => 'Customer contact', 'note' => 'What the customer said'])->validate();
        DB::connection('tm2sdi')->transaction(function () use ($d, $v, $login) {
            $d->update(['escalated_at' => now(), 'updated_by' => $login]);
            self::log($d, 'CUSTOMER_REJECTED', 'SALES', $login, $v);
        });
    }

    // Operational Manager: keputusan atas eskalasi. Produksi wajib mengisi ulang janji siap sesuai keputusan.
    public static function opsDecision(CoOrderDelivery $d, array $in, string $login): void
    {
        self::assertOpen($d);
        $v = validator($in, ['decision' => ['required', Rule::in(array_keys(self::DECISIONS))], 'note' => ['required', 'string', 'max:500']],
            [], ['decision' => 'Decision', 'note' => 'Instruction'])->validate();
        DB::connection('tm2sdi')->transaction(function () use ($d, $v, $login) {
            $d->update(['escalated_at' => null, 'ready_at' => $v['decision'] === 'NEW_OFFER' ? $d->ready_at : null, 'updated_by' => $login]);
            self::log($d, 'OPS_DECISION', 'OPS', $login, $v);
        });
    }

    public static function merge(array $ids, string $login): ?string
    {
        $rows = CoOrderDelivery::whereIn('id_delivery', $ids)->orderBy('ship_date')->get();
        if ($error = Cpdel::merge($ids)) {
            return $error;
        }
        foreach ($rows as $r) {
            if (CoOrderDelivery::whereKey($r->id_delivery)->exists()) {
                self::log($r, 'MERGED', 'SALES', $login, ['old_date' => $r->ship_date, 'old_time' => $r->ship_time, 'old_qty' => $r->ship_qty,
                    'new_qty' => CoOrderDelivery::whereKey($r->id_delivery)->value('ship_qty'), 'note' => t('Joined lines').' #'.$rows->pluck('id_delivery')->join(', #')]);
            }
        }

        return null;
    }

    private static function assertOpen(CoOrderDelivery $d): void
    {
        if ($d->status !== 'PLANNED') {
            throw ValidationException::withMessages(['status' => t('This line is already shipped.')]);
        }
    }

    private static function log(CoOrderDelivery $d, string $event, string $role, string $login, array $data): void
    {
        CoDeliveryNegotiation::create(['delivery_id' => $d->id_delivery, 'event' => $event, 'actor_role' => $role, 'created_by' => $login]
            + array_intersect_key($data, array_flip((new CoDeliveryNegotiation)->getFillable())));
    }
}
