<?php

use App\Models\Master\ProdProcess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// User 2026-09-04, setelah lihat screenshot readonly-summary section "5.
// Proses / Finishing": "Di attachment #1, finishing tidak tampil, contoh data
// dummy yang mana yang ada proses finishing nya?" -- jawaban jujurnya waktu
// itu: TIDAK ADA, 0 dari 66 dummy part (termasuk yang bertipe REVISE/RECALC)
// punya baris iqp_part_process, karena migration 000012 nulis inquiry REVISE/
// RECALC sbg baris independen (insert langsung), bukan lewat
// IqpInquiry::cloneStructureFrom() -- satu-satunya jalur nyata yang mengisi
// tabel ini. Migration ini nambal itu SUPAYA ada contoh nyata utk dicek user:
// isi baris proses utk SEMUA part dummy bertipe REVISE & RECALC (bukan NEW --
// selaras pesan section 5 sendiri: "otomatis terisi... saat dokumen berjenis
// REVISI/REKALKULASI"), pakai routing produksi box yang masuk akal & proses
// master yang SUNGGUHAN ada (prod_process, migrasi dari tm2old.m_process):
// CETAK -> LAMINASI -> PLONG -> LEM MESIN -> QC PACK. Murni tambah baris ke
// part yang SUDAH ADA dari 000012 (aditif, lihat feedback_keep_dummy_data.md).
return new class extends Migration
{
    public function up(): void
    {
        $sdi = DB::connection('tm2sdi');
        $now = now();

        $routing = ProdProcess::whereIn('process_name', ['CETAK', 'LAMINASI', 'PLONG', 'LEM MESIN', 'QC PACK'])
            ->get()->keyBy('process_name');
        $sequence = ['CETAK', 'LAMINASI', 'PLONG', 'LEM MESIN', 'QC PACK'];

        if ($routing->count() !== count($sequence)) {
            return; // master proses tak lengkap di environment ini -- jangan insert data setengah jadi.
        }

        $parts = $sdi->table('iqp_part')
            ->join('iqp_inquiry', 'iqp_part.inquiry_id', '=', 'iqp_inquiry.id_inquiry')
            ->where('iqp_inquiry.inquiry_no', 'like', 'IQ2690%')
            ->whereIn('iqp_inquiry.inquiry_type', ['REVISE', 'RECALC'])
            ->select('iqp_part.id_part')
            ->get();

        foreach ($parts as $row) {
            if ($sdi->table('iqp_part_process')->where('part_id', $row->id_part)->exists()) {
                continue;
            }

            foreach ($sequence as $i => $name) {
                $proc = $routing[$name];
                $sdi->table('iqp_part_process')->insert([
                    'part_id' => $row->id_part,
                    'branch_code' => 'MAIN',
                    'step_seq' => $i + 1,
                    'flow_type' => 'LINEAR',
                    'process_id' => $proc->id_process,
                    'process_name' => $proc->process_name,
                    'machine_id' => null,
                    'qty_factor' => 1.0000,
                    'created_by' => 'system',
                    'updated_by' => 'system',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $sdi = DB::connection('tm2sdi');

        $sdi->table('iqp_part_process')
            ->where('created_by', 'system')
            ->whereIn('part_id', function ($q) {
                $q->select('iqp_part.id_part')
                    ->from('iqp_part')
                    ->join('iqp_inquiry', 'iqp_part.inquiry_id', '=', 'iqp_inquiry.id_inquiry')
                    ->where('iqp_inquiry.inquiry_no', 'like', 'IQ2690%')
                    ->whereIn('iqp_inquiry.inquiry_type', ['REVISE', 'RECALC']);
            })
            ->delete();
    }
};
