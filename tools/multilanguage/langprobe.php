<?php
// Language probe of mockup.test (ddt*), GET only: renders every v2 screen / grid / form / print in EN and ID and
// collects (a) Indonesian text shown in English mode, (b) English text shown in Indonesian mode.
// Usage: php langprobe.php [record]   (record = also let the dictionary record new English texts, like a browser visit does)
$_SERVER['HTTP_HOST'] = 'mockup.test';
$_SERVER['SERVER_NAME'] = 'mockup.test';
ini_set('memory_limit', '3G');
chdir('C:/Users/iwanp/Herd/tmerp');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->instance('request', Illuminate\Http\Request::create('http://mockup.test/'));
$kernel->bootstrap();

use App\Support\Localization\Dictionary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$db = DB::connection('tm2sys')->getDatabaseName();
if ($db !== 'ddtsys') {
    fwrite(STDERR, "ABORT: not ddt ($db)\n");
    exit(1);
}
$recordMode = ($argv[2] ?? '') === 'record';
config(['devlib.dictionary_autoregister' => $recordMode]);
Dictionary::recordInConsole($recordMode);
$admin = App\Models\Sys\SecUser::find(1);
$out = __DIR__.'/probe';
@mkdir($out);

$get = function (string $path, string $lang) use ($app, $kernel, $admin) {
    $_COOKIE['dl_lang'] = $lang;
    $admin->lang_code = $lang;   // in memory only (never saved): the account language decides the display language
    $app['auth']->guard()->setUser($admin);
    try {
        $req = Request::create('http://mockup.test'.$path, 'GET', [], ['dl_lang' => $lang]);
        $res = $kernel->handle($req);

        return [$res->getStatusCode(), (string) $res->getContent()];
    } catch (Throwable $e) {
        return [599, $e->getMessage()];
    }
};

// ---- URL list ----
$urls = ['/v2' => 'home'];
foreach (glob('resources/views/v2/screens/*.blade.php') as $f) {
    $slug = basename($f, '.blade.php');
    if ($slug[0] === '_') {
        continue;
    }
    $src = file_get_contents($f);
    [$c, $body] = $get("/v2/s/$slug", 'en');
    if ($c !== 200) {
        echo "skip $slug ($c)\n";

        continue;
    }
    $urls["/v2/s/$slug"] = "screen:$slug";
    if (str_contains($src, "request('new')")) {
        $urls["/v2/s/$slug?new=1"] = "screen:$slug:new";
    }
    if (str_contains($src, "request('id')")) {
        preg_match_all('/&quot;id&quot;:(\d+)|"id":(\d+)/', $body, $m);
        $ids = array_values(array_unique(array_filter(array_merge($m[1], $m[2]))));
        foreach (array_unique(array_filter([$ids[0] ?? null, $ids ? end($ids) : null])) as $id) {
            $urls["/v2/s/$slug?id=$id"] = "screen:$slug:open";
        }
    }
}
foreach (App\Support\V2\Grid\Grids::all() as $g) {
    try {
        $u = preg_replace('#^https?://[^/]+#', '', $g->url());
        $urls[$u] ??= 'grid:'.$g->slug();
        if ($g->newUrl()) {
            $urls[preg_replace('#^https?://[^/]+#', '', $g->newUrl())] ??= 'grid:'.$g->slug().':new';
        }
        if ($g->openUrl() && ($rows = $g->rows())) {
            $urls[preg_replace('#^https?://[^/]+#', '', preg_replace_callback('/\{(\w+)\}/', fn ($m) => (string) ($rows[0][$m[1]] ?? ''), $g->openUrl()))] ??= 'grid:'.$g->slug().':open';
        }
    } catch (Throwable $e) {
        echo 'grid fail '.get_class($g).': '.substr($e->getMessage(), 0, 150)."\n";
    }
}
foreach (App\Models\Sys\SysAppMenu::query()->where('activate', true)->whereNotIn('menu_type', ['module', 'group'])->get() as $i) {
    try {
        [, $u] = App\Support\V2\AppMenu::resolve($i->code);
        if ($u) {
            $u = preg_replace('#^https?://[^/]+#', '', $u);
            if (str_starts_with($u, '/v2')) {
                $urls[$u] ??= 'menu:'.$i->code;
            }
        }
    } catch (Throwable) {
    }
}
foreach (['/v2/engines', '/v2/dictionary'] as $u) {
    $urls[$u] ??= 'dev';
}
// Prints: first id that renders, taken from the list of the owning screen.
$prints = [];
foreach (App\Support\V2\Print\PrintDocs::MAP as $type => [$class, $screen]) {
    [, $body] = $get("/v2/s/$screen", 'en');
    preg_match_all('/&quot;id&quot;:(\d+)|"id":(\d+)/', $body, $m);
    $ids = array_slice(array_values(array_unique(array_filter(array_merge($m[1], $m[2])))), 0, 12);
    foreach ($ids as $id) {
        [$c] = $get("/v2/print/$type/$id", 'en');
        if ($c === 200) {
            $prints["/v2/print/$type/$id"] = "print:$type";
            break;
        }
    }
    if (! isset($prints["/v2/print/$type/".($id ?? 0)])) {
        echo "print $type: no id renders\n";
    }
}
echo count($urls).' screen urls, '.count($prints)." prints\n";

