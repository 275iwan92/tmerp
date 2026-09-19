<?php

namespace App\Console\Commands;

use App\Services\Hrm\EmployeeMergeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

// Permintaan user 2026-09-19 (docs/HR/daftar karyawan - database.xlsx):
// 1) merge emp_employee yang 1 orang tapi tercatat berkali-kali,
// 2) karyawan di Excel = aktif (yang belum ada di DB dibuat baru),
// 3) yang tidak ada di Excel = nonaktif (kecuali employee_id=1 & baris DUMMY).
//
// Aturan "1 orang" (dari contoh user: ALDI x2 + ALDI ARDIANSYAH, ADAM x3 +
// ADAM M + ADAM KIRANA = masing-masing 1 orang):
// - nama sama persis (setelah normalisasi) = 1 orang, lintas data_origin;
// - di antara baris LEGACY berkata-depan sama, nama "lemah" (cuma kata depan,
//   atau ekor inisial/tag seperti M, F, PS, PSG, PUR, GUDANG) digabung ke satu-
//   satunya nama "kuat" di kelompok itu. Kalau ada >=2 nama kuat (AGUS BUDI
//   SUSANTO vs AGUS MUJIONO) nama lemah dibiarkan -- ambigu, dilaporkan saja.
// Kolom PIN Excel = device_pin mesin finger di tm2old.emp, dipakai sbg bukti
// tambahan saat mencocokkan nama yang ditulis beda (M. RAFLY = MUHAMMAD RAFLY).
class SyncEmployeesFromExcel extends Command
{
    protected $signature = 'hrm:sync-employees-excel
        {file=docs/HR/daftar karyawan - database.xlsx}
        {--dry-run : Tampilkan rencana saja, tanpa menulis ke DB}';

    protected $description = 'Merge karyawan dobel + sinkron status aktif dari Excel daftar karyawan HR';

    private const WEAK_TAGS = ['PSG', 'PUR', 'GUDANG'];

    // Bukti nyata yang tidak tertangkap aturan kata-depan (PIN legacy APRIL = 510
    // = PIN Excel APRILIANTI KARTIKA DEWI).
    private const EXTRA_SAME_PERSON = [[130, 372]];

    private const PROTECTED_IDS = [1];

    private const ACTOR = 'sync-excel-hr';

    /** @var array<int, array> */
    private array $emp = [];

    /** @var array<int, int> employee_id => parent (union-find) */
    private array $parent = [];

    public function handle(EmployeeMergeService $merger): int
    {
        $dry = (bool) $this->option('dry-run');
        $excel = $this->readExcel(base_path($this->argument('file')));
        $this->info(count($excel).' baris Excel dibaca.');

        $this->loadEmployees();
        $match = $this->matchExcel($excel);
        $newRows = array_filter($excel, fn ($x, $i) => ! isset($match[$i]), ARRAY_FILTER_USE_BOTH);

        $this->line("\n<comment>== Excel cocok ke DB: ".count($match).', baru: '.count($newRows).' ==</comment>');
        foreach ($newRows as $x) {
            $this->line("  BARU  PIN {$x['pin']}  {$x['name']}  ({$x['status']})");
        }
        foreach ($match as $i => $id) {
            if ($this->norm($excel[$i]['name']) !== $this->norm($this->emp[$id]['full_name'])) {
                $this->line("  COCOK beda tulisan: PIN {$excel[$i]['pin']} {$excel[$i]['name']}  =>  #{$id} {$this->emp[$id]['full_name']}");
            }
        }

        // Karyawan baru dibuat DULU supaya nama pendek lama (ALIF, Alif dummy)
        // bisa digabung ke dia (ALIF SYAM RAMADHAN). Dry-run: baris virtual id negatif.
        foreach ($newRows as $i => $x) {
            $id = $dry ? -($i + 1) : $this->createFromExcel($x);
            $this->emp[$id] ??= ['employee_id' => $id, 'employee_code' => 'BARU', 'full_name' => $x['name'],
                'data_origin' => 'LIVE', 'activate' => 1, 'legacy_pin' => null, 'nik_ktp' => null, 'birth_date' => $x['birth']];
            $match[$i] = $id;
        }
        if (! $dry) {
            $this->loadEmployees();
        }

        $groups = $this->buildGroups($match);
        $this->line("\n<comment>== Merge: ".count($groups).' kelompok ==</comment>');
        $plan = [];
        foreach ($groups as $ids) {
            $survivor = $this->pickSurvivor($ids, $match);
            $row = array_search($survivor, $match, true);
            $name = $this->bestName($ids, $row === false ? null : $excel[$row]['name']);
            $others = array_values(array_diff($ids, [$survivor]));
            $plan[] = [$survivor, $others, $name];
            $this->line(sprintf('  #%d %s  <=  %s', $survivor, $name,
                implode(', ', array_map(fn ($id) => "#{$id} {$this->emp[$id]['full_name']} ({$this->emp[$id]['data_origin']})", $others))));
        }

        $merged = array_merge([], ...array_column($plan, 1));
        $keepActive = array_merge(array_values($match), self::PROTECTED_IDS);
        $deactivate = array_keys(array_filter($this->emp, fn ($e) => $e['activate']
            && ! in_array($e['employee_id'], $keepActive, true)
            && ! in_array($e['employee_id'], $merged, true)));

        $this->line("\n<comment>== Dinonaktifkan (tidak ada di Excel): ".count($deactivate).' ==</comment>');
        foreach ($deactivate as $id) {
            $this->line("  #{$id} {$this->emp[$id]['employee_code']} {$this->emp[$id]['full_name']} ({$this->emp[$id]['data_origin']})");
        }

        if ($dry) {
            $this->warn("\nDry-run: tidak ada yang ditulis.");

            return self::SUCCESS;
        }

        foreach ($plan as [$survivor, $others, $name]) {
            $merger->merge($survivor, $others, self::ACTOR, 'Nama dobel (sync Excel HR 2026-09-19)', $name);
        }

        DB::connection('tm2mst')->transaction(function () use ($excel, $match, $keepActive) {
            // Setelah merge: survivor bisa ikut aktif karena mewarisi baris aktif.
            DB::connection('tm2mst')->table('emp_employee')
                ->where('activate', 1)->whereNotIn('employee_id', $keepActive)
                ->update(['activate' => 0, 'updated_at' => now(), 'updated_by' => self::ACTOR]);
            foreach ($match as $i => $id) {
                $this->applyExcel($id, $excel[$i]);
            }
        });

        $this->info("\nSelesai. Aktif sekarang: ".DB::connection('tm2mst')->table('emp_employee')->where('activate', 1)->count());

        return self::SUCCESS;
    }

