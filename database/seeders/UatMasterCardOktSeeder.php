<?php

/*
 * REMARK
 * Tujuan       : Dummy dari folder docs/attachments/mastercard (kartu PDF Okt 2026): Inquiry lengkap per kartu, lalu rantai resmi
 *                ProDev -> ProCost -> Quotation -> CO -> CPDEL Post -> Work Order -> plan Draft (berhenti SEBELUM APPLY / Confirm plan).
 * Sumber hulu  : Teks dan gambar kartu (docs/attachments/mastercard/*.pdf dan extracted/*), master customer / bahan / proses.
 * Input        : Daftar produk di products(); hanya situs dummy ddt* (--env=mockup).
 * Output       : iqp_* (Inquiry), prod_* (Master Product / part / layout lewat ProductMasterSync + ProDev saveLayout), quo_*, co_*, prd_wo*.
 * Dipakai oleh : Analisa Inquiry -> plan produksi (docs/mastercard-okt-analysis); layar PPIC (Work Order, Simulation).
 * Status       : Dummy. Harga ProCost = angka contoh CostingEngine; qty tier dan tanggal = contoh (kartu tidak memuatnya).
 */

namespace Database\Seeders;

use App\Models\Master\FlowLog;
use App\Models\Master\ProdMasterCard;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\IqpPart;
use App\Models\Sys\SecUser;
use App\Services\Sales\InquiryStage;
use App\Services\Sales\InquiryWriter;
use App\Services\Scheduling\Plan\PlanEvents;
use App\Support\V2\Data\Cpdel;
use App\Support\V2\Data\CustomerOrder;
use App\Support\V2\Data\Prodev;
use Database\Seeders\Concerns\PricedDummyChain;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

//   php artisan db:seed --class=UatMasterCardOktSeeder --env=mockup --force
//   Env MCOKT_PURGE=1 : hapus rantai milik seeder ini lalu keluar.  MCOKT_STOP=inquiry|prodev|procost|co|wo : berhenti di tahap itu.
class UatMasterCardOktSeeder extends Seeder
{
    use PricedDummyChain;

    private const MARK = 'UAT-MCOKT';

    private const SRC = __DIR__.'/../../docs/attachments/mastercard/extracted/';

    private $sdi;

    private $mst;

    private $prd;

    /** Urut tahap, dipakai MCOKT_STOP. */
    private const STAGES = ['inquiry', 'prodev', 'procost', 'co', 'wo'];

    // Mesin cetak dari kartu: nama di kartu -> machine_id master (Lithrone G40 / GL440 = Komori GL440; legacy_machine_names KOMORI_GL440*).
    private const PRESS = ['KOMORI' => 1, 'VERSAFIRE' => 42, 'HANWAY' => 13];

    private const PROC = ['POTONG' => 5, 'CETAK' => 1, 'QC' => 13, 'LAM GLOSSY' => 7, 'LAM DOFT' => 19, 'LAMINASI' => 2, 'PLONG' => 3, 'KUPAS' => 10, 'LEM' => 4,
        'HITUNG' => 15, 'PACK' => 11, 'WRAPPING' => 17, 'STITCHING' => 18];