// ---- vocabulary: words that occur only in Indonesian translations / only in English sources ----
$words = fn (string $s) => preg_split('/[^\p{L}]+/u', mb_strtolower($s), -1, PREG_SPLIT_NO_EMPTY);
$enW = $idW = [];
foreach (DB::select('SELECT d.source_en, l.text FROM ddtsys.sys_dictionary d LEFT JOIN ddtsys.sys_dictionary_lang l ON l.id_dictionary=d.id_dictionary AND l.lang_code=\'id\'') as $r) {
    if ($r->text !== null && $r->text !== '' && $r->text !== $r->source_en) {
        foreach ($words($r->source_en) as $w) {
            $enW[$w] = ($enW[$w] ?? 0) + 1;
        }
        foreach ($words($r->text) as $w) {
            $idW[$w] = ($idW[$w] ?? 0) + 1;
        }
    }
}
foreach (Devlib\Lang::DICT as [$id, $en]) {
    foreach ($words($en) as $w) {
        $enW[$w] = ($enW[$w] ?? 0) + 1;
    }
    foreach ($words($id) as $w) {
        $idW[$w] = ($idW[$w] ?? 0) + 1;
    }
}
$idOnly = array_filter(array_diff_key($idW, $enW), fn ($n, $w) => mb_strlen($w) >= 3, ARRAY_FILTER_USE_BOTH);
$enOnly = array_filter(array_diff_key($enW, $idW), fn ($n, $w) => mb_strlen($w) >= 3, ARRAY_FILTER_USE_BOTH);
file_put_contents("$out/vocab_id.txt", implode("\n", array_keys($idOnly)));
file_put_contents("$out/vocab_en.txt", implode("\n", array_keys($enOnly)));
echo count($idOnly).' id-only words, '.count($enOnly)." en-only words\n";

// ---- string extraction ----
$strings = function (string $html): array {
    $set = [];
    $add = function ($s) use (&$set) {
        $s = trim(preg_replace('/\s+/u', ' ', html_entity_decode((string) $s, ENT_QUOTES | ENT_HTML5)));
        if ($s !== '' && mb_strlen($s) <= 300 && preg_match('/\p{L}{2}/u', $s)) {
            $set[$s] = true;
        }
    };
    $walk = function ($v) use (&$walk, $add) {
        if (is_array($v)) {
            foreach ($v as $k => $x) {
                if (is_string($k) && ! preg_match('/^[a-z0-9_]+$/', $k)) {
                    $add($k);
                }
                $walk($x);
            }
        } elseif (is_string($v)) {
            if (str_contains($v, '<')) {
                foreach (preg_split('/<[^>]*>/', $v) as $part) {
                    $add($part);
                }
            } else {
                $add($v);
            }
        }
    };
    // JSON in <script type="application/json"> and in attributes
    preg_match_all('#<script[^>]*type="application/json"[^>]*>(.*?)</script>#s', $html, $m);
    foreach ($m[1] as $j) {
        $walk(json_decode($j, true));
    }
    $noScript = preg_replace(['#<script\b.*?</script>#s', '#<style\b.*?</style>#s', '#<pre\b.*?</pre>#s', '#<code\b.*?</code>#s', '#<template\b[^>]*data-dl-code.*?</template>#s'], ' ', $html);
    preg_match_all('/\s([a-zA-Z:@.\-]+)="([^"]*)"/', $noScript, $m, PREG_SET_ORDER);
    foreach ($m as [, $name, $val]) {
        $dec = html_entity_decode($val, ENT_QUOTES | ENT_HTML5);
        if ($dec !== '' && ($dec[0] === '{' || $dec[0] === '[')) {
            $j = json_decode($dec, true);
            if (is_array($j)) {
                $walk($j);
            }

            continue;
        }
        if (in_array($name, ['title', 'placeholder', 'aria-label', 'alt', 'data-tip', 'data-title', 'data-label', 'data-empty', 'data-confirm', 'data-hint', 'label'], true)) {
            $add($val);
        }
    }
    foreach (preg_split('/<[^>]*>/', $noScript) as $t) {
        $add($t);
    }

    return array_keys($set);
};

