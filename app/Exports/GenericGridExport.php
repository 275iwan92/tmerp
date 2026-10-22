<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

// Export .xlsx generik (headings + rows sudah disiapkan pemanggil), satu class dipakai ulang oleh rute export v2, bukan Export class baru per layar.
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
