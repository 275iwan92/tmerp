<?php

namespace App\View\Components\Demo;

use Devlib\Components\Kanban;

// Contoh pola override (dipakai mockup nanti): turunan Kanban standar.
// PROPERTI ditimpa di sini (kolom & alur pabrik bawaan), METHOD ditimpa di kelas JS pasangannya (KanbanProduksi).
class KanbanProduksi extends Kanban
{
    public function __construct(array $cards = [], string $me = 'Saya', array $members = [])
    {
        parent::__construct(
            columns: [
                ['key' => 'queue', 'label' => 'Antrian', 'tone' => 'neutral'],
                ['key' => 'print', 'label' => 'Cetak', 'tone' => 'accent', 'limit' => 2],
                ['key' => 'finish', 'label' => 'Finishing', 'tone' => 'warn'],
                ['key' => 'qc', 'label' => 'QC', 'tone' => 'violet'],
                ['key' => 'done', 'label' => 'Selesai', 'tone' => 'success', 'done' => true],
            ],
            cards: $cards,
            transitions: ['queue' => ['print'], 'print' => ['finish'], 'finish' => ['qc'], 'qc' => ['done', 'finish'], 'done' => []],
            members: $members,
            addColumn: false,
            me: $me,
        );
    }

    public function tag(): string
    {
        return 'x-demo.kanban-produksi';
    }

    public function source(): string
    {
        return 'tm2prd.prd_wo_operation (contoh)';
    }

    public function jsClass(): ?string
    {
        return 'KanbanProduksi';
    }
}
