<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdProduct extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_product';

    protected $primaryKey = 'id_product';

    protected $fillable = [
        'product_code',
        'product_name',
        'customer_id',
        'category_id',
        'description',
        'uom_stock',
        'uom_sales',
        'conversion_to_stock',
        'qty_limit_min',
        'qty_limit_max',
        'cm_length',
        'cm_width',
        'cm_height',
        'min_stock',
        'max_stock',
        'cur_stock',
        'default_selling_price',
        'minimum_selling_price',
        'is_confirmed',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_confirmed' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Same-DB (tm2mst): FK fisik prod_product.customer_id -> cust_customer.id_customer.
    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustCustomer::class, 'customer_id', 'id_customer');
    }

    // Same-DB (tm2mst): FK fisik prod_product.category_id -> prod_category.id_category.
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProdCategory::class, 'category_id', 'id_category');
    }

    // Tidak ada relasi balik ke iqp_inquiry (parent_product_id, tm2sdi) di sini secara
    // sengaja — sama seperti CustCustomer, master tetap netral. Lihat IqpInquiry::parentProduct().
}
