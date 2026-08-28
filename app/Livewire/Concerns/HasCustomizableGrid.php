<?php

namespace App\Livewire\Concerns;

use App\Exports\GenericGridExport;
use App\Models\Sys\SysGridPreference;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Fasilitas grid custom view/sort/group-by/export (keputusan user 2026-08-27,
// redesain 2026-08-27 setelah user tunjukkan referensi ScriptCase --
// dual-listbox Available/Selected, tombol berlabel Kolom/Sort/Export
// terpisah, bukan 1 ikon serba-guna). Lihat griduistandard.md §10.
//
// Kontrak yang WAJIB diimplementasikan komponen pemakai:
// - gridKey(): string                 -- identitas unik grid ini
// - gridColumnDefs(): array           -- ['col_key' => ['label' => .., 'sortable' => bool]]
// - gridDefaultColumns(): array       -- subset key dari gridColumnDefs(), urutan default (~5)
// - gridBaseQuery(): Builder          -- query dasar (eager-load dkk), TANPA search/sort/paginate
// - columnValue($row, $key): string   -- format tampil 1 sel (dipakai grid & export)
//
// Kontrak OPSIONAL (override kalau perlu, ada default di trait):
// - gridIdentitySearchColumns(): array -- ['col_key' => 'Label'] kolom identitas (nama+kode)
//   yang selalu bisa dicari tapi BUKAN bagian gridColumnDefs() (krn identitas tak pernah
//   disembunyikan, §3 griduistandard.md). Dukung dot-notation relasi bertingkat
//   (mis. 'quotation.customer.customer_name' -> whereHas('quotation.customer', ...)).
trait HasCustomizableGrid
{
    use HasGridPagination;

    public array $gridVisibleColumns = [];

    public ?string $gridSortColumn = null;

    public ?string $gridSortDirection = null; // 'asc' | 'desc' | null

    public ?string $gridGroupBy = null;

    // Search fleksibel (keputusan user 2026-08-27, referensi ScriptCase):
    // dropdown ringan nempel di search box (BUKAN modal spt Kolom/Sort/Export
    // -- cukup kecil, cukup 2 pengaturan) berisi pilihan kolom yang dicari +
    // kondisi (Contains/Starts with/Equal/Not Contains).
    public string $search = '';

    public array $searchColumns = []; // kosong = cari di SEMUA kolom searchable

    public string $searchCondition = 'contains'; // contains | starts_with | equal | not_contains

    public bool $showSearchDropdown = false;

    // perPage/setPerPage/gridPageRange() sekarang di HasGridPagination (dipakai
    // via `use HasGridPagination;` di atas) -- dipecah 2026-08-27 supaya
    // pagination-footer bisa dipakai grid yang TIDAK ikut fasilitas kustomisasi
    // kolom/sort/export ini juga.

    // Checkbox multi-select utk hapus massal (keputusan user 2026-08-27,
    // mengaktifkan lagi kemampuan yang SEBELUMNYA sengaja dihapus dari
    // griduistandard.md §4 -- kali ini DIBATASI: checkbox cuma valid utk
    // baris yang BELUM confirmed/final, syarat sama dgn ikon Confirm yang
    // sudah ada. Komponen pemakai yang menilai baris mana boleh dicentang
    // (lewat markup Blade, bukan trait -- beda grid beda kolom status).
    public array $selectedRows = [];

    public function toggleRowSelection(int|string $id): void
    {
        if (in_array($id, $this->selectedRows, true)) {
            $this->selectedRows = array_values(array_diff($this->selectedRows, [$id]));
        } else {
            $this->selectedRows[] = $id;
        }
    }

    public function clearSelection(): void
    {
        $this->selectedRows = [];
    }

    public array $searchModalColumns = [];

    public string $searchModalCondition = 'contains';

    // 3 modal terpisah (bukan 1 panel serba-guna) -- pola ScriptCase: tombol
    // "Kolom"/"Sort"/"Export" masing-masing buka modalnya sendiri.
    public bool $showColumnModal = false;

    public bool $showSortModal = false;

    public bool $showExportModal = false;

    // State kerja SEMENTARA di dalam modal Kolom -- dipisah dari
    // gridVisibleColumns supaya "Batal" beneran batal, bukan langsung apply
    // tiap klik (beda dari desain checkbox lama).
    public array $columnModalSelected = [];

    public string $columnModalSearch = '';

    public ?string $sortModalColumn = null;

    public ?string $sortModalDirection = 'asc';

    // Kolom export BOLEH beda dari kolom yang lagi tampil di grid (pola
    // ScriptCase: export punya pemilihan kolom sendiri, default ikut yang
    // lagi tampil tapi bisa diubah).
    public array $exportColumns = [];

    public string $exportFormat = 'excel'; // excel | csv | pdf

    abstract protected function gridKey(): string;

    abstract protected function gridColumnDefs(): array;

    abstract protected function gridDefaultColumns(): array;

    abstract protected function gridBaseQuery();

    public function mountHasCustomizableGrid(): void
    {
        $this->loadGridPreference();
    }

    protected function loadGridPreference(): void
    {
        $pref = SysGridPreference::where('user_login', Auth::user()->login)
            ->where('grid_key', $this->gridKey())
            ->first();

        if ($pref) {
            $valid = array_keys($this->gridColumnDefs());
            $this->gridVisibleColumns = array_values(array_intersect($pref->visible_columns, $valid));
            $this->gridSortColumn = in_array($pref->sort_column, $valid, true) ? $pref->sort_column : null;
            $this->gridSortDirection = $this->gridSortColumn ? $pref->sort_direction : null;
            $this->gridGroupBy = in_array($pref->group_by_column, $this->gridVisibleColumns, true) ? $pref->group_by_column : null;
        }

        if (empty($this->gridVisibleColumns)) {
            $this->gridVisibleColumns = $this->gridDefaultColumns();
        }
    }

    protected function saveGridPreference(): void
    {
        SysGridPreference::updateOrCreate(
            ['user_login' => Auth::user()->login, 'grid_key' => $this->gridKey()],
            [
                'visible_columns' => array_values($this->gridVisibleColumns),
                'sort_column' => $this->gridSortColumn,
                'sort_direction' => $this->gridSortDirection,
                'group_by_column' => $this->gridGroupBy,
            ]
        );
    }

    // ============== MODAL: KOLOM (dual-listbox) ==============
    public function openColumnModal(): void
    {
        $this->columnModalSelected = $this->gridVisibleColumns;
        $this->columnModalSearch = '';
        $this->showColumnModal = true;
    }

    public function addColumnToSelection(string $key): void
    {
        if (! in_array($key, $this->columnModalSelected, true) && array_key_exists($key, $this->gridColumnDefs())) {
            $this->columnModalSelected[] = $key;
        }
    }

    public function removeColumnFromSelection(string $key): void
    {
        if (count($this->columnModalSelected) <= 1) {
            return; // minimal 1 kolom kustom wajib tetap tampil
        }

        $this->columnModalSelected = array_values(array_diff($this->columnModalSelected, [$key]));
    }

    public function addAllColumns(): void
    {
        $this->columnModalSelected = array_keys($this->gridColumnDefs());
    }

    public function removeAllColumns(): void
    {
        // Sisakan 1 -- kolom pertama yang terdaftar -- daripada grid kosong total.
        $first = array_key_first($this->gridColumnDefs());
        $this->columnModalSelected = $first ? [$first] : [];
    }

    public function applyColumnSelection(): void
    {
        $this->gridVisibleColumns = $this->columnModalSelected;

        if ($this->gridGroupBy && ! in_array($this->gridGroupBy, $this->gridVisibleColumns, true)) {
            $this->gridGroupBy = null;
        }

        if ($this->gridSortColumn && ! in_array($this->gridSortColumn, $this->gridVisibleColumns, true)) {
            $this->gridSortColumn = null;
            $this->gridSortDirection = null;
        }

        $this->saveGridPreference();
        $this->showColumnModal = false;
    }

    public function setGridGroupBy(?string $key): void
    {
        if ($key !== null && ! in_array($key, $this->columnModalSelected, true)) {
            return;
        }

        $this->gridGroupBy = $key ?: null;
    }

    // ============== MODAL: SORT ==============
    public function openSortModal(): void
    {
        $this->sortModalColumn = $this->gridSortColumn;
        $this->sortModalDirection = $this->gridSortDirection ?? 'asc';
        $this->showSortModal = true;
    }

    public function applySortSelection(): void
    {
        $this->gridSortColumn = $this->sortModalColumn;
        $this->gridSortDirection = $this->sortModalColumn ? $this->sortModalDirection : null;
        $this->saveGridPreference();
        $this->showSortModal = false;
    }

    public function clearSort(): void
    {
        $this->sortModalColumn = null;
        $this->sortModalDirection = 'asc';
    }

    // Shortcut klik langsung di judul kolom (toggle 3-arah: asc -> desc -> none)
    // -- tetap ada di samping modal Sort, cara cepat tanpa buka modal.
    public function toggleGridSort(string $key): void
    {
        $defs = $this->gridColumnDefs();

        if (! in_array($key, $this->gridVisibleColumns, true) || ($defs[$key]['sortable'] ?? true) === false) {
            return;
        }

        if ($this->gridSortColumn !== $key) {
            $this->gridSortColumn = $key;
            $this->gridSortDirection = 'asc';
        } elseif ($this->gridSortDirection === 'asc') {
            $this->gridSortDirection = 'desc';
        } else {
            $this->gridSortColumn = null;
            $this->gridSortDirection = null;
        }

        $this->saveGridPreference();
    }

    public function resetGridPreference(): void
    {
        $this->gridVisibleColumns = $this->gridDefaultColumns();
        $this->gridSortColumn = null;
        $this->gridSortDirection = null;
        $this->gridGroupBy = null;
        $this->saveGridPreference();
    }

    // ============== SEARCH (dropdown ringan, bukan modal) ==============
    protected function gridIdentitySearchColumns(): array
    {
        return [];
    }

    protected function gridSearchColumnDefs(): array
    {
        $identity = $this->gridIdentitySearchColumns();
        $customizable = collect($this->gridColumnDefs())
            ->filter(fn ($def) => ($def['searchable'] ?? true) !== false)
            ->map(fn ($def) => $def['label'])
            ->all();

        return $identity + $customizable;
    }

    public function toggleSearchDropdown(): void
    {
        if (! $this->showSearchDropdown) {
            $this->searchModalColumns = $this->searchColumns;
            $this->searchModalCondition = $this->searchCondition;
        }

        $this->showSearchDropdown = ! $this->showSearchDropdown;
    }

    public function toggleSearchColumn(string $key): void
    {
        if (in_array($key, $this->searchModalColumns, true)) {
            $this->searchModalColumns = array_values(array_diff($this->searchModalColumns, [$key]));
        } else {
            $this->searchModalColumns[] = $key;
        }
    }

    public function applySearchSettings(): void
    {
        $this->searchColumns = $this->searchModalColumns;
        $this->searchCondition = $this->searchModalCondition;
        $this->showSearchDropdown = false;
    }

    public function resetSearchSettings(): void
    {
        $this->searchModalColumns = [];
        $this->searchModalCondition = 'contains';
    }

    // Kolom relasi yg MELINTAS DATABASE (mis. tm2sdi co_order -> tm2sdi
    // quo_quotation -> tm2mst cust_customer) TIDAK BISA dicari via
    // whereHas() bertingkat -- Eloquent membangun 1 subquery EXISTS
    // bersarang di KONEKSI YANG SAMA dgn query induk, jadi error "table
    // doesn't exist" begitu subquery-nya nyentuh tabel di DB lain (ketemu
    // nyata 2026-08-27, CO cari nama customer). Sesuai CLAUDE.md §2 ("query
    // terpisah, cocokkan di PHP"), grid yang punya kolom searchable begini
    // WAJIB override method ini: ['dotted.key' => fn(string $operator, string
    // $pattern): array daftar PK grid induk yang cocok].
    protected function gridCrossDbSearchResolvers(): array
    {
        return [];
    }

    protected function gridSearchedQuery()
    {
        $query = $this->gridBaseQuery();
        $term = trim($this->search);

        if ($term === '') {
            return $query;
        }

        $columns = ! empty($this->searchColumns) ? $this->searchColumns : array_keys($this->gridSearchColumnDefs());

        if (empty($columns)) {
            return $query;
        }

        $resolvers = $this->gridCrossDbSearchResolvers();

        if ($this->searchCondition === 'not_contains') {
            // "Tidak mengandung" = TAK SATUPUN kolom terpilih cocok (AND semua
            // negasi). Kolom relasi (dot-notation, termasuk cross-DB resolver)
            // dilewati dari NOT-match -- semantik AND-negasi lintas-relasi
            // rumit dipadukan, dampaknya kecil (baru sedikit grid punya kolom
            // relasi searchable).
            foreach ($columns as $key) {
                if (str_contains($key, '.')) {
                    continue;
                }
                $query->where($key, 'not like', "%{$term}%");
            }

            return $query;
        }

        // "Sama Dengan" (=) BAHAYA dipakai polos ke kolom numerik/boolean --
        // MySQL diam-diam meng-cast string non-numerik ke 0 utk perbandingan
        // (mis. `stat_confirm = 'ANIMAL GLUE'` jadi `stat_confirm = 0`,
        // cocok ke SEMUA baris draft!). Ketemu nyata 2026-08-27 (search
        // "Equal" balikin 5894 baris alih-alih 1). CAST AS CHAR paksa
        // perbandingan string, aman utk semua tipe kolom.
        [$operator, $pattern] = match ($this->searchCondition) {
            'starts_with' => ['like', "{$term}%"],
            'equal' => ['cast_equal', $term],
            default => ['like', "%{$term}%"],
        };

        $query->where(function ($q) use ($columns, $operator, $pattern, $resolvers) {
            foreach ($columns as $key) {
                if (isset($resolvers[$key])) {
                    $sqlOperator = $operator === 'cast_equal' ? '=' : $operator;
                    $ids = $resolvers[$key]($sqlOperator, $pattern);
                    $pk = $this->gridBaseQuery()->getModel()->getKeyName();
                    $q->orWhereIn($pk, $ids);
                } elseif (str_contains($key, '.')) {
                    $relation = substr($key, 0, strrpos($key, '.'));
                    $col = substr($key, strrpos($key, '.') + 1);
                    $q->orWhereHas($relation, function ($r) use ($col, $operator, $pattern) {
                        $operator === 'cast_equal'
                            ? $r->whereRaw("CAST(`{$col}` AS CHAR) = ?", [$pattern])
                            : $r->where($col, $operator, $pattern);
                    });
                } elseif ($operator === 'cast_equal') {
                    $q->orWhereRaw("CAST(`{$key}` AS CHAR) = ?", [$pattern]);
                } else {
                    $q->orWhere($key, $operator, $pattern);
                }
            }
        });

        return $query;
    }

    protected function gridSortedQuery()
    {
        $query = $this->gridSearchedQuery();

        if ($this->gridSortColumn && $this->gridSortDirection) {
            $query->orderBy($this->gridSortColumn, $this->gridSortDirection);
        }

        return $query;
    }

    // ============== MODAL: EXPORT ==============
    public function openExportModal(): void
    {
        $this->exportColumns = $this->gridVisibleColumns;
        $this->exportFormat = 'excel';
        $this->showExportModal = true;
    }

    public function addExportColumn(string $key): void
    {
        if (! in_array($key, $this->exportColumns, true) && array_key_exists($key, $this->gridColumnDefs())) {
            $this->exportColumns[] = $key;
        }
    }

    public function removeExportColumn(string $key): void
    {
        if (count($this->exportColumns) <= 1) {
            return;
        }

        $this->exportColumns = array_values(array_diff($this->exportColumns, [$key]));
    }

    public function addAllExportColumns(): void
    {
        $this->exportColumns = array_keys($this->gridColumnDefs());
    }

    public function removeAllExportColumns(): void
    {
        $first = array_key_first($this->gridColumnDefs());
        $this->exportColumns = $first ? [$first] : [];
    }

    protected function gridExportRows(array $columns, ?int $limit = null): array
    {
        $defs = $this->gridColumnDefs();
        $headings = array_map(fn ($key) => $defs[$key]['label'] ?? $key, $columns);

        $query = $this->gridSortedQuery();

        if ($limit) {
            $query->limit($limit);
        }

        $rows = $query->get()->map(function ($row) use ($columns) {
            return array_map(fn ($key) => $this->sanitizeExportValue($this->columnValue($row, $key)), $columns);
        })->all();

        return [$headings, $rows];
    }

    // Data lama hasil migrasi kadang menyimpan byte non-UTF-8 (mis. Windows-1252)
    // di kolom teks bebas (brand, notes, dst) -- lolos di render HTML biasa
    // (browser lebih toleran) tapi bikin dompdf gagal total dengan "Malformed
    // UTF-8". iconv //IGNORE buang byte yg tak valid, bukan bikin error.
    protected function sanitizeExportValue(string $value): string
    {
        return iconv('UTF-8', 'UTF-8//IGNORE', $value) ?: $value;
    }

    public function runExport()
    {
        $this->showExportModal = false;

        return match ($this->exportFormat) {
            'csv' => $this->exportGridCsv($this->exportColumns),
            'pdf' => $this->exportGridPdf($this->exportColumns),
            default => $this->exportGridExcel($this->exportColumns),
        };
    }

    public function exportGridCsv(?array $columns = null): StreamedResponse
    {
        [$headings, $rows] = $this->gridExportRows($columns ?? $this->gridVisibleColumns);

        return response()->streamDownload(function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headings, escape: '\\');
            foreach ($rows as $row) {
                fputcsv($out, $row, escape: '\\');
            }
            fclose($out);
        }, $this->gridKey().'.csv');
    }

    public function exportGridExcel(?array $columns = null)
    {
        [$headings, $rows] = $this->gridExportRows($columns ?? $this->gridVisibleColumns);

        return Excel::download(new GenericGridExport($rows, $headings), $this->gridKey().'.xlsx');
    }

    // dompdf boros memori utk tabel besar -- 2000 baris masih OOM di 512MB
    // (mesin layout-nya sendiri, bukan cuma soal load query). PDF secara
    // desain memang bukan format yang cocok utk dump ribuan baris (beda
    // dari CSV/Excel yang streaming/native tabular). 500 dipilih empiris,
    // cukup realistis utk kasus "cetak/kirim PDF ringkas" (bukan dump massal)
    // -- ditampilkan peringatan di PDF-nya kalau terpotong, bukan fatal
    // error diam-diam.
    private const PDF_ROW_LIMIT = 200;

    public function exportGridPdf(?array $columns = null)
    {
        ini_set('memory_limit', '512M');

        // Ambil 1 lebih dari limit di level QUERY (bukan fetch semua lalu
        // potong di PHP) -- 5894 baris mat_raw cukup besar utk boros
        // walau cuma dipakai ngecek "ada lebih tidak", biar hemat dari sisi
        // query juga, bukan cuma dompdf-nya.
        [$headings, $rows] = $this->gridExportRows($columns ?? $this->gridVisibleColumns, self::PDF_ROW_LIMIT + 1);

        $truncated = count($rows) > self::PDF_ROW_LIMIT;
        $rows = array_slice($rows, 0, self::PDF_ROW_LIMIT);

        $pdf = Pdf::loadView('exports.generic-grid-pdf', [
            'title' => class_basename($this).' — '.$this->gridKey(),
            'headings' => $headings,
            'rows' => $rows,
            'truncated' => $truncated,
            'rowLimit' => self::PDF_ROW_LIMIT,
        ])->setPaper('a4', count($headings) > 6 ? 'landscape' : 'portrait');

        return $pdf->download($this->gridKey().'.pdf');
    }
}
