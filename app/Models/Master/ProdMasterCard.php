<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Master Card Collection: kartu induk lama (Excel "MC" / foto) sebelum dijadikan Master Product atau Inquiry.
// Logika baca/lengkapi/konversi = App\Services\MasterCard\*.
class ProdMasterCard extends Model
{
    public const STATUS = ['INCOMPLETE' => 'Incomplete', 'READY' => 'Ready', 'PRODUCT' => 'Master Product', 'INQUIRY' => 'Inquiry'];

    protected $connection = 'tm2mst';

    protected $table = 'prod_master_card';

    protected $primaryKey = 'id_master_card';

    protected $guarded = ['id_master_card'];

    protected $casts = [
        'card_date' => 'date', 'dims_json' => 'array', 'materials_json' => 'array', 'finishing_json' => 'array', 'process_json' => 'array',
        'colors_json' => 'array', 'acc_json' => 'array', 'raw_json' => 'array',
        'dim_l' => 'float', 'dim_w' => 'float', 'dim_h' => 'float', 'knife_w' => 'float', 'knife_h' => 'float', 'print_w' => 'float', 'print_h' => 'float',
        'flat_w' => 'float', 'flat_h' => 'float',
    ];

    public function files(): HasMany
    {
        return $this->hasMany(ProdMasterCardFile::class, 'master_card_id', 'id_master_card')->orderBy('seq');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustCustomer::class, 'customer_id', 'id_customer');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ProdType::class, 'type_code', 'type_code');
    }

    public function converted(): bool
    {
        return in_array($this->status, ['PRODUCT', 'INQUIRY'], true);
    }
}
