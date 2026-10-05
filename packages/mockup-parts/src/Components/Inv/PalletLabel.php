<?php

namespace MockupParts\Components\Inv;

use Devlib\Components\DevlibComponent;
use MockupParts\Src;

// Form khusus Print Pallet Label (user 2026-10-02 / 2026-10-03): setiap palet yang datang wajib berlabel, dan setiap habis dipakai
// dilabel ulang dengan sisanya (0 = palet ditutup). Susunan tetap:
//   1. pengantar penerimaan barang ATAU pemilih gedung          2. panduan warna label (bulan datang)
//   3. satu posisi stok yang dibuka: New Pallets (Create & Print), Pallets of this Stock, Relabel after Use, Print Again
//   4. daftar pekerjaan label (Receipt Lines / Pallet Label Work)  5. Pallets in this Building
// Layar hanya menyerahkan $desk (App\Support\V2\Data\Pallets::labelDesk()). Palet tidak mengubah stok; aturan label, sisa dan
// pindah tumpukan = App\Services\Inventory\PalletService. Kolom tiap tabel ditetapkan di sini.
class PalletLabel extends DevlibComponent
{
    public function __construct(
        public array $desk = [],
    ) {}

    public function tag(): string
    {
        return 'x-mp::inv.pallet-label';
    }

    public function receipt(): bool
    {
        return (bool) ($this->desk['receipt'] ?? false);
    }

    public function newPalletColumns(string $codeHint): array
    {
        return [
            ['key' => 'qty', 'label' => 'Qty on the Pallet', 'type' => 'number', 'required' => true, 'sum' => true, 'width' => '160px'],
            ['key' => 'supplier_pallet_code', 'label' => 'Supplier Pallet Code', 'type' => 'text', 'width' => '220px', 'placeholder' => $codeHint],
            ['key' => 'lot_no', 'label' => 'Lot No.', 'type' => 'text', 'width' => '180px'],
            ['key' => 'expiry_date', 'label' => 'Expiry Date', 'type' => 'date', 'width' => '170px'],
        ];
    }

    public function stockPalletColumns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Pallet / Pack No.'],
            ['key' => 'stack', 'label' => 'Stack No.', 'type' => 'number'],
            ['key' => 'kind', 'label' => 'Kind'],
            ['key' => 'qty', 'label' => 'Qty Now', 'type' => 'number', 'sum' => true],
            ['key' => 'initial', 'label' => 'Qty at Arrival', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'arrived', 'label' => 'Arrived', 'type' => 'date'],
            ['key' => 'supplier_code', 'label' => 'Supplier Pallet Code'],
            ['key' => 'lot', 'label' => 'Lot No.'],
            ['key' => 'expiry', 'label' => 'Expiry Date', 'type' => 'date'],
            ['key' => 'expiry_state', 'label' => 'Expiry', 'type' => 'badge', 'tones' => $this->desk['expiryTones'] ?? []],
            ['key' => 'labels', 'label' => 'Labels Printed', 'type' => 'number'],
        ];
    }

    // Daftar pekerjaan label; kolom Received hanya saat dibuka dari penerimaan barang.
    public function todoColumns(): array
    {
        return array_values(array_filter([
            ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'bin', 'label' => 'Bin'],
            $this->receipt() ? ['key' => 'received', 'label' => 'Received', 'type' => 'number'] : null,
            ['key' => 'onhand', 'label' => 'On Hand in Bin', 'type' => 'number'],
            ['key' => 'labelled', 'label' => 'On Pallet Labels', 'type' => 'number'],
            ['key' => 'diff', 'label' => 'Difference', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'pallets', 'label' => 'Pallets', 'type' => 'number'],
            ['key' => 'arrived', 'label' => 'Arrived', 'type' => 'date'],
            ['key' => 'need', 'label' => 'To Do', 'type' => 'badge', 'tones' => $this->desk['todo']['needTones'] ?? []],
        ]));
    }

    public function buildingPalletColumns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Pallet No. / Item', 'type' => 'doc', 'party' => 'item'],
            ['key' => 'bin', 'label' => 'Bin'],
            ['key' => 'stack', 'label' => 'Stack No.', 'type' => 'number'],
            ['key' => 'kind', 'label' => 'Kind'],
            ['key' => 'qty', 'label' => 'Qty Now', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'arrived', 'label' => 'Arrived', 'type' => 'date'],
            ['key' => 'supplier_code', 'label' => 'Supplier Pallet Code'],
            ['key' => 'lot', 'label' => 'Lot No.'],
            ['key' => 'expiry', 'label' => 'Expiry Date', 'type' => 'date'],
            ['key' => 'expiry_state', 'label' => 'Expiry', 'type' => 'badge', 'tones' => $this->desk['expiryTones'] ?? []],
        ];
    }

    public function src(string ...$tables): ?string
    {
        return $tables ? Src::of(...$tables) : null;
    }

    public function render()
    {
        return view('mp::inv.pallet-label');
    }
}
