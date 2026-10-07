<?php

namespace App\Models\Mnt;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// SOP / WI (teks bernomor, revisi, status) dan dokumen berkas (manual, gambar, kontak vendor). Satu dokumen bisa dipakai banyak aset.
class MntDocument extends Model
{
    protected $connection = 'tm2mnt';

    protected $table = 'mnt_document';

    protected $primaryKey = 'id_document';

    protected $guarded = ['id_document'];

    protected $casts = ['activate' => 'boolean', 'approved_at' => 'datetime'];

    public const KINDS = ['SOP' => 'SOP', 'WI' => 'Work Instruction', 'MANUAL' => 'Manual', 'DRAWING' => 'Drawing', 'VENDOR_CONTACT' => 'Vendor contact', 'OTHER' => 'Other'];

    public const STATUS_TONES = ['Draft' => 'warn', 'Approved' => 'success'];

    public function lines(): HasMany
    {
        return $this->hasMany(MntDocumentLine::class, 'document_id', 'id_document')->orderBy('seq_no');
    }

    public function isApproved(): bool
    {
        return $this->status === 'APPROVED';
    }
}
