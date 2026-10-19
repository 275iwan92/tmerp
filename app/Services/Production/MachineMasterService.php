<?php

/*
 * REMARK
 * Tujuan       : Master Machine (prod_machine: aset mesin dan register kapabilitasnya) dari layar v2 prod-machine-form: simpan identitas, spesifikasi teknis, kapasitas, biaya dan aset, jadwal perawatan; matriks proses yang bisa dikerjakan (satu Save lewat ProcessMachineMatrixService sisi mesin); log downtime (stop baru, atau mengisi End untuk menutup); aktif / nonaktif; OEE dan keandalan hanya-baca; di mana mesin dipakai; riwayat. DATA REAL pabrik: angka kosong tetap NULL (SpecEngine, Imposition, Costing dan Scheduling membaca master ini, 0 dianggap fakta).
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::master.machine (diarsipkan di tmerp_unused/master_machine_v1_2026-10-19.blade.php): kode unik maks 20 huruf besar, nama maks 100, jenis dari 18 pilihan, kondisi dari ProdMachine::STATUSES, ukuran lembar maks >= min, gsm maks >= min, area cetak <= lembar maks, target kecepatan <= kecepatan maks, shift 0 sampai 4, jam per shift 0 sampai 24, kritikalitas A / B / C; downtime: jenis dari ProdMachineDowntime::KINDS, terencana atau tidak ditentukan jenisnya, End >= Start; Deactivate = tidak aktif + kondisi RETIRED, Activate = aktif + READY. OEE dan MTTR / MTBF: MachineOeeService; di mana dipakai: MachineCapabilityService.
 * Input        : Isian form (semua kolom prod_machine yang ada di form), baris matriks proses, baris downtime, user.
 * Output       : tm2mst.prod_machine, prod_process_machine dan prod_machine_downtime berubah dalam satu transaksi; kolom yang tidak ada di form (legacy_machine_names, photo_path, penanda data) tidak disentuh; stop yang dibuka atau ditutup memicu hitung ulang rencana PPIC lewat MachineDowntimeObserver, yang menunggu commit transaksi ini; ValidationException bila aturan dilanggar.
 * Dipakai oleh : routes/v2_production.php (v2.prod.machine.save), layar prod-machine-form, grid MachineGrid, tes MachineMasterServiceTest.
 * Status       : Aktif (Master T6b5, 2026-10-19). Tidak ada nilai master yang diubah oleh pemindahan layar.
 */

namespace App\Services\Production;

