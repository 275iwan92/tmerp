<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinVoucher extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'fin_voucher';

    protected $primaryKey = 'voucher_id';

    protected $fillable = [
        'voucher_number',
        'journal_id',
        'voucher_type',
        'main_account_id',
        'entry_date',
        'posting_date',
        'doc_type',
        'revision_no',
        'source_type',
        'source_id',
        'reference_no',
        'partner_name',
        'ppn_type',
        'efaktur_no',
        'pph_type',
        'bupot_no',
        'description',
        'status',
        'stat_cancel',
        'cancel_reason',
        'posted_by',
        'posted_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'posting_date' => 'date',
        'posted_at' => 'datetime',
        'revision_no' => 'integer',
        'stat_cancel' => 'boolean',
    ];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(FinJournal::class, 'journal_id', 'journal_id');
    }

    public function mainAccount(): BelongsTo
    {
        return $this->belongsTo(FinAccount::class, 'main_account_id', 'account_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FinVoucherLine::class, 'voucher_id', 'voucher_id');
    }
}