    /**
     * Satu entri = satu Inquiry. 'parts' = komponen (+ Body rakitan bila lebih dari satu part yang bergabung jadi satu produk fisik).
     * Ukuran mm. flat = bentangan per part = ukuran pisau / jumlah potong di kartu. bop: [kunci proses | null, nama langkah, catatan, mesin|null].
     * null di kunci proses = proses TIDAK ada di master proses (tetap ditulis sebagai langkah BOP, berjalan sebagai SUBCON / manual).
     */
    private function products(): array
    {
        $gloss = ['Laminating Glossy', null];
        $flute = fn ($id, $name, $fl) => [$id, $name, null, $fl];

        return [
            [
                'inq' => 'IQ99MC0006', 'design' => 'DS002581', 'code' => 'YFS-R10K IV350', 'name' => 'R10K IV350 NASKOT AYAM GORENG BALAIKOTA', 'customer' => 712,
                'date' => '2025-07-07', 'tiers' => [10000, 20000, 50000], 'pick' => 1, 'due_days' => 21, 'pack' => 100, 'attn' => 'Yukhsinu Food Solution',
                'note' => 'Kartu DS002581 (07 Jul 2025). Pisau PISAU R10K IV350 NASKOT AYAM GORENG BALAIKOTA 94,9 x 57,1 cm, 3 UP, cetak 97 x 59,5 cm di Komori GL440. Laminating Glossy bagian dalam. Pack plastik 100 biji / pack, ikat 200 / ikat.',
                'card' => 19, 'type' => 'RTE_BOX', 'dims' => [200, 200, 70], 'extra' => ['glue_flap' => 30, 'top_tuck_flap' => 60.17, 'bottom_tuck_flap' => 60.17, 'dust_flap' => 12, 'overflow_margin' => 0],
                'parts' => [[
                    'code' => 'P1', 'name' => 'R10K IV350 Naskot Ayam Goreng Balaikota', 'flat' => [949, 190.33], 'color' => '4/0', 'inks' => ['BLACK', 'CYAN', 'MAGENTA', 'YELLOW'],
                    'mats' => [[['Paper', 'Ivory', '350gr'], 350, 650, 1000, null]], 'fin' => [['Laminating Glossy', 'Dalam']], 'art' => 'mc-ds002581-r10k-iv350-naskot-ayam-goreng-balaikota-200x200x70mm-mc.png',
                    'bop' => [['POTONG', 'POTONG', 'Bahan 65 x 100 cm', null], ['CETAK', 'CETAK', 'GL440 · CMYK · ukuran cetak 97 x 59,5 cm', 'KOMORI'], ['QC', 'QC', null, null],
                        ['LAM GLOSSY', 'LAMINATING GLOSSY', 'Laminating Glossy Dalam', null], ['PLONG', 'PLONG DEPAN', 'Pisau R10K IV350 · 94,9 x 57,1 cm · 3 UP', null], ['KUPAS', 'KUPAS', null, null],
                        ['HITUNG', 'HITUNG', null, null], ['PACK', 'PACK', 'Plastik 100 biji / pack & ikat 200 / ikat', null]],
                    'knife' => [94.9, 57.1], 'print' => [97, 59.5], 'sheet' => [100, 65], 'up' => 3, 'plano' => 1, 'press' => 'KOMORI',
                ]],
            ],
            [
                'inq' => 'IQ99MC0007', 'design' => 'DS002904', 'code' => 'RNL ASFR-SLIDER IV', 'name' => 'BOX SLIDER IV RINAL', 'customer' => 790,
                'date' => '2025-11-24', 'tiers' => [3000, 5000, 10000], 'pick' => 1, 'due_days' => 24, 'pack' => 50, 'attn' => 'Rinal Asfurin',
                'note' => 'Kartu DS002904 (24 Nov 2025). Pisau BOX SLIDER IV RINAL 99,7 x 68,3 cm, 1 SET, Single Join, cetak 101 x 70,5 cm di Lithrone G40. Lem samping & bottom lock, pasang tali ± 40 cm, pack 50 pcs.',
                'card' => 20, 'type' => 'RTE_BOX', 'dims' => [230, 250, 90], 'extra' => ['glue_flap' => 30, 'top_tuck_flap' => 80, 'bottom_tuck_flap' => 80, 'dust_flap' => 12, 'overflow_margin' => 0],
                'join' => 'GLUE_SINGLE',
                'parts' => [[
                    'code' => 'P1', 'name' => 'Box Slider IV Rinal', 'flat' => [997, 683], 'color' => '4/0', 'inks' => ['BLACK', 'CYAN', 'MAGENTA', 'YELLOW'],
                    'mats' => [[['Paper', 'Ivory', '250gr'], 250, 790, 1090, null]], 'fin' => [['Laminating Glossy', null]], 'art' => 'mc-box-slider-iv-rinal-ds002904-mc.jpg',
                    'bop' => [['POTONG', 'POTONG', 'Bahan 79 x 109 cm', null], ['CETAK', 'CETAK', 'Lithrone G40 · CMYK · ukuran cetak 101 x 70,5 cm', 'KOMORI'], ['QC', 'QC', null, null],
                        ['LAM GLOSSY', 'LAMINATING GLOSSY', null, null], ['PLONG', 'PLONG DEPAN', 'Pisau BOX SLIDER IV RINAL (DS002904)_SEMARANG · 99,7 x 68,3 cm', null], ['KUPAS', 'KUPAS', null, null],
                        ['LEM', 'LEM SAMPING & BOTTOM LOCK', 'Single Join', null], [null, 'PASANG TALI', 'Tali ± 40 cm', null], ['HITUNG', 'HITUNG', null, null], ['PACK', 'PACK', '50 pcs / pack', null]],
                    'knife' => [99.7, 68.3], 'print' => [101, 70.5], 'sheet' => [109, 79], 'up' => 1, 'plano' => 4, 'press' => 'KOMORI',
                ]],
            ],
            [
                // Satu produk = Cover + SET 8 .. SET 1. Urutan part mengikuti urutan kerja kartu: set dikumpulkan MUNDUR dari 8 menuju 1 (user 2026-10-14).
                'inq' => 'IQ99MC0008', 'design' => 'DS002948', 'code' => 'RZA-BUKU PEDOMAN PKL SMKN8', 'name' => 'Buku Pedoman Siswa SMKN 8 Malang', 'customer' => 651,
                'date' => '2025-12-11', 'tiers' => [1500, 3000, 5000], 'pick' => 1, 'due_days' => 28, 'pack' => 20, 'attn' => 'Pak Riza',
                'note' => 'Kartu DS002948 (11 Des 2025): Cover (Art Carton 230 gsm, Versafire) + isi SET 8 .. SET 1 (HVS 70 gsm 65 x 90 cm, Lithrone G40, 1 warna hitam). Set (Komplit) dikumpulkan urut mundur dari SET 8 ke SET 1, lem punggung, potong jadi (3 sisi) 21 x 29,7 cm, packing 20 pcs. SET 1-6 = salinan pola SET 7 / SET 8 (hanya SET 7 dan SET 8 yang ada kartunya).',
                'card' => null, 'type' => 'BUKU', 'dims' => [297, 210, 3], 'extra' => ['page_count' => 50, 'binding_type' => 'KLEM_SENG', 'overflow_margin' => 0],
                'assembly' => [['PACK_SET', 'SET (KOMPLIT)', 'Kumpulkan urut mundur SET 8 -> SET 1, lalu Cover', null], ['LEM', 'LEM PUNGGUNG', null, null],
                    ['POTONG', 'POTONG JADI (3 SISI)', '21 x 29,7 cm', null], ['PACK', 'PACKING', '20 pcs', null]],
                'parts' => array_merge([[
                    'code' => 'COVER', 'name' => 'Cover', 'flat' => [426, 302], 'color' => '4/0', 'inks' => ['CYAN', 'MAGENTA', 'YELLOW', 'BLACK'],
                    'mats' => [[['Paper', 'Art carton', '230gr'], 230, 325, 485, null]], 'fin' => [], 'art' => 'mc-cover-buku-pedoman-siswa-smkn-8-malang-ds002948-mc.jpg', 'dims' => [297, 213, 3],
                    'bop' => [['POTONG', 'POTONG', 'Bahan 32,5 x 48,5 cm', null], ['CETAK', 'CETAK', 'Versafire · digital', 'VERSAFIRE'], ['QC', 'QC', null, null], ['POTONG', 'POTONG', 'Potong cover', null]],
                    'knife' => [42.6, 30.2], 'print' => [48.5, 32.5], 'sheet' => [48.5, 32.5], 'up' => 1, 'plano' => null, 'press' => 'VERSAFIRE',
                ]], array_map(fn ($n) => [
                    'code' => 'SET '.$n, 'name' => 'Isi SET '.$n, 'flat' => [852, 604], 'color' => '1/0', 'inks' => ['BLACK'],
                    'mats' => [[['Paper', 'HVS'], 70, 650, 900, null]], 'fin' => [], 'art' => $n % 2 ? 'mc-buku-pedoman-siswa-smkn-8-malang-ds002948-set-7-mc.jpg' : 'mc-buku-pedoman-siswa-smkn-8-malang-ds002948-set-8-mc.jpg',
                    'dims' => [297, 213, 0.1],
                    'bop' => [['POTONG', 'POTONG BAHAN', 'Bahan 65 x 90 cm', null], ['CETAK', 'CETAK', 'Lithrone G40 · 1 warna hitam · 8 halaman per sisi', 'KOMORI'], ['QC', 'QC', null, null],
                        ['POTONG', 'POTONG', 'Potong uk. 21,3 x 30,2 cm', null]],
                    'knife' => [85.2, 60.4], 'print' => [90, 65], 'sheet' => [90, 65], 'up' => 1, 'plano' => null, 'press' => 'KOMORI',
                ], [8, 7, 6, 5, 4, 3, 2, 1])),
            ],
            [
                'inq' => 'IQ99MC0009', 'design' => 'DS002722', 'code' => 'SJJ-JPC GLD N100 MF', 'name' => 'JPC GOLD N100 MF', 'customer' => 67,
                'date' => '2025-10-02', 'tiers' => [10000, 20000, 30000], 'pick' => 1, 'due_days' => 26, 'pack' => 10, 'attn' => 'Pak Habibi', 'join' => 'GLUE_DOUBLE',
                'note' => 'Kartu DS002722 (02 Okt 2025). Kemasan aki JPC GOLD N100 MF 41,3 x 17,7 x 26,4 cm, Double Join (Part A + Part B), pisau SJJ N100 MF (plong dari belakang) 62 x 45,4 cm, 1 UP per part. ACC email 29 Sep 2025, ACC digital 01 Okt 2025 oleh Pak Habibi.',
                'card' => 21, 'type' => 'RSC_2PC', 'dims' => [413, 177, 264], 'extra' => ['glue_flap' => 30, 'flap_extra' => 6.5, 'hand_hole_width' => 0, 'overflow_margin' => 0],
                'assembly' => [['LEM', 'LEM SAMPING', 'Part A + Part B direkatkan 2 sisi (Double Join)', null], ['HITUNG', 'HITUNG', null, null], ['PACK', 'PACK', '10 biji / pack', null]],
                'parts' => array_map(fn ($k) => [
                    'code' => 'PART '.$k, 'name' => 'Part '.$k, 'flat' => [620, 454], 'color' => '4/0', 'inks' => ['BLACK', 'CYAN', 'MAGENTA', 'YELLOW'], 'halves' => $k,
                    'mats' => [[['Paper', 'Duplex', '310gr'], 310, 1000, 700, null], [['Sheet', 'BCF', 'M125/M125X3/K125'], null, 470, 635, 'BCF']],
                    'fin' => [['Laminating Glossy', null]], 'art' => 'mc-jpc-gold-n100-mf-ds002722-mc.jpg',
                    'bop' => [['POTONG', 'POTONG BAHAN', 'Bahan 100 x 70 cm', null], ['CETAK', 'CETAK', 'Lithrone G40 · CMYK · ukuran cetak 97 x 64,5 cm', 'KOMORI'],
                        ['POTONG', 'POTONG', 'Potong uk. 64,5 x 48,5 cm', null], ['QC', 'QC', null, null], ['LAM GLOSSY', 'LAMINATING GLOSSY', null, null],
                        ['LAMINASI', 'LAMINASI', 'Duplex 310 gsm dilem ke SH B/C Flute', null], ['PLONG', 'PLONG BELAKANG', 'Pisau SJJ N100 MF (plong dari belakang) · 62 x 45,4 cm', null], ['KUPAS', 'KUPAS', null, null]],
                    'knife' => [62, 45.4], 'print' => [97, 64.5], 'sheet' => [100, 70], 'up' => 1, 'plano' => null, 'press' => 'KOMORI',
                ], ['A', 'B']),
            ],
            [
                'inq' => 'IQ99MC0010', 'design' => 'DS002908', 'code' => 'SJJ-PWR X N70 MF', 'name' => 'Power X N70 MF', 'customer' => 67,
                'date' => '2025-11-25', 'tiers' => [10000, 20000], 'pick' => 0, 'due_days' => 18, 'pack' => 25, 'attn' => 'Pak Habibi', 'join' => 'GLUE_SINGLE',
                'note' => 'Kartu DS002908 (25 Nov 2025), REVISI TINGGI 25,2 -> 24,9 cm. 30,7 x 17,35 x 24,9 cm, Single Join, pisau SJJ N70 MF 99 x 57,3 cm 1 UP. Warna: Black + DGM 11021 S Champion Red (DIC) / Kopiko Red (Tanindo). ACC email 25 Nov 2025 oleh Pak Habibi.',
                'card' => 22, 'type' => 'RTE_BOX', 'dims' => [307, 173.5, 249], 'extra' => ['glue_flap' => 29, 'top_tuck_flap' => 162, 'bottom_tuck_flap' => 162, 'dust_flap' => 12, 'overflow_margin' => 0],
                'parts' => [[
                    'code' => 'P1', 'name' => 'Power X N70 MF', 'flat' => [990, 573], 'color' => '2/0', 'inks' => ['BLACK', 'DGM 11021 S Champion Red (DIC)'],
                    'mats' => [[['Paper', 'Duplex', '310gr'], 310, 600, 1020, null], [['Sheet', 'SHBF', 'M125/M125/K125'], null, 590, 1015, 'SHBF']],
                    'fin' => [['Laminating Glossy', null]], 'art' => 'mc-power-x-n70-mf-ds002908-mc.jpg',
                    'bop' => [['POTONG', 'POTONG', 'Bahan 60 x 102 cm', null], ['CETAK', 'CETAK', 'Lithrone G40 · 2 warna', 'KOMORI'], ['QC', 'QC', null, null], ['LAM GLOSSY', 'LAMINATING GLOSSY', null, null],
                        ['LAMINASI', 'LAMINASI', 'Duplex 310 gsm dilem ke SH B/F', null], ['PLONG', 'PLONG DEPAN', 'Pisau SJJ N70 MF (30,7 x 17,35 x 24,9 cm)_SEMARANG · 99 x 57,3 cm', null], ['KUPAS', 'KUPAS', null, null],
                        ['LEM', 'LEM SAMPING', null, null], ['HITUNG', 'HITUNG', null, null], ['PACK', 'PACK', '25 biji / ikat', null]],
                    'knife' => [99, 57.3], 'print' => [102, 60], 'sheet' => [102, 60], 'up' => 1, 'plano' => null, 'press' => 'KOMORI',
                ]],
            ],
            [
                'inq' => 'IQ99MC0011', 'design' => 'DS003482', 'code' => 'GM-R10B GMLN DS3482', 'name' => 'R10B Gemmolin Bakpao', 'customer' => 648,
                'date' => '2026-07-29', 'tiers' => [20000, 50000], 'pick' => 0, 'due_days' => 20, 'pack' => 200, 'attn' => 'Pak Purna (Blitar)',
                'note' => 'Kartu DS003482 (29 Jul 2026): penambahan outlet dan varian baru. 22 x 22 x 8,5 cm, pisau R10B Gemmolin (220x220x85mm)_SEMARANG 78,6 x 69,5 cm, 2 UP, cetak 80 x 72 cm Lithrone G40. Laminating Glossy bolak-balik. Shrink 50 pcs, strapping 4 shrink / 200 pcs. ACC email dan digital 28 Jul 2026.',
                'card' => 23, 'type' => 'RTE_BOX', 'dims' => [220, 220, 85], 'extra' => ['glue_flap' => 8, 'top_tuck_flap' => 80, 'bottom_tuck_flap' => 80, 'dust_flap' => 12, 'overflow_margin' => 0],
                'parts' => [[
                    'code' => 'P1', 'name' => 'R10B Gemmolin Bakpao', 'flat' => [786, 347.5], 'color' => '4/0', 'inks' => ['BLACK', 'CYAN', 'MAGENTA', 'YELLOW'],
                    'mats' => [[['Paper', 'Ivory', '350gr'], 350, 800, 720, null]], 'fin' => [['Laminating Glossy', 'Bolak-Balik']], 'art' => 'mc-r10b-gemmolin-bakpao-ds003482-mc.png',
                    'bop' => [['POTONG', 'POTONG BAHAN', 'Bahan 80 x 72 cm', null], ['CETAK', 'CETAK', 'Lithrone G40 · CMYK · 80 x 72 cm', 'KOMORI'], ['QC', 'QC', null, null],
                        ['LAM GLOSSY', 'LAMINATING GLOSSY', 'Bolak-balik', null], ['PLONG', 'PLONG DEPAN', 'Pisau R10B Gemmolin (220x220x85mm)_SEMARANG · 78,6 x 69,5 cm · 2 UP', null],
                        ['KUPAS', 'KUPAS', null, null], ['HITUNG', 'HITUNG', null, null], ['WRAPPING', 'SHRINK', '50 pcs', null], [null, 'STRAPPING', '4 shrink / 200 pcs', null]],
                    'knife' => [78.6, 69.5], 'print' => [80, 72], 'sheet' => [80, 72], 'up' => 2, 'plano' => null, 'press' => 'KOMORI',
                ]],
            ],
            [
                'inq' => 'IQ99MC0012', 'design' => null, 'code' => 'YO-OMG OB BCF', 'name' => 'OB Omegamoto Side Rack', 'customer' => 718,
                'date' => '2026-02-02', 'tiers' => [1000, 2000, 5000], 'pick' => 1, 'due_days' => 14, 'pack' => null, 'attn' => 'Yogas Omegamoto',
                'note' => 'Kartu YO-OMG OB BCF (02 Feb 2026), belum bernomor desain. Master 60,3 x 37,3 x 19,6 cm, bahan corrugated SH BF K125/M125/M125 57,1 x 198,1 cm tanpa pisau (slitter slotter), cetak digital HANWAY / SUNTHINK.',
                'card' => 24, 'type' => 'RTE_BOX', 'dims' => [603, 373, 196], 'extra' => ['glue_flap' => 29, 'top_tuck_flap' => 80, 'bottom_tuck_flap' => 80, 'dust_flap' => 12, 'overflow_margin' => 0],
                'parts' => [[
                    'code' => 'P1', 'name' => 'OB Omegamoto Side Rack', 'flat' => [1981, 571], 'color' => '4/0', 'inks' => ['BLACK', 'CYAN', 'MAGENTA', 'YELLOW'],
                    'mats' => [[['Sheet', 'SHBF', 'K125/M125/M125'], null, 571, 1981, 'SHBF']], 'fin' => [], 'art' => 'mc-ob-omegamoto-side-rack-mc.png',
                    'bop' => [['CETAK', 'CETAK', 'Digital HANWAY / SUNTHINK', 'HANWAY'], ['QC', 'QC', null, null], [null, 'SLITTER SLOTTER', 'Alur dan slot', null],
                        ['STITCHING', 'STITCHING', null, null], ['HITUNG', 'HITUNG', null, null], ['PACK', 'PACK', null, null]],
                    'knife' => [57.1, 198.1], 'print' => [198.1, 57.1], 'sheet' => [198.1, 57.1], 'up' => 1, 'plano' => null, 'press' => 'HANWAY',
                ]],
            ],
        ];
    }

