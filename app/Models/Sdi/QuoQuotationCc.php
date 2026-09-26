<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;

class QuoQuotationCc extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'quo_quotation_cc';

    protected $primaryKey = 'id_quotation_cc';

    protected $fillable = ['quotation_id', 'line_seq', 'contact_name', 'created_by', 'updated_by'];
}
