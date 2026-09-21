<?php

namespace App\Livewire\Concerns;

// Kotak statistik grid = FILTER DATA (ketentuan user 2026-09-21, BACKLOG §22.1).
// Klik kotak -> grid tersaring; klik kotak yang sama lagi -> filter lepas.
//
// Ditaruh di trait TERSENDIRI (bukan di HasCustomizableGrid) karena kotak
// statistik jauh lebih luas dipakai daripada fasilitas kustomisasi kolom:
// ~70 layar memakai x-grid.stat-row, cuma 1 yang memakai HasCustomizableGrid.
// Jadi trait ini sengaja TIDAK bergantung apa pun selain komponen Livewire.
//
// Kontrak yang diisi layar:
//   protected function statFilterDefs(): array
//   [
//       'active' => [
//           'label' => 'Employee Aktif',
//           'value' => $this->metrics['active'],   // angka SELURUH data, bukan hasil saringan
//           'tone'  => 'active',                   // palet x-grid.stat-box
//           'subtitle' => null,                    // opsional
//           'filter' => fn ($q) => $q->where('activate', true),
//       ],
//       'nilai_rp' => ['label' => 'Total Nilai', 'value' => 'Rp 1,2 M'],  // TANPA 'filter'
//   ]
//
// Kotak TANPA kunci 'filter' = angka agregat (rupiah, rata-rata, jumlah hari)
// yang tidak mewakili kategori baris -- sengaja dibiarkan tidak bisa diklik,
// JANGAN dikarang kategorinya supaya semua kotak bisa diklik.
//
// Angka di kotak WAJIB dihitung dari keseluruhan data (query dasar + search),
// bukan dari query yang sudah kena applyStatFilter() -- kalau tidak, angkanya
// berubah sendiri begitu diklik dan membingungkan.
//
// Cara pakai di layar:
//   use HasGridStatFilter;                                 // di class komponen
//   protected function statFilterDefs(): array { ... }
//   $query = $this->applyStatFilter($this->baseQuery());   // di computed data
//   <x-grid.stat-filter-row :boxes="$this->statBoxes()" :active="$statFilter" />
trait HasGridStatFilter
{
    public string $statFilter = '';

    protected function statFilterDefs(): array
    {
        return [];
    }

    public function toggleStatFilter(string $key): void
    {
        $defs = $this->statFilterDefs();

        // Kotak agregat (tanpa 'filter') tidak bisa dipakai menyaring -- kalau
        // dipanggil paksa dari luar, diamkan saja.
        if (! isset($defs[$key]['filter'])) {
            return;
        }

        $this->statFilter = $this->statFilter === $key ? '' : $key;
        $this->resetStatFilterPage();
    }

    public function clearStatFilter(): void
    {
        $this->statFilter = '';
        $this->resetStatFilterPage();
    }

    // Saringan baru berarti isi grid berubah total -- balik ke halaman 1,
    // kalau tidak user bisa mendarat di halaman kosong.
    protected function resetStatFilterPage(): void
    {
        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    // Query builder: closure-nya cukup memanggil ->where(...) (builder mutable,
    // nilai balik boleh null). Collection: closure WAJIB mengembalikan hasil
    // ->filter(...) karena Collection immutable -- nilai balik itu yang dipakai.
    protected function applyStatFilter($query)
    {
        $filter = $this->statFilterDefs()[$this->statFilter]['filter'] ?? null;

        if (! $filter) {
            return $query;
        }

        return $filter($query) ?? $query;
    }

    // Bentuk siap-render buat x-grid.stat-filter-row.
    public function statBoxes(): array
    {
        $boxes = [];

        foreach ($this->statFilterDefs() as $key => $def) {
            $boxes[$key] = $def + [
                'tone' => 'total',
                'subtitle' => null,
            ];
            $boxes[$key]['filterable'] = isset($def['filter']);
            $boxes[$key]['selected'] = $this->statFilter === $key;
            unset($boxes[$key]['filter']);
        }

        return $boxes;
    }
}