    public function run(): void
    {
        $this->sdi = DB::connection('tm2sdi');
        $this->mst = DB::connection('tm2mst');
        $this->prd = DB::connection('tm2prd');
        foreach ([$this->sdi, $this->mst, $this->prd] as $db) {
            if (! str_starts_with($db->getDatabaseName(), 'ddt')) {
                throw new RuntimeException('UatMasterCardOktSeeder hanya boleh di database dummy ddt* (--env=mockup).');
            }
        }
        if (getenv('MCOKT_PURGE')) {
            $only = array_filter(explode(',', (string) getenv('MCOKT_ONLY')));
            foreach ($this->products() as $p) {
                if (! $only || in_array($p['inq'], $only, true)) {
                    $this->purge($p['inq']);
                }
            }

            return;
        }
        $stop = array_search(getenv('MCOKT_STOP') ?: 'wo', self::STAGES, true);
        $only = array_filter(explode(',', (string) getenv('MCOKT_ONLY')));

        foreach ($this->products() as $p) {
            if ($only && ! in_array($p['inq'], $only, true)) {
                continue;
            }
            $this->say("== {$p['inq']} {$p['name']}");
            foreach (self::STAGES as $i => $stage) {
                if ($i > $stop) {
                    break;
                }
                $this->{'stage'.ucfirst($stage)}($p);
            }
        }

        // Six older cards (xlsx / photo, Sep 2026) already hold Inquiry -> ProDev -> ProCost -> Quotation -> CO Draft; continue them from the CO.
        if ($stop >= array_search('co', self::STAGES, true) && ! $only) {
            $held = false;
            foreach (self::LEGACY as $inq => $dueDays) {
                $this->say("== {$inq} (kartu lama)");
                $layoutMade = $this->legacyLayouts($inq);
                $this->stageCo(['inq' => $inq, 'due_days' => $dueDays]);
                if (isset(self::HELD[$inq])) {
                    // Rule: no Work Order without a complete Master Card. The CO stays Confirmed and its delivery line stays un-posted.
                    $this->resetWorkOrders((int) IqpInquiry::withoutGlobalScopes()->where('inquiry_no', $inq)->value('co_id'));
                    $this->say('   DITAHAN: '.self::HELD[$inq]);
                    $held = true;

                    continue;
                }
                if ($stop >= array_search('wo', self::STAGES, true)) {
                    if ($layoutMade) {
                        $this->resetWorkOrders((int) IqpInquiry::withoutGlobalScopes()->where('inquiry_no', $inq)->value('co_id'));
                    }
                    $this->stageWo(['inq' => $inq]);
                }
            }
            if ($held) {
                PlanEvents::replan('Master Card Okt: Work Orders of a held product were removed');
            }
        }
    }

