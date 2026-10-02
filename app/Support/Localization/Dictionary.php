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
// mengisi sel kosong di Setup > Translation Dictionary. Kamus tunggal v1 & v2 (kamus kunci lama sys_translations dipensiunkan 2026-10-02).
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

    public static function t(?string $en, string $context = 'ui', ?string $lang = null): string
    {
        if ($en === null || trim($en) === '') {
            return (string) $en;
        }
        // Teks berisi nilai data yang ditandai raw(): hanya labelnya yang diterjemahkan / dicatat (Devlib\Lang::raw()).
        if (Lang::hasRaw($en)) {
            return $context === 'ui' && $lang === null ? Lang::text($en) : Lang::plain($en);
        }
        self::remember($en, $context);
        $lang ??= Locale::lang();

        return $lang === 'en' ? $en : (self::lookup($en, $context, $lang) ?? $en);
    }

    // Padanan persis dulu (source_en utf8mb4_bin, peka huruf). Tidak ada -> baris yang sama tanpa beda huruf, bentuk hurufnya
    // ikut sumber: 'CANCELLED' / 'cancelled' memakai terjemahan 'Cancelled' -> 'DIBATALKAN' / 'dibatalkan', tanpa baris kamus baru.
    // Terjemahan bila ada di kamus (tanpa jatuh ke English, tanpa mencatat teks baru) -- mis. kamus cetak konteks 'print'.
    public static function find(string $en, string $context, string $lang): ?string
    {
        return $lang === 'en' ? null : self::lookup($en, $context, $lang);
    }

    private static function lookup(string $en, string $context, string $lang): ?string
    {
        $map = self::context($context, $lang);
        if (isset($map[$en])) {
            return $map[$en];
        }
        $ci = self::$cache['ci'][$lang][$context] ??= self::foldKeys($map);
        $text = $ci[mb_strtolower($en)] ?? null;
        if ($text === null) {
            return null;
        }
        if ($en === mb_strtoupper($en) && $en !== mb_strtolower($en)) {
            return mb_strtoupper($text);
        }

        return $en === mb_strtolower($en) ? mb_strtolower($text) : $text;
    }

    private static function foldKeys(array $map): array
    {
        $out = [];
        foreach ($map as $k => $v) {
            $out[mb_strtolower((string) $k)] ??= $v;
        }

        return $out;
    }

    // source_en => teks, satu konteks satu bahasa ('' = belum diterjemahkan tidak ikut).
    public static function context(string $context, string $lang): array
    {
        return self::$cache['t'][$lang][$context] ??= self::cached("t:$lang:$context", fn () => self::db()->table('sys_dictionary as d')
            ->join('sys_dictionary_lang as l', 'l.id_dictionary', '=', 'd.id_dictionary')
            ->where('d.context', $context)->where('d.activate', 1)->where('l.lang_code', $lang)->where('l.text', '<>', '')
            ->pluck('l.text', 'd.source_en')->all());
    }

    // Semua teks sumber satu konteks (source_en => true), untuk pencatatan otomatis.
    public static function sources(string $context): array
    {
        return self::$cache['s'][$context] ??= self::cached("s:$context", fn () => array_fill_keys(
            self::db()->table('sys_dictionary')->where('context', $context)->pluck('source_en')->all(), true));
    }

    // Sama seperti sources(), tanpa beda huruf besar/kecil: varian huruf dari teks yang sudah ada tidak dicatat sebagai baris baru.
    private static function foldedSources(string $context): array
    {
        return self::$cache['fs'][$context] ??= self::foldKeys(self::sources($context));
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

    public static function looksIndonesian(string $s): bool
    {
        foreach (preg_split('/[^\p{L}]+/u', mb_strtolower($s), -1, PREG_SPLIT_NO_EMPTY) as $w) {
            if (in_array($w, self::ID_WORDS, true)) {
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
            foreach (self::$pending as $context => $list) {
                $rows = array_map(fn ($en) => ['context' => $context, 'source_en' => $en, 'created_at' => now(), 'created_by' => 'auto'], array_keys($list));
                foreach (array_chunk($rows, 200) as $chunk) {
                    $n += self::db()->table('sys_dictionary')->insertOrIgnore($chunk);
                }
                self::forget("s:$context");
                unset(self::$cache['s'][$context], self::$cache['fs'][$context]);
            }
        } catch (\Throwable) {
            // Tabel kamus belum ada di database ini -- abaikan, tampilan tetap English.
        }
        self::$pending = [];

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

    // Isi kamus dari migration: [English => terjemahan $lang]. Terjemahan yang sudah diisi penerjemah tidak ditimpa
    // (kecuali $overwrite). Kembalian = jumlah terjemahan yang ditulis.
    public static function seed(array $pairs, string $context = 'ui', string $lang = 'id', bool $overwrite = false): int
    {
        $db = self::db();
        if (! $db->getSchemaBuilder()->hasTable('sys_dictionary')) {
            return 0;
        }
        $n = 0;
        $now = now();
        foreach ($pairs as $en => $text) {
            $en = trim((string) $en);
            if ($en === '' || mb_strlen($en) > 255) {
                continue;
            }
            $db->table('sys_dictionary')->insertOrIgnore(['context' => $context, 'source_en' => $en, 'created_at' => $now, 'created_by' => 'migration']);
            $text = trim((string) $text);
            if ($lang === 'en' || $text === '' || mb_strlen($text) > 255) {
                continue;
            }
            $id = $db->table('sys_dictionary')->where('context', $context)->where('source_en', $en)->value('id_dictionary');
            $has = $db->table('sys_dictionary_lang')->where('id_dictionary', $id)->where('lang_code', $lang)->where('text', '<>', '')->exists();
            if ($has && ! $overwrite) {
                continue;
            }
            $db->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $id, 'lang_code' => $lang],
                ['text' => $text, 'updated_at' => $now, 'updated_by' => 'migration']);
            $n++;
        }
        self::flush();

        return $n;
    }

    // Teks English di kode diganti: terjemahannya ikut pindah ke teks baru. Teks baru sudah ada -> bahasa yang belum
    // diterjemahkan di sana diisi dari teks lama, lalu baris lama dihapus. Kembalian = false bila teks lama tidak ada.
    public static function rename(string $from, string $to, string $context = 'ui'): bool
    {
        $db = self::db();
        $to = trim($to);
        $old = $db->table('sys_dictionary')->where('context', $context)->where('source_en', $from)->first();
        if (! $old || $to === '' || $to === $from) {
            return false;
        }
        $new = $db->table('sys_dictionary')->where('context', $context)->where('source_en', $to)->first();
        $db->transaction(function () use ($db, $old, $new, $to) {
            if (! $new) {
                $db->table('sys_dictionary')->where('id_dictionary', $old->id_dictionary)->update(['source_en' => $to, 'updated_at' => now()]);

                return;
            }
            $have = $db->table('sys_dictionary_lang')->where('id_dictionary', $new->id_dictionary)->where('text', '<>', '')->pluck('lang_code')->all();
            $db->table('sys_dictionary_lang')->where('id_dictionary', $new->id_dictionary)->where('text', '')->delete();
            $db->table('sys_dictionary_lang')->where('id_dictionary', $old->id_dictionary)->whereNotIn('lang_code', $have)
                ->update(['id_dictionary' => $new->id_dictionary, 'updated_at' => now()]);
            $db->table('sys_dictionary')->where('id_dictionary', $old->id_dictionary)->delete();
        });
        self::flush();

        return true;
    }

    private static function remember(string $en, string $context, bool $force = false): void
    {
        $en = trim($en);
        if ($en === '' || mb_strlen($en) > 255 || ! preg_match('/\p{L}/u', $en) || Lang::hasRaw($en) || self::looksIndonesian($en) || self::looksLikeData($en)) {
            return;
        }
        // Tidak mencatat dari console/test (kecuali register() eksplisit) supaya test tidak mengotori kamus.
        if (! $force && (app()->runningInConsole() || ! config('devlib.dictionary_autoregister', true))) {
            return;
        }
        if (isset(self::$pending[$context][$en]) || isset(self::sources($context)[$en]) || isset(self::foldedSources($context)[mb_strtolower($en)])
            || self::isTranslation($en, $context)) {
            return;
        }
        if (! self::$pending && ! $force) {
            app()->terminating(fn () => self::flushPending());
        }
        self::$pending[$context][$en] = true;
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
