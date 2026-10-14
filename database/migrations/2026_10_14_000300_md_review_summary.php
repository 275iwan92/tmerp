<?php

/*
 * REMARK
 * Tujuan       : Menyimpan judul dan ringkasan 2-3 kalimat tiap berkas .md di sys_md_review, supaya layar MD Review membaca dari database (bukan membuka 193 berkas per tampilan) dan ringkasan tetap ada setelah berkasnya diarsipkan.
 * Sumber hulu  : Diisi php artisan review:import dari MdDigest::of() (judul H1 + kalimat pembuka berkas); tidak memakai AI.
 * Input        : -
 * Output       : Kolom title (varchar 200) dan summary (varchar 600), keduanya boleh kosong, di tm2sys.sys_md_review.
 * Dipakai oleh : ReviewImport (tulis), MdReviewLookup::posts() (baca).
 * Status       : Jalankan di ddt* (--env=mockup); di tm2* saat rilis atas izin Jo.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tm2sys');

        if ($schema->hasTable('sys_md_review') && ! $schema->hasColumn('sys_md_review', 'summary')) {
            $schema->table('sys_md_review', function (Blueprint $t) {
                $t->string('title', 200)->nullable()->after('file_path');
                $t->string('summary', 600)->nullable()->after('title');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('tm2sys');

        if ($schema->hasColumn('sys_md_review', 'summary')) {
            $schema->table('sys_md_review', fn (Blueprint $t) => $t->dropColumn(['title', 'summary']));
        }
    }
};
