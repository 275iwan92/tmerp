<?php

/* REMARK
 * Tujuan       : Dasar bersama header dokumen stok RM / SFG / FG (Stock In/Out/Adjustment/Opname/Return, dibedakan doc_cat + doc_type):
 *                koneksi tm2inv, kunci doc_id, status, opname, relasi baris dan departemen tujuan. Turunan menyebut tabel, kelas baris, dan kolom khasnya (EXTRA_KEYS).
 * Sumber hulu  : tm2inv.rm_stock / sfg_stock / fg_stock (inventory_tier1.md #2); flow_status menggantikan stat_auth lama, hanya flow_status + tm2sys.flow_log (FlowEngineService).
 * Input        : atribut header dokumen dari StockDocumentService / StockDocEntryService.
 * Output       : model Eloquent dengan lines() terurut seq_no dan issuedToDepartment().
 * Dipakai oleh : RmStock, SfgStock, FgStock; layanan inventori, grid dan form dokumen stok.
 * Status       : Aktif; menggantikan tiga salinan kelas yang sama (Jo 2026-10-20).
 */

namespace App\Models\Inventory;

use App\Models\Master\OrgDepartment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

abstract class StockDocHeader extends Model
{
    const CREATED_AT = 'create_log';

    const UPDATED_AT = 'update_log';

    protected const CORE = [
        'doc_no', 'doc_date', 'doc_type', 'doc_cat',
        'ref_doc_type', 'ref_doc_id', 'ref_doc_no',
        'warehouse_id', 'issued_to_dept', 'process_code', 'reason_code', 'attachment_path', 'notes',
        'stat_confirm', 'stat_cancel', 'stat_closed', 'post_date', 'period_id',
        'flow_status', 'posted_by', 'posted_at',
        'opn_start', 'opn_stop', 'opn_officer', 'opn_witness', 'full_scope',
        'create_by', 'update_by',
    ];

    /** Kolom khas kelas stok (product_id, wo_id, wo_no). */
    protected const EXTRA_KEYS = [];

    /** @var class-string<StockDocLine> */
    protected const LINE_MODEL = StockDocLine::class;

    protected $connection = 'tm2inv';

    protected $primaryKey = 'doc_id';

    protected $casts = [
        'doc_date' => 'datetime',
        'post_date' => 'date',
        'posted_at' => 'datetime',
        'opn_start' => 'datetime',
        'opn_stop' => 'datetime',
        'stat_confirm' => 'boolean',
        'stat_cancel' => 'boolean',
        'stat_closed' => 'boolean',
        'full_scope' => 'boolean',
    ];

    public function getFillable(): array
    {
        return array_merge(static::CORE, static::EXTRA_KEYS);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(static::LINE_MODEL, 'doc_id', 'doc_id')->orderBy('seq_no');
    }

    // Lintas-DB (tm2inv -> tm2mst): relasi Eloquent (query terpisah), BUKAN FK fisik/join, sesuai CLAUDE.md.
    public function issuedToDepartment(): BelongsTo
    {
        return $this->belongsTo(OrgDepartment::class, 'issued_to_dept', 'department_id');
    }
}