use App\Models\Master\OrgDepartment;
use App\Models\Master\OrgDivision;
use App\Models\Master\ProdMachine;
use App\Models\Master\ProdMachineDowntime;
use App\Models\Sys\SecUser;
use Carbon\CarbonImmutable;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class MachineMasterService
{
    // Jenis = awalan kode mesin. Paper Cutting (guillotine) sengaja terpisah dari Die Cutting (plong).
    public const TYPES = ['Offset Printing', 'Digital Printing', 'CTP Plate Setter', 'Paper Cutting', 'Die Cutting', 'Cutting Plotter', 'Folder Gluer', 'Laminating', 'UV Coating', 'Water-Based Coating',
        'Waxing', 'Gluing', 'Pressing', 'Shrink Wrapping', 'Stripping', 'Stitching', 'Seaming', 'Support Equipment'];

    public const DRYING = ['IR' => 'IR Dryer', 'UV' => 'UV Curing', 'HOT_AIR' => 'Hot Air', 'NONE' => 'No Dryer'];

    private const NUMERIC = ['year_acquired', 'max_sheet_length_mm', 'max_sheet_width_mm', 'min_sheet_length_mm', 'min_sheet_width_mm', 'max_print_length_mm', 'max_print_width_mm', 'gripper_margin_mm', 'color_count',
        'max_colors_per_pass', 'gsm_min', 'gsm_max', 'max_speed_per_hour', 'speed_target_per_hour', 'oee_target_pct', 'mtbf_hours', 'shifts_per_day', 'hours_per_shift', 'operator_min', 'helper_min', 'hourly_rate',
        'power_kw', 'acquisition_cost', 'useful_life_years', 'maintenance_interval_hours', 'maintenance_interval_days', 'total_run_hours', 'total_impressions'];

    public function __construct(private ProcessMachineMatrixService $matrix, private MachineCapabilityService $capabilities, private MachineOeeService $oee) {}

    /**
     * @param  array<int, array<string, mixed>>  $processes  baris matriks proses
     * @param  array<int, array<string, mixed>>  $downtimes  baris log downtime
     */
    public function save(?ProdMachine $m, array $in, array $processes, array $downtimes, SecUser $user): ProdMachine
    {
        foreach (self::NUMERIC as $k) {
            $in[$k] = ($in[$k] ?? '') === '' ? null : Locale::input($in[$k]);
        }
        $in['machine_code'] = trim((string) ($in['machine_code'] ?? ''));
        $id = $m?->machine_id;
        $d = Validator::make($in, [
            'machine_code' => ['required', 'string', 'max:20', 'unique:tm2mst.prod_machine,machine_code'.($id ? ','.$id.',machine_id' : '')],
            'machine_name' => ['required', 'string', 'max:100'],
            'nickname' => ['nullable', 'string', 'max:60'],
            'machine_model_id' => ['nullable', 'integer', 'exists:tm2mst.prod_machine_model,id_machine_model'],
            'machine_type' => ['required', 'string', 'max:30', 'in:'.implode(',', self::TYPES)],
            'division_id' => ['required', 'integer', 'exists:tm2mst.org_division,division_id'],
            'department_id' => ['nullable', 'integer'],
            'brand_model' => ['nullable', 'string', 'max:150'],
            'year_acquired' => ['nullable', 'integer', 'min:1950', 'max:'.(date('Y') + 1)],
            'acquired_date' => ['nullable', 'date'],
            'serial_no' => ['nullable', 'string', 'max:100'],
            'machine_status' => ['required', 'string', 'in:'.implode(',', array_keys(ProdMachine::STATUSES))],
            'criticality' => ['nullable', 'string', 'in:A,B,C'],
            'plant_code' => ['nullable', 'string', 'max:20'],
            'location_note' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string'],
            'max_paper_size' => ['nullable', 'string', 'max:50'],
            'max_sheet_length_mm' => ['nullable', 'integer', 'min:0'],
            'max_sheet_width_mm' => ['nullable', 'integer', 'min:0'],
            'min_sheet_length_mm' => ['nullable', 'integer', 'min:0'],
            'min_sheet_width_mm' => ['nullable', 'integer', 'min:0'],
            'max_print_length_mm' => ['nullable', 'integer', 'min:0'],
            'max_print_width_mm' => ['nullable', 'integer', 'min:0'],
            'gripper_margin_mm' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'plate_size' => ['nullable', 'string', 'max:50'],
            'drying_system' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::DRYING))],
            'color_count' => ['nullable', 'integer', 'min:0', 'max:255'],
            'max_colors_per_pass' => ['nullable', 'integer', 'min:0', 'max:255'],
            'substrate_types' => ['nullable', 'string', 'max:255'],
            'gsm_min' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'gsm_max' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'max_speed_per_hour' => ['nullable', 'integer', 'min:0'],
            'speed_target_per_hour' => ['nullable', 'integer', 'min:0'],
            'oee_target_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'mtbf_hours' => ['nullable', 'integer', 'min:0'],
            'capacity_per_shift' => ['nullable', 'string', 'max:50'],
            'shifts_per_day' => ['nullable', 'integer', 'min:0', 'max:4'],
            'hours_per_shift' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'operator_min' => ['nullable', 'integer', 'min:0', 'max:255'],
            'helper_min' => ['nullable', 'integer', 'min:0', 'max:255'],
            'cost_center_code' => ['nullable', 'string', 'max:20'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'power_kw' => ['nullable', 'numeric', 'min:0'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'useful_life_years' => ['nullable', 'integer', 'min:0', 'max:255'],
            'warranty_until' => ['nullable', 'date'],
            'maintenance_interval_hours' => ['nullable', 'integer', 'min:0'],
            'maintenance_interval_days' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'last_maintenance_date' => ['nullable', 'date'],
            'next_maintenance_date' => ['nullable', 'date'],
            'total_run_hours' => ['nullable', 'numeric', 'min:0'],
            'total_impressions' => ['nullable', 'integer', 'min:0'],
            'service_vendor' => ['nullable', 'string', 'max:150'],
        ], ['machine_code.unique' => 'This machine code already exists.'], ['machine_code' => 'Machine code', 'machine_name' => 'Machine name', 'machine_type' => 'Type classification', 'division_id' => 'Production division', 'machine_status' => 'Condition status'])->validate();

        // Kewajaran yang tidak bisa diserahkan ke aturan bawaan.
        foreach ([['max_sheet_length_mm', 'min_sheet_length_mm', 'Maximum sheet length must be >= minimum.'], ['max_sheet_width_mm', 'min_sheet_width_mm', 'Maximum sheet width must be >= minimum.'],
            ['gsm_max', 'gsm_min', 'Maximum grammage must be >= minimum.']] as [$max, $min, $msg]) {
            if (($d[$max] ?? null) !== null && ($d[$min] ?? null) !== null && (int) $d[$max] < (int) $d[$min]) {
                throw ValidationException::withMessages([$max => $msg]);
            }
        }
        if (($d['max_print_length_mm'] ?? null) !== null && ($d['max_sheet_length_mm'] ?? null) !== null && (int) $d['max_print_length_mm'] > (int) $d['max_sheet_length_mm']) {
            throw ValidationException::withMessages(['max_print_length_mm' => 'The print area must not be longer than the maximum sheet.']);
        }
        if (($d['speed_target_per_hour'] ?? null) !== null && ($d['max_speed_per_hour'] ?? null) !== null && (int) $d['speed_target_per_hour'] > (int) $d['max_speed_per_hour']) {
            throw ValidationException::withMessages(['speed_target_per_hour' => 'The target speed must not exceed the manufacturer maximum speed.']);
        }

        $plan = $this->planDowntimes($m, $downtimes);

        return DB::connection('tm2mst')->transaction(function () use ($m, $d, $in, $processes, $plan, $user) {
            $payload = [];
            foreach ($d as $k => $v) {
                $payload[$k] = ($v === '' || $v === null) ? null : $v;
            }
            $payload['machine_code'] = strtoupper($d['machine_code']);
            foreach (['perfecting', 'is_process_machine'] as $f) {
                $payload[$f] = filter_var($in[$f] ?? false, FILTER_VALIDATE_BOOLEAN);
            }
            $payload['updated_by'] = $user->login;
            if ($m) {
                $m->update($payload);
            } else {
                $m = ProdMachine::create($payload + ['activate' => true, 'created_by' => $user->login]);
            }
            $this->matrix->save('machine', (int) $m->machine_id, $processes, $user);

            foreach ($plan as $p) {
                if ($p['row']) {
                    $p['row']->update(['end_at' => $p['end'], 'updated_by' => $user->login]);
                } else {
                    ProdMachineDowntime::create(['machine_id' => $m->machine_id, 'downtime_kind' => $p['kind'], 'is_planned' => ProdMachineDowntime::KINDS[$p['kind']]['planned'], 'start_at' => $p['start'], 'end_at' => $p['end'],
                        'root_cause' => $p['cause'], 'reported_by' => $user->login, 'created_by' => $user->login, 'updated_by' => $user->login]);
                }
            }

            return $m;
        });
    }

    /**
     * Periksa semua baris downtime sebelum menulis apa pun. Stop yang ada terkunci kecuali End (menutup stop yang masih berjalan).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function planDowntimes(?ProdMachine $m, array $rows): array
    {
        $plan = [];
        $errors = [];
        foreach (array_values($rows) as $i => $r) {
            $n = $i + 1;
            $at = function (string $date, string $time) use ($n, &$errors): ?CarbonImmutable {
                $date = trim((string) ($date));
                $time = trim((string) ($time));
                if ($date === '' && $time === '') {
                    return null;
                }
                if ($date === '' || ! preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $time) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    $errors['downtimes'] = "Stop {$n}: enter a date and a time as HH:MM.";

                    return null;
                }

                return CarbonImmutable::createFromFormat('Y-m-d H:i', $date.' '.str_pad($time, 5, '0', STR_PAD_LEFT));
            };
            $end = $at((string) ($r['end_date'] ?? ''), (string) ($r['end_time'] ?? ''));
            $id = (int) ($r['downtime_id'] ?? 0);
            if ($id) {
                $row = $m ? ProdMachineDowntime::where('machine_id', $m->machine_id)->find($id) : null;
                if (! $row) {
                    $errors['downtimes'] = "Stop {$n}: this record does not belong to the machine.";

                    continue;
                }
                if ($row->end_at === null && $end) {
                    if ($end->lt($row->start_at)) {
                        $errors['downtimes'] = "Stop {$n}: the end must not be before the start.";

                        continue;
                    }
                    $plan[] = ['row' => $row, 'end' => $end];
                }

                continue;
            }
            $cause = trim((string) ($r['cause'] ?? ''));
            if ($cause === '' && trim((string) ($r['start_date'] ?? '').(string) ($r['start_time'] ?? '').(string) ($r['end_date'] ?? '').(string) ($r['end_time'] ?? '')) === '') {
                continue;
            }
            $kind = (string) ($r['kind'] ?? '');
            if (! isset(ProdMachineDowntime::KINDS[$kind])) {
                $errors['downtimes'] = "Stop {$n}: choose a type.";

                continue;
            }
            $start = $at((string) ($r['start_date'] ?? ''), (string) ($r['start_time'] ?? ''));
            if (! $start) {
                $errors['downtimes'] ??= "Stop {$n}: enter the start.";

                continue;
            }
            if ($end && $end->lt($start)) {
                $errors['downtimes'] = "Stop {$n}: the end must not be before the start.";

                continue;
            }
            if (mb_strlen($cause) > 255) {
                $errors['downtimes'] = "Stop {$n}: the cause must be at most 255 characters.";

                continue;
            }
            $plan[] = ['row' => null, 'kind' => $kind, 'start' => $start, 'end' => $end, 'cause' => $cause === '' ? null : $cause];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $plan;
    }

    /** Deactivate = tidak dimiliki lagi, kondisi ikut RETIRED supaya dua tanda tidak saling bohong; Activate = READY. */
    public function setActive(ProdMachine $m, bool $active, SecUser $user): void
    {
        $m->update(['activate' => $active, 'machine_status' => $active ? 'READY' : 'RETIRED', 'updated_by' => $user->login]);
    }

    /** @return array<int, string> */
    public function divisionOptions(): array
    {
        return OrgDivision::query()->where('activate', true)->orderBy('division_name')->pluck('division_name', 'division_id')->all();
    }

    /** @return array<int, string> */
    public function departmentOptions(): array
    {
        return OrgDepartment::query()->where('activate', true)->orderBy('department_name')->pluck('department_name', 'department_id')->all();
    }

    /** Baris log downtime (25 terbaru) untuk tabel. @return list<array<string, mixed>> */
    public function downtimeRows(ProdMachine $m): array
    {
        return ProdMachineDowntime::where('machine_id', $m->machine_id)->orderByDesc('start_at')->limit(25)->get()->map(fn (ProdMachineDowntime $d) => [
            'downtime_id' => $d->downtime_id, 'kind' => $d->downtime_kind, 'start_date' => $d->start_at->format('Y-m-d'), 'start_time' => $d->start_at->format('H:i'),
            'end_date' => $d->end_at?->format('Y-m-d') ?? '', 'end_time' => $d->end_at?->format('H:i') ?? '', 'cause' => (string) $d->root_cause,
            'nature' => t($d->is_planned ? 'Planned' : 'Unplanned'), 'hours' => $d->duration_minutes !== null ? number_format($d->duration_minutes / 60, 1, ',', '.').' h' : t('ongoing'), 'is_new' => false])->all();
    }

    /** OEE aktual dan keandalan untuk N hari terakhir (7, 30 atau 90). @return array{oee: array<string, mixed>, reliability: array<string, mixed>} */
    public function oee(ProdMachine $m, int $days): array
    {
        $from = now()->subDays($days)->startOfDay();
        $to = now()->endOfDay();

        return ['oee' => $this->oee->calculate((int) $m->machine_id, $from, $to), 'reliability' => $this->oee->reliability((int) $m->machine_id, $from, $to)];
    }

    /** @return array<string, int> */
    public function whereUsed(ProdMachine $m): array
    {
        return $this->capabilities->whereUsedMachine((int) $m->machine_id);
    }

    /** Jumlah proses aktif yang bisa dikerjakan. */
    public function processCount(ProdMachine $m): int
    {
        return $m->capabilities()->where('activate', true)->count();
    }

    /** Riwayat tab History: terbaru di atas. */
    public function history(ProdMachine $m): array
    {
        $rows = [['at' => $m->created_at?->toDateTimeString(), 'by' => $m->created_by, 'action' => 'Created', 'note' => '']];
        if ($m->updated_at && $m->updated_at != $m->created_at) {
            $rows[] = ['at' => $m->updated_at->toDateTimeString(), 'by' => $m->updated_by, 'action' => 'Last modified', 'note' => ''];
        }

        return array_reverse($rows);
    }
}
