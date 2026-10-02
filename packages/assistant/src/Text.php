<?php

namespace Assistant;

// Pencocokan kata kunci (tanpa model bahasa): pertanyaan dan sasaran dipecah jadi kata, kata pengisi dibuang, lalu dihitung
// berapa kata pertanyaan yang ditemukan di sasaran. Dua kata dianggap sama bila salah satunya awalan yang lain (minimal 4
// huruf) -- cukup untuk imbuhan sederhana (kirim / kiriman, order / orders) tanpa kamus kata dasar.
final class Text
{
    // Kata pengisi Indonesia + English yang tidak membedakan sasaran.
    private const STOP = ['yang', 'di', 'ke', 'dari', 'itu', 'ini', 'ya', 'dong', 'sih', 'deh', 'nih', 'kah', 'nya', 'saya', 'aku', 'kami', 'kita',
        'mau', 'ingin', 'tolong', 'mohon', 'bisa', 'dan', 'atau', 'untuk', 'pada', 'dengan', 'ada', 'adalah', 'sekarang', 'saat', 'tadi', 'dulu',
        'the', 'a', 'an', 'is', 'are', 'of', 'to', 'for', 'in', 'on', 'at', 'me', 'my', 'our', 'please', 'i', 'we', 'do', 'does', 'can', 'and', 'or',
        'with', 'now', 'it', 'this', 'that', 'there'];

    // Kata tanya / kata niat: dibaca untuk menentukan jenis jawaban, lalu dibuang dari pencocokan.
    private const CUES = [
        'how' => ['cara', 'caranya', 'bagaimana', 'gimana', 'langkah', 'how', 'steps', 'bikin', 'buat', 'membuat', 'input', 'menginput', 'mengisi', 'create', 'make'],
        'where' => ['dimana', 'mana', 'buka', 'bukakan', 'cari', 'menu', 'layar', 'letak', 'where', 'open', 'find', 'screen', 'show', 'lihat', 'tampilkan'],
        'field' => ['diisi', 'isi', 'arti', 'artinya', 'maksud', 'maksudnya', 'meaning', 'mean', 'means', 'kolom', 'field', 'isian'],
        'purpose' => ['fungsi', 'fungsinya', 'kegunaan', 'gunanya', 'guna', 'purpose', 'apa', 'what'],
        'number' => ['berapa', 'nilai', 'jumlah', 'total', 'much', 'many', 'value', 'amount'],
    ];

    public static function clean(string $s): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($s))));
    }

    /** @return array<int, string> semua kata, tanpa dibuang apa pun */
    public static function words(string $s): array
    {
        $s = self::clean($s);

        return $s === '' ? [] : explode(' ', $s);
    }

    /** @return array<int, string> kata pembeda: tanpa kata pengisi dan (bila $dropCues) tanpa kata tanya */
    public static function tokens(string $s, bool $dropCues = false): array
    {
        $drop = self::STOP;
        if ($dropCues) {
            $drop = array_merge($drop, ...array_values(self::CUES));
        }

        return array_values(array_unique(array_filter(self::words($s), fn ($w) => mb_strlen($w) > 1 && ! in_array($w, $drop, true))));
    }

    /** @return array<string, bool> jenis niat yang terbaca dari kata tanya */
    public static function cues(string $s): array
    {
        $words = self::words($s);

        return array_map(fn (array $list) => (bool) array_intersect($words, $list), self::CUES);
    }

    public static function same(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }
        [$short, $long] = mb_strlen($a) <= mb_strlen($b) ? [$a, $b] : [$b, $a];

        return mb_strlen($short) >= 4 && str_starts_with($long, $short);
    }

    /**
     * @param  array<int, string>  $needles
     * @param  array<int, string>  $haystack
     */
    public static function hits(array $needles, array $haystack): int
    {
        $n = 0;
        foreach ($needles as $needle) {
            foreach ($haystack as $word) {
                if (self::same($needle, $word)) {
                    $n++;

                    break;
                }
            }
        }

        return $n;
    }

    // Seberapa cocok pertanyaan dengan satu sasaran (0..1). $label = nama sasaran, $extra = kata cari lain.
    // Nama yang cocok penuh dinilai lebih tinggi daripada yang hanya cocok lewat kata cari tambahan.
    /** @param array<int, string> $query */
    public static function match(array $query, string $label, string $extra = ''): float
    {
        if (! $query) {
            return 0.0;
        }
        $labelWords = self::tokens($label);
        $inLabel = self::hits($query, $labelWords);
        $inAll = self::hits($query, array_merge($labelWords, self::tokens($extra)));
        $cover = $labelWords ? self::hits($labelWords, $query) / count($labelWords) : 0.0;

        return max(($inLabel / count($query)) * (0.7 + 0.3 * $cover), ($inAll / count($query)) * 0.75);
    }
}
