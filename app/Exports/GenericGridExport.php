<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

// Export .xlsx generik dipakai HasCustomizableGrid::exportGridExcel() --
// satu class dipakai ulang semua grid (headings + rows sudah disiapkan
// trait sesuai kolom yang lagi tampil), bukan bikin Export class baru
// per layar.
class GenericGridExport implements FromArray, WithHeadings
{
    public function __construct(
        protected array $rows,
        protected array $headings,
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }
}
