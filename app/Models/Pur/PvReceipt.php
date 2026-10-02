<?php

namespace App\Models\Pur;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PvReceipt extends Model
{
    protected $connection = 'tm2pur';

    protected $table = 'pv_receipt';

    protected $primaryKey = 'pv_id';

    protected $fillable = [
        'doc_no', 'i_receipt_type', 'i_doc_date', 'i_supplier_id', 'i_customer_id', 'i_co_id', 'i_do_id', 'i_warehouse_code',
        'i_supplier_do_no', 'i_vehicle_no', 'i_notes',
        'c_po_count', 'c_po_doc_list',
        'status', 'posted_at', 'posted_by', 'rm_stock_doc_id', 'cancel_reason', 'cancelled_at', 'cancelled_by', 'rm_stock_rev_doc_id',
        'is_handed_over', 'handed_over_at', 'handed_over_by',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'i_doc_date' => 'date',
        'posted_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'is_handed_over' => 'boolean',
        'handed_over_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(PvLine::class, 'pv_id', 'pv_id')->orderBy('line_no');
    }

    // Seri nomor per jenis penerimaan (user 2026-10-02): SPB hanya untuk penerimaan dari supplier, supaya urutan nomor SPB
    // bisa diperiksa accounting terhadap tagihan supplier; bahan baku dari customer dan barang dari subkon punya seri sendiri.
    public const NUMBER_PREFIX = ['PO' => 'SPB', 'CUSTOMER' => 'PBC', 'SUBCON' => 'PBS'];

    // Nomor lahir saat dokumen diposting (user 2026-10-02), bukan saat Draft dibuat: Draft yang dihapus tidak boleh
    // meninggalkan nomor kosong di urutan SPB. Draft tampil sebagai "Draft #<id>".
    public function label(): string
    {
        return $this->doc_no ?: 'Draft #'.$this->pv_id;
    }

    public static function nextDocNo(string $type = 'PO'): string
    {
        $prefix = (self::NUMBER_PREFIX[$type] ?? self::NUMBER_PREFIX['PO']).now()->format('y');

        $last = static::where('doc_no', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('doc_no')
            ->value('doc_no');

        $seq = $last ? ((int) substr($last, 5)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    public function refreshPoSummary(): void
    {
        $this->c_po_count = PvLine::where('pv_id', $this->pv_id)->distinct('c_po_id')->count('c_po_id');
        $this->c_po_doc_list = PvLine::where('pv_id', $this->pv_id)->select('c_po_doc_no')->distinct()->orderBy('c_po_doc_no')->pluck('c_po_doc_no')->implode(', ');
    }
}
