<?php

// Mengubah foto (PNG/JPG, ukuran bebas) menjadi gambar latar STANDAR DevLib:
//   <nama>.jpg           1920x1080 (16:9), JPEG kualitas 80  -> layar lebar
//   <nama>-portrait.jpg  1080x1920 (9:16), JPEG kualitas 80  -> HP tegak
// Dipotong dari tengah (cover), jadi tidak ada yang gepeng.
// Pakai: php tools/import-bg.php <folder-sumber> <kelompok, mis. login>

[$_, $src, $group] = $argv + [null, null, 'login'];
if (! $src || ! is_dir($src)) {
    exit("Pakai: php tools/import-bg.php <folder-sumber> <kelompok>\n");
}
$dst = __DIR__.'/../public/media/bg/'.$group;
@mkdir($dst, 0777, true);

function cover($im, int $w, int $h)
{
    $sw = imagesx($im);
    $sh = imagesy($im);
    $scale = max($w / $sw, $h / $sh);
    $cw = (int) round($w / $scale);
    $ch = (int) round($h / $scale);
    $out = imagecreatetruecolor($w, $h);
    imagecopyresampled($out, $im, 0, 0, (int) (($sw - $cw) / 2), (int) (($sh - $ch) / 2), $w, $h, $cw, $ch);

    return $out;
}

foreach (glob(rtrim($src, '/\\').'/*.{png,PNG,jpg,jpeg,JPG,JPEG}', GLOB_BRACE) as $file) {
    $im = @imagecreatefromstring(file_get_contents($file));
    if (! $im) {
        echo "LEWATI (bukan gambar): $file\n";

        continue;
    }
    $name = pathinfo($file, PATHINFO_FILENAME);
    foreach (['' => [1920, 1080], '-portrait' => [1080, 1920]] as $suffix => [$w, $h]) {
        $out = cover($im, $w, $h);
        imagejpeg($out, "$dst/$name$suffix.jpg", 80);
        imagedestroy($out);
    }
    imagedestroy($im);
    printf("%-14s -> %d KB + portrait %d KB\n", $name, filesize("$dst/$name.jpg") / 1024, filesize("$dst/$name-portrait.jpg") / 1024);
}
