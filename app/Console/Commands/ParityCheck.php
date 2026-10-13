<?php

/*
 * REMARK
 * Tujuan       : Pemeriksa satu perintah bahwa tmerp.test (tm2*) dan mockup.test (ddt*) tidak berdualisme; gagal (exit 1) bila berbeda.
 * Sumber hulu  : sys_app_menu (ddtsys vs tm2sys), tabel migrations kedua sisi, tabel master ProDev / ProCost / Inquiry, APP_UI di .env dan .env.mockup.
 * Input        : -
 * Output       : Daftar selisih per bagian + exit code 0 (identik) / 1 (ada selisih). Baca-saja, tidak menulis ke database.
 * Dipakai oleh : Dijalankan setiap selesai batch kerja (aturan CLAUDE.md "Tanpa dualisme"); php artisan parity:check.
 * Status       : Aktif.
 *
 * Pengecualian SENGAJA (bukan dualisme): data transaksi bisnis (customer order, quotation, WO, stok, jurnal) dummy di ddt* dan nyata di tm2*.
 * Daftar di EXCEPTIONS; hanya yang tertulis di sana yang boleh berbeda.
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ParityCheck extends Command
{
    protected $signature = 'parity:check';

    protected $description = 'Bandingkan tm2* dengan ddt*: menu, migrasi, master ProDev/ProCost/Inquiry, APP_UI (gagal bila berbeda)';

    // Selisih yang disengaja: data transaksi (dummy vs nyata). Tambah di sini hanya atas keputusan Jo.
    private const EXCEPTIONS = ['co_*', 'quo_*', 'do_*', 'prd_wo*', 'inv_*', 'fin_*', 'pr_*', 'po_*', 'pv_*'];

    // Migrasi lama yang sengaja hanya di tm2 (modul Maintenance 1 Okt dibuang dari ddt* 8 Okt, dibangun ulang lewat migrasi 8 Okt).
    private const MIGRATION_EXCEPTIONS = ['2026_10_01_000040_create_maintenance_module', '2026_10_01_000041_maintenance_dummy_ddt'];

    // Master yang wajib identik (keputusan Jo 2026-10-13: ProDev / ProCost / Inquiry yang benar = mockup.test).
    private const MASTER = [
        'mst' => ['prod_type_size', 'prod_type_size_val', 'prod_market', 'prod_product', 'prod_part', 'prod_part_artwork', 'prod_part_attachment', 'prod_part_finishing',
            'prod_part_ink', 'prod_part_material', 'prod_part_process', 'prod_layout_group', 'prod_layout_group_part', 'prod_master_card', 'prod_master_card_file', 'prod_product_acc'],
        'sdi' => ['iqp_inquiry', 'iqp_part', 'iqp_part_finishing', 'iqp_part_ink', 'iqp_part_material', 'iqp_part_process', 'iqp_tier', 'iqp_costing', 'iqp_costing_part', 'iqp_costing_price_tier'],
    ];

    public function handle(): int
    {
        $diff = 0;
        $diff += $this->menu();
        $diff += $this->migrations();
        $diff += $this->master();
        $diff += $this->appUi();
        $this->line($diff === 0 ? 'PARITY OK: tmerp.test = mockup.test (kecuali pengecualian: '.implode(', ', self::EXCEPTIONS).')' : "PARITY FAIL: $diff selisih");

        return $diff === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function menu(): int
    {
        $load = fn (string $db) => collect(DB::connection('tm2sys')->select(
            "SELECT m.code, m.label, m.sort_order, m.activate, m.hide_in_menu, m.target, p.code AS parent FROM `$db`.sys_app_menu m LEFT JOIN `$db`.sys_app_menu p ON p.id = m.parent_id"
        ))->keyBy('code');
        $d = $load('ddtsys');
        $t = $load('tm2sys');
        $bad = [];
        foreach ($d->keys()->merge($t->keys())->unique() as $code) {
            if (! isset($t[$code]) || ! isset($d[$code])) {
                $bad[] = "$code hanya di ".(isset($d[$code]) ? 'ddtsys' : 'tm2sys');

                continue;
            }
            foreach (['label', 'sort_order', 'activate', 'hide_in_menu', 'target', 'parent'] as $f) {
                if ((string) $d[$code]->$f !== (string) $t[$code]->$f) {
                    $bad[] = "$code.$f: ddt={$d[$code]->$f} tm2={$t[$code]->$f}";
                }
            }
        }

        return $this->report('Menu sys_app_menu ('.$d->count().' / '.$t->count().' baris)', $bad);
    }

    private function migrations(): int
    {
        $load = fn (string $db) => array_map(fn ($r) => $r->migration, DB::connection('tm2sys')->select("SELECT migration FROM `$db`.migrations"));
        $d = $load('ddtsys');
        $t = $load('tm2sys');
        $bad = [];
        foreach (array_diff($d, $t) as $m) {
            $bad[] = "$m belum jalan di tm2";
        }
        foreach (array_diff($t, $d, self::MIGRATION_EXCEPTIONS) as $m) {
            $bad[] = "$m belum jalan di ddt";
        }

        return $this->report('Migrasi ('.count($d).' / '.count($t).')', $bad);
    }

    private function master(): int
    {
        $bad = [];
        foreach (self::MASTER as $m => $tables) {
            foreach ($tables as $tb) {
                $a = DB::connection('tm2sys')->selectOne("SELECT COUNT(*) c FROM `tm2$m`.`$tb`")->c;
                $b = DB::connection('tm2sys')->selectOne("SELECT COUNT(*) c FROM `ddt$m`.`$tb`")->c;
                if ($a !== $b) {
                    $bad[] = "$tb: tm2=$a ddt=$b";
                }
            }
        }

        return $this->report('Master ProDev / ProCost / Inquiry', $bad);
    }

    private function appUi(): int
    {
        $read = function (string $file): ?string {
            $p = base_path($file);

            return is_file($p) && preg_match('/^APP_UI=(\S+)/m', file_get_contents($p), $m) ? trim($m[1], "\"'") : null;
        };
        $mock = $read('.env.mockup') ?? 'v2';
        $live = $read('.env') ?? 'v2';

        return $this->report("APP_UI (.env.mockup=$mock, .env=$live)", $mock === $live ? [] : ["APP_UI berbeda: mockup=$mock live=$live"]);
    }

    private function report(string $title, array $bad): int
    {
        $this->line(($bad ? '[BEDA] ' : '[OK]   ').$title);
        foreach (array_slice($bad, 0, 15) as $b) {
            $this->line('       '.$b);
        }
        if (count($bad) > 15) {
            $this->line('       ... +'.(count($bad) - 15).' lainnya');
        }

        return count($bad);
    }
}
