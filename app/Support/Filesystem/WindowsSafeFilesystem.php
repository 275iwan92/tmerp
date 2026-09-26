<?php

namespace App\Support\Filesystem;

use Illuminate\Filesystem\Filesystem;

// Windows menolak rename() ke file yang sedang dibuka proses lain ("Access is denied (code: 5)").
// Blade menulis view terkompilasi lewat replace() (tmp -> rename); request paralel yang membaca view
// yang sama memicu error 500 acak. Di sini rename dicoba ulang sebentar, lalu jatuh ke tulis langsung
// dengan kunci file. Hanya dipasang di Windows (AppServiceProvider).
class WindowsSafeFilesystem extends Filesystem
{
    public function replace($path, $content, $mode = null)
    {
        clearstatcache(true, $path);
        $path = realpath($path) ?: $path;
        $tempPath = tempnam(dirname($path), basename($path));
        @chmod($tempPath, $mode ?? (0777 - umask()));
        file_put_contents($tempPath, $content);

        for ($try = 0; $try < 5; $try++) {
            if (@rename($tempPath, $path)) {
                return;
            }
            usleep(40000);
        }

        @unlink($tempPath);

        // Proses lain mungkin sudah menulis isi yang sama.
        if (is_file($path) && @file_get_contents($path) === $content) {
            return;
        }

        file_put_contents($path, $content, LOCK_EX);
    }
}
