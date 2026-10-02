<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

// Kata harian opening screen: day_no = hari ke-n dalam tahun kabisat (60 = 29 Februari), jadi tanggal yang sama = baris yang sama tiap tahun.
class SysDailyQuote extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_daily_quote';

    protected $primaryKey = 'id_daily_quote';

    protected $fillable = ['day_no', 'quote_text', 'author', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['activate' => 'boolean', 'day_no' => 'integer'];
}