$label = $argv[1] ?? 'run';
$snapFile = fopen("$out/snap_$label.jsonl", 'w');
$sources = array_fill_keys(DB::table('ddtsys.sys_dictionary')->where('context', 'ui')->pluck('source_en')->all(), true);
$translated = array_fill_keys(DB::table('ddtsys.sys_dictionary as d')->join('ddtsys.sys_dictionary_lang as l', 'l.id_dictionary', '=', 'd.id_dictionary')
    ->where('l.lang_code', 'id')->where('l.text', '<>', '')->pluck('d.source_en')->all(), true);
$idTexts = array_fill_keys(DB::table('ddtsys.sys_dictionary_lang')->where('lang_code', 'id')->pluck('text')->all(), true);
foreach (Devlib\Lang::DICT as [$id, $en]) {
    $translated[$en] = true;
    $idTexts[$id] = true;
}

$idInEn = $enInId = $fail = [];
$n = 0;
foreach ($urls + $prints as $path => $tag) {
    $isPrint = str_starts_with($tag, 'print:');
    foreach (['en', 'id'] as $lang) {
        [$code, $html] = $get($path.($isPrint ? '?print_lang='.$lang : ''), $lang);
        if ($recordMode) {
            Dictionary::flushPending();
        }
        if ($code !== 200) {
            $fail[] = "$code $lang $path";

            continue;
        }
        $list = $strings($html);
        fwrite($snapFile, json_encode(['u' => $path, 'l' => $lang, 's' => $list], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."
");
        foreach ($list as $s) {
            $ws = $words($s);
            if ($lang === 'en') {
                $hit = array_values(array_filter($ws, fn ($w) => isset($idOnly[$w])));
                if ($hit) {
                    $idInEn[$s]['w'] = $hit;
                    $idInEn[$s]['at'][$tag] = true;
                }
            } else {
                if (isset($idTexts[$s])) {
                    continue;
                }
                $hit = array_values(array_filter($ws, fn ($w) => isset($enOnly[$w])));
                $known = isset($sources[$s]) && ! isset($translated[$s]);
                if ($known || ($hit && count($hit) * 2 >= count($ws))) {
                    $enInId[$s]['w'] = $hit;
                    $enInId[$s]['known'] = $known;
                    $enInId[$s]['at'][$tag] = true;
                }
            }
        }
    }
    if (++$n % 25 === 0) {
        echo "$n ";
    }
}
echo "\n";
$fmt = fn (array $a) => array_map(fn ($v) => $v + ['at' => array_slice(array_keys($v['at']), 0, 5), 'n' => count($v['at'])], $a);
fclose($snapFile);
file_put_contents("$out/id_in_en.json", json_encode($fmt($idInEn), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
file_put_contents("$out/en_in_id.json", json_encode($fmt($enInId), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
file_put_contents("$out/fail.txt", implode("\n", $fail));
file_put_contents("$out/urls.json", json_encode($urls + $prints, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo 'Indonesian shown in EN mode: '.count($idInEn).' strings; English shown in ID mode: '.count($enInId).' strings; failed requests: '.count($fail)."\n";
