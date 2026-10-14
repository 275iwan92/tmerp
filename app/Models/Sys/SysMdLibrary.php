<?php

/*
 * REMARK
 * Tujuan       : Satu baris = satu dokumen .md usang yang diarsipkan, lengkap dengan isinya, untuk dicari kembali per topik.
 * Sumber hulu  : php artisan md:library add (membaca folder arsip C:\Users\iwanp\tmerp_unused dan MANIFEST.md-nya).
 * Input        : -
 * Output       : file_name, original_path, title, summary, body, reason, archived_at.
 * Dipakai oleh : App\Console\Commands\MdLibrary.
 * Status       : Aktif (ddt*).
 */

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

class SysMdLibrary extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_md_library';

    protected $primaryKey = 'id_md_library';

    protected $guarded = [];
}
