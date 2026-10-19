<?php

/*
 * REMARK
 * Tujuan       : Matriks kapabilitas Proses x Mesin (prod_process_machine) untuk dua arah layar v2: Master Process (mesin pelaksana per proses) dan, nanti, Master Machine (proses per mesin). Satu aturan untuk keduanya: mesin utama (preference_rank 1 atau 2), bersertifikat, parameter khusus pasangan (kosong = warisi default, TIDAK PERNAH 0), pasangan tidak pernah dihapus (dinonaktifkan), pasangan lama yang dipasang lagi dihidupkan kembali.
 * Sumber hulu  : Aturan dipindah dari trait v1 App\Livewire\Concerns\ManagesProcessMachineCapability (diarsipkan di tmerp_unused/livewire_concerns_ManagesProcessMachineCapability_v1_2026-10-19.php) (capAdd, capRemove, capRestore, capTogglePrimary, capToggleCertified, capSaveParams) yang dipakai halaman v1 master-process dan machine: setup 0 sampai 65535 menit, kecepatan >= 0, waste 0 sampai 100 persen, waste tetap >= 0, tarif >= 0, crew 0 sampai 255, gramatur 0 sampai 65535 dengan maksimum >= minimum, catatan maks 255. Nilai efektif dan estimasi jam: MachineCapabilityService.
 * Input        : Sisi pemilik ('process' atau 'machine') dan id-nya, baris form (capability_id bila pasangan sudah ada, other_id = id sisi seberang, primary, is_certified, activate, setup_minutes, speed_per_hour, waste_pct, waste_fixed_qty, hourly_rate, manpower, gsm_min, gsm_max, note), user.
 * Output       : tm2mst.prod_process_machine berubah (tanpa delete); ValidationException bila aturan dilanggar. Kolom ukuran lembar min / max tidak disentuh.
 * Dipakai oleh : ProcessMasterService (layar prod-process-form) dan MachineMasterService (layar prod-machine-form, sisi 'machine': hanya proses aktif yang memakai mesin); tes ProcessMasterServiceTest dan MachineMasterServiceTest.
 * Status       : Aktif (Master T6b, 2026-10-19). Data real pabrik: tidak ada nilai yang diisi atau diubah oleh pemindahan layar.
 */

namespace App\Services\Production;

