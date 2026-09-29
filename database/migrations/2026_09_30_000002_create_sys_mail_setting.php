<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Setting e-mail sistem diisi lewat layar (aturan "semua data wajib punya inputan"), bukan hanya .env (backlog09 §18).
// Satu baris. password disimpan terenkripsi (cast encrypted, APP_KEY). Kosong/mailer=env -> tetap pakai .env.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sys')->create('sys_mail_setting', function (Blueprint $table) {
            $table->id('id_mail_setting');
            $table->string('mailer', 10)->default('env')->comment('env = pakai .env | smtp | log');
            $table->string('host', 150)->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->string('encryption', 10)->nullable()->comment('tls | ssl | null');
            $table->string('username', 150)->nullable();
            $table->text('password')->nullable()->comment('terenkripsi');
            $table->string('from_address', 150)->nullable();
            $table->string('from_name', 150)->nullable();
            $table->boolean('from_user')->default(false)->comment('From = e-mail user pengirim yang terverifikasi');
            $table->boolean('require_verified')->default(false)->comment('user tanpa e-mail terverifikasi tidak bisa kirim dokumen');
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sys')->dropIfExists('sys_mail_setting');
    }
};
