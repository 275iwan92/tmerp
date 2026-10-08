<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Mnt\MntAsset;
use App\Models\Mnt\MntDailyCheck;
use App\Models\Mnt\MntLog;
use App\Models\Mnt\MntRequest;
use App\Support\V2\Data\Maintenance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Daily Check: lembar cek operator per aset per shift dari watch point operator yang aktif (frekuensi Every shift atau Daily).
// Hasil NOT_OK saat Submit membuka Maintenance Request (bukan order), supaya kepala maintenance yang menilai.
// Nilai ukur di luar batas normal disarankan NOT_OK; tanpa batas normal sistem tidak pernah menilai.
final class DailyCheckService
{
    public function __construct(private ?string $login = null)
    {
        $this->login ??= auth()->user()?->login ?? 'system';
    }

    // Lembar yang sudah ada untuk aset + tanggal + shift dikembalikan apa adanya (tidak dobel).
    public function open(MntAsset $asset, string $date, string $shift): MntDailyCheck
    {
        $existing = MntDailyCheck::where('asset_id', $asset->id_asset)->whereDate('check_date', $date)->where('shift_code', $shift)->first();
        if ($existing) {
            return $existing;
        }
        $points = $asset->watchPoints()->where('activate', true)->where('checked_by_role', 'OPERATOR')->whereIn('frequency', ['SHIFT', 'DAILY'])->get();
        if ($points->isEmpty()) {
            throw ValidationException::withMessages(['daily' => t('This asset has no active operator watch point. Add watch points in Asset Maintenance Profile first.')]);
        }

        return DB::connection('tm2mnt')->transaction(function () use ($asset, $date, $shift, $points) {
            $sheet = MntDailyCheck::create(['doc_no' => DocNumber::next('DC', MntDailyCheck::class), 'asset_id' => $asset->id_asset, 'check_date' => $date, 'shift_code' => $shift,
                'checked_by' => $this->login, 'status' => 'OPEN', 'created_by' => $this->login, 'updated_by' => $this->login]);
            foreach ($points as $w) {
                $sheet->lines()->create(['watch_point_id' => $w->id_watch_point, 'component_code' => $w->component_code, 'check_text' => $w->check_text, 'normal_min' => $w->normal_min,
                    'normal_max' => $w->normal_max, 'measure_uom' => $w->measure_uom, 'created_by' => $this->login, 'updated_by' => $this->login]);
            }
            MntLog::add('CHECK', $sheet->id_daily_check, 'Opened');

            return $sheet;
        });
    }

    /** @param array<int|string, array{result?: string, measured_value?: mixed, note?: string}> $rows kunci = id baris */
    public function save(MntDailyCheck $sheet, array $rows): void
    {
        $this->guard(! $sheet->isSubmitted(), t('A submitted check sheet is locked.'));
        foreach ($rows as $id => $row) {
            $line = $sheet->lines()->whereKey((int) $id)->first();
            if (! $line) {
                continue;
            }
            $measured = ($row['measured_value'] ?? '') === '' ? null : (float) $row['measured_value'];
            $result = ($row['result'] ?? '') ?: null;
            $line->fill(['measured_value' => $measured, 'note' => ($row['note'] ?? '') ?: null, 'result' => $result, 'updated_by' => $this->login]);
            if ($result === null && $line->outOfLimit()) {
                $line->result = 'NOT_OK';
            }
            $line->save();
        }
    }

    // Submit: semua baris wajib punya hasil; tiap NOT_OK membuka satu Request.
    public function submit(MntDailyCheck $sheet): int
    {
        $this->guard(! $sheet->isSubmitted(), t('This check sheet is already submitted.'));
        $open = $sheet->lines()->whereNull('result')->count();
        $this->guard($open === 0, tf('Cannot submit yet: :n line(s) have no result.', ['n' => $open]));

        return DB::connection('tm2mnt')->transaction(function () use ($sheet) {
            $made = 0;
            $asset = $sheet->asset;
            foreach ($sheet->lines()->where('result', 'NOT_OK')->whereNull('request_id')->get() as $line) {
                $request = MntRequest::create(['doc_no' => DocNumber::next('MR', MntRequest::class), 'request_date' => now(), 'asset_id' => $sheet->asset_id, 'request_kind' => 'CORRECTIVE',
                    'priority' => in_array(Maintenance::criticalityOf($asset), ['A', 'B'], true) ? 'P2' : 'P3', 'asset_stopped' => false,
                    'symptom' => mb_substr('Daily Check '.$sheet->doc_no.': '.$line->check_text.($line->measured_value !== null ? ' = '.$line->measured_value.' '.$line->measure_uom : '').($line->note ? ' ('.$line->note.')' : ''), 0, 1000),
                    'source_type' => 'DAILY_CHECK', 'daily_check_line_id' => $line->id_daily_check_line, 'status' => 'OPEN', 'created_by' => $this->login, 'updated_by' => $this->login]);
                $line->update(['request_id' => $request->id_request]);
                MntLog::add('REQUEST', $request->id_request, 'Created', 'From '.$sheet->doc_no);
                $made++;
            }
            $sheet->update(['status' => 'SUBMITTED', 'submitted_at' => now(), 'checked_by' => $this->login, 'updated_by' => $this->login]);
            MntLog::add('CHECK', $sheet->id_daily_check, 'Submitted', $made.' request(s)');

            return $made;
        });
    }

    private function guard(bool $ok, string $msg): void
    {
        if (! $ok) {
            throw ValidationException::withMessages(['daily' => $msg]);
        }
    }
}
