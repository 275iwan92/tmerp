<?php

namespace App\Livewire\Concerns;

// Layar v1 dibuka dari Grid DB v2 (App\Support\V2\Grid\DbGrid): ?open={id} = langsung ke form baris itu,
// ?new=1 = form baru. Layar yang nama metodenya lain cukup menimpa openFromQuery()/newFromQuery().
trait OpensFromQuery
{
    // Layar daftar tanpa form per baris (mis. approval): ?open={id} menyaring daftar ke baris itu saja.
    public ?int $focusId = null;

    public function mountOpensFromQuery(): void
    {
        if ($id = (int) request()->query('open')) {
            $this->openFromQuery($id);
        } elseif (request()->query('new')) {
            $this->newFromQuery();
        }
    }

    protected function openFromQuery(int $id): void
    {
        method_exists($this, 'openEdit') ? $this->openEdit($id) : $this->focusId = $id;
    }

    protected function newFromQuery(): void
    {
        $this->openNew();
    }
}