    // The older products were confirmed in ProDev by flag only (no layout row: ups unknown). The layout of each part comes from its card:
    // [knife w, h cm (per part), print w, h cm, sheet w, h cm, up per part, cols, plano id]. Returns true when a layout was written.
    private const LEGACY_LAYOUT = [
        'IQ99KSP001' => [[72.9, 46.4], [75.5, 49], [74.5, 47.5], 1, 1, null],
        'IQ99MC0001' => [[62.3, 45.2], [96, 64.5], [100, 70], 1, 1, null],
        'IQ99MC0002' => [[76.5, 49.4], [79, 53], [109, 79], 1, 1, 4],
        'IQ99MC0003' => [[60.2, 48.45], [98.5, 62.5], [100, 65], 1, 1, 1],
        'IQ99MC0004' => [[83.4, 70.1], [84.5, 72], [84.5, 72], 4, 2, null],
        'IQ99MC0005' => [[64.5, 50.2], [65, 51], [102, 65], 8, 4, 1],
    ];

    private function legacyLayouts(string $inqNo): bool
    {
        $inq = IqpInquiry::withoutGlobalScopes()->where('inquiry_no', $inqNo)->firstOrFail();
        [$knife, $print, $sheet, $up, $cols, $plano] = self::LEGACY_LAYOUT[$inqNo];
        Auth::setUser($this->user('dedi'));
        $made = false;
        foreach (IqpPart::where('inquiry_id', $inq->id_inquiry)->where('part_kind', 'COMPONENT')->orderBy('part_seq')->get() as $ip) {
            $prodPart = (int) $ip->prod_part_id;
            $has = $this->mst->table('prod_layout_group_part')->where('part_id', $prodPart)->exists();
            if (! $prodPart || $has) {
                continue;
            }
            $out = Prodev::component($prodPart)->saveLayout($this->layout($prodPart, ['knife' => $knife, 'print' => $print, 'sheet' => $sheet, 'up' => $up,
                'cols' => $cols, 'plano' => $plano, 'press' => 'KOMORI']), true);
            if (! ($out['ok'] ?? false)) {
                throw new RuntimeException("ProDev {$inqNo} part {$ip->part_code}: ".($out['message'] ?? 'gagal'));
            }
            $made = true;
        }
        if ($made) {
            InquiryStage::syncProdev((int) $inq->id_inquiry);
            $this->say('   ProDev layout dari kartu');
        }

        return $made;
    }

