<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Print & Email dokumen (user 2026-09-29): tiap cetak menyimpan salinan HTML dokumen (snapshot saat dicetak) dengan token acak.
// QR di PDF/JPEG/HTML menunjuk /d/{token} (tanpa login) untuk serah terima. Isi sama = token lama dipakai lagi (html_hash).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sys')->create('sys_doc_share', function (Blueprint $t) {
            $t->id('id_share');
            $t->char('token', 32)->unique();
            $t->string('doc_type', 40)->comment('slug layar, mis. inquiry-recalc');
            $t->unsignedBigInteger('doc_id')->nullable();
            $t->string('doc_no', 60)->nullable();
            $t->string('title', 200);
            $t->longText('html')->comment('Snapshot dokumen saat dicetak, sudah dibersihkan dari script');
            $t->char('html_hash', 64);
            $t->dateTime('expires_at')->nullable();
            $t->unsignedInteger('view_count')->default(0);
            $t->dateTime('last_viewed_at')->nullable();
            $t->timestamps();
            $t->string('created_by', 100)->nullable();
            $t->string('updated_by', 100)->nullable();
            $t->index(['doc_type', 'doc_id']);
        });

        Schema::connection('tm2sys')->create('sys_doc_email', function (Blueprint $t) {
            $t->id('id_email');
            $t->unsignedBigInteger('share_id');
            $t->text('to_list');
            $t->text('cc_list')->nullable();
            $t->text('bcc_list')->nullable();
            $t->string('subject', 255);
            $t->text('message')->nullable();
            $t->boolean('with_link')->default(true);
            $t->boolean('with_image')->default(false)->comment('Dokumen (JPEG) tampil di isi e-mail');
            $t->boolean('with_pdf')->default(false);
            $t->string('status', 10)->comment('SENT | FAILED');
            $t->text('error')->nullable();
            $t->timestamps();
            $t->string('created_by', 100)->nullable();
            $t->string('updated_by', 100)->nullable();
            $t->foreign('share_id')->references('id_share')->on('sys_doc_share')->cascadeOnDelete();
        });

        $c = DB::connection('tm2sys')->table('sys_constant');
        $c->where('constant_key', 'DOC_SHARE_DAYS')->exists() || $c->insert(['constant_key' => 'DOC_SHARE_DAYS', 'category_code' => 'DATA', 'constant_group' => 'DATA',
            'constant_number' => 0, 'description' => 'Masa berlaku link dokumen online (QR di Print/Email), hari. 0 = tanpa batas.', 'activate' => 1,
            'created_at' => now(), 'updated_at' => now(), 'created_by' => 'claude_code']);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_constant')->where('constant_key', 'DOC_SHARE_DAYS')->delete();
        Schema::connection('tm2sys')->dropIfExists('sys_doc_email');
        Schema::connection('tm2sys')->dropIfExists('sys_doc_share');
    }
};
