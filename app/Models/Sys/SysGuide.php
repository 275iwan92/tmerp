<?php

/*
 * REMARK
 * Tujuan       : Satu baris = satu panduan (artikel) per modul: bagian umum dan bagian rumus terpisah.
 * Sumber hulu  : docs/guides/<modul>/NN_slug.md lewat php artisan guides:import.
 * Input        : -
 * Output       : module, slug, title, summary, body, body_secret, sort_order, source_path.
 * Dipakai oleh : App\Console\Commands\GuidesImport, App\Support\V2\Grid\Setup\GuidesLookup.
 * Status       : Aktif.
 */

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

class SysGuide extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_guide';

    protected $primaryKey = 'id_guide';

    protected $guarded = [];
}
