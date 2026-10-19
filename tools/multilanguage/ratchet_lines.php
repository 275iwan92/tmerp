<?php
// Print what MultiLanguageRatchetTest counts (id_label, js_id_text, data_in_text_prop) with file names.
use App\Support\Localization\Dictionary;
use Symfony\Component\Finder\Finder;
use Tests\Feature\MultiLanguageRatchetTest as R;

require_once base_path('tests/TestCase.php');
require_once base_path('tests/Feature/MultiLanguageRatchetTest.php');
$php = (new Finder)->files()->in([resource_path('views/v2'), app_path('Support/V2'), base_path('../devlib/src'), base_path('../devlib/resources/views')])->name(['*.php']);
foreach ($php as $f) {
    $src = $f->getContents();
    preg_match_all("/(?:\\b(?:label|title|placeholder|hint|description|message|text|search)=\"([^\"{}\$]+)\"|'(?:label|title|placeholder|hint|description|message|search)'\\s*=>\\s*'([^'{}]+)')/u", $src, $m);
    foreach (array_merge($m[1], $m[2]) as $text) {
        if ($text !== '' && Dictionary::looksIndonesian($text)) {
            echo 'id_label  '.$f->getRelativePathname().' : '.$text."\n";
        }
    }
    foreach (R::dataInTextProps($src) as $e) {
        echo 'data_prop '.$f->getRelativePathname().' : '.$e."\n";
    }
}
$n = 0;
foreach ((new Finder)->files()->in(base_path('../devlib/resources/assets/js'))->name('*.js') as $f) {
    $src = preg_replace('#^\s*//.*$#m', '', $f->getContents());
    preg_match_all('/\'((?:[^\'\\\\\n]|\\\\.){3,})\'|`([^`\n]{3,})`/', $src, $m);
    foreach (array_merge($m[1], $m[2]) as $text) {
        if ($text !== '' && Dictionary::looksIndonesian(preg_replace('/\$\{[^}]*\}|<[^>]*>/', ' ', $text))) {
            $n++;
            if (($argv[1] ?? '') !== 'nojs') {
                echo 'js_id_text '.$f->getFilename().' : '.mb_substr($text, 0, 110)."\n";
            }
        }
    }
}
echo "js_id_text total $n\n";