    // Work Orders born before the layout existed are removed and their lines un-posted, so Post to Production derives them again.
    private function resetWorkOrders(int $orderId): void
    {
        $wos = $this->prd->table('prd_wo')->where('source_co_id', $orderId)->pluck('wo_id');
        if ($wos->isEmpty()) {
            return;
        }
        $ops = $this->prd->table('prd_wo_operation')->whereIn('wo_id', $wos)->pluck('operation_id');
        foreach (['prd_wo_operation_machine', 'prd_operation_time_log', 'prd_wo_operation_output'] as $t) {
            $this->prd->table($t)->whereIn('operation_id', $ops)->delete();
        }
        $this->prd->table('prd_wo_operation')->whereIn('wo_id', $wos)->delete();
        $this->prd->table('prd_wo_material')->whereIn('wo_id', $wos)->delete();
        $this->prd->table('prd_wo')->whereIn('wo_id', $wos)->delete();
        $this->sdi->table('co_order_delivery')->where('order_id', $orderId)->update(['posted_at' => null, 'posted_by' => null]);
        $this->say('   WO lama dibuat ulang (layout baru)');
    }

    private const HELD = ['IQ99MC0002' => 'YAMA Din170 MF: artwork Part A belum ada (kartu aslinya hanya Part B), Master Card belum lengkap.'];

    // Inquiry no => due days (Osima, Yama, Outer Alsamsi, Alsamsi Top, Yahya, KS Power).
    private const LEGACY = ['IQ99KSP001' => 30, 'IQ99MC0001' => 16, 'IQ99MC0002' => 20, 'IQ99MC0003' => 24, 'IQ99MC0004' => 28, 'IQ99MC0005' => 18];

    // ---------------------------------------------------------------- tahap 1: Inquiry (isi dari kartu)

    private function stageInquiry(array $p): void
    {
        $now = now();
        $row = fn (array $x) => $x + ['created_at' => $now, 'updated_at' => $now, 'created_by' => 'uat', 'updated_by' => 'uat', 'data_origin' => 'DUMMY'];
        $inq = $this->sdi->table('iqp_inquiry')->where('inquiry_no', $p['inq'])->first();
        if (! $inq) {
            $join = ($p['join'] ?? null) ? $this->mst->table('mat_join_type')->where('code', $p['join'])->value('id_join_type') : null;
            $parts = $p['parts'];
            $multi = count($parts) > 1;
            $id = $this->sdi->table('iqp_inquiry')->insertGetId($row([
                'inquiry_no' => $p['inq'], 'inquiry_date' => $p['date'], 'source' => 'MASTER CARD', 'pic_sales' => 'Firman', 'customer_id' => $p['customer'], 'is_prospect' => 0,
                'deadline_quotation' => $p['date'], 'product_draft_name' => $p['name'], 'design_number' => $p['design'], 'sales_note' => $p['note'],
                'inquiry_type' => 'NEW', 'revision_no' => 0, 'material_source' => 'OWN', 'job_nature' => 'PRODUCT', 'tax_scheme' => 'PPN', 'pph_applicable' => 0,
                'ppn_percent' => 11, 'pph_percent' => 2, 'sub_state' => 'DRAFT', 'stat_confirm_sales' => 0, 'stat_cancel' => 0, 'is_approved' => 0,
            ]));
            foreach ($p['tiers'] as $i => $qty) {
                $this->sdi->table('iqp_tier')->insert($row(['inquiry_id' => $id, 'tier_seq' => $i + 1, 'qty' => $qty, 'is_primary' => $i === $p['pick'] ? 1 : 0]));
            }
            [$l, $w, $h] = $p['dims'];
            $spec = fn (array $dims) => json_encode(['type_code' => $p['type'], 'dims' => ['product_length' => $dims[0], 'product_width' => $dims[1], 'product_height' => $dims[2]] + $p['extra']]);
            $body = null;
            $seq = 0;
            if ($multi) {
                $body = $this->sdi->table('iqp_part')->insertGetId($row(['inquiry_id' => $id, 'part_seq' => ++$seq, 'part_code' => 'BODY', 'part_name' => 'Body', 'part_type' => 'DC',
                    'part_kind' => 'ASSEMBLY', 'join_type_id' => $join, 'qty_in_parent' => 1, 'qty_per_sku' => 1, 'pack_qty' => $p['pack'], 'dieline_spec' => $spec([$l, $w, $h])]));
                $this->bop($body, $p['assembly'] ?? [], $row);
            }
            foreach ($parts as $part) {
                $dims = $part['dims'] ?? [$l, $w, $h];
                $partType = count($part['mats']) > 1 ? 'DC+SHEET' : (str_starts_with($part['mats'][0][0][0], 'Sheet') ? 'SHEET' : 'DC');
                $pid = $this->sdi->table('iqp_part')->insertGetId($row([
                    'inquiry_id' => $id, 'parent_part_id' => $body, 'part_seq' => ++$seq, 'part_code' => $part['code'], 'part_name' => $part['name'], 'part_type' => $partType,
                    'part_kind' => 'COMPONENT', 'join_type_id' => $multi ? null : $join, 'qty_in_parent' => 1, 'qty_per_sku' => 1, 'pack_qty' => $multi ? null : $p['pack'],
                    'cfg_color' => $part['color'], 'cfg_ink' => implode(', ', array_map(fn ($i) => explode(' ', $i)[0], $part['inks'])), 'dieline_spec' => $spec($dims),
                    'flat_w' => $part['flat'][0], 'flat_h' => $part['flat'][1], 'flat_area' => round($part['flat'][0] * $part['flat'][1], 2),
                ]));
                foreach ($part['mats'] as $m => [$path, $gsm, $sp, $sl, $flute]) {
                    $this->sdi->table('iqp_part_material')->insert($row(['part_id' => $pid, 'layer_seq' => $m + 1, 'category_id' => $this->category($path), 'rawmat_name' => implode(' › ', $path),
                        'gram_weight' => $gsm, 'flute_type' => $flute, 'size_p' => $sp, 'size_l' => $sl, 'is_primary' => $m === 0 ? 1 : 0, 'material_source' => 'OWN', 'source' => 'MANUAL']));
                }
                foreach ($part['inks'] as $u => $ink) {
                    $this->sdi->table('iqp_part_ink')->insert($row(['part_id' => $pid, 'pass_seq' => 1, 'unit_no' => $u + 1, 'ink_name' => mb_substr($ink, 0, 40), 'source' => 'MANUAL']));
                }
                foreach ($part['fin'] as $f => [$name, $attr]) {
                    $type = $this->mst->table('ref_finishing_type')->where('finishing_name', $name)->value('id_finishing_type') ?? throw new RuntimeException("ref_finishing_type: {$name}");
                    $this->sdi->table('iqp_part_finishing')->insert($row(['part_id' => $pid, 'seq' => ($f + 1) * 10, 'finishing_type_id' => $type, 'finishing_name' => $name, 'attribute' => $attr]));
                }
                $this->bop($pid, $part['bop'], $row, self::PRESS);
                $this->artwork($pid, $part);
            }
            $inq = $this->sdi->table('iqp_inquiry')->where('id_inquiry', $id)->first();
            $this->say("   inquiry {$p['inq']} dibuat (id {$id}), ".count($parts).' part');
        }
        if (! $inq->stat_confirm_sales) {
            Auth::setUser($this->user('yuna'));
            (new InquiryWriter($this->user('yuna')->login))->confirm(IqpInquiry::withoutGlobalScopes()->findOrFail($inq->id_inquiry));
            $this->say('   Sales Confirm');
        }
    }

