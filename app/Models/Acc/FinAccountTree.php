<?php

namespace App\Models\Acc;

use Illuminate\Database\Eloquent\Model;

// Model baca-saja di atas VIEW v1_fin_account_tree (tree_level + default_cc_code
// sudah dihitung DB) — sumber grid Master COA, FIN_TIER_4.md §2.
class FinAccountTree extends Model
{
    protected $connection = 'tm2acc';

    protected $table = 'v1_fin_account_tree';

    protected $primaryKey = 'account_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $casts = [
        'is_recon' => 'boolean',
        'is_postable' => 'boolean',
        'is_active' => 'boolean',
        'tree_level' => 'integer',
    ];
}
