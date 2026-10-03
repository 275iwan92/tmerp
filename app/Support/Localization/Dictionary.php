<?php

namespace App\Support\Localization;

use Devlib\Lang;
use Devlib\Locale;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Kamus penerjemah (LOCKED user 2026-10-01): database & formula English; teks lain HANYA saat tampil (layar/cetak).
// t('Net 30 Days', 'tm2mst.fin_payment_term.term_label') -> "Bayar 30 Hari" bila bahasa tampilan id; tidak ada terjemahan
// -> English apa adanya. Sumber: tm2sys.sys_dictionary (context + source_en) + sys_dictionary_lang (per bahasa).
// Teks English yang dipakai tetapi belum ada di kamus DICATAT OTOMATIS (akhir request, web saja) supaya penerjemah tinggal
// mengisi sel kosong di Setup > Translation Dictionary. Beda dari Translator (kamus kunci v1, sys_translations).
final class Dictionary
{
    private const TTL = 600;

    // Kata Indonesia yang umum di label: teks seperti ini BUKAN sumber English -> tidak dicatat (label lama yang belum
    // dipindah ke English; dijaga MultiLanguageRatchetTest).
    private const ID_WORDS = ['dan', 'yang', 'untuk', 'dari', 'dengan', 'atau', 'tidak', 'belum', 'sudah', 'akan', 'pada', 'ke', 'di', 'oleh',
        'ini', 'itu', 'baru', 'semua', 'cari', 'pilih', 'simpan', 'batal', 'tutup', 'tanggal', 'nama', 'jumlah', 'harga', 'pelanggan', 'barang',
        'bahan', 'catatan', 'keterangan', 'tambah', 'hapus', 'ubah', 'lihat', 'cetak', 'kirim', 'terima', 'gudang', 'satuan', 'berat', 'panjang',
        'lebar', 'tinggi', 'warna', 'ukuran', 'bulan', 'tahun', 'hari', 'jam', 'mesin', 'proses', 'pesanan', 'penawaran', 'pengiriman', 'surat',
        'jalan', 'bayar', 'biaya', 'baris', 'kolom', 'halaman', 'aktif', 'nonaktif', 'sisa', 'diskon', 'pajak', 'tagihan', 'dibuat', 'diubah',
        'disetujui', 'ditolak', 'menunggu', 'dikirim', 'diterima', 'kode', 'nomor', 'tgl', 'rencana', 'jenis', 'kategori', 'pemasok', 'karyawan',
        'jabatan', 'bagian', 'lembur', 'cuti', 'gaji', 'potongan', 'tunjangan', 'masuk', 'keluar', 'awal', 'akhir', 'sampai', 'hingga', 'ada',
        'bisa', 'harus', 'wajib', 'perlu', 'sebelum', 'sesudah', 'setelah', 'saat', 'dalam', 'luar', 'atas', 'bawah', 'kiri', 'kanan',
        // Tambahan 2026-10-02: kata yang lolos di label grid v2 (Departemen, Mata Uang, Tipe Produk, Mingguan, ...).
        'departemen', 'periode', 'penerima', 'terkirim', 'nilai', 'uang', 'tipe', 'produk', 'induk', 'grup', 'industri', 'alasan', 'hasil', 'sumber',
        'tahap', 'lokasi', 'merek', 'pabrikan', 'peran', 'posisi', 'revisi', 'tekanan', 'telat', 'terlambat', 'termin', 'tunai', 'mingguan', 'bulanan',
        'anomali', 'berjalan', 'berlaku', 'diajukan', 'diblokir', 'dikerjakan', 'dokumen', 'foto', 'gramatur', 'hadir', 'jarak', 'kelas', 'kontak',
        'lembar', 'pisau', 'potong', 'pulang', 'saldo', 'setelan', 'subkon', 'penggerak', 'penunjang', 'terdekat', 'rekening', 'menang', 'kalah',
        'lokal', 'prospek', 'produksi', 'bekerja', 'dirumahkan', 'sakit', 'izin', 'ya', 'judul', 'pegawai', 'kosong', 'gagal', 'berhasil'];

