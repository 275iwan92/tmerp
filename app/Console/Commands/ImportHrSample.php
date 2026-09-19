<?php

namespace App\Console\Commands;

use App\Models\Pyr\PyrEmployeeSalary;
use App\Models\Pyr\PyrPayrollRun;
use App\Services\Payroll\PayrollEngineService;
use App\Support\Hrm\PersonName;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

// Lanjutan hrm:sync-employees-excel (permintaan user 2026-09-19):
//   attributes -- adopsi kolom Excel HR yang belum terpetakan (jabatan, berkas,
//                 deposit, BPJS+KET, validasi tanggal) + frekuensi gaji dari file gaji;
//   roster     -- formasi shift minggu ke-2 & ke-3 September (gambar WhatsApp HR,
//                 ditranskrip ke database/data/hr/formasi_shift_2026_09.json) sbg data
//                 REAL (LIVE); semua jadwal dummy/uji lama dihapus (permintaan user 2026-09-19);
//   payroll    -- sample payroll dari 2 file gaji mingguan (1659, 1660) menggantikan
//                 sample VAL-001/002 lama, lalu engine dijalankan & dibandingkan dgn Excel.
// Input file gaji = JSON hasil scripts/hr/extract_payroll_xlsb.py (xlsb tak terbaca PHP).
class ImportHrSample extends Command
{
    protected $signature = 'hrm:import-hr-sample
        {--step=all : attributes | roster | payroll | all}
        {--excel=docs/HR/daftar karyawan - database.xlsx}';

    protected $description = 'Adopsi kolom Excel HR, formasi shift & sample payroll dari file gaji HR';

    private const ACTOR = 'hr-excel-2026-09-19';

    private const ROSTER_ACTOR = 'hr-formasi-2026-09';

    private const OLD_ROSTER_ACTORS = ['hr-formasi-sample'];

    private const PAYROLL_ACTOR = 'hr-payroll-sample';

    private const BPJS_CATEGORIES = ['STAFF', 'OPERATOR', 'HIGH RISK', 'UMUR'];

    /** @var array<int, string> employee_id => full_name (karyawan hasil sync Excel) */
    private array $people = [];

    public function handle(): int
    {
        $step = $this->option('step');
        $this->people = DB::connection('tm2mst')->table('emp_employee')
            ->whereNotNull('device_pin')->pluck('full_name', 'employee_id')->all();

        if (in_array($step, ['attributes', 'all'], true)) {
            $this->importAttributes();
        }
        if (in_array($step, ['roster', 'all'], true)) {
            $this->importRoster();
        }
        if (in_array($step, ['payroll', 'all'], true)) {
            $this->importPayroll();
        }

        return self::SUCCESS;
    }

    // ------------------------------------------------------------ attributes