    // ---------------------------------------------------------------- tahap 2: ProDev (susunan = keputusan officer dari kartu; BOP/BOM = SpecEngine)

    private function stageProdev(array $p): void
    {
        $officer = $this->user('dedi');
        Auth::setUser($officer);
        $inq = IqpInquiry::withoutGlobalScopes()->where('inquiry_no', $p['inq'])->firstOrFail();
        InquiryStage::openInProdev($inq, $officer->login);
        $iqp = IqpPart::where('inquiry_id', $inq->id_inquiry)->where(fn ($q) => $q->whereNull('part_kind')->orWhere('part_kind', '<>', 'ASSEMBLY'))->orderBy('part_seq')->get();
        $defs = array_values($p['parts']);
        foreach ($iqp as $i => $ip) {
            $prodPart = (int) $ip->refresh()->prod_part_id;
            $latest = $this->mst->table('prod_layout_group as g')->join('prod_layout_group_part as gp', 'gp.id_layout_group', '=', 'g.id_layout_group')
                ->where('gp.part_id', $prodPart)->orderByDesc('g.id_layout_group')->value('g.status');
            if ($latest === 'CONFIRMED') {
                continue;
            }
            $out = Prodev::component($prodPart)->saveLayout($this->layout($prodPart, $defs[$i]), true);
            if (! ($out['ok'] ?? false)) {
                throw new RuntimeException("ProDev {$p['inq']} part {$ip->part_code}: ".($out['message'] ?? 'gagal'));
            }
        }
        InquiryStage::syncProdev((int) $inq->id_inquiry);
        $this->say('   ProDev Confirm semua part');
    }

    // ---------------------------------------------------------------- tahap 3: ProCost + Quotation + WIN

    private function stageProcost(array $p): void
    {
        $inq = IqpInquiry::withoutGlobalScopes()->where('inquiry_no', $p['inq'])->firstOrFail();
        Auth::setUser($this->user('wiwik'));
        if (! $this->sdi->table('iqp_costing')->where('inquiry_id', $inq->id_inquiry)->where('published_flag', 1)->exists()) {
            $this->priceAndPublish((int) $inq->id_inquiry, $this->user('wiwik')->login);
            InquiryStage::syncProcost((int) $inq->id_inquiry);
            $this->say('   ProCost published');
        }
        if (! $inq->refresh()->co_id) {
            Auth::setUser($this->user('yuna'));
            $order = $this->winViaQuotation((int) $inq->id_inquiry, ['attn_contact' => $p['attn'], 'sales_pic' => 'Firman', 'issued_date' => now()->toDateString(),
                'valid_until' => now()->addDays(30)->toDateString(), 'payment_term' => '30 hari', 'notes' => self::MARK, 'data_origin' => 'DUMMY']);
            $this->sdi->table('co_order')->where('id_order', $order->id_order)->update(['data_origin' => 'DUMMY']);
            $this->sdi->table('co_order_item')->where('order_id', $order->id_order)->update(['data_origin' => 'DUMMY']);
            $product = (int) IqpInquiry::withoutGlobalScopes()->find($inq->id_inquiry)->product_id;
            $this->mst->table('prod_product')->where('id_product', $product)->update(['product_code' => $p['code'], 'drawing_by' => 'RINGGA', 'design_date' => $p['date'], 'data_origin' => 'DUMMY']);
            $this->mst->table('prod_part')->where('product_id', $product)->update(['data_origin' => 'DUMMY']);
            $this->linkCard($p, $product, (int) $inq->id_inquiry);
            $this->say("   Quotation WIN -> {$order->co_no}, Master Product #{$product}");
        }
    }

    // ---------------------------------------------------------------- tahap 4: CO (pilih qty, due date, Confirm + approval) -> baris CPDEL

