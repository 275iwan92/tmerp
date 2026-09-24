<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Database dummy system `ddtsys` = skema identik tm2sys + salinan data kelompok tertentu (keputusan user 2026-09-24).
// ddtsys dan tm2sys tidak saling menyentuh; perintah ini hanya MEMBACA tm2sys.
//   php artisan ddt:copy-sys                       -> buat ddtsys bila belum ada, lengkapi tabel, salin semua kelompok
//   php artisan ddt:copy-sys --only=security       -> salin ulang sec_* saja (mis. ada pengguna baru di tmerp)
//   php artisan ddt:copy-sys --fresh               -> buang ddtsys, bangun ulang dari nol
// ddtsys sudah punya perubahan sendiri (mis. akun carrij, status menu v2): kelompok yang sudah terisi TIDAK
// ditimpa kecuali --force, supaya salin ulang tidak diam-diam menghapusnya.
class DdtCopySys extends Command
{
    protected $signature = 'ddt:copy-sys {--only= : menu,security,config (dipisah koma)} {--fresh : buang dan bangun ulang ddtsys} {--force : timpa kelompok yang sudah terisi (hapus perubahan milik ddtsys)}';

    protected $description = 'Salin skema tm2sys + data menu/security/config ke database dummy ddtsys';

    private const SOURCE = 'tm2sys';

    private const TARGET = 'ddtsys';

    // Kelompok data yang disalin. Tabel lain (sesi, cache, antrean, chat, notifikasi, tiket, snapshot dummy)
    // hanya skemanya: isinya transaksi/runtime tmerp, bukan milik ddtsys.
    public const GROUPS = [
        'menu' => ['sys_app_menu', 'sys_app_menu_color', 'sys_user_menu'],
        'security' => ['sec_module', 'sec_group', 'sec_group_permission', 'sec_user', 'sec_user_group', 'sec_recovery_question'],
        'config' => ['migrations', 'sys_constant_category', 'sys_constant', 'sys_setting', 'sys_theme', 'sys_languages', 'sys_translations',
            'sys_login_hero', 'sys_quotes', 'sys_doc_reference', 'sys_data_origin_rule', 'sys_data_requirement'],
    ];

    public function handle(): int
    {
        $db = DB::connection(self::SOURCE);
        $src = self::SOURCE;
        $dst = self::TARGET;

        if ($this->option('fresh')) {
            $db->statement("DROP DATABASE IF EXISTS `$dst`");
        }
        $db->statement("CREATE DATABASE IF NOT EXISTS `$dst` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $db->statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $existing = array_map(fn ($r) => array_values((array) $r)[0], $db->select("SHOW TABLES FROM `$dst`"));
            foreach ($db->select("SHOW FULL TABLES FROM `$src` WHERE Table_type = 'BASE TABLE'") as $row) {
                $table = array_values((array) $row)[0];
                if (in_array($table, $existing, true)) {
                    continue;
                }
                $create = array_values((array) $db->selectOne("SHOW CREATE TABLE `$src`.`$table`"))[1];
                $db->statement(preg_replace('/^CREATE TABLE `/', "CREATE TABLE `$dst`.`", $create));
                $this->line("  skema  $table");
            }

            $groups = $this->option('only') ? array_map('trim', explode(',', $this->option('only'))) : array_keys(self::GROUPS);
            foreach ($groups as $g) {
                $filled = collect(self::GROUPS[$g] ?? [])->first(fn ($t) => $db->table("$dst.$t")->exists());
                if ($filled && ! $this->option('force') && ! $this->option('fresh')) {
                    $this->warn("  lewati kelompok $g: $dst.$filled sudah terisi (pakai --force untuk menimpa)");

                    continue;
                }
                foreach (self::GROUPS[$g] ?? [] as $table) {
                    $db->statement("DELETE FROM `$dst`.`$table`");
                    $db->statement("INSERT INTO `$dst`.`$table` SELECT * FROM `$src`.`$table`");
                    $this->line(sprintf('  data   %-24s %5d baris  (%s)', $table, $db->table("$dst.$table")->count(), $g));
                }
            }
        } finally {
            $db->statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->info("$dst siap.");

        return self::SUCCESS;
    }
}