    private function importAttributes(): void
    {
        $this->line("\n<comment>== 1-6. Kolom Excel HR ==</comment>");
        $db = DB::connection('tm2mst');
        $payroll = $this->payrollFiles();
        $mapping = $this->json('position_mapping_2026_09.json');
        $positionsByName = $db->table('org_position')->pluck('position_id', 'position_name')
            ->mapWithKeys(fn ($id, $n) => [strtoupper(trim($n)) => $id])->all();
        $codeById = $db->table('emp_employee')->whereIn('employee_id', array_keys($this->people))->pluck('employee_code', 'employee_id')->all();

        // Karyawan di file gaji mingguan (sheet PROPOSAL/TA) -> WEEKLY, title keahlian terbaru.
        $weekly = [];
        $unmatchedPayroll = [];
        foreach ($payroll as $file) {
            foreach ($file['proposal'] as $p) {
                $id = PersonName::match($p['name'], $this->people);
                if ($id === null) {
                    $unmatchedPayroll[$p['name']] = true;

                    continue;
                }
                $weekly[$id] = $p + ['batch' => $file['batch']];
            }
        }

        $report = ['tanggal' => [], 'jabatan_kosong' => [], 'cek' => []];
        $counts = ['posisi' => 0, 'bpjs' => 0];
        $asOf = Carbon::parse('2026-09-19');

        $db->transaction(function () use ($db, $weekly, $mapping, $positionsByName, $codeById, $asOf, &$report, &$counts) {
            foreach ($this->readExcel() as $x) {
                $id = $db->table('emp_employee')->where('device_pin', (string) $x['pin'])->value('employee_id');
                if (! $id) {
                    $report['cek'][] = "PIN {$x['pin']} {$x['name']}: tidak ditemukan di emp_employee";

                    continue;
                }
                $code = $codeById[$id] ?? null;
                $patch = [
                    'gender' => $x['gender'],
                    'join_date' => $x['join'],
                    'birth_date' => $x['birth'],
                    'payment_frequency' => isset($weekly[$id]) ? 'WEEKLY' : 'MONTHLY',
                    'personal_file_complete' => match ($x['berkas']) {
                        'ADA' => 1, 'TIDAK ADA' => 0, default => null
                    },
                    'deposit_status' => match ($x['deposit']) {
                        'ADA' => 'ADA', 'NO' => 'TIDAK', 'PROSES' => 'PROSES', 'BELUM' => 'BELUM', default => null
                    },
                ];

                // Poin 6: tanggal Excel dipakai, kecuali jelas mustahil.
                if ($x['birth'] && ($x['birth'] >= $asOf->toDateString() || ($x['join'] && Carbon::parse($x['birth'])->diffInYears(Carbon::parse($x['join']), true) < 14))) {
                    $report['tanggal'][] = "{$x['name']}: tgl lahir {$x['birth']} tidak masuk akal (masuk kerja {$x['join']}) -> dikosongkan";
                    $patch['birth_date'] = null;
                }
                if ($x['lama'] && $x['join'] && ! $this->durationMatches($x['lama'], $x['join'], $asOf)) {
                    $report['tanggal'][] = "{$x['name']}: LAMA KERJA '{$x['lama']}' tidak sesuai MASUK KERJA {$x['join']}";
                }
                if ($x['umur'] && $patch['birth_date'] && ! $this->durationMatches($x['umur'], $x['birth'], $asOf)) {
                    $report['tanggal'][] = "{$x['name']}: UMUR '{$x['umur']}' tidak sesuai TGL LAHIR {$x['birth']}";
                }
                if (isset($weekly[$id]) && $weekly[$id]['join'] && $x['join'] && $weekly[$id]['join'] !== $x['join']) {
                    $report['tanggal'][] = "{$x['name']}: MASUK KERJA Excel HR {$x['join']} vs file gaji {$weekly[$id]['join']} (dipakai Excel HR)";
                }
                if (! is_string($x['divisi'])) {
                    $report['cek'][] = "{$x['name']}: DIVISI berisi '{$x['divisi']}' (salah ketik di Excel)";
                }

                $position = $this->resolvePosition($code, $weekly[$id] ?? null, $mapping, $positionsByName);
                if ($position) {
                    $patch['position_id'] = $position;
                    $counts['posisi']++;
                } elseif ($x['status'] === 'AKTIF') {
                    $report['jabatan_kosong'][] = "{$code} {$x['name']} ({$x['divisi']} / ".($x['posisi'] ?? '-').')';
                }

                $db->table('emp_employee')->where('employee_id', $id)
                    ->update($patch + ['updated_at' => now(), 'updated_by' => self::ACTOR]);

                if ($x['status'] === 'AKTIF') {
                    $this->upsertBpjs($id, $x['bpjs'], $x['ket']);
                    $counts['bpjs']++;
                }
            }

            foreach ($mapping['reset_to_unmapped'] as $employeeId) {
                $db->table('emp_employee')->where('employee_id', $employeeId)->update(['position_id' => 9, 'updated_by' => self::ACTOR]);
            }
        });

        $this->info("Jabatan terpetakan: {$counts['posisi']}, BPJS TK: {$counts['bpjs']}, mingguan (ada di file gaji): ".count($weekly));
        $this->section('Jabatan belum bisa dipastikan (tetap "Belum Dipetakan")', $report['jabatan_kosong']);
        $this->section('Validasi tanggal', $report['tanggal']);
        $this->section('Perlu dicek', array_merge($report['cek'], array_map(fn ($n) => "Nama di file gaji tidak cocok ke karyawan: {$n}", array_keys($unmatchedPayroll))));
    }

