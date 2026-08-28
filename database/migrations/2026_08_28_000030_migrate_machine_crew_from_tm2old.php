<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Migrasi + pecah tm2old.mst_machine (255 baris, PK machine_name) sesuai
// request user 2026-08-28:
// - machine_type=MESIN (35) + SUBCON (3) -> tm2mst.prod_machine (skema sudah
//   ada & dipakai layar Master Mesin, 5 baris dummy existing TIDAK dihapus --
//   salah satunya di-FK dummy Target Rate F15, lihat pola sama migration
//   000027 utk prod_process).
// - machine_type=CREW (217) -> tm2mst.prod_crew (tabel baru, migration 000029).
//
// Data lama TIDAK punya kolom divisi (konsep SBU Offset/Digital/Indostry baru
// ada di V2) -- default ke Offset Printing (division_id=1, mayoritas proses
// cetak/plong/laminasi/potong emang lini Offset), kecuali nama mengandung
// "DIGITAL"/"VERSAFIRE" (jelas mesin digital printing) -> division_id=2.
// Ini TEBAKAN BERBASIS HEURISTIK, bukan fakta -- semua bisa dikoreksi manual
// lewat form edit Master Mesin, makanya ditandai di kolom notes.
//
// machine_code di-derive dari machine_name (uppercase, non-alnum -> "_",
// potong 20 char, tambah suffix angka kalau bentrok) krn skema prod_machine
// wajib machine_code unik per divisi & tm2old cuma punya machine_name bebas.
//
// Proses default CREW cuma di-link ke prod_process kalau match PERSIS
// (case-insensitive) ke salah satu dari 19 proses hasil migrasi 000027 --
// sisanya (label lama lebih granular, mis. "PASANG MIKA"/"KANTONG SOLASI
// IKAT") dibiarkan process_id NULL, label asli disimpan di notes (BUKAN
// didrop diam-diam).
return new class extends Migration
{
    private const DIGITAL_KEYWORDS = ['DIGITAL', 'VERSAFIRE'];

    private const TYPE_MAP = [
        'CETAK' => 'Cetak',
        'CUTTING' => 'Potong',
        'DIGITAL' => 'Cetak',
        'LAMINASI' => 'Laminasi',
        'LAMINATING' => 'Laminasi',
        'LEM' => 'Lainnya',
        'LILIN' => 'Lainnya',
        'PLONG' => 'Potong',
        'POTONG' => 'Potong',
        'POTONG BAHAN' => 'Potong',
        'UV' => 'Coating',
        'HOTPRINT' => 'Lainnya',
    ];

    public function up(): void
    {
        $mst = DB::connection('tm2mst');
        $old = DB::connection('tm2old')->table('mst_machine')->orderBy('machine_name')->get();

        $processByName = $mst->table('prod_process')->pluck('id_process', 'process_name')
            ->mapWithKeys(fn ($id, $name) => [strtoupper($name) => $id]);

        $usedCodes = $mst->table('prod_machine')->where('division_id', 1)->pluck('machine_code')
            ->merge($mst->table('prod_machine')->where('division_id', 2)->pluck('machine_code'))
            ->map(fn ($c) => strtoupper($c))->flip()->toArray();

        foreach ($old as $row) {
            if ($row->machine_type === 'CREW') {
                $this->migrateCrew($mst, $row, $processByName);
            } else {
                $this->migrateMachine($mst, $row, $usedCodes);
            }
        }
    }

    private function migrateCrew($mst, $row, $processByName): void
    {
        $processId = $processByName[strtoupper(trim((string) $row->default_process))] ?? null;

        $notes = trim((string) $row->machine_description) ?: null;
        if (! $processId && $row->default_process) {
            $extra = 'Proses asal (tdk match ke Master Proses): '.$row->default_process;
            $notes = $notes ? $notes."\n".$extra : $extra;
        }

        $exists = $mst->table('prod_crew')->whereRaw('LOWER(crew_name) = ?', [strtolower($row->machine_name)])->exists();
        if ($exists) {
            return;
        }

        $mst->table('prod_crew')->insert([
            'crew_name' => trim($row->machine_name),
            'process_id' => $processId,
            'legacy_employee_code' => $row->employee_code ?: null,
            'notes' => $notes,
            'activate' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'created_by' => 'migration:tm2old.mst_machine',
            'updated_by' => 'migration:tm2old.mst_machine',
        ]);
    }

    private function migrateMachine($mst, $row, array &$usedCodes): void
    {
        $exists = $mst->table('prod_machine')->whereRaw('LOWER(machine_name) = ?', [strtolower($row->machine_name)])->exists();
        if ($exists) {
            return;
        }

        $nameUpper = strtoupper($row->machine_name);
        $divisionId = 1;
        foreach (self::DIGITAL_KEYWORDS as $kw) {
            if (str_contains($nameUpper, $kw)) {
                $divisionId = 2;
                break;
            }
        }

        $processKey = strtoupper(trim((string) $row->default_process));
        $machineType = self::TYPE_MAP[$processKey] ?? ($row->machine_type === 'SUBCON' ? 'Subkontraktor' : 'Lainnya');

        $code = $this->generateCode($row->machine_name, $usedCodes);

        $notes = trim((string) $row->machine_description) ?: null;
        $extra = 'Dimigrasi dari tm2old.mst_machine -- divisi & tipe hasil tebakan otomatis, cek ulang manual. Proses default asal: '.($row->default_process ?: '-');
        $notes = $notes ? $notes."\n".$extra : $extra;

        $mst->table('prod_machine')->insert([
            'division_id' => $divisionId,
            'machine_code' => $code,
            'machine_name' => trim($row->machine_name),
            'machine_type' => $machineType,
            'notes' => $notes,
            'activate' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'created_by' => 'migration:tm2old.mst_machine',
            'updated_by' => 'migration:tm2old.mst_machine',
        ]);
    }

    private function generateCode(string $name, array &$usedCodes): string
    {
        $base = strtoupper(trim($name));
        $base = preg_replace('/[^A-Z0-9]+/', '_', $base);
        $base = trim($base, '_');
        $base = substr($base, 0, 20) ?: 'MSN';

        $code = $base;
        $i = 1;
        while (isset($usedCodes[$code])) {
            $suffix = '_'.$i;
            $code = substr($base, 0, 20 - strlen($suffix)).$suffix;
            $i++;
        }

        $usedCodes[$code] = true;

        return $code;
    }

    public function down(): void
    {
        DB::connection('tm2mst')->table('prod_crew')->where('created_by', 'migration:tm2old.mst_machine')->delete();
        DB::connection('tm2mst')->table('prod_machine')->where('created_by', 'migration:tm2old.mst_machine')->delete();
    }
};
