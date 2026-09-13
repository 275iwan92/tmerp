<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Isi jabatan kosong di Struktur Organisasi (hrm.org-chart) dengan pegawai
// simulasi/dummy dari nama-nama nyata di screenshot org chart yang diberikan
// user 2026-09-13 ("Organization-Structure-Mockup" + 5 screenshot turunannya,
// dikonfirmasi user itu SATU pohon yang sama, cuma dipecah gara-gara
// keterbatasan canvas alat desainnya -- bukan 6 chart terpisah).
//
// PENTING: struktur posisi (org_position, id 60-108) & hierarkinya SUDAH ADA
// dari sesi 2026-08-29 (migrasi dari STRUKTUR.pdf, lihat memory
// project_org_chart_structure.md) -- screenshot baru ini konten & hierarkinya
// SAMA PERSIS dengan yang sudah tersimpan, sudah diverifikasi baris-per-baris
// sebelum migration ini ditulis. Migration ini TIDAK mengubah satu pun
// position_id/reports_to_position_id -- HANYA mengisi occupant (emp_employee)
// yang sebelumnya sengaja dibiarkan kosong.
//
// 8 posisi SUDAH punya occupant dari sesi lalu (dicek live sebelum migration
// ini ditulis, TIDAK disentuh lagi): 62 General Manager (Johannes Iwan, id=1
// -- akun user sendiri, sudah benar), 64 Operational Manager, 67 General
// Affair Dept., 68 Human Capital Dept., 95 Warehouse Admin, 99 Logistic
// Admin, 100 Logistic Staff, 107 Joining SPV, 108 QC SPV.
//
// KEPUTUSAN: bukan mencocokkan nama ke 493 pegawai legacy yang sudah ada
// (itu jalan sesi lalu, terbukti berisiko -- banyak nama ambigu/bentrok,
// lihat memory project_org_chart_structure.md bagian "masih sengaja belum
// ditaut"). Sesuai instruksi user eksplisit kali ini ("make simulation or
// data dummy from real captured screens"), SEMUA occupant baru di sini
// adalah baris emp_employee BARU (bukan menaut ke baris lama), supaya tidak
// mewarisi risiko salah atribusi dari data legacy yang berantakan.
//
// Data pribadi (NIK/KK/HP/tanggal lahir/alamat) SENGAJA dibiarkan kosong --
// mengarang NIK 16-digit utk orang sungguhan itu aktif menyesatkan, beda
// dari sekadar "belum lengkap" seperti 493 pegawai lain yang sudah ada.
// Jenis kelamin ditebak dari konvensi nama Indonesia (BUKAN fakta HR
// terverifikasi) -- ditandai jelas di sini & di memory, HR perlu koreksi
// kalau ada yang meleset.
return new class extends Migration
{
    // [nama, position_id, gender tebakan L/P]
    private const OCCUPANTS = [
        ['Carrij Soehendra', 60, 'L'],
        ['Puspita Dewi', 61, 'P'],
        ['Mia Friani', 63, 'P'],
        ['Firman', 65, 'L'],
        ['Dinul Rohman', 66, 'L'],
        ['Wiwik', 72, 'P'],
        ['Dini', 76, 'P'],
        ['Ringga', 77, 'L'],       // NPD -- beda box dari Ringga Pre Press SPV (102) di bawah, lihat catatan ambiguitas nama
        ['Alif', 77, 'L'],         // NPD (ditulis "Ringga/Alif" -- 2 nama 1 kotak)
        ['Zizi', 78, 'P'],
        ['Eni', 79, 'P'],
        ['Purna', 80, 'L'],        // B2B Sales (ditulis "Purna/Yuna" -- 2 nama 1 kotak)
        ['Yuna', 80, 'P'],
        ['Tia', 93, 'P'],          // PPIC Planner (ditulis "Tia/Suro/Fardan" -- 3 nama 1 kotak)
        ['Suro', 93, 'L'],
        ['Fardan', 93, 'L'],
        ['Triono', 96, 'L'],
        ['Mahmud', 97, 'L'],       // Warehouse Staff (ditulis "Mahmud/Heru" -- 2 nama 1 kotak)
        ['Heru', 97, 'L'],
        ['Annisa Ika', 98, 'P'],   // Purchasing Staff -- beda box dari Annisa Ika Plong&Kupas SPV (106), lihat catatan ambiguitas nama
        ['Trio', 101, 'L'],        // Fleet Staff (ditulis "Trio/Agung/Dwi/Puji" -- 4 nama 1 kotak)
        ['Agung', 101, 'L'],
        ['Dwi', 101, 'L'],
        ['Puji', 101, 'L'],
        ['Ringga', 102, 'L'],      // Pre Press SPV -- lihat catatan ambiguitas nama di atas
        ['Gani', 103, 'L'],
        ['Agus', 104, 'L'],
        ['Diyana', 105, 'P'],
        ['Annisa Ika', 106, 'P'],  // Plong & Kupas SPV -- lihat catatan ambiguitas nama di atas
    ];

    public function up(): void
    {
        $db = DB::connection('tm2mst');
        $companyId = $db->table('org_company')->where('activate', true)->value('company_id');
        $nextSeq = (int) $db->table('emp_employee')
            ->where('employee_code', 'like', 'TM-%')
            ->selectRaw("MAX(CAST(SUBSTRING(employee_code, 4) AS UNSIGNED)) AS max_seq")
            ->value('max_seq') + 1;
        $now = now();

        $rows = [];
        foreach (self::OCCUPANTS as [$name, $positionId, $gender]) {
            $rows[] = [
                'employee_code' => 'TM-'.str_pad((string) $nextSeq++, 5, '0', STR_PAD_LEFT),
                'company_id' => $companyId,
                'position_id' => $positionId,
                'full_name' => $name,
                'gender' => $gender,
                'work_status' => 'WORKING',
                'payment_frequency' => 'MONTHLY',
                'activate' => true,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => 'claude_code',
                'updated_by' => 'claude_code',
            ];
        }

        $db->table('emp_employee')->insert($rows);
    }

    public function down(): void
    {
        $db = DB::connection('tm2mst');

        foreach (self::OCCUPANTS as [$name, $positionId]) {
            $db->table('emp_employee')
                ->where('created_by', 'claude_code')
                ->where('full_name', $name)
                ->where('position_id', $positionId)
                ->delete();
        }
    }
};
