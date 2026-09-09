<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// Permintaan user 2026-09-09: "saya mau di ilustrasikan di data paling atas
// agar bisa beneran nyata print out master card nya. termasuk artwork dan
// lainnya. Tiru yang ada di mockup." -- part yg sedang dilihat user di
// screenshot (id_part=65, "Rigid Box Parfum #5", mastercard_code fallback
// "MC-65", bagian dari 33 prod_part dummy PDDUM% -- lihat memory
// project_prodev_inqprod_dummy_workload) ternyata BANYAK field kosong
// (die_code/printsize/pack_qty/color_set_1 null, 0 baris processes/
// finishings/artwork) -- itu sebabnya Master Card-nya tampil banyak "--"
// sebelumnya. Migration ini ENRICH part yg SUDAH ADA (bukan bikin part
// baru/duplikat), isi field kosongnya dgn data ilustratif realistis
// mengikuti pola docs/mockups/mockup_mastercard.jpg (Kode Pisau/Ukuran
// Pisau/Warna Pertama/Urutan Pekerjaan/Jenis Finishing + artwork asli),
// TAPI pakai vocabulary/master data project sungguhan (prod_process/
// prod_machine/ref_finishing_type yg SUDAH ADA), bukan menyalin teks
// mockup mentah-mentah. Material/Kualitas TIDAK disentuh di sini -- part
// ini SUDAH Mode #1 (link ke iqp_part 497, sudah py iqp_part_material
// "B KRAFT 275 GR MSE..." 275gsm), originMaterial() di prodev-print.blade.php
// otomatis ambil dari situ begitu inquiry 505 confirmed & bukan DRAFT/
// CANCELLED/LOST (dicek dulu, tidak diasumsikan).
return new class extends Migration
{
    private const PART_ID = 65;

    public function up(): void
    {
        $mst = DB::connection('tm2mst');

        // Idempoten -- kalau sudah pernah dijalankan (ada baris routing),
        // jangan dobel-insert.
        if ($mst->table('prod_part_process')->where('part_id', self::PART_ID)->exists()) {
            return;
        }

        $part = $mst->table('prod_part')->where('id_part', self::PART_ID)->first();

        if (! $part) {
            return;
        }

        $mst->table('prod_part')->where('id_part', self::PART_ID)->update([
            'die_code' => 'PISAU RBP5 STANDAR',
            'printsize_p' => $part->sheet_raw_w_cm,
            'printsize_l' => $part->sheet_raw_h_cm,
            'pack_qty' => 24,
            'color_set_1' => json_encode(['BLACK', 'CYAN', 'MAGENTA', 'YELLOW']),
            'updated_by' => 'system',
            'updated_at' => now(),
        ]);

        $mst->table('prod_product')->where('id_product', $part->product_id)->update([
            'design_no' => 'DS'.str_pad((string) $part->product_id, 6, '0', STR_PAD_LEFT),
            'updated_by' => 'system',
            'updated_at' => now(),
        ]);

        // Urutan Pekerjaan (Routing) -- process_id/machine_id nyata dari master
        // (prod_process/prod_machine), bukan teks bebas.
        $processCodes = [
            ['CETAK', 'Cetak 1'],
            ['QC CETAK', null],
            ['LAMINASI', 'Laminasi'],
            ['UV', null],
            ['PLONG', 'Plong 1'],
            ['KUPAS', null],
            ['HITUNG', null],
            ['WRAPPING', null],
        ];

        $seq = 10;
        foreach ($processCodes as [$processName, $machineName]) {
            $processId = $mst->table('prod_process')->where('process_name', $processName)->value('id_process');
            $machineId = $machineName ? $mst->table('prod_machine')->where('machine_name', $machineName)->value('machine_id') : null;

            if (! $processId) {
                continue;
            }

            $mst->table('prod_part_process')->insert([
                'part_id' => self::PART_ID,
                'branch_code' => 'MAIN',
                'step_seq' => $seq,
                'flow_type' => 'LINEAR',
                'process_id' => $processId,
                'machine_id' => $machineId,
                'qty_factor' => 1,
                'created_by' => 'system',
            ]);

            $seq += 10;
        }

        // Jenis Finishing -- ref_finishing_type nyata.
        $finishings = [
            ['Laminating Doff', 'Plastik doff 85 cm, 1 sisi'],
            ['UV Spot', 'Area logo brand'],
            ['Hot Print', 'Hot print gold, area nama produk'],
            ['Emboss', 'Emboss logo di tutup'],
        ];

        $seq = 10;
        foreach ($finishings as [$finishingName, $notes]) {
            $finishingTypeId = $mst->table('ref_finishing_type')->where('finishing_name', $finishingName)->value('id_finishing_type');

            if (! $finishingTypeId) {
                continue;
            }

            $mst->table('prod_part_finishing')->insert([
                'part_id' => self::PART_ID,
                'seq' => $seq,
                'finishing_type_id' => $finishingTypeId,
                'notes' => $notes,
                'created_by' => 'system',
            ]);

            $seq += 10;
        }

        // Artwork contoh -- generate lewat GD (bukan aset eksternal), pola
        // SAMA persis dgn 2026_09_04_000008_seed_demo_artwork_preview_inquiry.php
        // (Inquiry artwork engine demo) -- box parfum look (navy/gold), disk
        // 'public' spy sama persis dgn upload sungguhan.
        $img = imagecreatetruecolor(700, 900);
        $navy = imagecolorallocate($img, 20, 24, 38);
        $gold = imagecolorallocate($img, 197, 160, 89);
        $goldDark = imagecolorallocate($img, 150, 118, 58);
        $cream = imagecolorallocate($img, 245, 238, 224);
        imagefilledrectangle($img, 0, 0, 699, 899, $navy);
        imagefilledrectangle($img, 24, 24, 675, 875, $navy);
        imagerectangle($img, 24, 24, 675, 875, $gold);
        imagerectangle($img, 34, 34, 665, 865, $gold);
        imagefilledellipse($img, 350, 320, 260, 260, $goldDark);
        imagefilledellipse($img, 350, 320, 200, 200, $navy);
        imagefilledrectangle($img, 320, 220, 380, 380, $cream);
        imagefilledrectangle($img, 335, 190, 365, 225, $cream);
        imagestring($img, 5, 210, 480, 'RIGID BOX PARFUM', $gold);
        imagestring($img, 3, 260, 515, '#5 EXCLUSIVE', $cream);
        imagestring($img, 2, 250, 560, 'CV. TELASIH MAKMUR', $gold);
        imagestring($img, 1, 275, 800, 'CONTOH ARTWORK ILUSTRATIF', $cream);
        imagestring($img, 1, 260, 820, '(demo Master Card, bukan file desain asli)', $cream);

        $relativePath = 'prod_part/'.self::PART_ID.'/artwork/demo-mastercard.jpg';
        $absoluteDir = Storage::disk('public')->path('prod_part/'.self::PART_ID.'/artwork');

        if (! is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0755, true);
        }

        imagejpeg($img, Storage::disk('public')->path($relativePath), 90);
        imagedestroy($img);

        $mst->table('prod_part_artwork')->insert([
            'part_id' => self::PART_ID,
            'version_no' => 1,
            'artwork_path' => $relativePath,
            'proof_required' => true,
            'is_approved' => true,
            'approved_by' => 'system',
            'approved_at' => now(),
            'activate' => true,
            'created_at' => now(),
            'created_by' => 'system',
        ]);
    }

    public function down(): void
    {
        $mst = DB::connection('tm2mst');

        Storage::disk('public')->deleteDirectory('prod_part/'.self::PART_ID.'/artwork');

        $mst->table('prod_part_artwork')->where('part_id', self::PART_ID)->delete();
        $mst->table('prod_part_finishing')->where('part_id', self::PART_ID)->delete();
        $mst->table('prod_part_process')->where('part_id', self::PART_ID)->delete();

        $part = $mst->table('prod_part')->where('id_part', self::PART_ID)->first();

        $mst->table('prod_part')->where('id_part', self::PART_ID)->update([
            'die_code' => null,
            'printsize_p' => null,
            'printsize_l' => null,
            'pack_qty' => null,
            'color_set_1' => null,
            'updated_by' => 'system',
            'updated_at' => now(),
        ]);

        if ($part) {
            $mst->table('prod_product')->where('id_product', $part->product_id)->update([
                'design_no' => null,
                'updated_by' => 'system',
                'updated_at' => now(),
            ]);
        }
    }
};