    private function loadEmployees(): void
    {
        $this->emp = collect(DB::connection('tm2mst')->select(
            'SELECT e.*, o.device_pin AS legacy_pin FROM emp_employee e
             LEFT JOIN tm2old.emp o ON o.employee_id = e.legacy_employee_id'
        ))->map(fn ($r) => (array) $r)->keyBy('employee_id')->all();
    }

    /** @return array<int, array{pin:int,name:string,gender:?string,birth:?string,join:?string,status:string}> */
    private function readExcel(string $path): array
    {
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false);
        $out = [];
        foreach (array_slice($rows, 2) as $r) {
            if (! is_numeric($r[0]) || trim((string) $r[1]) === '') {
                continue;
            }
            $out[] = [
                'pin' => (int) $r[0],
                'name' => preg_replace('/\s+/', ' ', trim((string) $r[1])),
                'gender' => in_array($r[9], ['L', 'P'], true) ? $r[9] : null,
                'birth' => $this->excelDate($r[7]),
                'join' => $this->excelDate($r[5]),
                'status' => strtoupper(trim((string) $r[4])) ?: 'AKTIF',
            ];
        }

        return $out;
    }

    private function excelDate(mixed $v): ?string
    {
        return is_numeric($v) ? ExcelDate::excelToDateTimeObject($v)->format('Y-m-d') : null;
    }

    private function norm(?string $s): string
    {
        $s = strtoupper(str_replace('.', ' ', (string) $s));

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Z ]/', '', $s)));
    }

    /** Nama sama, atau beda tulisan tapi jelas 1 orang (inisial, penggalan, typo 1-2 huruf). */
    private function compatible(string $a, string $b): bool
    {
        [$a, $b] = [$this->norm($a), $this->norm($b)];
        if ($a === $b || str_contains(" $b ", " $a ") || str_contains(" $a ", " $b ")) {
            return true;
        }
        $ta = explode(' ', $a);
        $tb = explode(' ', $b);
        if (count($ta) === count($tb)) {
            $ok = true;
            foreach ($ta as $k => $t) {
                $u = $tb[$k];
                $ok = $ok && ($t === $u || (strlen($t) === 1 && str_starts_with($u, $t)) || (strlen($u) === 1 && str_starts_with($t, $u)));
            }
            if ($ok) {
                return true;
            }
        }
        // Kata-kata nama pendek muncul berurutan di nama panjang (boleh penggalan
        // awal kata): ENI HANINGTYAS ~ ENI KURNIATI HANINGTYAS, APRIL ~ APRILIANTI ...
        [$short, $long] = count($ta) <= count($tb) ? [$ta, $tb] : [$tb, $ta];
        $j = 0;
        foreach ($long as $t) {
            if ($j < count($short) && str_starts_with($t, $short[$j])) {
                $j++;
            }
        }
        if ($j === count($short)) {
            return true;
        }
        similar_text($a, $b, $pct);

        return $pct >= 80;
    }

    /** @return array<int, int> index baris Excel => employee_id */
    private function matchExcel(array $excel): array
    {
        $candidates = array_filter($this->emp, fn ($e) => $e['data_origin'] !== 'DUMMY');
        $match = [];
        foreach ($excel as $i => $x) {
            $key = $this->norm($x['name']);
            $exact = array_keys(array_filter($candidates, fn ($e) => $this->norm($e['full_name']) === $key));
            $byPin = array_keys(array_filter($candidates, fn ($e) => (int) $e['legacy_pin'] === $x['pin'] && $this->compatible($x['name'], $e['full_name'])));
            $loose = array_keys(array_filter($candidates, fn ($e) => str_word_count($this->norm($e['full_name'])) >= 2
                && str_starts_with($key.' ', $this->norm($e['full_name']).' ')));

            $pick = $byPin ?: $exact ?: (count($loose) === 1 ? $loose : []);
            if ($pick) {
                $match[$i] = $pick[0];
            }
        }

        return $match;
    }

    private function find(int $id): int
    {
        $this->parent[$id] ??= $id;
        while ($this->parent[$id] !== $id) {
            $id = $this->parent[$id] = $this->parent[$this->parent[$id]] ?? $this->parent[$id];
        }

        return $id;
    }

    private function union(int $a, int $b): void
    {
        $this->parent[$this->find($a)] = $this->find($b);
    }

    /** @return array<int, int[]> */
    private function buildGroups(array $match): array
    {
        $inExcel = fn (int $id) => in_array($id, $match, true);
        // Baris LIVE di luar Excel = data uji/seed (Rudi Hartono, Kandidat E2E) -- tidak ikut merge.
        $usable = array_filter($this->emp, fn ($e) => ! in_array($e['employee_id'], self::PROTECTED_IDS, true)
            && ($e['data_origin'] !== 'LIVE' || $inExcel($e['employee_id'])));

        foreach (collect($usable)->groupBy(fn ($e) => $this->norm($e['full_name'])) as $rows) {
            $ids = $rows->pluck('employee_id')->all();
            foreach ($ids as $id) {
                $this->union($id, $ids[0]);
            }
        }

        foreach (collect($usable)->groupBy(fn ($e) => explode(' ', $this->norm($e['full_name']))[0]) as $first => $rows) {
            if (strlen($first) < 2 || $rows->count() < 2) {
                continue;
            }
            [$weak, $strong] = $rows->partition(fn ($e) => $this->isWeak($e['full_name']));

            // Nama lengkap DUMMY (pengisi org chart, mis. "Dinul Rohman") = orang
            // asli yang namanya mirip (DINUL ROCHMAN), bila kandidatnya tunggal.
            [$dummyStrong, $realStrong] = $strong->partition(fn ($e) => $e['data_origin'] === 'DUMMY');
            foreach ($dummyStrong as $d) {
                $hits = $realStrong->filter(fn ($e) => $this->compatible($d['full_name'], $e['full_name']))
                    ->map(fn ($e) => $this->find($e['employee_id']))->unique();
                if ($hits->count() === 1) {
                    $this->union($d['employee_id'], $hits->first());
                }
            }

            $classes = $realStrong->map(fn ($e) => $this->find($e['employee_id']))->unique()->values();
            $excelClasses = $realStrong->filter(fn ($e) => $inExcel($e['employee_id']))
                ->map(fn ($e) => $this->find($e['employee_id']))->unique()->values();

            foreach ($weak as $w) {
                $target = match (true) {
                    $classes->count() <= 1 => $classes->first() ?? $weak->first()['employee_id'],
                    $w['data_origin'] === 'DUMMY' && $excelClasses->count() === 1 => $excelClasses->first(),
                    default => null,
                };
                if ($target === null) {
                    $this->warn("  Ambigu, dilewati: #{$w['employee_id']} {$w['full_name']} ({$w['data_origin']}) -> "
                        .$realStrong->pluck('full_name')->unique()->implode(' / '));

                    continue;
                }
                $this->union($w['employee_id'], $target);
            }
        }

        foreach (self::EXTRA_SAME_PERSON as [$a, $b]) {
            $this->union($a, $b);
        }

        $groups = [];
        foreach (array_keys($usable) as $id) {
            $groups[$this->find($id)][] = $id;
        }
        $groups = array_values(array_filter($groups, fn ($g) => count($g) > 1));

        // Pengaman: 2 baris yang masing-masing cocok ke orang Excel berbeda, atau
        // NIK/tgl lahir terisi tapi beda, jelas bukan 1 orang -- kelompok batal.
        return array_values(array_filter($groups, function ($ids) use ($match) {
            $excelHits = array_intersect($ids, $match);
            $nik = array_filter(array_unique(array_map(fn ($id) => $this->emp[$id]['nik_ktp'], $ids)));
            $birth = array_filter(array_unique(array_map(fn ($id) => $this->emp[$id]['birth_date'], $ids)));
            if (count($excelHits) > 1 || count($nik) > 1 || count($birth) > 1) {
                $this->warn('  Batal merge (bukti beda orang): '.implode(', ', array_map(fn ($id) => "#{$id} {$this->emp[$id]['full_name']}", $ids)));

                return false;
            }

            return true;
        }));
    }

    private function isWeak(string $name): bool
    {
        $tail = array_slice(explode(' ', $this->norm($name)), 1);

        return collect($tail)->every(fn ($t) => strlen($t) <= 2 || in_array($t, self::WEAK_TAGS, true));
    }

    private function pickSurvivor(array $ids, array $match): int
    {
        return collect($ids)->sortBy([
            fn ($a, $b) => (int) in_array($b, $match, true) <=> (int) in_array($a, $match, true),
            fn ($a, $b) => ($this->emp[$b]['data_origin'] === 'LIVE') <=> ($this->emp[$a]['data_origin'] === 'LIVE'),
            fn ($a, $b) => ($this->emp[$b]['data_origin'] !== 'DUMMY') <=> ($this->emp[$a]['data_origin'] !== 'DUMMY'),
            fn ($a, $b) => $this->emp[$b]['activate'] <=> $this->emp[$a]['activate'],
            fn ($a, $b) => $this->filled($b) <=> $this->filled($a),
            fn ($a, $b) => $a <=> $b,
        ])->first();
    }

    private function filled(int $id): int
    {
        return count(array_filter($this->emp[$id], fn ($v) => $v !== null && $v !== ''));
    }

    /** Nama terlengkap (kata terbanyak, lalu terpanjang) di kelompok, termasuk tulisan Excel. */
    private function bestName(array $ids, ?string $excelName): string
    {
        $names = array_map(fn ($id) => $this->emp[$id]['full_name'], $ids);
        if ($excelName) {
            $names[] = $excelName;
        }
        usort($names, fn ($a, $b) => [str_word_count($this->norm($b)), strlen($b)] <=> [str_word_count($this->norm($a)), strlen($a)]);

        return trim($names[0]);
    }

    private function applyExcel(int $id, array $x): void
    {
        $db = DB::connection('tm2mst');
        $e = (array) $db->table('emp_employee')->where('employee_id', $id)->first();

        // device_pin dipakai ulang mesin finger utk orang lain -> lepas dari pemegang lama.
        $db->table('emp_employee')->where('device_pin', (string) $x['pin'])->where('employee_id', '<>', $id)
            ->update(['device_pin' => null]);

        $patch = [
            'device_pin' => (string) $x['pin'],
            'activate' => $x['status'] === 'RESIGN' ? 0 : 1,
            'birth_date' => $e['birth_date'] ?? $x['birth'],
            'join_date' => $e['join_date'] ?? $x['join'],
            'gender' => $e['gender'] ?? $x['gender'],
        ];
        if (str_word_count($this->norm($x['name'])) > str_word_count($this->norm($e['full_name']))) {
            $patch['full_name'] = $x['name'];
        }

        $db->table('emp_employee')->where('employee_id', $id)
            ->update($patch + ['updated_at' => now(), 'updated_by' => self::ACTOR]);
    }

    private function createFromExcel(array $x): int
    {
        $db = DB::connection('tm2mst');
        $company = $db->table('org_company')->where('company_id', 1)->value('company_code');
        $seq = (int) $db->table('emp_employee')->where('employee_code', 'like', $company.'-%')
            ->selectRaw('MAX(CAST(SUBSTRING(employee_code, ?) AS UNSIGNED)) s', [strlen($company) + 2])->value('s');

        $db->table('emp_employee')->where('device_pin', (string) $x['pin'])->update(['device_pin' => null]);

        return (int) $db->table('emp_employee')->insertGetId([
            'employee_code' => $company.'-'.str_pad((string) ($seq + 1), 5, '0', STR_PAD_LEFT),
            'device_pin' => (string) $x['pin'],
            'company_id' => 1,
            'position_id' => EmployeeMergeService::PLACEHOLDER_POSITION_ID,
            'full_name' => $x['name'],
            'gender' => $x['gender'],
            'birth_date' => $x['birth'],
            'join_date' => $x['join'],
            'work_status' => 'WORKING',
            'activate' => $x['status'] === 'RESIGN' ? 0 : 1,
            'data_origin' => 'LIVE',
            'created_at' => now(),
            'created_by' => self::ACTOR,
            'updated_at' => now(),
            'updated_by' => self::ACTOR,
        ]);
    }
}
