<?php

/*
 * REMARK
 * Tujuan       : Satu register SOP / Work Instruction perusahaan (doc_set kosong, lingkup departemen, diedit HR Officer) dan dokumen set alat
 *                Maintenance (doc_set terisi, dibaca Maintenance), plus dokumen berkas (manual, gambar, kontak vendor).
 * Sumber hulu  : tm2mnt.mnt_document dan mnt_document_line; tm2mst.org_department (logis, lintas-DB).
 * Input        : doc_no, kind, doc_set, department_id, title, purpose, revision, effective_date, status, file_location, activate.
 * Output       : Relasi lines() dan department(); contentText(); scope company(), equipment(), inForce().
 * Dipakai oleh : Halaman HR SOP, Self-Service, Opening Board, layar Maintenance SOP and Work Instructions, MaintenanceAccess.
 * Status       : Aktif; tabel sop_document dilebur ke sini 2026-10-18.
 */

namespace App\Models\Mnt;

use App\Models\Master\OrgDepartment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// SOP / WI (teks bernomor, revisi, status) dan dokumen berkas (manual, gambar, kontak vendor). Satu dokumen bisa dipakai banyak aset.
class MntDocument extends Model
{
    protected $connection = 'tm2mnt';

    protected $table = 'mnt_document';

    protected $primaryKey = 'id_document';

    protected $guarded = ['id_document'];

    protected $casts = ['activate' => 'boolean', 'approved_at' => 'datetime', 'effective_date' => 'date'];

    public const KINDS = ['SOP' => 'SOP', 'WI' => 'Work Instruction', 'MANUAL' => 'Manual', 'DRAWING' => 'Drawing', 'VENDOR_CONTACT' => 'Vendor contact', 'OTHER' => 'Other'];

    public const COMPANY_KINDS = ['SOP' => 'SOP', 'WI' => 'Work Instruction'];

    public const STATUS_TONES = ['Draft' => 'warn', 'Approved' => 'success'];

    public function lines(): HasMany
    {
        return $this->hasMany(MntDocumentLine::class, 'document_id', 'id_document')->orderBy('seq_no');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(OrgDepartment::class, 'department_id', 'department_id');
    }

    public function isApproved(): bool
    {
        return $this->status === 'APPROVED';
    }

    /** Dokumen perusahaan: bukan milik set alat Maintenance. */
    public function scopeCompany(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('doc_set')->orWhere('doc_set', ''))->whereIn('kind', array_keys(self::COMPANY_KINDS));
    }

    public function scopeEquipment(Builder $q): Builder
    {
        return $q->whereNotNull('doc_set')->where('doc_set', '<>', '');
    }

    /** Berlaku bagi pegawai: aktif dan sudah Approved. */
    public function scopeInForce(Builder $q): Builder
    {
        return $q->where('activate', true)->where('status', 'APPROVED');
    }

    /** Langkah per baris, satu baris teks per langkah. */
    public function contentText(): string
    {
        return $this->lines->pluck('line_text')->implode("\n");
    }
}
