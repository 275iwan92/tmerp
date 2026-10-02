<?php
// Uji buka semua layar satu situs (GET saja): php tools/multilanguage/siteprobe.php tmerp.test|mockup.test [en|id]
// Menu aktif + tiap grid v2 (daftar, New, buka baris pertama). Menampilkan yang TIDAK 200 beserta sebabnya.
$host = $argv[1] ?? 'tmerp.test';
$lang = $argv[2] ?? 'id';
$_SERVER['HTTP_HOST'] = $host;
$_SERVER['SERVER_NAME'] = $host;
ini_set('memory_limit', '2G');
chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->instance('request', Illuminate\Http\Request::create("http://$host/"));
$kernel->bootstrap();
config(['devlib.dictionary_autoregister' => false]);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

echo "$host -> ".DB::connection('tm2sys')->getDatabaseName()." ($lang)\n";
$admin = App\Models\Sys\SecUser::find(1);
$admin->lang_code = $lang;   // hanya di memori
$strip = fn (string $u) => preg_replace('#^https?://[^/]+#', '', $u) ?: '/';
$urls = ['/v2' => 'home'];
foreach (App\Models\Sys\SysAppMenu::query()->where('activate', true)->whereNotIn('menu_type', ['module', 'group'])->get() as $i) {
    try {
        [, $u] = App\Support\V2\AppMenu::resolve($i->code);
        if ($u) {
            $urls[$strip($u)] ??= 'menu '.$i->code;
        }
    } catch (Throwable $e) {
        echo 'resolve '.$i->code.': '.substr($e->getMessage(), 0, 120)."\n";
    }
}
foreach (App\Support\V2\Grid\Grids::all() as $g) {
    try {
        $urls[$strip($g->url())] ??= 'grid '.$g->slug();
        if ($g->newUrl()) {
            $urls[$strip($g->newUrl())] ??= 'new '.$g->slug();
        }
        if ($g->openUrl() && ($rows = $g->rows())) {
            $urls[$strip(preg_replace_callback('/\{(\w+)\}/', fn ($m) => (string) ($rows[0][$m[1]] ?? ''), $g->openUrl()))] ??= 'open '.$g->slug();
        }
    } catch (Throwable $e) {
        echo 'GRID FAIL '.$g->slug().': '.substr($e->getMessage(), 0, 160)."\n";
    }
}
$bad = 0;
foreach ($urls as $path => $tag) {
    if (str_contains($path, '/prodev/open')) {
        continue;   // rute ini menulis saat GET
    }
    $app['auth']->guard()->setUser($admin);
    try {
        $res = $kernel->handle(Request::create("http://$host$path", 'GET'));
        $code = $res->getStatusCode();
        $e = $res->exception ?? null;
    } catch (Throwable $e) {
        $code = 599;
    }
    if (! in_array($code, [200, 302], true)) {
        $bad++;
        echo "$code $tag $path :: ".($e ? substr($e->getMessage(), 0, 170) : '')."\n";
    }
}
echo count($urls)." urls, $bad not OK\n";