    private function resolvePosition(?string $code, ?array $weekly, array $mapping, array $positionsByName): ?int
    {
        if ($code && isset($mapping['org_chart'][$code])) {
            return $mapping['org_chart'][$code][0];
        }
        $title = $weekly['title'] ?? null;
        if ($title === null && $code && isset($mapping['title_fallback'][$code])) {
            $title = $mapping['title_fallback'][$code][0];
        }
        if ($title === null) {
            return null;
        }
        $title = strtoupper(trim($title));
        if (array_key_exists($title, $mapping['title_alias'])) {
            $title = $mapping['title_alias'][$title];
        }

        return $title === null ? null : ($positionsByName[$title] ?? null);
    }

    private function upsertBpjs(int $employeeId, ?string $bpjs, ?string $ket): void
    {
        $bpjs = strtoupper(trim((string) $bpjs));
        $ket = trim((string) $ket);
        [$status, $batch, $programs] = match (true) {
            $bpjs === '2P' => ['TERDAFTAR', null, 2],
            $bpjs === '3P' => ['TERDAFTAR', null, 3],
            str_starts_with($bpjs, 'BATCH') => ['ANTRE', $bpjs, 0],
            $bpjs === 'NO' => ['TIDAK_IKUT', null, 0],
            default => ['BELUM', null, 0],
        };
        $category = in_array(strtoupper($ket), self::BPJS_CATEGORIES, true) ? strtoupper($ket) : null;

        DB::connection('tm2mst')->table('emp_bpjs_ketenagakerjaan')->updateOrInsert(
            ['employee_id' => $employeeId],
            [
                'activate' => $status === 'TERDAFTAR' ? 1 : 0,
                'program_jkk' => $programs >= 2 ? 1 : 0,
                'program_jkm' => $programs >= 2 ? 1 : 0,
                'program_jht' => $programs >= 3 ? 1 : 0,
                'program_jp' => 0,
                'program_jkp' => 0,
                'enrollment_status' => $status,
                'registration_batch' => $batch,
                'bpjs_category' => $category,
                'enrollment_note' => $category === null && $ket !== '' ? $ket : null,
                'updated_at' => now(),
                'updated_by' => self::ACTOR,
            ],
        );
    }

    // "28 THN 6 BLN" dibanding selisih tanggal s/d $asOf, toleransi 1 bulan.
    private function durationMatches(string $text, string $from, Carbon $asOf): bool
    {
        if (! preg_match('/(\d+)\s*THN\s*(\d+)\s*BLN/i', $text, $m)) {
            return true;
        }
        $months = (int) Carbon::parse($from)->diffInMonths($asOf, true);

        return abs($months - ((int) $m[1] * 12 + (int) $m[2])) <= 1;
    }

    /** @return array<int, array<string, mixed>> */
    private function readExcel(): array
    {
        $rows = IOFactory::load(base_path($this->option('excel')))->getActiveSheet()->toArray(null, true, false, false);
        $date = fn ($v) => is_numeric($v) ? ExcelDate::excelToDateTimeObject($v)->format('Y-m-d') : null;
        $text = fn ($v) => ($v = trim((string) $v)) === '' ? null : strtoupper($v);
        $out = [];
        foreach (array_slice($rows, 2) as $r) {
            if (! is_numeric($r[0]) || trim((string) $r[1]) === '') {
                continue;
            }
            $out[] = [
                'pin' => (int) $r[0], 'name' => trim((string) $r[1]),
                'divisi' => is_numeric($r[2]) ? $r[2] : $text($r[2]), 'posisi' => $text($r[3]),
                'status' => $text($r[4]) ?? 'AKTIF', 'join' => $date($r[5]), 'lama' => $text($r[6]),
                'birth' => $date($r[7]), 'umur' => $text($r[8]),
                'gender' => in_array($r[9], ['L', 'P'], true) ? $r[9] : null,
                'berkas' => $text($r[10]), 'deposit' => $text($r[11]), 'bpjs' => $text($r[12]), 'ket' => $text($r[13]),
            ];
        }

        return $out;
    }

    // ---------------------------------------------------------------- roster

