<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Taut 6 karyawan LEGACY-UNMAPPED (position_id=9, "belum ada penugasan
// riil") ke posisi baru dari STRUKTUR.pdf -- HANYA yang aman: nama unik
// (1 match aktif di emp_employee), 1 kemunculan di PDF, BUKAN kotak
// tim-berbagi (mis. "Mahmud/Heru", "Trio/Agung/Dwi/Puji"). Dicek manual
// 2026-08-29 (lihat search_pdf_names.php di riwayat sesi).
//
// SENGAJA TIDAK ditaut (dicatat di sini, bukan diam-diam dilewati):
// - Gani/Mahmud/Heru/Trio/Agung -- SUDAH punya position_id spesifik BUKAN 9
//   (mis. Gani = "Helper Gudang Lv 2", position_id=11) -- menimpa itu
//   berisiko: bisa jadi data lama akurat & PDF-nya orang lain dgn nama sama.
// - Ringga -- muncul 2x di PDF utk 2 peran beda (NPD di Marketing & Pre
//   Press SPV di Produksi), DB cuma punya 1 "RINGGA GITA LESMANA" aktif --
//   tidak bisa dipastikan yang mana, mungkin malah 2 orang beda nama sama.
// - Wiwik -- 2 kandidat aktif+nonaktif di DB dgn nama sama, tak bisa dipastikan.
// - Firman -- exact match "FIRMAN" (id 354) berstatus NONAKTIF di DB,
//   kontradiksi dgn PDF yg menunjukkan dia manager aktif -- data mencurigakan,
//   tidak ditaut sampai dicek manual oleh HR.
// - Kotak tim-berbagi (Tia/Suro/Fardan, Mahmud/Heru, Trio/Agung/Dwi/Puji,
//   Purna/Yuna, Ringga/Alif) -- 1 posisi org chart cuma bisa tampilkan 1
//   occupant, menaut salah satu orang akan salah merepresentasikan sisanya
//   sbg "tidak ada" padahal mereka juga menjabat peran itu.
// - Carrij Soehendra, Puspita Dewi, Mia Friani, Dinul Rohman, Diyana,
//   Triono, Dini, Zizi, Purna, Fardan -- TIDAK DITEMUKAN sama sekali di
//   484 emp_employee (mungkin belum ter-migrasi dari sistem lama).
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tm2mst');

        $link = function (int $employeeId, string $positionName) use ($db) {
            $posId = $db->table('org_position')->where('position_name', $positionName)->value('position_id');
            $emp = $db->table('emp_employee')->where('employee_id', $employeeId)->where('position_id', 9)->first();

            if ($posId && $emp) {
                $db->table('emp_employee')->where('employee_id', $employeeId)->update(['position_id' => $posId, 'updated_by' => 'claude_code']);
            }
        };

        $link(113, 'General Affair Dept.'); // Adi Pratomo
        $link(125, 'QC SPV');                // Umi Nafiah
        $link(81, 'Logistic Staff');         // Subandi (Bandi)
        $link(119, 'Logistic Admin');        // Windayanti (Winda)
        $link(115, 'Joining SPV');           // Maulana Malik Ibnu Aziz
        $link(136, 'Warehouse Admin');       // Arinda Nindy Putri Silvya
    }

    public function down(): void
    {
        DB::connection('tm2mst')->table('emp_employee')
            ->whereIn('employee_id', [113, 125, 81, 119, 115, 136])
            ->update(['position_id' => 9]);
    }
};