    private function stageCo(array $p): void
    {
        $inq = IqpInquiry::withoutGlobalScopes()->where('inquiry_no', $p['inq'])->firstOrFail();
        $co = CoOrder::findOrFail((int) $inq->co_id);
        if ($co->doc_status === 'DRAFT') {
            Auth::setUser($this->user('yuna'));
            $primary = $this->sdi->table('iqp_tier')->where('inquiry_id', $inq->id_inquiry)->where('is_primary', 1)->value('qty');
            // pickQty menghapus baris tier lain; baris yang dipilih = tier utama.
            $item = $co->items()->where('line_kind', 'PRODUCT')->where('inquiry_id', $inq->id_inquiry)->where('qty', $primary)->first() ?? $co->items()->where('line_kind', 'PRODUCT')->first();
            CustomerOrder::pickQty($co, (int) $item->id_item);
            $due = now()->addDays($p['due_days'])->toDateString();
            $items = $co->refresh()->items->where('line_kind', 'PRODUCT')->mapWithKeys(fn ($i) => [$i->id_item => ['due_date' => $due]])->all();
            CustomerOrder::save($co, ['items' => $items, 'customer_po_no' => 'PO-MC-'.substr($p['inq'], -2)]);
            if ($error = CustomerOrder::confirm($co->refresh())) {
                throw new RuntimeException("Confirm {$co->co_no}: {$error}");
            }
            for ($n = 0; $n < 6 && ($co = $co->refresh())->doc_status === 'SUBMITTED'; $n++) {
                $this->approve($co);
            }
            if ($co->refresh()->doc_status !== 'CONFIRMED') {
                throw new RuntimeException("{$co->co_no} belum CONFIRMED setelah approval (status {$co->doc_status}).");
            }
            $this->say("   {$co->co_no} CONFIRMED, due {$due}");
        }
    }

    // ---------------------------------------------------------------- tahap 5: Post to Production -> WO + plan Draft (tidak ada APPLY)

    private function stageWo(array $p): void
    {
        $inq = IqpInquiry::withoutGlobalScopes()->where('inquiry_no', $p['inq'])->firstOrFail();
        Auth::setUser($this->user('yuna'));
        foreach (CoOrderDelivery::where('order_id', $inq->co_id)->whereNull('posted_at')->orderBy('id_delivery')->pluck('id_delivery') as $id) {
            if ($error = Cpdel::post((int) $id)) {
                throw new RuntimeException("Post to Production line {$id}: {$error}");
            }
            $this->say("   CPDEL line {$id} Posted -> WO");
        }
    }

    // ---------------------------------------------------------------- bantu

    private function approve(CoOrder $co): void
    {
        $log = FlowLog::where('module_code', 'CO')->where('doc_id', $co->id_order)->orderByDesc('flow_log_id')->first();
        if (! $log || ! in_array($log->action, ['SUBMITTED', 'HOLD'], true)) {
            return;
        }
        $approver = SecUser::where('activate', true)->get()->first(fn ($u) => (int) $u->currentPositionId() === (int) $log->actor_position_id)
            ?? throw new RuntimeException("CO {$co->co_no}: tidak ada user aktif pemegang jabatan approver {$log->actor_position_id}.");
        Auth::setUser($approver);
        if ($error = CustomerOrder::decide($co, 'approve', '')) {
            throw new RuntimeException("Approve {$co->co_no} oleh {$approver->login}: {$error}");
        }
    }

    // State layar Pro-Dev dari angka kartu: ukuran cetak = kanvas, ukuran pisau = knife, jumlah UP, mesin cetak, plano bila ada.
    private function layout(int $prodPart, array $d): array
    {
        $press = $this->mst->table('prod_machine')->where('machine_id', self::PRESS[$d['press']])->first();
        [$kw, $kh] = $d['knife'];
        [$cl, $cw] = $d['print'];
        [$pl, $pw] = $d['sheet'];
        $up = $d['up'];
        $cols = max(1, (int) ($d['cols'] ?? 1));
        $rows = max(1, intdiv($up, $cols));
        $pieceW = round($kw / $cols, 2);
        $pieceH = round($kh / $rows, 2);
        $objects = [];
        for ($i = 0; $i < $up; $i++) {
            $objects[] = ['x' => round(($i % $cols) * $pieceW, 2), 'y' => round(intdiv($i, $cols) * $pieceH, 2), 'w' => $pieceW, 'h' => $pieceH, 'r' => 0, 'fx' => false, 'fy' => false];
        }
        $eff = round(min(100, $up * $pieceW * $pieceH / ($pl * $pw) * 100), 2);
        $plano = $d['plano'] ? $this->mst->table('mat_plano_size')->where('id_plano_size', $d['plano'])->first() : null;

        return [
            'partId' => $prodPart, 'canvasL' => max($cl, $cw), 'canvasW' => min($cl, $cw), 'canvasLimit' => 'CUSTOM', 'gapCm' => 0.3,
            'upCount' => $up, 'method' => 'CARD', 'planoId' => $plano?->id_plano_size, 'efficiencyPct' => $eff,
            'machine' => ['id' => $press->machine_id, 'name' => $press->machine_name, 'bedL' => round(($press->max_sheet_length_mm ?: 1000) / 10, 1), 'bedW' => round(($press->max_sheet_width_mm ?: 720) / 10, 1)],
            'knifeW' => $kw, 'knifeH' => $kh,
            'spec' => ['sheetL' => max($pl, $pw), 'sheetW' => min($pl, $pw), 'planoL' => max($pl, $pw), 'planoW' => min($pl, $pw), 'planoName' => $plano?->plano_name ?? '',
                'sheetsPerPlano' => 1, 'printMachineId' => $press->machine_id, 'diecutMachineId' => null, 'upCount' => $up, 'orderQty' => 0, 'artworkColors' => null],
            'specOverride' => [], 'specReset' => [], 'objects' => $objects,
        ];
    }

    private function bop(int $partId, array $steps, callable $row, array $press = []): void
    {
        foreach (array_values($steps) as $i => [$key, $name, $note, $machine]) {
            $this->sdi->table('iqp_part_process')->insert($row(['part_id' => $partId, 'branch_code' => 'MAIN', 'step_seq' => ($i + 1) * 10, 'flow_type' => 'LINEAR',
                'process_id' => $key ? (self::PROC[$key] ?? null) : null, 'process_name' => mb_substr($name, 0, 80), 'machine_id' => $machine ? ($press[$machine] ?? null) : null,
                'qty_factor' => 1, 'note' => $note, 'source' => 'MANUAL']));
        }
    }

    private function artwork(int $partId, array $part): void
    {
        $file = $part['art'];
        $src = self::SRC.$file;
        $bytes = file_get_contents($src);
        $name = $file;
        // JPC: satu gambar memuat Part A (kiri) dan Part B (kanan): tiap part memakai separuhnya.
        if (isset($part['halves']) && function_exists('imagecreatefromstring')) {
            $img = imagecreatefromstring($bytes);
            $w = imagesx($img);
            $h = imagesy($img);
            $crop = imagecrop($img, ['x' => $part['halves'] === 'A' ? 0 : (int) ($w / 2), 'y' => 0, 'width' => (int) ($w / 2), 'height' => (int) ($h * 0.86)]);
            ob_start();
            imagejpeg($crop, null, 90);
            $bytes = ob_get_clean();
            $name = 'jpc-gold-n100-'.strtolower($part['halves']).'.jpg';
        }
        $path = "inquiry/{$partId}/artwork/{$name}";
        Storage::disk('public')->put($path, $bytes);
        $this->sdi->table('iqp_part')->where('id_part', $partId)->update(['artwork_path' => $path, 'artwork_original_filename' => $name]);
    }

