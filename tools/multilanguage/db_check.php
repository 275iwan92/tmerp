<?php
// Connection + multi-language tables check for the current environment (tmerp.test = tm2*, --env=mockup = ddt*). Read only.
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$out = [];
foreach (['tm2mst', 'tm2sdi', 'tm2pur', 'tm2inv', 'tm2prd', 'tm2acc', 'tm2hrm', 'tm2hrr', 'tm2pyr', 'tm2mnt', 'tm2sys'] as $c) {
    try {
        $name = DB::connection($c)->getDatabaseName();
        $tables = count(DB::connection($c)->select('SHOW TABLES'));
        $out[] = "$c -> $name ($tables tables)";
    } catch (Throwable $e) {
        $out[] = "$c FAILED: ".substr($e->getMessage(), 0, 120);
    }
}
echo implode(' | ', $out)."\n";
$sys = DB::connection('tm2sys');
foreach (['sys_languages', 'sys_dictionary', 'sys_dictionary_lang', 'sys_app_menu', 'sec_user'] as $t) {
    echo $t.': '.(Schema::connection('tm2sys')->hasTable($t) ? $sys->table($t)->count().' rows' : 'MISSING')."\n";
}
echo 'languages: '.json_encode($sys->table('sys_languages')->where('activate', 1)->pluck('name', 'code'))."\n";
echo 'user languages: '.json_encode($sys->table('sec_user')->selectRaw('lang_code, COUNT(*) n')->groupBy('lang_code')->pluck('n', 'lang_code'))."\n";
echo 'sec_user.lang_code column: '.(Schema::connection('tm2sys')->hasColumn('sec_user', 'lang_code') ? 'yes' : 'NO')."\n";
foreach ($sys->table('sys_dictionary as d')->leftJoin('sys_dictionary_lang as l', fn ($j) => $j->on('l.id_dictionary', '=', 'd.id_dictionary')->where('l.lang_code', 'id'))
    ->selectRaw("d.context, COUNT(*) total, SUM(l.text IS NULL OR l.text = '') missing")->groupBy('d.context')->get() as $r) {
    echo "  {$r->context}: {$r->total} texts, {$r->missing} without Indonesian\n";
}
echo 'pending migrations: ';
Illuminate\Support\Facades\Artisan::call('migrate:status', ['--pending' => true]);
echo trim(preg_replace('/\s+/', ' ', Illuminate\Support\Facades\Artisan::output()))."\n";