    private static array $cache = [];

    private static array $pending = [];

    // Baris lama berkelompok 'general' yang kini tampil lewat komponen berkelompok -> pindah kelompok (satu UPDATE per kelompok).
    private static array $regroup = [];

    // Kelompok bawaan per konteks (pohon kiri Translation Dictionary); teks 'ui' ikut petunjuk komponen (Devlib\Lang::hint).
    public const GROUP_GENERAL = 'general';

    private static bool $recordInConsole = false;

    private static ?array $idWords = null;

    // Uji render semua layar dari console (bukan browser): teks English yang belum dikenal ikut dicatat, sama seperti
    // saat layar dibuka user, supaya daftar "belum diterjemahkan" lengkap sebelum user membuka layarnya.
    public static function recordInConsole(bool $on = true): void
    {
        self::$recordInConsole = $on;
    }

    public static function t(?string $en, string $context = 'ui', ?string $lang = null, ?string $group = null): string
    {
        if ($en === null || trim($en) === '') {
            return (string) $en;
        }
        // Teks berisi nilai data yang ditandai raw(): hanya labelnya yang diterjemahkan / dicatat (Devlib\Lang::raw()).
        if (Lang::hasRaw($en)) {
            return $context === 'ui' && $lang === null ? Lang::text($en) : Lang::plain($en);
        }

        return self::lookup($en, $context, $lang, $group) ?? $en;
    }

    // Terjemahan dari kamus, null bila belum ada (Devlib\Lang memakai DICT bawaannya sebagai cadangan). Teks tercatat
    // otomatis dengan kelompok ($group: petunjuk komponen / tf() = messages / menu) dan layar tempat pertama kali tampil.
    public static function lookup(string $en, string $context = 'ui', ?string $lang = null, ?string $group = null): ?string
    {
        $group ??= $context === 'ui' ? Lang::currentHint() : null;
        self::remember($en, $context, false, $group);
        $lang ??= Locale::lang();

        return $lang === 'en' ? $en : (self::context($context, $lang)[$en] ?? null);
    }

    // source_en => teks, satu konteks satu bahasa ('' = belum diterjemahkan tidak ikut).
    public static function context(string $context, string $lang): array
    {
        return self::$cache['t'][$lang][$context] ??= self::cached("t:$lang:$context", fn () => self::db()->table('sys_dictionary as d')
            ->join('sys_dictionary_lang as l', 'l.id_dictionary', '=', 'd.id_dictionary')
            ->where('d.context', $context)->where('d.activate', 1)->where('l.lang_code', $lang)->where('l.text', '<>', '')
            ->pluck('l.text', 'd.source_en')->all());
    }

    // Semua teks sumber satu konteks (source_en => kelompok), untuk pencatatan otomatis dan pemindahan kelompok.
    public static function sources(string $context): array
    {
        return self::$cache['s'][$context] ??= self::cached("s:$context", fn () => self::db()->table('sys_dictionary')
            ->where('context', $context)->pluck('group_code', 'source_en')->all());
    }

    // Kelompok bawaan sebuah konteks: help -> help, date.* -> dates, nilai lookup DB -> lookup, 'ui' -> general.
    public static function defaultGroup(string $context): string
    {
        return match (true) {
            $context === 'help' => 'help',
            str_starts_with($context, 'date.') => 'dates',
            $context !== 'ui' => 'lookup',
            default => self::GROUP_GENERAL,
        };
    }

