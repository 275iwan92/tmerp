<?php

namespace App\Support\V2\Grid;

use App\Support\V2\Src;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

// Daftar dokumen di layar v2 yang punya form sendiri (Customer Order, Quotation, Delivery Order, ...): Grid Transactional
// yang barisnya sudah disiapkan kelas data layar itu (App\Support\V2\Data\*::rows(), termasuk aturan sembunyi harga).
// Turunan hanya mendeklarasikan slug, judul, modul, sumber, baris, kolom (+ detail / stats / tabs / filters); perilaku
// (Open = ?id=, + New Record = ?new=1, keluarga, tampilan) diwarisi dari sini, jadi mengubah induk mengubah semua daftar.
// Layar memanggilnya lewat view generik: @include('v2.screens._dbgrid', ['screen' => ['grid' => new XxxListGrid]]).
abstract class DocListGrid extends DbGrid
{
    abstract protected function listRows(): array;

    /** @return list<string> tabel sumber (koneksi.tabel) untuk tooltip Src */
    abstract protected function sources(): array;

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    // id x-dl::grid = kunci setelan kolom / density yang sudah disimpan user; turunan menimpa bila id lamanya berbeda.
    protected function listId(): string
    {
        return $this->slug().'-list';
    }

    public function rows(): array
    {
        return $this->listRows();
    }

    public function openUrl(): ?string
    {
        return $this->url().'?id={id}';
    }

    public function newUrl(): ?string
    {
        return $this->url().'?new=1';
    }

    public function props(): array
    {
        return ['id' => $this->listId()] + parent::props();
    }

    protected function sourceText(): string
    {
        return Src::of(...$this->sources());
    }

    protected function models(): array
    {
        return [];
    }

    protected function query(): Builder
    {
        throw new LogicException(static::class.' reads its rows from listRows(), not from a query.');
    }

    protected function row(Model $m): array
    {
        return [];
    }
}
