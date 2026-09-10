<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// Permintaan user 2026-09-10: "buatkan beberapa dummy data untuk master card
// dari data dummy yang ada... minim 5 dummy data dengan artwork" -- part
// id=65 sudah diperkaya (migration 2026_09_09_000004), ini 4 TAMBAHAN dari
// 33 prod_part dummy PDDUM% yang SUDAH ADA (memory
// project_prodev_inqprod_dummy_workload -- JANGAN bikin part baru/duplikat,
// enrich yg sudah ada), total jadi 5. Dipilih 4 yang variasi kategori produk
// beda (food carton/corrugated elektronik/paper bag/hang tag) supaya contoh
// Master Card kelihatan beragam, bukan 5x kotak yg sama. Semua 4 part ini
// SUDAH Mode #1 (link ke Inquiry CONFIRMED+SUBMITTED, iqp_part_material
// terisi) -- Material/Kualitas di Master Card otomatis kebaca dari situ via
// originMaterial(), TIDAK disentuh di sini, sama persis pola part 65.
return new class extends Migration
{
    private const PARTS = [
        61 => [
            'die_code' => 'PISAU FBK1 STANDAR',
            'pack_qty' => 50,
            'colors' => ['CYAN', 'MAGENTA', 'YELLOW', 'BLACK'],
            'processes' => [
                ['CETAK', 'Cetak 1'], ['QC CETAK', null], ['LAMINATING GLOSSY', 'Laminasi'],
                ['PLONG', 'Plong 1'], ['LEM MESIN', 'FOLDER GLUER'], ['QC PACK', null],
            ],
            'finishings' => [
                ['Laminating Glossy', 'Plastik glossy foodgrade, 1 sisi'],
                ['Varnish', 'Varnish spot area logo'],
            ],
            'artwork' => 'food_carton',
            'artwork_label' => 'FOLDING BOX KARTON MAKANAN',
        ],
        62 => [
            'die_code' => 'PISAU CBE2 STANDAR',
            'pack_qty' => 1,
            'colors' => ['BLACK'],
            'processes' => [
                ['CETAK', 'Cetak 2'], ['QC CETAK', null], ['PLONG', 'Plong 1'],
                ['LEM MESIN', 'FOLDER GLUER 2'], ['QC PACK', null],
            ],
            'finishings' => [],
            'artwork' => 'corrugated_kraft',
            'artwork_label' => 'CORRUGATED BOX ELEKTRONIK',
        ],
        68 => [
            'die_code' => 'PISAU PBB8 STANDAR',
            'pack_qty' => 100,
            'colors' => ['BLACK', 'GOLD'],
            'processes' => [
                ['CETAK', 'Cetak 1'], ['QC CETAK', null], ['LAMINATING DOFT', 'Laminasi'],
                ['PLONG', 'Plong 1'], ['LEM MESIN', 'FOLDER GLUER 2'], ['KUPAS', null], ['QC PACK', null],
            ],
            'finishings' => [
                ['Laminating Doff', 'Matte look premium, 1 sisi'],
                ['Foil Stamping', 'Gold foil area logo & handle'],
            ],
            'artwork' => 'boutique_bag',
            'artwork_label' => 'PAPER BAG BELANJA',
        ],
        69 => [
            'die_code' => 'PISAU HTP9 STANDAR',
            'pack_qty' => 500,
            'colors' => ['BLACK'],
            'processes' => [
                ['CETAK', 'Cetak 2'], ['QC CETAK', null], ['LAMINATING GLOSSY', 'Laminasi'],
                ['PLONG', 'Plong 1'], ['HITUNG', null], ['WRAPPING', null],
            ],
            'finishings' => [
                ['Emboss', 'Emboss logo di tengah tag'],
                ['Deboss', 'Deboss garis tepi tag'],
            ],
            'artwork' => 'hang_tag',
            'artwork_label' => 'HANG TAG PRODUK',
        ],
    ];

    public function up(): void
    {
        $mst = DB::connection('tm2mst');

        foreach (self::PARTS as $partId => $cfg) {
            // Idempoten per part -- sama pola migration 000004.
            if ($mst->table('prod_part_process')->where('part_id', $partId)->exists()) {
                continue;
            }

            $part = $mst->table('prod_part')->where('id_part', $partId)->first();

            if (! $part) {
                continue;
            }

            $mst->table('prod_part')->where('id_part', $partId)->update([
                'die_code' => $cfg['die_code'],
                'printsize_p' => $part->sheet_raw_w_cm,
                'printsize_l' => $part->sheet_raw_h_cm,
                'pack_qty' => $cfg['pack_qty'],
                'color_set_1' => json_encode($cfg['colors']),
                'updated_by' => 'system',
                'updated_at' => now(),
            ]);

            $mst->table('prod_product')->where('id_product', $part->product_id)->update([
                'design_no' => 'DS'.str_pad((string) $part->product_id, 6, '0', STR_PAD_LEFT),
                'updated_by' => 'system',
                'updated_at' => now(),
            ]);

            $seq = 10;
            foreach ($cfg['processes'] as [$processName, $machineName]) {
                $processId = $mst->table('prod_process')->where('process_name', $processName)->value('id_process');
                $machineId = $machineName ? $mst->table('prod_machine')->where('machine_name', $machineName)->value('machine_id') : null;

                if (! $processId) {
                    continue;
                }

                $mst->table('prod_part_process')->insert([
                    'part_id' => $partId,
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

            $seq = 10;
            foreach ($cfg['finishings'] as [$finishingName, $notes]) {
                $finishingTypeId = $mst->table('ref_finishing_type')->where('finishing_name', $finishingName)->value('id_finishing_type');

                if (! $finishingTypeId) {
                    continue;
                }

                $mst->table('prod_part_finishing')->insert([
                    'part_id' => $partId,
                    'seq' => $seq,
                    'finishing_type_id' => $finishingTypeId,
                    'notes' => $notes,
                    'created_by' => 'system',
                ]);

                $seq += 10;
            }

            $relativePath = 'prod_part/'.$partId.'/artwork/demo-mastercard.jpg';
            $absoluteDir = Storage::disk('public')->path('prod_part/'.$partId.'/artwork');

            if (! is_dir($absoluteDir)) {
                mkdir($absoluteDir, 0755, true);
            }

            $this->generateArtwork($cfg['artwork'], $cfg['artwork_label'], Storage::disk('public')->path($relativePath));

            $mst->table('prod_part_artwork')->insert([
                'part_id' => $partId,
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
    }

    // Artwork ilustratif via GD (bukan aset eksternal) -- pola sama persis
    // migration 2026_09_09_000004 (part 65) & 2026_09_04_000008 (Inquiry
    // artwork engine demo). 4 gaya beda supaya kelihatan variasi kategori
    // produk, bukan template tunggal diulang.
    private function generateArtwork(string $style, string $label, string $absolutePath): void
    {
        $img = imagecreatetruecolor(700, 900);

        match ($style) {
            'food_carton' => $this->drawFoodCarton($img, $label),
            'corrugated_kraft' => $this->drawCorrugatedKraft($img, $label),
            'boutique_bag' => $this->drawBoutiqueBag($img, $label),
            'hang_tag' => $this->drawHangTag($img, $label),
            default => null,
        };

        imagejpeg($img, $absolutePath, 90);
        imagedestroy($img);
    }

    private function drawFoodCarton($img, string $label): void
    {
        $green = imagecolorallocate($img, 46, 125, 50);
        $greenDark = imagecolorallocate($img, 27, 94, 32);
        $white = imagecolorallocate($img, 255, 255, 255);
        $cream = imagecolorallocate($img, 255, 249, 230);
        imagefilledrectangle($img, 0, 0, 699, 899, $cream);
        imagefilledrectangle($img, 0, 0, 699, 220, $green);
        imagefilledellipse($img, 350, 480, 320, 320, $white);
        imagefilledellipse($img, 350, 480, 260, 260, $green);
        imagefilledellipse($img, 300, 430, 60, 100, $greenDark);
        imagefilledellipse($img, 400, 430, 60, 100, $greenDark);
        imagestring($img, 5, 160, 80, 'NIKI ECO CATERING', $white);
        imagestring($img, 3, 220, 130, 'FRESH & HEALTHY', $white);
        imagestring($img, 4, 190, 780, $label, $greenDark);
        imagestring($img, 1, 220, 850, 'CONTOH ARTWORK ILUSTRATIF (demo Master Card)', $greenDark);
        imagerectangle($img, 15, 15, 684, 884, $green);
    }

    private function drawCorrugatedKraft($img, string $label): void
    {
        $kraft = imagecolorallocate($img, 178, 140, 94);
        $kraftDark = imagecolorallocate($img, 122, 92, 58);
        $black = imagecolorallocate($img, 20, 20, 20);
        imagefilledrectangle($img, 0, 0, 699, 899, $kraft);
        for ($y = 0; $y < 900; $y += 18) {
            imageline($img, 0, $y, 700, $y, $kraftDark);
        }
        imagefilledrectangle($img, 60, 350, 640, 560, $kraft);
        imagerectangle($img, 60, 350, 640, 560, $black);
        imagerectangle($img, 70, 360, 630, 550, $black);
        imagestring($img, 5, 130, 400, 'FRAGILE - ELEKTRONIK', $black);
        imagestring($img, 4, 200, 440, 'HANDLE WITH CARE', $black);
        imagestring($img, 4, 210, 780, $label, $black);
        imagestring($img, 1, 220, 850, 'CONTOH ARTWORK ILUSTRATIF (demo Master Card)', $black);
    }

    private function drawBoutiqueBag($img, string $label): void
    {
        $black = imagecolorallocate($img, 18, 18, 18);
        $gold = imagecolorallocate($img, 197, 160, 89);
        imagefilledrectangle($img, 0, 0, 699, 899, $black);
        imagerectangle($img, 30, 30, 669, 869, $gold);
        imagerectangle($img, 40, 40, 659, 859, $gold);
        imagefilledellipse($img, 350, 400, 200, 200, $black);
        imagearc($img, 320, 300, 60, 90, 180, 360, $gold);
        imagearc($img, 380, 300, 60, 90, 180, 360, $gold);
        imagestring($img, 5, 220, 560, 'TANTO EDY', $gold);
        imagestring($img, 3, 240, 600, 'BOUTIQUE COLLECTION', $gold);
        imagestring($img, 4, 220, 780, $label, $gold);
        imagestring($img, 1, 220, 850, 'CONTOH ARTWORK ILUSTRATIF (demo Master Card)', $gold);
    }

    private function drawHangTag($img, string $label): void
    {
        $tan = imagecolorallocate($img, 232, 220, 196);
        $black = imagecolorallocate($img, 30, 30, 30);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, 0, 0, 699, 899, $white);
        imagefilledrectangle($img, 150, 200, 550, 700, $tan);
        imagerectangle($img, 150, 200, 550, 700, $black);
        imagerectangle($img, 165, 215, 535, 685, $black);
        imagefilledellipse($img, 350, 260, 40, 40, $white);
        imageellipse($img, 350, 260, 40, 40, $black);
        imagestring($img, 5, 260, 420, 'PAK TANTO', $black);
        imagestring($img, 3, 250, 460, 'PREMIUM QUALITY', $black);
        imagestring($img, 4, 200, 780, $label, $black);
        imagestring($img, 1, 220, 850, 'CONTOH ARTWORK ILUSTRATIF (demo Master Card)', $black);
    }

    public function down(): void
    {
        $mst = DB::connection('tm2mst');

        foreach (array_keys(self::PARTS) as $partId) {
            Storage::disk('public')->deleteDirectory('prod_part/'.$partId.'/artwork');

            $mst->table('prod_part_artwork')->where('part_id', $partId)->delete();
            $mst->table('prod_part_finishing')->where('part_id', $partId)->delete();
            $mst->table('prod_part_process')->where('part_id', $partId)->delete();

            $part = $mst->table('prod_part')->where('id_part', $partId)->first();

            $mst->table('prod_part')->where('id_part', $partId)->update([
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
    }
};
