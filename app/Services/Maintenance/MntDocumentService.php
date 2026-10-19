<?php

/*
 * REMARK
 * Tujuan       : Satu pintu aturan dokumen SOP / Work Instruction (tm2mnt.mnt_document dan mnt_document_line): simpan kepala dokumen, baris isi, Approve (terkunci), Back to Draft (revisi naik), Activate / Deactivate, dan catatan MntLog. Dipakai dua layar: Maintenance (maintenance-documents, dokumen set alat dan berkas) dan HRM (hrm-sop-form, SOP / WI perusahaan dengan departemen, tanggal berlaku, tujuan). Hak mengubah (HR Officer) tetap diperiksa pemanggil lewat MaintenanceAccess::canManageDocuments.
 * Sumber hulu  : Aturan diekstrak dari route v2.mnt.document.save (routes/v2_maintenance.php) dan halaman v1 pages::hrm.sop-documents (diarsipkan di tmerp_unused/hrm_sop_documents_v1_2026-10-19.blade.php); tidak ada aturan yang berubah: Approve hanya bila Draft, Back to Draft hanya bila Approved (revisi + 1, dua digit), dokumen Approved terkunci, nomor dokumen unik, jenis SOP / WI untuk dokumen perusahaan.
 * Input        : Dokumen, data kepala, baris isi, user (login untuk created_by / updated_by).
 * Output       : tm2mnt.mnt_document dan mnt_document_line berubah (transaksi tm2mnt), MntLog dicatat; ValidationException (kunci document / field) bila aturan dilanggar.
 * Dipakai oleh : routes/v2_maintenance.php (v2.mnt.document.save), routes/v2_hrm.php (v2.hrm.sop.save), layar hrm-sop-form, tes SopRegisterMergedTest dan MaintenanceAssetTest.
 * Status       : Aktif (T7f HRM, 2026-10-19).
 */

namespace App\Services\Maintenance;

