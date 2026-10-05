<?php

namespace MockupParts\Components\Inv;

use Devlib\Components\DevlibComponent;
use MockupParts\Src;

// Form khusus Track via QR / Barcode (user 2026-10-02): kotak pindai (x-dl::scan), lalu SATU hasil menurut kode yang dipindai:
//   none     = pesan tidak ditemukan            transfer = tombol buka dokumen Stock Transfer
//   pallet   = label palet + keterangan + riwayat palet (+ Move this Pallet, Relabel / Print, Show on Map)
//   bin      = label lokasi + berat / volume / status + isi bin (+ Show on Map, Label)
//   item     = di bin mana saja stoknya, palet berlabelnya, 15 pergerakan terakhir
// Layar hanya menyerahkan $desk (App\Support\V2\Data\Labels::trackDesk()). Kolom tiap tabel hasil ditetapkan di sini, jadi
// mengubahnya di objek ini berlaku untuk semua hasil lacak.
class ScanTrack extends DevlibComponent
{
    public function __construct(
        public array $desk = [],
    ) {}

    public function tag(): string
    {
        return 'x-mp::inv.scan-track';
    }

    public function type(): ?string
    {
        return $this->desk['result']['type'] ?? null;
    }

    public function palletColumns(array $tones): array
    {
        return [
            ['key' => 'at', 'label' => 'When', 'type' => 'date'],
            ['key' => 'action', 'label' => 'What', 'type' => 'badge', 'tones' => $tones],
            ['key' => 'before', 'label' => 'Qty Before', 'type' => 'number'],
            ['key' => 'after', 'label' => 'Qty After', 'type' => 'number'],
            ['key' => 'from', 'label' => 'From Bin'],
            ['key' => 'to', 'label' => 'To Bin'],
            ['key' => 'ref', 'label' => 'Document'],
            ['key' => 'note', 'label' => 'Note'],
            ['key' => 'by', 'label' => 'By'],
        ];
    }

    public function binColumns(): array
    {
        return [
            ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'class', 'label' => 'Class'],
            ['key' => 'qty', 'label' => 'On Hand', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'weight', 'label' => 'Weight (kg)', 'type' => 'number'],
            ['key' => 'pallets', 'label' => 'Pallets', 'type' => 'number'],
            ['key' => 'arrived', 'label' => 'Arrived', 'type' => 'date'],
            ['key' => 'expiry', 'label' => 'Expiry Date', 'type' => 'date'],
            ['key' => 'last', 'label' => 'Last Movement', 'type' => 'date'],
            ['key' => 'idle', 'label' => 'Idle (days)', 'type' => 'number'],
            ['key' => 'state', 'label' => 'Stock', 'type' => 'badge', 'tones' => ['Dead stock' => 'danger', 'Expired' => 'danger', 'Expires soon' => 'warn', 'OK' => 'success']],
        ];
    }

    public function positionColumns(): array
    {
        return [
            ['key' => 'bin', 'label' => 'Bin'],
            ['key' => 'qty', 'label' => 'On Hand', 'type' => 'number', 'sum' => true],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'weight', 'label' => 'Weight (kg)', 'type' => 'number', 'sum' => true],
            ['key' => 'arrived', 'label' => 'Arrived', 'type' => 'date'],
            ['key' => 'last', 'label' => 'Last Movement', 'type' => 'date'],
            ['key' => 'idle', 'label' => 'Idle (days)', 'type' => 'number'],
            ['key' => 'state', 'label' => 'Stock', 'type' => 'badge', 'tones' => ['Dead stock' => 'danger', 'OK' => 'success']],
        ];
    }

    public function itemPalletColumns(array $expiryTones): array
    {
        return [
            ['key' => 'no', 'label' => 'Pallet No.'],
            ['key' => 'bin', 'label' => 'Bin'],
            ['key' => 'qty', 'label' => 'Qty Now', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'arrived', 'label' => 'Arrived', 'type' => 'date'],
            ['key' => 'supplier_code', 'label' => 'Supplier Pallet Code'],
            ['key' => 'lot', 'label' => 'Lot No.'],
            ['key' => 'expiry', 'label' => 'Expiry Date', 'type' => 'date'],
            ['key' => 'expiry_state', 'label' => 'Expiry', 'type' => 'badge', 'tones' => $expiryTones],
        ];
    }

    public function moveColumns(): array
    {
        return [
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'doc_no', 'label' => 'Doc No.'],
            ['key' => 'move', 'label' => 'Movement', 'type' => 'badge', 'tones' => ['IN' => 'success', 'OUT' => 'danger', 'AD' => 'warn', 'TF' => 'info', 'BB' => 'neutral']],
            ['key' => 'warehouse', 'label' => 'Warehouse'],
            ['key' => 'bin', 'label' => 'Bin'],
            ['key' => 'in', 'label' => 'In', 'type' => 'number'],
            ['key' => 'out', 'label' => 'Out', 'type' => 'number'],
            ['key' => 'adj', 'label' => 'Adjustment', 'type' => 'number'],
            ['key' => 'balance', 'label' => 'Balance in Bin', 'type' => 'number'],
        ];
    }

    public function src(string ...$tables): ?string
    {
        return $tables ? Src::of(...$tables) : null;
    }

    public function render()
    {
        return view('mp::inv.scan-track');
    }
}
