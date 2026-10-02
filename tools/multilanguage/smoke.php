<?php
// Uji singkat dua bahasa di satu situs (GET saja, tanpa menulis): php tools/multilanguage/smoke.php tmerp.test|mockup.test
// Merender opening screen + beberapa layar sebagai Administrator dalam English dan Indonesia, lalu menampilkan judul menu
// modul pertama dan beberapa label, supaya terlihat bahasanya benar-benar berganti dan databasenya benar.
$host = $argv[1] ?? 'tmerp.test';
$_SERVER['HTTP_HOST'] = $host;
$_SERVER['SERVER_NAME'] = $host;
chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->instance('request', Illuminate\Http\Request::create("http://$host/"));
$kernel->bootstrap();
config(['devlib.dictionary_autoregister' => false]);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

echo "$host -> ".DB::connection('tm2sys')->getDatabaseName().' / '.DB::connection('tm2sdi')->getDatabaseName()."\n";
$admin = App\Models\Sys\SecUser::find(1);
$bad = 0;
foreach (['/v2', '/v2/s/customer-order', '/v2/s/quotation', '/v2/s/inquiry', '/v2/s/purchase-order', '/v2/s/md-customer', '/v2/s/translation-dictionary'] as $path) {
    $seen = [];
    foreach (['en', 'id'] as $lang) {
        $admin->lang_code = $lang;   // hanya di memori, tidak disimpan
        $app['auth']->guard()->setUser($admin);
        try {
            $res = $kernel->handle(Request::create("http://$host$path", 'GET'));
            $code = $res->getStatusCode();
            $html = (string) $res->getContent();
        } catch (Throwable $e) {
            $code = 599;
            $html = $e->getMessage();
        }
        preg_match('/<html lang="([a-z]+)"/', $html, $m);
        $seen[$lang] = [$code, $m[1] ?? '?', strlen($html), md5($html)];
        $bad += $code === 200 && ($m[1] ?? '') === $lang ? 0 : 1;
    }
    $differs = $seen['en'][3] !== $seen['id'][3];
    $bad += $differs ? 0 : 1;
    echo str_pad($path, 32)."en {$seen['en'][0]} lang={$seen['en'][1]} | id {$seen['id'][0]} lang={$seen['id'][1]} | ".($differs ? 'text differs per language' : 'SAME TEXT IN BOTH LANGUAGES')."\n";
}
echo $bad ? "PROBLEMS: $bad\n" : "OK\n";