    private function importRoster(): void
    {
        $this->line("\n<comment>== Formasi shift (data real) ==</comment>");
        $db = DB::connection('tm2mst');
        $data = $this->json('formasi_shift_2026_09.json');
        $idByCode = $db->table('emp_employee')->pluck('employee_id', 'employee_code')->all();
        $shiftIds = $db->table('emp_shift')->whereIn('shift_code', ['SH1', 'SH2', 'REG'])->pluck('shift_id', 'shift_code')->all();
        $centers = $db->table('emp_work_center as w')->leftJoin('org_department as d', 'd.department_id', '=', 'w.department_id')
            ->select('w.work_center_id', 'w.work_center_code', 'w.machine_id', 'w.department_id', 'd.division_id')
            ->get()->keyBy('work_center_code');
        $scheduleByDept = $db->table('emp_roster_schedule_department')->pluck('schedule_id', 'department_id')->all();

        $rows = [];
        $taken = [];
        $skipped = [];
        foreach ($data['weeks'] as $week) {
            foreach ($week['rows'] as $row) {
                [$post, $shiftCode, $names] = $row;
                $dates = $row[3] ?? $week['dates'];
                $center = $centers[$data['work_centers'][$post]] ?? null;
                if (! $center) {
                    throw new \RuntimeException("Work center {$post} tidak ada.");
                }
                foreach ($names as $nick) {
                    $code = $data['nicknames'][$nick] ?? null;
                    if (! $code) {
                        $skipped["{$nick} ({$post})"] = 'nama panggilan tidak bisa dipastikan';

                        continue;
                    }
                    foreach ($dates as $d) {
                        $empId = $idByCode[$code];
                        if (isset($taken[$empId][$d])) {
                            $skipped["{$nick} {$d} {$post}"] = "sudah di {$taken[$empId][$d]} hari itu";

                            continue;
                        }
                        $taken[$empId][$d] = $post;
                        $rows[] = [
                            'employee_id' => $empId, 'division_id' => $center->division_id, 'machine_id' => $center->machine_id,
                            'work_center_id' => $center->work_center_id, 'schedule_id' => $scheduleByDept[$center->department_id] ?? null,
                            'shift_id' => $shiftIds[$shiftCode], 'roster_date' => $d, 'assigned_by_role' => 'PPIC', 'is_overtime' => 0,
                            'data_origin' => 'LIVE', 'created_at' => now(), 'created_by' => self::ROSTER_ACTOR,
                            'updated_at' => now(), 'updated_by' => self::ROSTER_ACTOR,
                        ];
                    }
                }
            }
        }

        $db->transaction(function () use ($db, $rows) {
            // Jadwal dummy/uji: baris DUMMY, formasi versi sample, 5 baris uji 28 Agu (created_by=system).
            $db->table('emp_shift_roster')
                ->where(fn ($q) => $q->where('data_origin', 'DUMMY')
                    ->orWhereIn('created_by', [self::ROSTER_ACTOR, ...self::OLD_ROSTER_ACTORS])
                    ->orWhere(fn ($q2) => $q2->where('created_by', 'system')->where('roster_date', '2026-08-28')))
                ->delete();
            foreach (array_chunk($rows, 200) as $chunk) {
                $db->table('emp_shift_roster')->insert($chunk);
            }
        });

        $this->info(count($rows).' baris roster ('.count($taken).' karyawan) untuk 7-12 & 14-19 Sep 2026.');
        $this->section('Dilewati', array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($skipped), $skipped));
    }

    // --------------------------------------------------------------- payroll

    private function importPayroll(): void
    {
        $this->line("\n<comment>== Sample payroll (DUMMY) ==</comment>");
        $files = $this->payrollFiles();
        if (! $files) {
            $this->error('Belum ada storage/app/private/hr/payroll_*.json -- jalankan scripts/hr/extract_payroll_xlsb.py dulu.');

            return;
        }

        $this->deleteOldSamples();

        // Rate gaji: file terbaru menang (PyrEmployeeSalary::activeFor tidak melihat periode).
        $rates = [];
        foreach ($files as $file) {
            foreach ($file['proposal'] as $p) {
                if ($id = PersonName::match($p['name'], $this->people)) {
                    $rates[$id] = $p;
                }
            }
        }
        foreach ($rates as $employeeId => $p) {
            // Engine menghitung tarif harian = nilai bulanan / 30 -> simpan harian x 30
            // supaya tarif harian di engine sama persis dengan file gaji.
            PyrEmployeeSalary::create([
                'employee_id' => $employeeId,
                'basic_salary_monthly' => $p['pokok'] * 30,
                'tj_keahlian_monthly' => $p['keahlian'] * 30,
                'tj_loyalitas_monthly' => $p['loyalitas'] * 30,
                'tj_prestasi_monthly' => $p['prestasi'] * 30,
                'bonus_pagi_amount' => $p['bonus_pagi'],
                'bonus_hadir_amount' => $p['bonus_hadir'],
                'bpjs_active' => false,
                'pph21_active' => false,
                'effective_from' => '2026-09-05',
                'activate' => true,
                'created_by' => self::PAYROLL_ACTOR,
            ])->forceFill(['data_origin' => 'DUMMY'])->save();
        }
        $this->info(count($rates).' rate gaji mingguan dibuat.');

        $engine = app(PayrollEngineService::class);
        foreach ($files as $file) {
            $this->importAttendance($file);
            $run = $engine->calculate('WEEKLY', Carbon::parse($file['period_start']), Carbon::parse($file['period_end']), self::PAYROLL_ACTOR);
            DB::connection('tm2pyr')->table('pyr_payroll_run')->where('id_run', $run->id_run)->update(['data_origin' => 'DUMMY']);
            DB::connection('tm2pyr')->table('pyr_payslip')->where('id_run', $run->id_run)->update(['data_origin' => 'DUMMY']);
            DB::connection('tm2pyr')->update('UPDATE pyr_payslip_detail d JOIN pyr_payslip p ON p.id_payslip = d.id_payslip SET d.data_origin = ? WHERE p.id_run = ?', ['DUMMY', $run->id_run]);
            $this->compare($run, $file);
        }
    }

    private function deleteOldSamples(): void
    {
        $mst = DB::connection('tm2mst');
        $pyr = DB::connection('tm2pyr');

        $runIds = $pyr->table('pyr_payroll_run')
            ->where(fn ($q) => $q->where('period_start', '2026-08-22')->orWhere('triggered_by', self::PAYROLL_ACTOR))->pluck('id_run');
        $slipIds = $pyr->table('pyr_payslip')->whereIn('id_run', $runIds)->pluck('id_payslip');
        $pyr->table('pyr_payslip_detail')->whereIn('id_payslip', $slipIds)->delete();
        $pyr->table('pyr_payslip')->whereIn('id_run', $runIds)->delete();
        $pyr->table('pyr_payroll_run')->whereIn('id_run', $runIds)->delete();
        $pyr->table('pyr_employee_salary')->whereIn('created_by', ['claude_code', self::PAYROLL_ACTOR])->delete();

        // Sample VAL lama (MAHMUD/HERU, 22-28 Agu) + import sample sebelumnya.
        $importIds = $mst->table('att_finger_import')
            ->where(fn ($q) => $q->where('imported_by', self::PAYROLL_ACTOR)->orWhere('file_name', 'real_attendance_sample.csv'))->pluck('id_import');
        $mst->table('att_finger_raw')->whereIn('id_import', $importIds)->delete();
        $mst->table('att_finger_raw')->whereIn('employee_id', [7, 8])->whereBetween('scan_datetime', ['2026-08-22', '2026-08-29'])->delete();
        $mst->table('att_daily_status')->whereIn('employee_id', [7, 8])->whereBetween('attendance_date', ['2026-08-22', '2026-08-28'])->delete();
        $mst->table('att_daily_status')->where('source', 'IMPORT_EXCEL')->delete();
        $mst->table('att_finger_import')->whereIn('id_import', $importIds)->delete();

        $valShift = $mst->table('emp_shift')->where('shift_code', 'VAL')->value('shift_id');
        if ($valShift) {
            $mst->table('emp_employee')->where('default_shift_id', $valShift)->update(['default_shift_id' => null]);
            $mst->table('emp_shift_detail')->where('shift_id', $valShift)->delete();
            $mst->table('emp_shift')->where('shift_id', $valShift)->delete();
        }
        $this->info('Sample lama dihapus (run '.$runIds->implode(',').', shift VAL, presensi 22-28 Agu).');
    }

    // TA = presensi yang sudah dinormalisasi HR (07:30-16:30, Sabtu 15:30) --
    // diimpor apa adanya ke att_finger_raw + att_daily_status (bukan dihitung
    // ulang dari roster: formasi Shift 1/2 tidak tercermin di jam TA).
    private function importAttendance(array $file): void
    {
        $mst = DB::connection('tm2mst');
        $importId = $mst->table('att_finger_import')->insertGetId([
            'import_date' => $file['period_end'], 'file_name' => $file['source'], 'imported_by' => self::PAYROLL_ACTOR,
            'row_count' => 0, 'status' => 'PROCESSED', 'created_at' => now(), 'processed_at' => now(), 'data_origin' => 'DUMMY',
        ]);
        $rekap = collect($file['rekap'])->keyBy(fn ($r) => PersonName::norm($r['name']));
        $pins = $mst->table('emp_employee')->pluck('device_pin', 'employee_id');
        $raw = [];
        $daily = [];
        $unmatched = [];

        foreach ($file['attendance'] as $a) {
            $id = PersonName::match($a['name'], $this->people);
            if (! $id) {
                $unmatched[] = $a['name'];

                continue;
            }
            $r = $rekap->get(PersonName::norm($a['name']));
            $leave = array_merge(
                array_fill(0, (int) ($r['sakit'] ?? 0), 'S'),
                array_fill(0, (int) ($r['ijin'] ?? 0), 'I'),
                array_fill(0, (int) ($r['cuti'] ?? 0), 'C'),
            );

            foreach ($a['days'] as $d) {
                $date = Carbon::parse($d['date']);
                if ($date->isSunday() && ! $d['in']) {
                    continue;
                }
                $shiftEnd = $date->isSaturday() ? '15:30' : '16:30';
                if (! $d['in']) {
                    $daily[] = $this->dailyRow($id, $d['date'], array_shift($leave) ?? 'A');

                    continue;
                }
                foreach (['IN' => $d['in'], 'OUT' => $d['out']] as $type => $time) {
                    if ($time) {
                        $raw[] = ['id_import' => $importId, 'employee_id' => $id, 'scan_datetime' => "{$d['date']} {$time}:00",
                            'scan_type' => $type, 'raw_device_code' => $pins[$id], 'data_origin' => 'DUMMY'];
                    }
                }
                $late = max(0, (int) Carbon::parse("{$d['date']} 07:30")->diffInMinutes(Carbon::parse("{$d['date']} {$d['in']}"), false));
                $overtime = (int) round(max(0, $d['hours'] - $d['normative']) * 60);
                $status = match (true) {
                    $late === 0 => 'H', $late < 5 => 'T-5', $late <= 15 => 'T+5', default => 'T+15',
                };
                $daily[] = $this->dailyRow($id, $d['date'], $status, [
                    'finger_in' => "{$d['date']} {$d['in']}:00", 'finger_out' => $d['out'] ? "{$d['date']} {$d['out']}:00" : null,
                    'shift_start' => '07:30:00', 'shift_end' => "{$shiftEnd}:00", 'minutes_late' => $late,
                    'overtime_minutes' => $overtime, 'overtime_type' => $overtime > 0 ? ($date->isSunday() ? 'SUNDAY' : 'WORKDAY') : null,
                    'is_uang_roti' => $overtime >= 120 ? 1 : 0,
                ]);
            }
        }

        $mst->transaction(function () use ($mst, $raw, $daily, $importId) {
            foreach (array_chunk($raw, 500) as $chunk) {
                $mst->table('att_finger_raw')->insert($chunk);
            }
            foreach (array_chunk($daily, 500) as $chunk) {
                $mst->table('att_daily_status')->upsert($chunk, ['employee_id', 'attendance_date']);
            }
            $mst->table('att_finger_import')->where('id_import', $importId)->update(['row_count' => count($raw)]);
        });

        $this->info("{$file['period_label']}: ".count($raw).' scan, '.count($daily).' status harian.');
        $this->section('Nama presensi tidak cocok', $unmatched);
    }

    private function dailyRow(int $employeeId, string $date, string $status, array $extra = []): array
    {
        return $extra + [
            'employee_id' => $employeeId, 'attendance_date' => $date, 'status_code' => $status,
            'finger_in' => null, 'finger_out' => null, 'shift_start' => null, 'shift_end' => null,
            'minutes_late' => 0, 'minutes_early_leave' => 0, 'minutes_out_during_shift' => 0,
            'overtime_minutes' => 0, 'overtime_type' => null, 'is_uang_roti' => 0, 'late_violation_seq_in_period' => null,
            'source' => 'IMPORT_EXCEL', 'calculated_at' => now(), 'data_origin' => 'DUMMY',
        ];
    }

    // Selisih engine vs file gaji -- hasil ditulis ke storage/app/private/hr (berisi angka gaji).
    private function compare(PyrPayrollRun $run, array $file): void
    {
        $excel = [];
        foreach ($file['totals'] as $t) {
            if ($id = PersonName::match($t['name'], $this->people)) {
                $excel[$id] = $t['total'];
            }
        }
        $rekap = [];
        foreach ($file['rekap'] as $r) {
            if ($id = PersonName::match($r['name'], $this->people)) {
                $rekap[$id] = $r;
            }
        }

        $lines = ['employee_id,nama,excel_total,engine_total,selisih,excel_gaji_diterima,engine_komponen,excel_lembur,engine_lembur,excel_uang_roti,engine_uang_roti,anomali'];
        $sum = ['excel' => 0, 'engine' => 0, 'base_ok' => 0, 'n' => 0];
        foreach ($run->payslips()->get() as $slip) {
            $id = $slip->employee_id;
            $components = (float) $slip->total_pokok + $slip->total_tj_keahlian + $slip->total_tj_loyalitas
                + $slip->total_tj_prestasi + $slip->total_bonus_pagi + $slip->total_bonus_hadir;
            $x = $excel[$id] ?? null;
            $r = $rekap[$id] ?? null;
            if ($x !== null && ! $slip->has_anomaly) {
                $sum['excel'] += $x;
                $sum['engine'] += (float) $slip->total_gaji_bersih;
                $sum['n']++;
                if ($r && abs($components - $r['gaji_diterima']) < 1) {
                    $sum['base_ok']++;
                }
            }
            $lines[] = implode(',', [$id, '"'.($this->people[$id] ?? '?').'"', $x ?? '', (float) $slip->total_gaji_bersih,
                $x !== null ? round((float) $slip->total_gaji_bersih - $x) : '', $r['gaji_diterima'] ?? '', $components,
                $r['lembur'] ?? '', $slip->total_overtime_pay, $r['uang_roti'] ?? '', $slip->total_uang_roti, $slip->has_anomaly ? 1 : 0]);
        }

        $path = storage_path("app/private/hr/payroll_compare_{$file['batch']}.csv");
        file_put_contents($path, implode("\n", $lines));
        $anomalies = $run->payslips()->where('has_anomaly', true)->count();
        $this->info(sprintf(
            '  Run #%d %s: %d slip (%d anomali tanpa rate). Pokok+tunjangan+bonus sama persis dgn Excel: %d/%d. Total Excel %s vs engine %s. Detail: %s',
            $run->id_run, $file['period_label'], $run->payslips()->count(), $anomalies, $sum['base_ok'], $sum['n'],
            number_format($sum['excel']), number_format($sum['engine']), $path,
        ));
    }

    // ---------------------------------------------------------------- helpers

    /** @return array<int, array> urut batch naik (file terbaru terakhir) */
    private function payrollFiles(): array
    {
        $files = glob(storage_path('app/private/hr/payroll_[0-9]*.json')) ?: [];
        sort($files);

        return array_map(fn ($f) => json_decode(file_get_contents($f), true), $files);
    }

    private function json(string $name): array
    {
        return json_decode(file_get_contents(database_path("data/hr/{$name}")), true, flags: JSON_THROW_ON_ERROR);
    }

    private function section(string $title, array $lines): void
    {
        if (! $lines) {
            return;
        }
        $this->line("  <comment>{$title} (".count($lines).'):</comment>');
        foreach ($lines as $l) {
            $this->line("    - {$l}");
        }
    }
}
