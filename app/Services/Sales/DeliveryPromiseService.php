<?php

namespace App\Services\Sales;

use App\Models\Master\CustReceivingHour;
use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sdi\DoDelivery;
use App\Models\Sdi\DoDeliveryPod;
use App\Models\Sdi\DoLine;
use Carbon\CarbonImmutable;
use Devlib\Locale;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// KPI janji kirim (user 2026-10-01, KPI semua departemen): janji DITEPATI hanya bila truk TIBA di hari yang dijanjikan,
// tidak lewat dari jam slot (bila slot berjam), dan barang DITERIMA. Tiba telat / customer tutup / ditolak / tidak jadi kirim
// = meleset, dengan penyebab yang menunjuk departemen penanggung jawab.
final class DeliveryPromiseService
{
    public const RESULTS = ['RECEIVED' => 'Received', 'PARTIAL' => 'Partially received', 'CUSTOMER_CLOSED' => 'Customer closed, not received',
        'REJECTED' => 'Rejected by customer', 'NOT_SHIPPED' => 'Not shipped', 'ACCIDENT' => 'Accident on the way'];

    // Penyebab meleset -> departemen yang bertanggung jawab (dasar KPI per departemen).
    public const CAUSES = ['TRUCK_LATE' => ['Truck late / route', 'Delivery'], 'PRODUCTION_NOT_READY' => ['Production not ready', 'Production'],
        'SLOT_OUTSIDE_CUSTOMER_HOURS' => ['Slot agreed outside customer hours', 'Sales'], 'MATERIAL_LATE' => ['Material late', 'Purchasing'],
        'QUALITY' => ['Quality problem', 'Production'], 'CUSTOMER' => ['Customer side (changed plan, no one to receive)', 'Customer'],
        'ACCIDENT' => ['Accident on the way', 'Delivery'], 'OTHER' => ['Other', 'Other']];

    // Mengapa customer menerima kurang dari yang dikirim (per baris Surat Jalan). Yang tidak diterima tidak ditagih.
    public const DIFF_REASONS = ['DAMAGED' => 'Damaged in transit', 'RETURNED' => 'Returned by customer', 'SHORT' => 'Short shipped', 'OTHER' => 'Other'];

    // Slot yang dijanjikan ke customer untuk satu Surat Jalan = slot CPDEL paling awal dari baris yang ditarik ke DO ini
    // (tanggal + jam); tanpa tautan CPDEL = rencana kirim DO tanpa jam.
    public static function promise(DoDelivery $do): array
    {
        $slot = CoOrderDelivery::query()->whereIn('id_delivery', DoLine::where('delivery_id', $do->id_delivery)->whereNotNull('co_delivery_id')->pluck('co_delivery_id'))
            ->orderBy('ship_date')->orderByRaw('ship_time IS NULL')->orderBy('ship_time')->first();

        return $slot?->ship_date
            ? ['date' => $slot->ship_date->toDateString(), 'time' => $slot->ship_time]
            : ['date' => ($do->scheduled_ship_date ?? $do->do_date)?->toDateString(), 'time' => null];
    }

    public static function kept(string $result, ?CarbonImmutable $arrived, ?string $promiseDate, ?string $promiseTime): bool
    {
        if ($result !== 'RECEIVED' || ! $arrived || ! $promiseDate || $arrived->toDateString() !== $promiseDate) {
            return false;
        }

        return $promiseTime === null || $arrived->format('H:i') <= substr($promiseTime, 0, 5);
    }

    // Jam terima customer pada tanggal itu: baris customer sendiri bila ada, selain itu jam default.
    public static function receivingHours(?int $customerId, string $date): ?CustReceivingHour
    {
        $dow = CarbonImmutable::parse($date)->dayOfWeekIso;
        $rows = CustReceivingHour::query()->where('activate', true)->where('day_of_week', $dow)
            ->where(fn ($q) => $q->whereNull('customer_id')->when($customerId, fn ($q) => $q->orWhere('customer_id', $customerId)))->get();

        return $rows->firstWhere('customer_id', $customerId) ?? $rows->firstWhere('customer_id', null);
    }

    public static function receivingText(?CustReceivingHour $h): string
    {
        if (! $h || ! $h->is_open) {
            return t('Closed that day');
        }
        $t = fn ($v) => substr((string) $v, 0, 5);

        return $t($h->open_time).'-'.$t($h->close_time).($h->break_start ? ' ('.t('break').' '.$t($h->break_start).'-'.$t($h->break_end).')' : '');
    }

    public static function withinHours(?CustReceivingHour $h, CarbonImmutable $at): bool
    {
        if (! $h || ! $h->is_open) {
            return false;
        }
        $t = $at->format('H:i:s');
        $inBreak = $h->break_start && $t >= $h->break_start && $t < $h->break_end;

        return $t >= $h->open_time && $t <= $h->close_time && ! $inBreak;
    }

