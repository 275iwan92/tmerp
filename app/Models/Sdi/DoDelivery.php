<?php

namespace App\Models\Sdi;

use App\Models\Master\CustCustomer;
use App\Models\Master\SupSupplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Header Delivery Order -- juga sumber cetakan Surat Jalan (DISPATCH-00,
// satu objek data dua cetakan). dispatch_tier3.md §2.
class DoDelivery extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'do_delivery';

    protected $primaryKey = 'id_delivery';

    protected $fillable = [
        'do_no', 'do_type', 'is_ppn', 'do_date',
        'customer_id', 'supplier_id', 'pr_id', 'ref_part_id', 'subcon_process', 'ship_area_id', 'ship_area_level', 'ship_address_label', 'ship_address_detail', 'ship_postal_code',
        'pickup_name', 'pickup_vehicle', 'pickup_plate',
        'scheduled_ship_date', 'actual_ship_date',
        'status',
        'price_change_flag', 'price_change_approval_status',
        'notes',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'do_date' => 'date',
        'scheduled_ship_date' => 'date',
        'actual_ship_date' => 'datetime',
        'price_change_flag' => 'boolean',
        'is_ppn' => 'boolean',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(DoLine::class, 'delivery_id', 'id_delivery')->orderBy('line_no');
    }

    // Lintas-DB (tm2sdi -> tm2mst) -- Eloquent relationship (query terpisah), CLAUDE.md §2.
    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustCustomer::class, 'customer_id', 'id_customer');
    }

    // Tujuan surat jalan subkon (do_type SUBCON).
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(SupSupplier::class, 'supplier_id', 'id_supplier');
    }

    // DO dari CO berjenis Pickup (diambil sendiri): butuh keterangan pengambilan (nama, kendaraan, nomor polisi).
    public function isPickup(): bool
    {
        return CoOrder::whereIn('id_order', $this->lines()->where('status', '<>', 'CANCELLED')->pluck('co_order_id')->filter())->where('shipment_method', 'PICKUP')->exists();
    }

    // Baris keterangan di blok alamat kirim cetakan DO / Delivery Note (format Jo 2026-10-24); null bila belum diisi.
    public function pickupNote(): ?string
    {
        if (trim((string) $this->pickup_name) === '' && trim((string) $this->pickup_plate) === '') {
            return null;
        }

        return 'PICKUP by '.$this->pickup_name.' by '.($this->pickup_vehicle ?: 'truck').' No. Polisi '.$this->pickup_plate;
    }

    public function isSubcon(): bool
    {
        return $this->do_type === 'SUBCON';
    }
}
