<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Master\SupSupplier;
use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Models\Master\WhZone;
use RuntimeException;

// Lokasi maya v2.01: tempat stok yang ada di buku tetapi tidak di lantai gudang. Satu gudang bertipe VIRTUAL (kode VIRT) berisi
//   area LOSS    -> satu bin (barang hilang / rusak yang sudah dibukukan)
//   area TRANSIT -> satu bin (barang dalam perjalanan antar gedung, transfer dua langkah)
//   area SUBCON  -> satu bin per supplier subkon (bahan di tangan vendor)
// Semua dibuat sesuai permintaan dan idempoten: dipanggil berulang menghasilkan baris yang sama. Area virtual tidak tampil di
// Warehouse Map dan tidak dibuatkan bin oleh BinGenerator. Dipakai jalur subkon, In Transit, dan loss.
final class VirtualLocationService
{
    public const WAREHOUSE_CODE = 'VIRT';

    // jenis area => [kode area, nama area]
    private const ZONES = ['LOSS' => ['LOSS', 'Loss (virtual)'], 'TRANSIT' => ['TRN', 'In Transit (virtual)'], 'SUBCON' => ['SUB', 'At Subcontractor (virtual)']];

    public function __construct(private readonly string $login = 'system (virtual locations)') {}

    public function warehouse(): WhWarehouse
    {
        return WhWarehouse::query()->where('warehouse_code', self::WAREHOUSE_CODE)->first()
            ?? WhWarehouse::create(['warehouse_code' => self::WAREHOUSE_CODE, 'warehouse_name' => 'Virtual Locations', 'warehouse_type' => 'VIRTUAL', 'activate' => true,
                'floor_count' => 1, 'shape' => 'RECT', 'created_by' => $this->login, 'updated_by' => $this->login]);
    }

    public function zone(string $type): WhZone
    {
        [$code, $name] = self::ZONES[$type] ?? throw new RuntimeException("Jenis area virtual tidak dikenal: {$type}");
        $wh = $this->warehouse();

        return WhZone::query()->where('warehouse_id', $wh->id_warehouse)->where('zone_code', $code)->first()
            ?? WhZone::create(['warehouse_id' => $wh->id_warehouse, 'floor_no' => 1, 'zone_code' => $code, 'zone_name' => $name, 'zone_type' => $type, 'storage_mode' => 'AREA',
                'stock_class' => 'ANY', 'activate' => true, 'created_by' => $this->login, 'updated_by' => $this->login]);
    }

    // Bin Loss (satu-satunya).
    public function loss(): WhLoc
    {
        return $this->single('LOSS');
    }

    // Bin In Transit (satu-satunya).
    public function transit(): WhLoc
    {
        return $this->single('TRANSIT');
    }

    // Bin subkon milik satu supplier: stok bahan yang sedang ada di tangan vendor itu.
    public function vendor(int $supplierId): WhLoc
    {
        $supplier = SupSupplier::query()->find($supplierId) ?? throw new RuntimeException("Supplier tidak ditemukan: #{$supplierId}");
        $zone = $this->zone('SUBCON');

        return $this->bin($zone, 'VIRT-SUB-'.$supplier->supplier_code, (string) $supplier->supplier_name, (string) $supplier->id_supplier);
    }

    // Semua area virtual + bin tunggalnya sekaligus (untuk layar / pemeriksaan).
    /** @return array{warehouse: WhWarehouse, loss: WhLoc, transit: WhLoc, subcon_zone: WhZone} */
    public function ensure(): array
    {
        return ['warehouse' => $this->warehouse(), 'loss' => $this->loss(), 'transit' => $this->transit(), 'subcon_zone' => $this->zone('SUBCON')];
    }

    private function single(string $type): WhLoc
    {
        $zone = $this->zone($type);

        return $this->bin($zone, self::WAREHOUSE_CODE.'-'.$zone->zone_code, $zone->zone_name, '0');
    }

    // Bin virtual: koordinat unik gudang (row = kode area, col = kunci pemilik, stack 0), sama dengan bin AREA biasa.
    private function bin(WhZone $zone, string $code, string $name, string $col): WhLoc
    {
        return WhLoc::query()->where('warehouse_id', $zone->warehouse_id)->where('row_code', $zone->zone_code)->where('col_code', $col)->where('stack_code', '0')->first()
            ?? WhLoc::create(['warehouse_id' => $zone->warehouse_id, 'zone_id' => $zone->id_zone, 'row_code' => $zone->zone_code, 'col_code' => $col, 'stack_code' => '0',
                'location_code' => mb_substr($code, 0, 60), 'location_name' => mb_substr($name, 0, 100), 'location_type' => $zone->zone_type, 'is_usable' => true, 'activate' => true,
                'created_by' => $this->login, 'updated_by' => $this->login]);
    }
}
