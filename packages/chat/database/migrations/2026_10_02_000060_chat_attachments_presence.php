<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabel paket chat (Chat\ChatServiceProvider). Aman dijalankan di dua keadaan:
//  - aplikasi baru: keempat tabel dibuat lengkap;
//  - tmerp (tabel sys_chat_* lama sudah ada, dipakai panel chat menu v1): hanya kolom yang belum ada ditambahkan --
//    lampiran pesan, pair_key, last_read_message_id -- plus tabel sys_user_presence. Nama file tetap karena migrasi ini
//    sudah tercatat di ddtsys dan tm2sys sebelum dipindah ke paket.
// pair_key = "<id user kecil>:<id user besar>" UNIQUE: dua orang yang saling mengirim pesan pertama pada saat yang sama
// tetap mendapat satu percakapan. last_read_message_id = penanda dibaca yang tepat (last_read_at berbatas detik).
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection(config('chat.connection'));

        if (! $schema->hasTable('sys_chat_conversations')) {
            $schema->create('sys_chat_conversations', function (Blueprint $t) {
                $t->id();
                $t->boolean('is_group')->nullable()->default(false);
                $t->string('title', 100)->nullable();
                $t->string('pair_key', 50)->nullable()->unique();
                $t->timestamp('created_at')->nullable()->useCurrent();
            });
        } elseif (! $schema->hasColumn('sys_chat_conversations', 'pair_key')) {
            $schema->table('sys_chat_conversations', fn (Blueprint $t) => $t->string('pair_key', 50)->nullable()->unique()->after('title'));
        }

        if (! $schema->hasTable('sys_chat_messages')) {
            $schema->create('sys_chat_messages', function (Blueprint $t) {
                $t->id();
                $t->foreignId('conversation_id')->constrained('sys_chat_conversations');
                $t->string('sender', 100);
                $t->text('body');
                $t->string('attachment_path')->nullable();
                $t->string('attachment_name')->nullable();
                $t->string('attachment_mime', 120)->nullable();
                $t->unsignedBigInteger('attachment_size')->nullable();
                $t->timestamp('sent_at')->nullable()->useCurrent();
                $t->index(['conversation_id', 'sent_at'], 'idx_conv_time');
            });
        } elseif (! $schema->hasColumn('sys_chat_messages', 'attachment_path')) {
            $schema->table('sys_chat_messages', function (Blueprint $t) {
                $t->string('attachment_path')->nullable()->after('body');
                $t->string('attachment_name')->nullable()->after('attachment_path');
                $t->string('attachment_mime', 120)->nullable()->after('attachment_name');
                $t->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime');
            });
        }

        if (! $schema->hasTable('sys_chat_participants')) {
            $schema->create('sys_chat_participants', function (Blueprint $t) {
                $t->id('id_participant');
                $t->foreignId('conversation_id')->constrained('sys_chat_conversations');
                $t->string('user_id', 100);
                $t->timestamp('last_read_at')->nullable();
                $t->unsignedBigInteger('last_read_message_id')->nullable();
                $t->unique(['conversation_id', 'user_id']);
                $t->index('user_id', 'idx_chat_participant_user');
            });
        } elseif (! $schema->hasColumn('sys_chat_participants', 'last_read_message_id')) {
            $schema->table('sys_chat_participants', function (Blueprint $t) {
                $t->unsignedBigInteger('last_read_message_id')->nullable()->after('last_read_at');
                $t->index('user_id', 'idx_chat_participant_user');
            });
        }

        if (! $schema->hasTable('sys_user_presence')) {
            $schema->create('sys_user_presence', function (Blueprint $t) {
                $t->id('id_presence');
                $t->unsignedBigInteger('user_id')->unique();
                $t->boolean('signed_in')->default(false);
                $t->timestamp('last_seen_at')->nullable();
                if (config('chat.users_table')) {
                    $t->foreign('user_id')->references(config('chat.users_key'))->on(config('chat.users_table'))->cascadeOnDelete();
                }
                $t->comment('Heartbeat of signed-in users (chat): signed_in = 0 after sign out, last_seen_at = last time their screen called the server.');
            });
        }
    }

    // Percakapan tidak ikut dihapus saat rollback: tabel pesan bisa sudah ada sebelum migrasi ini (tmerp) dan berisi
    // pesan user. up() aman dijalankan ulang di atas tabel yang tersisa.
    public function down(): void
    {
        Schema::connection(config('chat.connection'))->dropIfExists('sys_user_presence');
    }
};
