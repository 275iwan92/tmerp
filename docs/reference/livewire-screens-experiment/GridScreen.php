<?php

namespace App\Livewire\Screens;

use App\Livewire\Concerns\HasGridPagination;
use App\Livewire\Concerns\HasGridStatFilter;
use App\Support\Localization\Translator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

// Induk layar grid worklist/master (ala ScriptCase "Grid application"). Layar
// anak hanya mendeklarasikan query, kolom, kotak statistik, pencarian & tujuan
// buka baris; tampilannya satu: resources/views/screens/grid.blade.php
// (view anak cukup `@include('screens.grid')`). Ubah di sini/di view itu =
// semua grid turunan ikut. Standar yang dijalankan: griduistandard.md §2b-§6.
abstract class GridScreen extends Component
{
    use HasGridPagination;
    use HasGridStatFilter;
    use WithPagination;

    public string $search = '';

    abstract protected function query(): Builder;

    /** @return Column[] */
    abstract protected function columns(): array;

    protected function applySearch(Builder $query, string $term): void {}

    // null = baris tidak bisa dibuka (tanpa klik 2x & tanpa tombol Buka).
    protected function recordUrl(int|string $id): ?string
    {
        return null;
    }

    // Ekspresi wire:click tombol "+ New Record"; null = tanpa tombol.
    protected function newRecordAction(): ?string
    {
        return null;
    }

    protected function searchPlaceholder(): string
    {
        return Translator::t('shared_grid_search_placeholder', 'Cari…');
    }

    protected function emptyMessage(): string
    {
        return Translator::t('shared_grid_empty', 'Belum ada data.');
    }

    // Partial tambahan di bawah grid (popup picker, dsb), melihat $this.
    protected function extraViews(): array
    {
        return [];
    }

    protected function applyOrder(Builder $query): void
    {
        $query->orderByDesc($query->getModel()->getKeyName());
    }

    #[Computed]
    public function rows()
    {
        $query = $this->query();
        $term = trim($this->search);

        if ($term !== '') {
            $query->where(fn ($q) => $this->applySearch($q, $term));
        }

        $this->applyStatFilter($query);
        $this->applyOrder($query);

        return $query->paginate($this->perPage);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openRecord(int|string $id)
    {
        $url = $this->recordUrl($id);

        return $url ? $this->redirect($url) : null;
    }
}
