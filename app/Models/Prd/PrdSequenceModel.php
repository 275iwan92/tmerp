<?php

namespace App\Models\Prd;

use Illuminate\Database\Eloquent\Model;

/** A trained pairwise ranking model of the job order; only one row is ACTIVE (the one that suggests). */
class PrdSequenceModel extends Model
{
    protected $connection = 'tm2prd';

    protected $table = 'prd_sequence_model';

    protected $primaryKey = 'id_sequence_model';

    protected $guarded = [];

    protected $casts = ['weights' => 'array', 'feature_names' => 'array', 'trained_at' => 'datetime'];
}
