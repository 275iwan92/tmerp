<?php

/*
 * REMARK
 * Tujuan       : Master Process (prod_process: proses produksi dan parameter bawaannya) dari layar v2 prod-process-form: simpan data utama, parameter dan kapasitas bawaan, ongkos dan tooling, matriks mesin pelaksana (satu Save lewat ProcessMachineMatrixService), aktif / nonaktif, di mana proses dipakai, riwayat. DATA REAL pabrik: angka kosong tetap NULL (CostingEngine dan Scheduling membaca master ini, 0 dianggap fakta).
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::production.master-process (diarsipkan di tmerp_unused/production_master_process_v1_2026-10-19.blade.php): nama wajib unik maks 200, kode opsional unik maks 10 (huruf / angka / _ / -, huruf besar), tahap dan satuan keluaran dari daftar ProdProcess, angka bawaan sama rentang v1 (setup 0 sampai 65535, waste dan toleransi 0 sampai 100, crew 0 sampai 255), lot maksimum >= minimum, tarif standar wajib punya basis ongkos. Matriks mesin: ProcessMachineMatrixService.
 * Input        : Isian form (process_code, process_name, department_id, process_stage, routing_seq, gantt_color, skill_code, inhouse, is_manual, qc_required, description, output_uom, default_*, min_lot_qty, max_lot_qty, is_bottleneck, allow_parallel, cost_basis, standard_rate, requires_*, subcon_lead_days) dan baris matriks mesin, user.
 * Output       : tm2mst.prod_process dan prod_process_machine berubah dalam satu transaksi; kolom yang tidak ada di form (washup_minutes, qc_blocking, work_center_id, default_supplier_id) tidak disentuh; ValidationException bila aturan dilanggar.
 * Dipakai oleh : routes/v2_production.php (v2.prod.process.save), layar prod-process-form, grid ProcessGrid, tes ProcessMasterServiceTest.
 * Status       : Aktif (Master T6b, 2026-10-19). Tidak ada nilai master yang diubah oleh pemindahan layar.
 */

namespace App\Services\Production;

