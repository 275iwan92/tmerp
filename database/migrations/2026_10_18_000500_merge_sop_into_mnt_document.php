<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * REMARK
 * Tujuan       : Satukan dua register SOP / Work Instruction. tm2mst.sop_document (HR) dipindah ke tm2mnt.mnt_document (+ baris langkah di
 *                mnt_document_line); sop_document dihapus. Dokumen perusahaan = doc_set kosong; dokumen set alat Maintenance = doc_set terisi.
 * Sumber hulu  : audit modul ganda 2026-10-18, disetujui Jo (A1): satu register di mnt_document, HR Officer yang mengedit, Maintenance hanya melihat.
 * Input        : tm2mst.sop_document (sop_code, doc_type, title, revision, effective_date, department_id, purpose, content, activate).
 * Output       : mnt_document + kolom department_id, effective_date, purpose; baris sop_document menjadi dokumen APPROVED atau DRAFT (bila tidak aktif)
 *                dengan satu baris langkah per baris isi; tabel sop_document dihapus; down() membuat ulang struktur kosong dan menghapus kolom.
 * Dipakai oleh : php artisan migrate --env=mockup lalu migrate biasa (tmerp).
 * Status       : Aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        $mnt = Schema::connection('tm2mnt');
        $mnt->table('mnt_document', function (Blueprint $t) use ($mnt) {
            if (! $mnt->hasColumn('mnt_document', 'department_id')) {
                $t->unsignedBigInteger('department_id')->nullable()->index()->after('doc_set')->comment('tm2mst.org_department (logis); kosong = semua departemen');
            }
            if (! $mnt->hasColumn('mnt_document', 'effective_date')) {
                $t->date('effective_date')->nullable()->after('revision');
            }
            if (! $mnt->hasColumn('mnt_document', 'purpose')) {
                $t->text('purpose')->nullable()->after('title');
            }
        });

        $mst = Schema::connection('tm2mst');
        if (! $mst->hasTable('sop_document')) {
            return;
        }

        $docs = DB::connection('tm2mst')->table('sop_document')->orderBy('sop_id')->get();
        $moved = 0;
        foreach ($docs as $d) {
            $db = DB::connection('tm2mnt');
            if ($db->table('mnt_document')->where('doc_no', $d->sop_code)->exists()) {
                throw new RuntimeException("mnt_document sudah punya nomor {$d->sop_code}; ubah salah satu sebelum migrasi.");
            }
            $id = $db->table('mnt_document')->insertGetId([
                'doc_no' => $d->sop_code, 'kind' => $d->doc_type ?: 'SOP', 'doc_set' => null, 'department_id' => $d->department_id,
                'title' => $d->title, 'purpose' => $d->purpose, 'revision' => $d->revision, 'effective_date' => $d->effective_date,
                'status' => $d->activate ? 'APPROVED' : 'DRAFT', 'approved_by' => $d->activate ? 'migration sop_document' : null,
                'approved_at' => $d->activate ? now() : null, 'activate' => (bool) $d->activate,
                'created_at' => $d->created_at, 'created_by' => $d->created_by, 'updated_at' => $d->updated_at, 'updated_by' => $d->updated_by,
            ]);
            $seq = 0;
            foreach (preg_split('/\R/', (string) $d->content) ?: [] as $line) {
                $text = trim((string) preg_replace('/^\s*\d+[.)]\s*/', '', $line));
                if ($text !== '') {
                    $db->table('mnt_document_line')->insert(['document_id' => $id, 'seq_no' => ++$seq, 'section' => 'Steps', 'line_text' => $text,
                        'created_at' => now(), 'created_by' => 'migration sop_document']);
                }
            }
            $moved++;
        }
        if ($moved !== $docs->count()) {
            throw new RuntimeException('Jumlah dokumen yang dipindah tidak sama dengan isi sop_document.');
        }

        $mst->drop('sop_document');
    }

    public function down(): void
    {
        $mst = Schema::connection('tm2mst');
        if (! $mst->hasTable('sop_document')) {
            $mst->create('sop_document', function (Blueprint $t) {
                $t->id('sop_id');
                $t->string('sop_code', 50)->unique();
                $t->string('doc_type', 10)->default('SOP');
                $t->string('title', 200);
                $t->string('revision', 10)->default('0');
                $t->date('effective_date')->nullable();
                $t->unsignedBigInteger('department_id')->nullable();
                $t->text('purpose')->nullable();
                $t->longText('content')->nullable();
                $t->boolean('activate')->default(true);
                $t->timestamps();
                $t->string('created_by', 100)->nullable();
                $t->string('updated_by', 100)->nullable();
                $t->string('data_origin', 10)->default('LIVE');
                $t->boolean('data_ready')->default(true);
                $t->boolean('data_origin_manual')->default(false);
            });
        }
        Schema::connection('tm2mnt')->table('mnt_document', function (Blueprint $t) {
            $t->dropColumn(['department_id', 'effective_date', 'purpose']);
        });
    }
};