    // Layar tempat teks pertama kali tampil ("Used on" di Translation Dictionary): slug layar v2 / nama route.
    private static function screenName(): ?string
    {
        try {
            if (app()->runningInConsole() || ! ($route = request()->route())) {
                return null;
            }
            $name = $route->parameter('slug') ?: $route->getName();

            return is_string($name) && $name !== '' ? mb_substr($name, 0, 120) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    // Kamus 'ui' untuk browser (window.DL_DICT): semua sumber -> terjemahan bahasa itu ('' = belum ada; JS mencatat yang tidak dikenal).
    public static function uiMap(string $lang): array
    {
        $tr = $lang === 'en' ? [] : self::context('ui', $lang);
        $out = [];
        foreach (self::sources('ui') as $en => $_) {
            $out[$en] = $tr[$en] ?? '';
        }

        return $out;
    }

    // Aturan tampil per bahasa untuk Devlib\Locale: sys_languages (pemisah angka, locale Intl) + bulan dari kamus.
    // Tabel belum ada (mis. DB lama) -> [] = Locale memakai bawaan id/en.
    public static function languages(): array
    {
        try {
            $rows = self::db()->table('sys_languages')->get();
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows->sortBy(fn ($r) => $r->sort_order ?? $r->id_language) as $r) {
            if (isset($r->activate) && ! $r->activate) {
                continue;
            }
            $mon = $r->code === 'en' ? [] : self::context('date.month_short', $r->code);
            $out[$r->code] = ['name' => $r->native_name ?? $r->name, 'intl' => $r->intl_locale ?? ($r->code === 'id' ? 'id-ID' : 'en-US'),
                'dec' => $r->decimal_separator ?: '.', 'grp' => $r->thousand_separator ?? ',',
                'months' => array_map(fn ($m) => $mon[$m] ?? $m, Locale::MONTHS_EN)];
        }

        return $out;
    }

    // Kata Indonesia = ID_WORDS (kata umum, termasuk yang juga ada di teks English seperti 'di' / 'ini' tidak dipakai)
    // + indonesian_words.php (kosakata yang diturunkan dari isi kamus, 2026-10-03).
    public static function looksIndonesian(string $s): bool
    {
        self::$idWords ??= array_fill_keys(self::ID_WORDS, true) + array_fill_keys(require __DIR__.'/indonesian_words.php', true);
        foreach (preg_split('/[^\p{L}]+/u', mb_strtolower($s), -1, PREG_SPLIT_NO_EMPTY) as $w) {
            if (isset(self::$idWords[$w])) {
                return true;
            }
        }

        return false;
    }

    // Jaring pengaman bila layar lupa raw(): teks yang memuat nomor dokumen (CO2600001, PR2600009) atau '#5' adalah data,
    // bukan label -> tidak dicatat. Nama customer / product tidak bisa ditebak dari bentuknya: itu tugas raw() di layar.
    public static function looksLikeData(string $s): bool
    {
        return (bool) preg_match('/\b[A-Z]{2,5}\d{5,}\b|#\d/', $s);
    }

    // Teks yang SUDAH hasil terjemahan bahasa tampilan (layar memanggil t() lalu mengirimnya ke prop komponen, atau pesan
    // flash) bukan sumber English -> tidak dicatat lagi sebagai baris baru.
    private static function isTranslation(string $text, string $context): bool
    {
        $lang = Locale::lang();
        if ($lang === 'en') {
            return false;
        }
        // Terjemahan milik kamus DevLib sendiri ('Tema', 'Setujui'): teks jadi yang dikirim lagi ke prop teks komponen.
        if (Lang::isTranslation($text, $lang)) {
            return true;
        }

        return isset((self::$cache['v'][$lang][$context] ??= array_flip(self::context($context, $lang)))[$text]);
    }

    // Catat teks baru (juga dipanggil dari browser lewat route v2.dictionary.register). Kembalian = jumlah yang dicatat.
    public static function register(array $texts, string $context = 'ui'): int
    {
        foreach ($texts as $en) {
            if (is_string($en)) {
                self::remember($en, $context, true);
            }
        }

        return self::flushPending();
    }

    public static function flushPending(): int
    {
        $n = 0;
        try {
            $seen = self::screenName();
            foreach (self::$pending as $context => $list) {
                $rows = [];
                foreach ($list as $en => $group) {
                    $rows[] = ['context' => $context, 'source_en' => $en, 'group_code' => $group, 'first_seen' => $seen,
                        'created_at' => now(), 'created_by' => 'auto'];
                }
                foreach (array_chunk($rows, 200) as $chunk) {
                    $n += self::db()->table('sys_dictionary')->insertOrIgnore($chunk);
                }
                self::forget("s:$context");
                unset(self::$cache['s'][$context]);
            }
            foreach (self::$regroup as $context => $groups) {
                foreach ($groups as $group => $list) {
                    self::db()->table('sys_dictionary')->where('context', $context)->where('group_code', self::GROUP_GENERAL)
                        ->whereIn('source_en', array_keys($list))->update(['group_code' => $group]);
                }
                self::forget("s:$context");
                unset(self::$cache['s'][$context]);
            }
        } catch (\Throwable) {
            // Tabel kamus belum ada di database ini -- abaikan, tampilan tetap English.
        }
        self::$pending = [];
        self::$regroup = [];

        return $n;
    }

    // Isi kamus berubah (layar Translation Dictionary / pencatatan) -> buang cache.
    public static function flush(): void
    {
        foreach (array_keys(self::db()->table('sys_languages')->pluck('code', 'code')->all() + ['en' => 1]) as $lang) {
            foreach (self::db()->table('sys_dictionary')->distinct()->pluck('context') as $ctx) {
                self::forget("t:$lang:$ctx");
                self::forget("s:$ctx");
            }
        }
        self::$cache = [];
        Locale::languagesUsing(fn () => self::languages());
    }

    private static function remember(string $en, string $context, bool $force = false, ?string $group = null): void
    {
        $en = trim($en);
        if ($en === '' || mb_strlen($en) > 255 || ! preg_match('/\p{L}/u', $en) || Lang::hasRaw($en) || self::looksIndonesian($en) || self::looksLikeData($en)) {
            return;
        }
        // Tidak mencatat dari console/test (kecuali register() eksplisit, atau uji render layar yang memintanya lewat
        // recordInConsole()) supaya test tidak mengotori kamus.
        if (! $force && ((app()->runningInConsole() && ! self::$recordInConsole) || ! config('devlib.dictionary_autoregister', true))) {
            return;
        }
        $group = $context === 'ui' && $group ? $group : self::defaultGroup($context);
        $known = self::sources($context);
        if (array_key_exists($en, $known)) {
            // Sudah tercatat sebagai 'general' tetapi kini tampil lewat komponen berkelompok -> pindah kelompok itu.
            if ($context === 'ui' && $group !== self::GROUP_GENERAL && ($known[$en] ?? self::GROUP_GENERAL) === self::GROUP_GENERAL && ! isset(self::$regroup[$context][$group][$en])) {
                if (! self::$pending && ! self::$regroup && ! $force) {
                    app()->terminating(fn () => self::flushPending());
                }
                self::$regroup[$context][$group][$en] = true;
            }

            return;
        }
        if (isset(self::$pending[$context][$en]) || self::isTranslation($en, $context)) {
            return;
        }
        if (! self::$pending && ! self::$regroup && ! $force) {
            app()->terminating(fn () => self::flushPending());
        }
        self::$pending[$context][$en] = $group;
    }

    private static function cached(string $key, \Closure $load): array
    {
        try {
            return Cache::remember(self::key($key), self::TTL, $load);
        } catch (\Throwable) {
            return [];
        }
    }

    private static function forget(string $key): void
    {
        Cache::forget(self::key($key));
    }

    private static function key(string $key): string
    {
        return 'dict:'.self::db()->getDatabaseName().':'.$key;
    }

    private static function db(): Connection
    {
        return DB::connection('tm2sys');
    }
}