use App\Models\Master\OrgDepartment;
use App\Models\Master\ProdProcess;
use App\Models\Sys\SecUser;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ProcessMasterService
{
    private const NUMERIC = ['default_speed_per_hour', 'default_waste_pct', 'default_tolerance_pct', 'standard_rate', 'default_setup_minutes', 'default_waste_fixed_qty', 'default_manpower', 'min_lot_qty', 'max_lot_qty', 'routing_seq', 'subcon_lead_days'];

    private const FLAGS = ['inhouse', 'is_manual', 'qc_required', 'is_bottleneck', 'allow_parallel', 'requires_plate', 'requires_die', 'requires_tooling'];

    public function __construct(private ProcessMachineMatrixService $matrix, private MachineCapabilityService $capabilities) {}

    /**
     * @param  array<int, array<string, mixed>>  $machines  baris matriks mesin
     */
    public function save(?ProdProcess $p, array $in, array $machines, SecUser $user): ProdProcess
    {
        foreach (self::NUMERIC as $k) {
            $in[$k] = ($in[$k] ?? '') === '' ? null : Locale::input($in[$k]);
        }
        $in['process_code'] = trim((string) ($in['process_code'] ?? ''));
        $id = $p?->id_process;
        $d = Validator::make($in, [
            'process_code' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:tm2mst.prod_process,process_code'.($id ? ','.$id.',id_process' : '')],
            'process_name' => ['required', 'string', 'max:200', 'unique:tm2mst.prod_process,process_name'.($id ? ','.$id.',id_process' : '')],
            'department_id' => ['nullable', 'integer'],
            'process_stage' => ['nullable', 'string', 'in:'.implode(',', array_keys(ProdProcess::STAGES))],
            'routing_seq' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'gantt_color' => ['nullable', 'string', 'max:10'],
            'output_uom' => ['nullable', 'string', 'in:'.implode(',', array_keys(ProdProcess::OUTPUT_UOM))],
            'default_setup_minutes' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'default_speed_per_hour' => ['nullable', 'numeric', 'min:0'],
            'default_waste_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_waste_fixed_qty' => ['nullable', 'integer', 'min:0'],
            'default_tolerance_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_manpower' => ['nullable', 'integer', 'min:0', 'max:255'],
            'min_lot_qty' => ['nullable', 'integer', 'min:0'],
            'max_lot_qty' => ['nullable', 'integer', 'min:0'],
            'cost_basis' => ['nullable', 'string', 'in:'.implode(',', array_keys(ProdProcess::COST_BASIS))],
            'standard_rate' => ['nullable', 'numeric', 'min:0'],
            'skill_code' => ['nullable', 'string', 'max:30'],
            'subcon_lead_days' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'description' => ['nullable', 'string'],
        ], ['process_name.unique' => 'This process name already exists.', 'process_code.unique' => 'This process code already exists.'],
            ['process_code' => 'Process code', 'process_name' => 'Process name', 'process_stage' => 'Production stage', 'default_speed_per_hour' => 'Standard capacity', 'default_setup_minutes' => 'Setup time'])->validate();

        if ($d['min_lot_qty'] !== null && $d['max_lot_qty'] !== null && (int) $d['min_lot_qty'] > (int) $d['max_lot_qty']) {
            throw ValidationException::withMessages(['max_lot_qty' => 'Maximum lot must be >= minimum lot.']);
        }
        // Tarif tanpa basis = angka tak bermakna (per jam? per 1000?), dan CostingEngine akan salah 3 digit.
        if ($d['standard_rate'] !== null && ($d['cost_basis'] ?? '') === '') {
            throw ValidationException::withMessages(['cost_basis' => 'Select a cost basis first: a rate without a basis cannot be used to calculate COGS (HPP).']);
        }

        return DB::connection('tm2mst')->transaction(function () use ($p, $d, $in, $machines, $user) {
            $payload = [];
            foreach ($d as $k => $v) {
                $payload[$k] = ($v === '' || $v === null) ? null : $v;
            }
            $payload['process_code'] = ! empty($d['process_code']) ? strtoupper($d['process_code']) : null;
            // Pemilih warna selalu menampilkan biru bawaan DevLib; proses real yang warnanya masih kosong tidak diisi dengan warna bawaan itu.
            if (array_key_exists('gantt_color', $d)) {
                $payload['gantt_color'] = (! $p?->gantt_color && strtolower((string) $d['gantt_color']) === '#2563eb') ? null : ($d['gantt_color'] ?: null);
            }
            foreach (self::FLAGS as $f) {
                $payload[$f] = filter_var($in[$f] ?? false, FILTER_VALIDATE_BOOLEAN);
            }
            $payload['updated_by'] = $user->login;
            if ($p) {
                $p->update($payload);
            } else {
                $p = ProdProcess::create($payload + ['activate' => true, 'created_by' => $user->login]);
            }
            $this->matrix->save('process', (int) $p->id_process, $machines, $user);

            return $p;
        });
    }

    public function setActive(ProdProcess $p, bool $active, SecUser $user): void
    {
        $p->update(['activate' => $active, 'updated_by' => $user->login]);
    }

    /** @return array<int, string> departemen aktif */
    public function departmentOptions(): array
    {
        return OrgDepartment::query()->where('activate', true)->orderBy('department_name')->pluck('department_name', 'department_id')->all();
    }

    /** Hitungan di mana proses ini dipakai (hanya-baca). */
    public function whereUsed(ProdProcess $p): array
    {
        return $this->capabilities->whereUsedProcess((int) $p->id_process);
    }

    /** Jumlah mesin aktif yang dipasangkan. */
    public function machineCount(ProdProcess $p): int
    {
        return $p->capabilities()->where('activate', true)->count();
    }

    /** Riwayat tab History: terbaru di atas. */
    public function history(ProdProcess $p): array
    {
        $rows = [['at' => $p->created_at?->toDateTimeString(), 'by' => $p->created_by, 'action' => 'Created', 'note' => '']];
        if ($p->updated_at && $p->updated_at != $p->created_at) {
            $rows[] = ['at' => $p->updated_at->toDateTimeString(), 'by' => $p->updated_by, 'action' => 'Last modified', 'note' => ''];
        }

        return array_reverse($rows);
    }
}
