<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

class SysTranslation extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_translations';

    public $timestamps = false;

    protected $fillable = [
        'lang_code',
        'trans_key',
        'trans_value',
    ];

    // Semua teks utk 1 bahasa, keyed by trans_key — dipakai halaman login/
    // forgot-password (mirror objek T[lang] di mockup, sumbernya tabel ini).
    public static function forLang(string $langCode): array
    {
        return self::query()
            ->where('lang_code', $langCode)
            ->pluck('trans_value', 'trans_key')
            ->all();
    }
}
