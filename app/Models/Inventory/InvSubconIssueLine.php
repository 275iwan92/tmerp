<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvSubconIssueLine extends Model
{
    protected $connection = 'tm2inv';

    protected $table = 'inv_subcon_issue_line';

    protected $primaryKey = 'id_subcon_issue_line';

    protected $guarded = ['id_subcon_issue_line'];

    protected $casts = ['qty_sent' => 'float', 'qty_consumed' => 'float', 'qty_returned' => 'float', 'unit_cost' => 'float'];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(InvSubconIssue::class, 'issue_id', 'id_subcon_issue');
    }

    // Sisa di tangan vendor: dikirim - terpakai - dikembalikan.
    public function atVendor(): float
    {
        return round($this->qty_sent - $this->qty_consumed - $this->qty_returned, 4);
    }
}