    public static function record(DoDelivery $do, array $in, ?UploadedFile $photo, ?string $login): DoDeliveryPod
    {
        $data = validator($in, [
            'arrived_at' => ['nullable', 'date', 'required_unless:result,NOT_SHIPPED,ACCIDENT'],
            'result' => ['required', Rule::in(array_keys(self::RESULTS))],
            'received_by' => ['nullable', 'string', 'max:100', 'required_if:result,RECEIVED,PARTIAL'],
            'miss_cause' => ['nullable', Rule::in(array_keys(self::CAUSES))],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], ['arrived_at' => t('Arrived at'), 'result' => t('Result'), 'received_by' => t('Received by'), 'miss_cause' => t('Cause')])->validate();

        $promise = self::promise($do);
        $arrived = empty($data['arrived_at']) ? null : CarbonImmutable::parse($data['arrived_at']);
        $kept = self::kept($data['result'], $arrived, $promise['date'], $promise['time']);
        if ($data['result'] === 'ACCIDENT' && empty($data['miss_cause'])) {
            $data['miss_cause'] = 'ACCIDENT';  // kecelakaan selalu berpenyebab kecelakaan, tanpa memaksa petugas memilihnya lagi
        }
        if (! $kept && empty($data['miss_cause'])) {
            throw ValidationException::withMessages(['miss_cause' => t('Promise missed: choose the cause.')]);
        }
        $received = self::receivedLines($do, $data['result'], (array) ($in['received'] ?? []));
        foreach ($received as $row) {
            if ($row['qty'] < (float) $row['line']->qty_do - 0.005 && ! $row['reason']) {
                throw ValidationException::withMessages(['received' => t('Choose the Reason for Difference on every line the customer received less than what was shipped.')]);
            }
        }

        $pod = DoDeliveryPod::create([
            'delivery_id' => $do->id_delivery, 'attempt_no' => (int) DoDeliveryPod::where('delivery_id', $do->id_delivery)->max('attempt_no') + 1,
            'promise_date' => $promise['date'], 'promise_time' => $promise['time'], 'arrived_at' => $arrived, 'result' => $data['result'],
            'received_by' => $data['received_by'] ?? null, 'miss_cause' => $kept ? null : $data['miss_cause'], 'promise_kept' => $kept,
            'photo_path' => $photo?->store('pod/'.now()->format('Ym'), 'public'), 'notes' => $data['notes'] ?? null,
            'created_by' => $login, 'updated_by' => $login,
        ]);
        self::applyReceived($do, $data['result'], (array) ($in['received'] ?? []), $login);

        return $pod;
    }

    // Qty yang diterima customer per baris Surat Jalan = dasar tagih Customer Invoice (customerinvoice_accounting.md bagian 7).
    // RECEIVED: semua baris = qty kirim. PARTIAL: isian per baris (kosong = qty kirim) + alasan selisih + catatan kondisi.
    // Hasil lain tidak mengubah apa pun. Tidak pernah lebih dari qty kirim dan tidak pernah di bawah yang sudah ditagih.
    // Kolomnya baru ada di database yang sudah menjalankan migrasi Customer Invoice; di database lain langkah ini dilewati.
    /** @return array<int, array{line: DoLine, qty: float, reason: ?string, note: ?string}> */
    public static function receivedLines(DoDelivery $do, string $result, array $received): array
    {
        if (! in_array($result, ['RECEIVED', 'PARTIAL'], true) || ! Schema::connection('tm2sdi')->hasColumn('do_line', 'receive_diff_reason')) {
            return [];
        }

        return $do->lines()->get()->map(function (DoLine $l) use ($result, $received) {
            $row = $received[$l->id_line] ?? null;
            $typed = Locale::input(is_array($row) ? ($row['qty_received'] ?? null) : $row);
            $qty = $result === 'PARTIAL' && $typed !== null && $typed !== '' ? (float) $typed : (float) $l->qty_do;
            $qty = max((float) $l->qty_invoiced, min((float) $l->qty_do, max(0.0, $qty)));
            $reason = is_array($row) ? (string) ($row['reason'] ?? '') : '';

            return ['line' => $l, 'qty' => $qty, 'reason' => $qty < (float) $l->qty_do - 0.005 && isset(self::DIFF_REASONS[$reason]) ? $reason : null,
                'note' => is_array($row) ? (mb_substr(trim((string) ($row['note'] ?? '')), 0, 150) ?: null) : null];
        })->all();
    }

    public static function applyReceived(DoDelivery $do, string $result, array $received, ?string $login): void
    {
        foreach (self::receivedLines($do, $result, $received) as $row) {
            $row['line']->update(['qty_received' => $row['qty'], 'receive_diff_reason' => $row['reason'], 'receive_note' => $row['note'], 'updated_by' => $login]);
        }
    }
}
