<?php

namespace App\Models\Crp;

use Illuminate\Database\Eloquent\Model;

// Model Plan B (tm2crp) -- koneksi TERPISAH dari 10 DB tm2* internal, sengaja
// tidak punya relationship Eloquent apa pun ke model tm2hrr (portal tidak
// boleh tahu skema internal). Lihat docs/recruitment_tier3.md §1.
class CrpCandidateAccount extends Model
{
    protected $connection = 'tm2crp';

    protected $table = 'crp_candidate_account';

    protected $primaryKey = 'id_account';

    public $timestamps = false;

    protected $fillable = ['national_id', 'email', 'phone', 'password_hash', 'email_verified'];

    protected $casts = ['email_verified' => 'boolean'];
}
