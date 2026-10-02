<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabel paket asisten (Assistant\AssistantServiceProvider); nama tabel dan koneksi dari config('assistant.*').
//  - message: satu baris = satu pertanyaan + jawabannya (percakapan pribadi user dengan asisten, sekaligus log untuk admin).
//    user_level dicatat saat bertanya: masa simpan riwayat berbeda per level (assistant:prune).
//  - metric: katalog pertanyaan angka yang dirawat user (frasa pemicu, modul, level minimum); kelasnya milik aplikasi.
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection(config('assistant.connection'));

        if (! $schema->hasTable(config('assistant.tables.message'))) {
            $schema->create(config('assistant.tables.message'), function (Blueprint $t) {
                $t->id('id_assistant_message');
                $t->string('user_id', 100)->comment('Asker id of the application (not a foreign key: the package does not know the user table)');
                $t->string('user_name', 150)->nullable();
                $t->unsignedTinyInteger('user_level')->default(0)->comment('Security level of the asker when asking; sets how long the row is kept');
                $t->string('question', 500);
                $t->string('reply_kind', 10)->comment('SCREEN | HELP | NUMBER | DENIED | UNKNOWN');
                $t->string('reply_key', 100)->nullable()->comment('Metric key, help topic key or screen key the answer came from');
                $t->longText('reply')->comment('Answer as shown, JSON');
                $t->boolean('answered')->default(true);
                $t->tinyInteger('feedback')->nullable()->comment('1 = thumbs up, -1 = thumbs down');
                $t->boolean('model_used')->default(false)->comment('The local language model was asked for this answer');
                $t->unsignedInteger('duration_ms')->default(0);
                $t->timestamp('created_at')->nullable()->useCurrent();
                $t->index(['user_id', 'id_assistant_message'], 'idx_assistant_message_user');
                $t->index(['user_level', 'created_at'], 'idx_assistant_message_prune');
                $t->comment('Assistant: questions and answers per user');
            });
        }
        if (! $schema->hasTable(config('assistant.tables.metric'))) {
            $schema->create(config('assistant.tables.metric'), function (Blueprint $t) {
                $t->id('id_assistant_metric');
                $t->string('metric_key', 60)->unique()->comment('Key of the metric class registered by the application');
                $t->string('title', 150);
                $t->text('phrases')->comment('Trigger phrases, any language, comma separated');
                $t->string('module_code', 30)->nullable()->comment('Module the asker must be allowed to open; empty = everyone');
                $t->unsignedTinyInteger('min_level')->default(0)->comment('Minimum security level on top of the module right; 0 = module right is enough');
                $t->integer('sort_order')->default(0);
                $t->boolean('activate')->default(true);
                $t->timestamps();
                $t->string('created_by', 100)->nullable();
                $t->string('updated_by', 100)->nullable();
                $t->comment('Assistant: catalogue of number questions');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection(config('assistant.connection'));
        $schema->dropIfExists(config('assistant.tables.metric'));
        $schema->dropIfExists(config('assistant.tables.message'));
    }
};
