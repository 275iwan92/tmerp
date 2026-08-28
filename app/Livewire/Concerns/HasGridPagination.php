<?php

namespace App\Livewire\Concerns;

// Dipecah dari HasCustomizableGrid (2026-08-27) supaya pagination-footer bisa
// dipakai SEMUA grid, bukan cuma yang ikut fasilitas kustomisasi kolom/sort/
// export (itu tetap opsional per griduistandard.md §10). Komponen pemakai
// tinggal `use HasGridPagination;` + panggil `->paginate($this->perPage)` di
// computed property datanya, lalu render `@include('components.grid.pagination-footer',
// ['paginator' => $this->xxx])`.
trait HasGridPagination
{
    public int $perPage = 25;

    public function setPerPage(int $n): void
    {
        $this->perPage = in_array($n, [10, 25, 50, 100], true) ? $n : 25;
        $this->resetPage();
    }

    protected function gridPageRange($paginator, int $window = 5): array
    {
        $last = max(1, $paginator->lastPage());
        $current = $paginator->currentPage();
        $start = max(1, min($current - intdiv($window, 2), $last - $window + 1));
        $end = min($last, $start + $window - 1);
        $start = max(1, $end - $window + 1);

        return range($start, $end);
    }
}
