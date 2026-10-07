<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Master\AttFingerImport;
use App\Models\Master\AttFingerRaw;
use App\Models\Master\EmpEmployee;
use App\Services\Hrm\AttendanceCalculationService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

// HRM > Payroll > Attendance Upload (v2): file presensi mesin finger (.csv / .txt / .xlsx, kolom device_pin, tanggal, jam_masuk,
// jam_keluar opsional). Dua langkah, aturan sama dengan layar v1: (1) baca file -> pratinjau + device_pin tak dikenal,
// (2) Simpan & Eksekusi Matching Shift -> att_finger_import / att_finger_raw lalu AttendanceCalculationService per pegawai + tanggal.
// Hasil baca file disimpan di cache per user antara dua langkah (bukan sesi: bisa ribuan baris).
final class AttendanceUpload
{
    private const TTL_MINUTES = 60;

    private static function key(int $userId): string
    {
        return 'att-upload:'.$userId;
    }

    /** @return array{file_name: string, rows: list<array<string, mixed>>, unmatched: list<string>}|null */
    public static function pending(int $userId): ?array
    {
        return Cache::get(self::key($userId));
    }

    public static function discard(int $userId): void
    {
        Cache::forget(self::key($userId));
    }

    /** Langkah 1: baca file, cocokkan device_pin ke Master Karyawan, simpan pratinjau. @return int jumlah baris */
    public static function parse(int $userId, UploadedFile $file): int
    {
        Validator::make(['file' => $file], ['file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120']])->validate();
        $rows = self::readRows($file->getRealPath(), $file->getClientOriginalExtension());
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $rows[0] ?? []);
        [$pin, $date, $in, $out] = [array_search('device_pin', $header, true), array_search('tanggal', $header, true), array_search('jam_masuk', $header, true), array_search('jam_keluar', $header, true)];
        if ($pin === false || $date === false || $in === false) {
            throw ValidationException::withMessages(['file' => t('Required columns: device_pin, tanggal, jam_masuk (jam_keluar optional). Check the header in the first row of the file.')]);
        }

        $body = array_slice($rows, 1);
        $employees = EmpEmployee::whereIn('device_pin', collect($body)->pluck($pin)->filter()->unique()->all())->get()->keyBy('device_pin');
        $preview = [];
        $unmatched = [];
        foreach ($body as $row) {
            if (empty($row[$pin])) {
                continue;
            }
            $pinValue = (string) $row[$pin];
            $employee = $employees->get($pinValue);
            $preview[] = ['device_pin' => $pinValue, 'employee_name' => $employee?->full_name ?? '', 'tanggal' => $row[$date] ?? null,
                'jam_masuk' => $row[$in] ?? null, 'jam_keluar' => $out !== false ? ($row[$out] ?? null) : null, 'matched' => (bool) $employee];
            if (! $employee) {
                $unmatched[$pinValue] = $pinValue;
            }
        }
        Cache::put(self::key($userId), ['file_name' => $file->getClientOriginalName(), 'rows' => $preview, 'unmatched' => array_values($unmatched)], now()->addMinutes(self::TTL_MINUTES));

        return count($preview);
    }

    /** Langkah 2: simpan baris yang cocok lalu hitung kehadiran. @return array{rows: int, days: int} */
    public static function process(int $userId, string $by): array
    {
        $pending = self::pending($userId) ?? throw ValidationException::withMessages(['file' => t('Nothing to process — upload a file first.')]);
        $matched = array_filter($pending['rows'], fn ($r) => $r['matched']);

        $import = AttFingerImport::create(['import_date' => now()->toDateString(), 'file_name' => $pending['file_name'], 'imported_by' => $by,
            'row_count' => count($matched), 'status' => 'PROCESSED', 'processed_at' => now()]);
        $employees = EmpEmployee::whereIn('device_pin', array_column($matched, 'device_pin'))->get()->keyBy('device_pin');

        $affected = [];
        foreach ($matched as $row) {
            $employee = $employees->get($row['device_pin']);
            $date = Carbon::parse($row['tanggal']);
            foreach (['IN' => 'jam_masuk', 'OUT' => 'jam_keluar'] as $type => $field) {
                if (! empty($row[$field])) {
                    AttFingerRaw::create(['id_import' => $import->id_import, 'employee_id' => $employee->employee_id,
                        'scan_datetime' => $date->copy()->setTimeFromTimeString($row[$field]), 'scan_type' => $type]);
                }
            }
            $affected[$employee->employee_id][] = $date->toDateString();
        }

        $service = app(AttendanceCalculationService::class);
        $days = 0;
        foreach ($affected as $employeeId => $dates) {
            $periodStart = Carbon::parse(min($dates))->startOfWeek(Carbon::MONDAY);
            foreach (array_unique($dates) as $dateStr) {
                $service->calculateForDate((int) $employeeId, Carbon::parse($dateStr), $periodStart);
                $days++;
            }
        }
        self::discard($userId);

        return ['rows' => (int) $import->row_count, 'days' => $days];
    }

    /** CSV / TXT dengan fgetcsv, .xlsx lewat PhpSpreadsheet langsung. @return list<array<int, mixed>> */
    private static function readRows(string $path, string $extension): array
    {
        if (! in_array(strtolower($extension), ['csv', 'txt'], true)) {
            return IOFactory::load($path)->getActiveSheet()->toArray();
        }
        $rows = [];
        if (($handle = fopen($path, 'r')) !== false) {
            while (($line = fgetcsv($handle)) !== false) {
                $rows[] = $line;
            }
            fclose($handle);
        }

        return $rows;
    }
}