    // Kartu Master Card Collection yang sudah ada (cards 19-24, impor sesi lain) ditautkan ke Master Product; kartu yang belum ada dibuat lewat jalur impor.
    private function linkCard(array $p, int $product, int $inquiry): void
    {
        $card = $p['card'] ? ProdMasterCard::find($p['card']) : null;
        if (! $card) {
            $card = ProdMasterCard::where('card_code', $p['code'])->first() ?? new ProdMasterCard(['created_by' => 'uat', 'data_origin' => 'DUMMY', 'source_kind' => 'MANUAL']);
            $card->fill(['card_code' => $p['code'], 'design_no' => $p['design'], 'product_name' => $p['name'], 'card_date' => $p['date'], 'customer_id' => $p['customer'],
                'dim_l' => $p['dims'][0], 'dim_w' => $p['dims'][1], 'dim_h' => $p['dims'][2], 'type_code' => $p['type'], 'notes' => $p['note']]);
        }
        $card->fill(['customer_id' => $p['customer'], 'status' => 'PRODUCT', 'product_id' => $product, 'inquiry_id' => $inquiry, 'updated_by' => 'uat'])->save();
    }

    private function category(array $path): ?int
    {
        $id = null;
        foreach ($path as $level => $name) {
            $id = $this->mst->table('mat_category')->where('level', $level + 1)->whereRaw('UPPER(TRIM(category_name)) = ?', [strtoupper($name)])
                ->when($id, fn ($q) => $q->where('parent_id', $id), fn ($q) => $q->whereNull('parent_id'))->orderBy('id_category')->value('id_category');
            if (! $id) {
                throw new RuntimeException('mat_category tidak ada: '.implode(' › ', $path));
            }
        }

        return $id;
    }

    private function user(string $login): SecUser
    {
        return SecUser::where('login', $login)->where('activate', true)->first() ?? SecUser::findOrFail(1);
    }

    private function say(string $m): void
    {
        $this->command?->info($m);
        if (! $this->command) {
            echo $m."\n";
        }
    }

    // Hapus seluruh rantai satu inquiry milik seeder ini (WO, CO, quotation, costing, product, part, inquiry). Hanya baris milik inquiry itu.
    private function purge(string $no): void
    {
        $inq = $this->sdi->table('iqp_inquiry')->where('inquiry_no', $no)->first();
        if (! $inq) {
            return;
        }
        $items = $this->sdi->table('co_order_item')->where('inquiry_id', $inq->id_inquiry)->pluck('order_id');
        $orders = $this->sdi->table('co_order')->whereIn('id_order', $items)->pluck('id_order');
        $wos = $this->prd->table('prd_wo')->whereIn('source_co_id', $orders)->pluck('wo_id');
        $ops = $this->prd->table('prd_wo_operation')->whereIn('wo_id', $wos)->pluck('operation_id');
        foreach (['prd_wo_operation_machine', 'prd_operation_time_log', 'prd_wo_operation_output'] as $t) {
            $this->prd->table($t)->whereIn('operation_id', $ops)->delete();
        }
        $this->prd->table('prd_wo_operation')->whereIn('wo_id', $wos)->delete();
        $this->prd->table('prd_wo_material')->whereIn('wo_id', $wos)->delete();
        $this->prd->table('prd_wo')->whereIn('wo_id', $wos)->delete();
        $this->sdi->table('co_order_delivery')->whereIn('order_id', $orders)->delete();
        $this->sdi->table('co_order_item')->whereIn('order_id', $orders)->delete();
        $quo = $this->sdi->table('quo_quotation')->where('notes', self::MARK)->whereIn('id_quotation', $this->sdi->table('quo_quotation_item')->where('inquiry_id', $inq->id_inquiry)->pluck('quotation_id'))->pluck('id_quotation');
        $this->sdi->table('co_order')->whereIn('id_order', $orders)->delete();
        $this->sdi->table('quo_quotation_item')->whereIn('quotation_id', $quo)->delete();
        $this->sdi->table('quo_quotation')->whereIn('id_quotation', $quo)->delete();
        $this->dropCosting((int) $inq->id_inquiry);
        $parts = $this->sdi->table('iqp_part')->where('inquiry_id', $inq->id_inquiry)->pluck('id_part');
        $prodParts = $this->mst->table('prod_part')->where('product_id', $inq->product_id)->pluck('id_part');
        $groups = $this->mst->table('prod_layout_group')->where('product_id', $inq->product_id)->pluck('id_layout_group');
        $this->mst->table('prod_layout_group_part')->whereIn('id_layout_group', $groups)->delete();
        $this->mst->table('prod_layout_group')->whereIn('id_layout_group', $groups)->delete();
        foreach (['prod_part_material', 'prod_part_ink', 'prod_part_process', 'prod_part_finishing', 'prod_part_artwork', 'prod_part_attachment'] as $t) {
            $this->mst->table($t)->whereIn('part_id', $prodParts)->delete();
        }
        $this->mst->table('prod_part')->whereIn('id_part', $prodParts)->update(['parent_part_id' => null]);
        $this->mst->table('prod_part')->whereIn('id_part', $prodParts)->delete();
        if ($inq->product_id) {
            $this->mst->table('prod_product_acc')->where('product_id', $inq->product_id)->delete();
            $this->mst->table('prod_master_card')->where('product_id', $inq->product_id)->update(['status' => 'READY', 'product_id' => null, 'inquiry_id' => null]);
            $this->mst->table('prod_product')->where('id_product', $inq->product_id)->delete();
        }
        $this->sdi->table('iqp_layout')->whereIn('part_id', $parts)->delete();
        foreach (['iqp_part_material', 'iqp_part_finishing', 'iqp_part_process', 'iqp_part_ink'] as $t) {
            $this->sdi->table($t)->whereIn('part_id', $parts)->delete();
        }
        $this->sdi->table('iqp_part')->whereIn('id_part', $parts)->update(['parent_part_id' => null]);
        $this->sdi->table('iqp_part')->where('inquiry_id', $inq->id_inquiry)->delete();
        $this->sdi->table('iqp_tier')->where('inquiry_id', $inq->id_inquiry)->delete();
        $this->sdi->table('iqp_inquiry')->where('id_inquiry', $inq->id_inquiry)->delete();
        $this->say("purged {$no}");
    }
}
