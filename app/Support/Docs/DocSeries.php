<?php

namespace App\Support\Docs;

use App\Support\DbName;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

// Nomor dokumen dari seri (tm2sys.sys_doc_series + sys_doc_series_counter). Dipanggil saat Confirm, DI DALAM transaksi
// dokumennya: $connection = koneksi transaksi itu, supaya penghitung ikut batal bila Confirm gagal (satu server MySQL,
// tabel disebut lengkap dengan nama databasenya). Nomor tidak pernah dipakai ulang; dokumen batal tetap memegang nomornya.
// Token format: {SEQ} {SEQ:5} (urut, opsional lebar dengan nol di depan) {ROMAN} {MM} {YY} {YYYY}.
final class DocSeries
{
    private const ROMAN = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    public static function next(string $code, string $date, string $connection = 'tm2sys'): string
    {
        $db = DB::connection($connection);
        $series = $db->table(DbName::table('tm2sys.sys_doc_series'))->where('series_code', $code)->where('activate', 1)->first();
        if (! $series) {
            throw new DomainException(str_replace(':code', $code, t('Document number series :code is missing or inactive (Setup > Document Number Series).')));
        }
        $d = CarbonImmutable::parse($date);
        $period = match ($series->reset_period) {
            'MONTH' => $d->format('Y-m'),
            'YEAR' => $d->format('Y'),
            default => 'ALL',
        };
        $counter = DbName::table('tm2sys.sys_doc_series_counter');
        $key = ['doc_series_id' => $series->id_doc_series, 'period_key' => $period];

        return $db->transaction(function () use ($db, $counter, $key, $series, $d) {
            $db->table($counter)->insertOrIgnore($key + ['last_no' => 0, 'updated_at' => now()]);
            $no = (int) $db->table($counter)->where($key)->lockForUpdate()->value('last_no') + 1;
            $db->table($counter)->where($key)->update(['last_no' => $no, 'updated_at' => now()]);

            return self::format($series->number_format, $no, $d);
        });
    }

    public static function format(string $format, int $no, CarbonImmutable $d): string
    {
        $out = preg_replace_callback('/\{SEQ(?::(\d+))?\}/', fn ($m) => isset($m[1]) ? str_pad((string) $no, (int) $m[1], '0', STR_PAD_LEFT) : (string) $no, $format);

        return strtr($out, ['{ROMAN}' => self::ROMAN[$d->month - 1], '{MM}' => $d->format('m'), '{YY}' => $d->format('y'), '{YYYY}' => $d->format('Y')]);
    }
}
