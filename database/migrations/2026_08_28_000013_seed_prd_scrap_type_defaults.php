<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Data awal tipe reject/waste -- kategori umum yang dipakai print-industry
// MIS (Systrade, PrintVis, eProductivity/EFI) sebagai "waste/spoilage reason
// code", disesuaikan istilah cetak offset & packaging. Ini DATA, bukan enum
// kode -- PPIC/QA bebas edit/tambah lewat layar Kelola Tipe Scrap kapan pun,
// tanpa deploy ulang. User akan menyusulkan daftar riil PT Telasih Makmur
// (dari histori lama) untuk menggantikan/melengkapi -- baris di sini cuma
// starting point supaya fitur langsung bisa dipakai, bukan daftar final.
return new class extends Migration
{
    private array $types = [
        // REJECT -- barang jadi cacat, biasanya ketahuan saat QC.
        ['REJECT', 'PRINT_DEFECT', 'Cacat Cetak (misalign/ghosting/streaking)', 10],
        ['REJECT', 'COLOR_OFF', 'Warna Tidak Sesuai / Off-Shade', 20],
        ['REJECT', 'REGISTER_ERROR', 'Register Meleset', 30],
        ['REJECT', 'DIMENSION_ERROR', 'Ukuran Tidak Presisi', 40],
        ['REJECT', 'LAMINATION_DEFECT', 'Cacat Laminasi (gelembung/kerut/kupas)', 50],
        ['REJECT', 'DIECUT_DEFECT', 'Cacat Plong/Potong', 60],
        ['REJECT', 'GLUING_DEFECT', 'Lem Tidak Sempurna', 70],
        ['REJECT', 'CREASE_CRACK', 'Lipatan Retak/Pecah', 80],
        ['REJECT', 'MATERIAL_DEFECT', 'Bahan Baku Cacat dari Supplier', 90],
        ['REJECT', 'CONTAMINATION', 'Kotor/Noda', 100],
        ['REJECT', 'OTHER', 'Lainnya', 999],

        // WASTE -- bahan/proses terbuang, bukan barang jadi cacat.
        ['WASTE', 'MAKEREADY', 'Waste Setup/Makeready', 10],
        ['WASTE', 'CHANGEOVER', 'Waste Ganti Job', 20],
        ['WASTE', 'MACHINE_FAULT', 'Kerusakan Mesin', 30],
        ['WASTE', 'OPERATOR_ERROR', 'Kesalahan Operator', 40],
        ['WASTE', 'TRIM_OFFCUT', 'Sisa Potong Plano', 50],
        ['WASTE', 'HANDLING_DAMAGE', 'Kerusakan Penyimpanan/Handling', 60],
        ['WASTE', 'SPEC_CHANGE', 'Perubahan Spek Customer', 70],
        ['WASTE', 'OTHER', 'Lainnya', 999],
    ];

    public function up(): void
    {
        $prd = DB::connection('tm2prd');

        foreach ($this->types as [$category, $code, $name, $sort]) {
            $prd->table('prd_scrap_type')->updateOrInsert(
                ['category' => $category, 'type_code' => $code],
                ['type_name' => $name, 'sort_order' => $sort, 'activate' => true, 'created_by' => 'system']
            );
        }
    }

    public function down(): void
    {
        $prd = DB::connection('tm2prd');

        foreach ($this->types as [$category, $code, $name, $sort]) {
            $prd->table('prd_scrap_type')->where('category', $category)->where('type_code', $code)->delete();
        }
    }
};
