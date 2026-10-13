<?php

/*
 * REMARK
 * Tujuan       : Tabel dan menu untuk layar Setup > Others > MD Review dan Old Stories Review (Jo menandai berkas .md dan cerita lama, lalu mengirim ke Claude untuk diproses).
 * Sumber hulu  : docs/ringkas/md_review_tabel.md dan cerita_lama_penuh.md (diisi oleh php artisan review:import); keputusan diketik Jo di layar.
 * Input        : -
 * Output       : sys_md_review (keputusan per berkas), sys_story_review (keputusan per pesan), sys_review_request (antrean Proses ke Claude), 2 baris menu di SET_GRP_OTHERS, pasangan Indonesia di kamus.
 * Dipakai oleh : MdReviewLookup, StoryReviewLookup, ReviewRequestService, php artisan review:requests.
 * Status       : Jalankan di ddt* (--env=mockup); di tm2* saat rilis atas izin Jo.
 */

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU = [
        ['SET_MD_REVIEW', 'MD Review', 'v2:setup-md-review', 'fa-solid fa-file-circle-check', 10],
        ['SET_STORY_REVIEW', 'Old Stories Review', 'v2:setup-story-review', 'fa-solid fa-book-open', 20],
    ];

    // Teks layar (English) -> Indonesia. Hanya mengisi yang belum punya terjemahan.
    private const UI = [
        'MD Review' => 'Tinjauan Berkas MD',
        'Old Stories Review' => 'Tinjauan Cerita Lama',
        'Process with Claude' => 'Proses dengan Claude',
        'Keep' => 'Pertahankan',
        'Condense' => 'Ringkas',
        'Discard' => 'Buang',
        'Unsure' => 'Ragu',
        'Adopt' => 'Adopsi',
        'Later' => 'Nanti',
        'Ignore' => 'Abaikan',
        'Decision' => 'Keputusan',
        'Proposal' => 'Usulan',
        'File' => 'Berkas',
        'Lines' => 'Baris',
        'Last changed' => 'Terakhir diubah',
        'References' => 'Rujukan',
        'Reason' => 'Alasan',
        'Not decided' => 'Belum diputuskan',
        'Proposed discard' => 'Usulan buang',
        'Proposed condense' => 'Usulan ringkas',
        'Proposed keep' => 'Usulan pertahankan',
        'Message date' => 'Tanggal pesan',
        'Session' => 'Sesi',
        'Characters' => 'Karakter',
        'Message' => 'Pesan',
        'Full message' => 'Pesan utuh',
        'Decide' => 'Putuskan',
        'Full text' => 'Teks utuh',
        'Pick Keep, Condense or Discard for each file. Nothing is deleted from here. Process with Claude queues your decisions; Claude reads the queue at the start of a session and applies them, and you approve the result before anything is committed.' => 'Pilih Pertahankan, Ringkas atau Buang untuk tiap berkas. Tidak ada yang dihapus dari layar ini. Proses dengan Claude mengantre keputusan Anda; Claude membaca antrean di awal sesi lalu menerapkannya, dan Anda menyetujui hasilnya sebelum ada yang di-commit.',
        'Pick Adopt (this is my story), Later or Ignore for each message. Process with Claude queues your decisions; Claude reads the queue at the start of a session and writes the adopted stories into the right document, and you approve the result.' => 'Pilih Adopsi (ini cerita saya), Nanti atau Abaikan untuk tiap pesan. Proses dengan Claude mengantre keputusan Anda; Claude membaca antrean di awal sesi lalu menulis cerita yang diadopsi ke dokumen yang benar, dan Anda menyetujui hasilnya.',
        'Decided' => 'Diputuskan',
        'Last request: :status, :at by :by. :decided of :total decided (:summary).' => 'Permintaan terakhir: :status, :at oleh :by. :decided dari :total diputuskan (:summary).',
        'Rows cannot be added on this screen.' => 'Baris tidak dapat ditambahkan di layar ini.',
        'Make at least one decision before sending to Claude.' => 'Buat minimal satu keputusan sebelum dikirim ke Claude.',
        'Sent to Claude. Claude reads the queue at the start of a session.' => 'Terkirim ke Claude. Claude membaca antrean di awal sesi.',
        'The waiting request was updated with your latest decisions.' => 'Permintaan yang menunggu sudah diperbarui dengan keputusan terbaru.',
    ];

    public function up(): void
    {
        $schema = Schema::connection('tm2sys');

        if (! $schema->hasTable('sys_md_review')) {
            $schema->create('sys_md_review', function (Blueprint $t) {
                $t->id('id_md_review');
                $t->string('file_path', 255)->unique();
                $t->unsignedInteger('line_count')->default(0);
                $t->date('last_changed')->nullable();
                $t->unsignedInteger('ref_count')->nullable();
                $t->string('proposal', 12);          // KEEP | CONDENSE | UNSURE | DISCARD (usulan Claude)
                $t->string('reason', 500)->nullable();
                $t->string('decision', 12)->nullable(); // KEEP | CONDENSE | DISCARD; null = belum diputuskan
                $t->string('decided_by', 60)->nullable();
                $t->dateTime('decided_at')->nullable();
                $t->string('created_by', 60)->nullable();
                $t->string('updated_by', 60)->nullable();
                $t->timestamps();
            });
        }

        if (! $schema->hasTable('sys_story_review')) {
            $schema->create('sys_story_review', function (Blueprint $t) {
                $t->id('id_story_review');
                $t->date('msg_date');
                $t->string('session_ref', 12);
                $t->unsignedInteger('char_count')->default(0);
                $t->mediumText('full_text');
                $t->string('decision', 12)->nullable(); // ADOPT | LATER | IGNORE; null = belum diputuskan
                $t->string('decided_by', 60)->nullable();
                $t->dateTime('decided_at')->nullable();
                $t->string('created_by', 60)->nullable();
                $t->string('updated_by', 60)->nullable();
                $t->timestamps();
                $t->index(['msg_date']);
            });
        }

        if (! $schema->hasTable('sys_review_request')) {
            $schema->create('sys_review_request', function (Blueprint $t) {
                $t->id('id_review_request');
                $t->string('scope', 10);              // MD | STORY
                $t->string('status', 12)->default('REQUESTED'); // REQUESTED | DONE
                $t->unsignedInteger('n_total')->default(0);
                $t->unsignedInteger('n_decided')->default(0);
                $t->string('summary', 500)->nullable(); // mis. KEEP=12; CONDENSE=3; DISCARD=29
                $t->string('requested_by', 60);
                $t->dateTime('requested_at');
                $t->string('handled_by', 60)->nullable();
                $t->dateTime('handled_at')->nullable();
                $t->string('note', 500)->nullable();
                $t->timestamps();
                $t->index(['scope', 'status']);
            });
        }

        $db = DB::connection('tm2sys');
        $menu = $db->table('sys_app_menu');
        $others = (clone $menu)->where('code', 'SET_GRP_OTHERS')->first();
        if ($others) {
            foreach (self::MENU as [$code, $label, $target, $icon, $sort]) {
                if (! (clone $menu)->where('code', $code)->exists()) {
                    $menu->insert(['parent_id' => $others->id, 'code' => $code, 'label' => $label, 'icon' => $icon, 'menu_type' => 'item',
                        'object_type' => 'grid', 'target' => $target, 'sort_order' => $sort, 'activate' => 1, 'required_object' => null,
                        'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now()]);
                }
            }
        }

        if ($schema->hasTable('sys_dictionary')) {
            foreach (self::UI as $en => $id) {
                $db->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => $en, 'group_code' => 'general', 'origin' => 'app',
                    'activate' => 1, 'created_at' => now(), 'created_by' => 'migration']);
                $key = $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary');
                if ($key && ! $db->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
                    $db->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => $id]);
                }
            }
            Dictionary::flush();
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', array_column(self::MENU, 0))->delete();
        $schema = Schema::connection('tm2sys');
        $schema->dropIfExists('sys_review_request');
        $schema->dropIfExists('sys_story_review');
        $schema->dropIfExists('sys_md_review');
    }
};