use App\Models\Master\ProdMachine;
use App\Models\Master\ProdProcess;
use App\Models\Master\ProdProcessMachine;
use App\Models\Sys\SecUser;
use Devlib\Locale;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ProcessMachineMatrixService
{
    public const FIELDS = ['setup_minutes', 'speed_per_hour', 'waste_pct', 'waste_fixed_qty', 'hourly_rate', 'manpower', 'gsm_min', 'gsm_max', 'note'];

    public function __construct(private MachineCapabilityService $capabilities) {}

    /**
     * Baris x-dl::lines untuk semua pasangan pemilik ini (aktif dulu, utama dulu).
     *
     * @return list<array<string, mixed>>
     */
    public function rows(string $side, int $ownerId): array
    {
        $own = $side === 'process' ? 'process_id' : 'machine_id';
        $caps = ProdProcessMachine::query()->with(['machine', 'process'])->where($own, $ownerId)->get()->filter(fn ($c) => $c->machine && $c->process)
            ->sortBy([fn ($a, $b) => $b->activate <=> $a->activate, fn ($a, $b) => $a->preference_rank <=> $b->preference_rank,
                fn ($a, $b) => strcmp($side === 'process' ? $a->machine->machine_code : $a->process->process_name, $side === 'process' ? $b->machine->machine_code : $b->process->process_name)])->values();

        return $caps->map(fn ($c) => $this->row($side, $c))->all();
    }

    private function row(string $side, ProdProcessMachine $c): array
    {
        $other = $side === 'process' ? $c->machine : $c->process;
        $otherId = $side === 'process' ? $c->machine_id : $c->process_id;
        $out = ['capability_id' => $c->capability_id, 'other_id' => $otherId, 'other_id_label' => $side === 'process' ? $c->machine->machine_code.' — '.$c->machine->machine_name : $c->process->process_name,
            'is_new' => false, 'primary' => $c->preference_rank === 1, 'is_certified' => (bool) $c->is_certified, 'activate' => (bool) $c->activate];
        foreach (self::FIELDS as $f) {
            $out[$f] = $c->{$f} === null ? '' : (is_numeric($c->{$f}) ? (float) $c->{$f} + 0 : (string) $c->{$f});
        }

        return $out;
    }

    /**
     * Nilai efektif (pasangan, lalu proses, lalu mesin) dan estimasi jam untuk 10.000 unit, hanya pasangan aktif; hanya-baca.
     *
     * @return list<array<string, mixed>>
     */
    public function effective(string $side, int $ownerId): array
    {
        $own = $side === 'process' ? 'process_id' : 'machine_id';

        return ProdProcessMachine::query()->with(['machine', 'process'])->where($own, $ownerId)->where('activate', true)->get()->filter(fn ($c) => $c->machine && $c->process)
            ->sortBy([fn ($a, $b) => $a->preference_rank <=> $b->preference_rank, fn ($a, $b) => strcmp($a->machine->machine_code, $b->machine->machine_code)])
            ->map(function ($c) {
                $p = $this->capabilities->resolveParams($c, $c->process);

                return ['machine' => $c->machine->machine_code.' — '.$c->machine->machine_name, 'process' => $c->process->process_name,
                    'speed' => $p['speed_per_hour']['value'], 'speed_source' => $p['speed_per_hour']['source'], 'setup' => $p['setup_minutes']['value'], 'waste' => $p['waste_pct']['value'], 'hours_10k' => $this->capabilities->estimateHours($c, 10000, $c->process)];
            })->values()->all();
    }

    /**
     * Simpan baris matriks pemilik. Dipanggil di dalam transaksi pemilik; salah satu baris salah = tidak ada yang tersimpan.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function save(string $side, int $ownerId, array $rows, SecUser $user): void
    {
        $own = $side === 'process' ? 'process_id' : 'machine_id';
        $oth = $side === 'process' ? 'machine_id' : 'process_id';
        $errors = [];
        $plan = [];
        $seen = [];
        foreach (array_values($rows) as $i => $r) {
            $n = $i + 1;
            $capId = (int) ($r['capability_id'] ?? 0);
            $cap = $capId ? ProdProcessMachine::where($own, $ownerId)->find($capId) : null;
            if ($capId && ! $cap) {
                $errors['machines'] = "Row {$n}: this pair does not belong to the record.";

                continue;
            }
            $otherId = $cap ? (int) $cap->{$oth} : (int) ($r['other_id'] ?? 0);
            if (! $cap && ! $otherId) {
                if ($this->blank($r)) {
                    continue;
                }
                $errors['machines'] = "Row {$n}: choose ".($side === 'process' ? 'a machine' : 'a process').'.';

                continue;
            }
            if (! $cap) {
                $candidate = $side === 'process' ? ProdMachine::where('activate', true)->where('is_process_machine', true)->find($otherId) : ProdProcess::where('activate', true)->where('is_manual', false)->find($otherId);
                $existing = ProdProcessMachine::where([$own => $ownerId, $oth => $otherId])->first();
                if (! $candidate && ! $existing) {
                    $errors['machines'] = "Row {$n}: ".($side === 'process' ? 'only active machines that run processes can be paired.' : 'choose an active process that uses a machine.');

                    continue;
                }
                $cap = $existing;
            }
            if (isset($seen[$otherId])) {
                $errors['machines'] = "Row {$n}: this pair appears twice.";

                continue;
            }
            $seen[$otherId] = true;
            $v = [];
            foreach (self::FIELDS as $f) {
                $raw = $r[$f] ?? '';
                $v[$f] = $f === 'note' ? (trim((string) $raw) === '' ? null : trim((string) $raw)) : (($raw === '' || $raw === null) ? null : Locale::input($raw));
            }
            $check = Validator::make($v, ['setup_minutes' => ['nullable', 'integer', 'min:0', 'max:65535'], 'speed_per_hour' => ['nullable', 'numeric', 'min:0'], 'waste_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'waste_fixed_qty' => ['nullable', 'integer', 'min:0'], 'hourly_rate' => ['nullable', 'numeric', 'min:0'], 'manpower' => ['nullable', 'integer', 'min:0', 'max:255'], 'gsm_min' => ['nullable', 'integer', 'min:0', 'max:65535'],
                'gsm_max' => ['nullable', 'integer', 'min:0', 'max:65535'], 'note' => ['nullable', 'string', 'max:255']]);
            if ($check->fails()) {
                $errors['machines'] = "Row {$n}: ".$check->errors()->first();

                continue;
            }
            if ($v['gsm_min'] !== null && $v['gsm_max'] !== null && (int) $v['gsm_min'] > (int) $v['gsm_max']) {
                $errors['machines'] = "Row {$n}: the maximum grammage must be at least the minimum.";

                continue;
            }
            $plan[] = [$cap, [$own => $ownerId, $oth => $otherId], $v + ['preference_rank' => ! empty($r['primary']) && $r['primary'] !== 'false' ? 1 : 2, 'is_certified' => $this->flag($r['is_certified'] ?? false),
                'activate' => $this->flag($r['activate'] ?? true)]];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        foreach ($plan as [$cap, $keys, $data]) {
            if ($cap) {
                $cap->update($data + ['updated_by' => $user->login]);
            } else {
                ProdProcessMachine::create($keys + $data + ['created_by' => $user->login, 'updated_by' => $user->login]);
            }
        }
    }

    private function flag(mixed $v): bool
    {
        return filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }

    private function blank(array $r): bool
    {
        foreach (self::FIELDS as $f) {
            if (trim((string) ($r[$f] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }
}
