<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Salin satu akun dari sec_user mockup.test (ddtsys) ke database devlib, termasuk hash password -- jadi login
// devlib.test memakai password yang sama tanpa pernah menampilkannya. Dijalankan ulang = memperbarui akun itu.
class CopyUser extends Command
{
    protected $signature = 'devlib:copy-user {login} {--from=ddtsys} {--role=} {--developer}';

    protected $description = 'Salin akun (termasuk password) dari sec_user mockup.test ke database devlib';

    public function handle(): int
    {
        $from = preg_replace('/[^A-Za-z0-9_]/', '', (string) $this->option('from'));
        $src = DB::table($from.'.sec_user')->where('login', $this->argument('login'))->first();
        if (! $src) {
            $this->error("Akun {$this->argument('login')} tidak ada di {$from}.sec_user");

            return self::FAILURE;
        }
        $exists = DB::table('sec_user')->where('login', $src->login)->exists();
        DB::table('sec_user')->updateOrInsert(['login' => $src->login], [
            'pswd' => $src->pswd, 'name' => $src->name, 'email' => $src->email, 'phone' => $src->phone, 'birth_date' => $src->birth_date,
            'role' => $this->option('role') ?: null, 'developer' => (bool) $this->option('developer'),
            'lang_code' => $src->lang_code, 'theme_code' => $src->theme_code, 'activate' => (bool) $src->activate,
            'pswd_last_updated' => $src->pswd_last_updated, 'copied_from' => $from,
            'updated_at' => now(), 'updated_by' => 'devlib:copy-user',
        ] + ($exists ? [] : ['created_at' => now(), 'created_by' => 'devlib:copy-user']));
        $this->info(($exists ? 'Diperbarui' : 'Dibuat').": {$src->login} ({$src->name}) dari {$from}");

        return self::SUCCESS;
    }
}