use App\Models\Mnt\MntDocument;
use App\Models\Mnt\MntLog;
use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class MntDocumentService
{
    public function approve(MntDocument $d, SecUser $user): void
    {
        if ($d->isApproved()) {
            throw ValidationException::withMessages(['document' => 'This document is already approved.']);
        }
        $d->update(['status' => 'APPROVED', 'approved_by' => $user->login, 'approved_at' => now(), 'updated_by' => $user->login]);
        MntLog::add('DOCUMENT', $d->id_document, 'Approved', 'Revision '.$d->revision);
    }

    public function backToDraft(MntDocument $d, SecUser $user): void
    {
        if (! $d->isApproved()) {
            throw ValidationException::withMessages(['document' => 'Only an approved document can go back to draft.']);
        }
        $rev = str_pad((string) ((int) $d->revision + 1), 2, '0', STR_PAD_LEFT);
        $d->update(['status' => 'DRAFT', 'revision' => $rev, 'approved_by' => null, 'approved_at' => null, 'updated_by' => $user->login]);
        MntLog::add('DOCUMENT', $d->id_document, 'Back to draft', 'New revision '.$rev);
    }

    public function setActive(MntDocument $d, bool $active, SecUser $user): void
    {
        $d->update(['activate' => $active, 'updated_by' => $user->login]);
        MntLog::add('DOCUMENT', $d->id_document, $active ? 'Activated' : 'Deactivated');
    }

    /** Kepala dokumen yang sudah divalidasi pemanggil (layar Maintenance). Dokumen baru: revisi 00, Draft, aktif. */
    public function saveHeader(?MntDocument $m, array $data, SecUser $user): MntDocument
    {
        $isNew = ! $m;
        $m = $m ? tap($m)->update($data + ['updated_by' => $user->login]) : MntDocument::create($data + ['revision' => '00', 'status' => 'DRAFT', 'activate' => true, 'created_by' => $user->login, 'updated_by' => $user->login]);
        MntLog::add('DOCUMENT', $m->id_document, $isNew ? 'Created' : 'Saved');

        return $m;
    }

    /** Layar Maintenance: ubah teks baris yang ada dan hapus baris terpilih (per id baris). */
    public function updateLines(MntDocument $m, array $remove, array $rows, SecUser $user): void
    {
        foreach ($remove as $lid) {
            $m->lines()->whereKey((int) $lid)->delete();
        }
        foreach ($rows as $lid => $row) {
            $m->lines()->whereKey((int) $lid)->update(['section' => trim((string) ($row['section'] ?? '')) ?: 'Steps', 'line_text' => trim((string) ($row['line_text'] ?? '')) ?: '-', 'updated_by' => $user->login]);
        }
    }

    public function addLine(MntDocument $m, string $section, string $text, SecUser $user): void
    {
        $m->lines()->create(['seq_no' => (int) $m->lines()->max('seq_no') + 1, 'section' => trim($section) ?: 'Steps', 'line_text' => trim($text), 'created_by' => $user->login, 'updated_by' => $user->login]);
    }

    /**
     * Layar HRM: SOP / WI perusahaan (doc_set kosong) lengkap dengan baris isi yang ditulis ulang. Dokumen Approved terkunci.
     *
     * @param  array<int, array{section?: string, text?: string}>  $lines
     */
    public function saveCompany(?MntDocument $m, array $in, array $lines, SecUser $user): MntDocument
    {
        if ($m && $m->isApproved()) {
            throw ValidationException::withMessages(['document' => 'An approved document is locked. Use Back to Draft first.']);
        }
        $d = Validator::make($in + ['activate' => true], [
            'doc_no' => ['required', 'string', 'max:40', Rule::unique('tm2mnt.mnt_document', 'doc_no')->ignore($m?->id_document, 'id_document')],
            'kind' => ['required', Rule::in(array_keys(MntDocument::COMPANY_KINDS))],
            'title' => ['required', 'string', 'max:200'],
            'revision' => ['required', 'string', 'max:10'],
            'effective_date' => ['nullable', 'date'],
            'department_id' => ['nullable', 'integer', 'exists:tm2mst.org_department,department_id'],
            'purpose' => ['nullable', 'string'],
            'activate' => ['boolean'],
        ], [], ['doc_no' => 'Document No.', 'kind' => 'Document type', 'title' => 'Title'])->validate();
        foreach ($lines as $i => $line) {
            if (mb_strlen(trim((string) ($line['section'] ?? ''))) > 60) {
                throw ValidationException::withMessages(['document' => 'Step '.($i + 1).': the section must be at most 60 characters.']);
            }
        }

        $payload = ['doc_no' => $d['doc_no'], 'kind' => $d['kind'], 'title' => $d['title'], 'revision' => $d['revision'], 'effective_date' => ($d['effective_date'] ?? null) ?: null,
            'department_id' => ($d['department_id'] ?? null) ?: null, 'purpose' => ($d['purpose'] ?? null) ?: null, 'activate' => (bool) $d['activate'], 'updated_by' => $user->login];

        return DB::connection('tm2mnt')->transaction(function () use ($m, $payload, $lines, $user) {
            $doc = $m ? tap($m)->update($payload) : MntDocument::create($payload + ['status' => 'DRAFT', 'created_by' => $user->login]);
            $doc->lines()->delete();
            $seq = 0;
            foreach ($lines as $line) {
                $text = trim((string) ($line['text'] ?? ''));
                if ($text !== '') {
                    $doc->lines()->create(['seq_no' => ++$seq, 'section' => trim((string) ($line['section'] ?? '')) ?: 'Steps', 'line_text' => $text, 'created_by' => $user->login, 'updated_by' => $user->login]);
                }
            }
            MntLog::add('DOCUMENT', $doc->id_document, $m ? 'Saved' : 'Created');

            return $doc;
        });
    }
}
