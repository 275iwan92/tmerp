<?php

namespace App\Support\V2\Grid\Master;

use App\Support\V2\Grid\DbLookup;

// Induk Grid Lookup modul Master Data (Satuan, Currency, Kurs, Kategori Supplier): hak akses MDATA, diisi langsung di sel.
// Kolom kode memakai 'lockSaved' => kode yang sudah tersimpan tidak bisa diganti (sudah dirujuk data lain), cukup dinonaktifkan.
abstract class MasterDataLookup extends DbLookup
{
    public function module(): string
    {
        return 'MDATA';
    }

    public function moduleLabel(): string
    {
        return 'Master Data';
    }

    // Kode master selalu huruf besar tanpa spasi tepi (sama dengan layar v1).
    protected function upper(array $in, string ...$keys): array
    {
        foreach ($keys as $k) {
            if (isset($in[$k]) && is_string($in[$k])) {
                $in[$k] = strtoupper(trim($in[$k]));
            }
        }

        return $in;
    }
}
