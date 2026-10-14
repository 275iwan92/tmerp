<?php

/*
 * REMARK
 * Tujuan       : Ringkasan otomatis sebuah berkas .md (judul + 2-3 kalimat pembuka) untuk kartu MD Review, tanpa AI dan tanpa tabel baru.
 * Sumber hulu  : Berkas .md di repo (path relatif dari sys_md_review.file_path).
 * Input        : path relatif repo (atau path absolut folder arsip); jumlah kalimat dan batas karakter.
 * Output       : ['title' => judul H1 atau null, 'summary' => kalimat pembuka, '' bila berkas tidak ada atau tanpa prosa].
 * Dipakai oleh : App\Support\V2\Grid\Setup\MdReviewLookup::posts().
 * Status       : Aktif.
 */

namespace App\Services\Sys;

use Illuminate\Support\Str;

final class MdDigest
{
    private const READ_BYTES = 24000;

    /** @return array{title: ?string, summary: string} */
    public static function of(string $relPath, int $sentences = 3, int $maxChars = 520): array
    {
        $path = is_file($relPath) ? $relPath : base_path($relPath);
        if (! is_file($path)) {
            return ['title' => null, 'summary' => ''];
        }
        $lines = preg_split('/\R/', (string) file_get_contents($path, false, null, 0, self::READ_BYTES)) ?: [];
        $title = null;
        $prose = [];
        $fence = false;
        foreach ($lines as $i => $line) {
            $line = trim($line);
            if (str_starts_with($line, '```')) {
                $fence = ! $fence;

                continue;
            }
            if ($fence || $line === '' || str_starts_with($line, '|') || str_starts_with($line, '<') || preg_match('/^(-{3,}|\*{3,}|={3,})$/', $line) || ($i === 0 && $line === '---')) {
                continue;
            }
            if (preg_match('/^#{1,6}\s+(.+)$/', $line, $m)) {
                $title ??= self::plain($m[1]);

                continue;
            }
            $prose[] = self::plain(preg_replace('/^(>\s*|[-*]\s+|\d+[.)]\s+)/', '', $line));
            if (mb_strlen(implode(' ', $prose)) > $maxChars * 2) {
                break;
            }
        }
        $text = trim(implode(' ', array_filter($prose)));
        $parts = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return ['title' => $title, 'summary' => Str::limit(implode(' ', array_slice($parts, 0, $sentences)), $maxChars, '...')];
    }

    // Buang penanda markdown inline: tautan, tebal, miring, kode.
    private static function plain(string $s): string
    {
        $s = preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $s);

        return trim(str_replace(['**', '__', '`', '*'], '', (string) $s));
    }
}
