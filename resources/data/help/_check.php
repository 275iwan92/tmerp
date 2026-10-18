<?php

// Periksa file isi Help sebelum di-import: php resources/data/help/_check.php <file.json> [...]
// Aturan = resources/data/help/_format.md (batas 255 karakter mengikuti kolom sys_dictionary).

$bad = 0;
$seen = [];
foreach (array_slice($argv, 1) as $file) {
    $topics = json_decode((string) @file_get_contents($file), true);
    if (! is_array($topics)) {
        echo "$file: not valid JSON (".json_last_error_msg().")\n";
        $bad++;

        continue;
    }
    $err = function (string $msg) use ($file, &$bad) {
        echo "$file: $msg\n";
        $bad++;
    };
    $text = function (string $where, $t) use ($err) {
        foreach (['en', 'id'] as $lang) {
            $s = is_array($t) ? ($t[$lang] ?? null) : null;
            if (! is_string($s) || trim($s) === '') {
                $err("$where: '$lang' is missing");
            } elseif (mb_strlen($s) > 255) {
                $err("$where: '$lang' is ".mb_strlen($s).' characters (max 255)');
            } elseif (preg_match('/[\r\n]/', $s)) {
                $err("$where: '$lang' has a line break");
            }
        }
    };
    foreach ($topics as $i => $t) {
        $key = $t['key'] ?? "#$i";
        if (! preg_match('/^[a-z0-9-]{2,80}$/', (string) ($t['key'] ?? ''))) {
            $err("$key: key must be lowercase letters, digits and dashes");
        }
        if (isset($seen[$key])) {
            $err("$key: duplicate key (also in {$seen[$key]})");
        }
        $seen[$key] = $file;
        if (! in_array($t['type'] ?? null, ['INPUT', 'REPORT', 'GUIDE'], true)) {
            $err("$key: type must be INPUT, REPORT or GUIDE");
        }
        if (! preg_match('/^[A-Z]{2,30}$/', (string) ($t['module'] ?? ''))) {
            $err("$key: module code is missing");
        }
        if (trim((string) ($t['title'] ?? '')) === '' || mb_strlen($t['title']) > 150) {
            $err("$key: title is missing or over 150 characters");
        }
        if (isset($t['title_id']) && (! is_string($t['title_id']) || trim($t['title_id']) === '' || mb_strlen($t['title_id']) > 150 || preg_match('/[\r\n]/', $t['title_id']))) {
            $err("$key: title_id must be one line of text, at most 150 characters");
        }
        if (mb_strlen((string) ($t['keywords'] ?? '')) > 255) {
            $err("$key: keywords over 255 characters");
        }
        $text("$key purpose", $t['purpose'] ?? null);
        if (count($t['steps'] ?? []) < 2) {
            $err("$key: needs at least 2 steps");
        }
        foreach ($t['steps'] ?? [] as $n => $s) {
            $text("$key step ".($n + 1), $s);
        }
        foreach ($t['fields'] ?? [] as $n => $f) {
            if (trim((string) ($f['label'] ?? '')) === '' || mb_strlen($f['label']) > 100) {
                $err("$key field ".($n + 1).': label is missing or over 100 characters');
            }
            $text("$key field '".($f['label'] ?? $n)."'", $f);
        }
    }
    echo "$file: ".count($topics)." topics checked\n";
}
exit($bad ? 1 : 0);
