<?php

/*
 * REMARK
 * Tujuan       : Grid lookup kecil = Sheet mode lookup; meneruskan saveUrl, reorderUrl, reasonOnSave, links, autoSave, addLabel, deleteUrl/deleteLabel, resetUrl/resetLabel, searchable, showCount.
 * Sumber hulu  : Turunan Sheet.
 * Input        : Sama dengan Sheet.
 * Output       : Sama dengan Sheet.
 * Dipakai oleh : Form WO (BOP dan BOM), grid bantu lain.
 * Status       : Aktif (DevLib).
 */

namespace Devlib\Components;

// Grid Lookup (Grid Pre-Entry): daftar bantu kecil yang jarang berubah (Agama, Golongan Darah, ...), diisi langsung di sel.
// = Sheet mode lookup: Cari + Baris + Undo + Save, rapat, tanpa Hapus; kolom 'unique' => true tidak boleh kembar.
class LookupGrid extends Sheet
{
    public function __construct(
        array $columns = [],
        array $rows = [],
        string $rowKey = 'id',
        string $title = '',
        ?string $source = null,
        ?string $saveUrl = null,
        array $stats = [],
        bool $addRows = true,
        ?string $reorderUrl = null,
        ?string $reasonOnSave = null,
        array $links = [],
        bool $autoSave = false,
        ?string $addLabel = null,
        ?string $deleteUrl = null,
        bool $searchable = true,
        bool $showCount = true,
        ?string $deleteLabel = null,
        ?string $resetUrl = null,
        ?string $resetLabel = null,
    ) {
        parent::__construct($columns, $rows, $rowKey, $title, $source, lookup: true, saveUrl: $saveUrl, stats: $stats, addRows: $addRows, reorderUrl: $reorderUrl, reasonOnSave: $reasonOnSave, links: $links, autoSave: $autoSave, addLabel: $addLabel, deleteUrl: $deleteUrl,
            searchable: $searchable, showCount: $showCount, deleteLabel: $deleteLabel, resetUrl: $resetUrl, resetLabel: $resetLabel);
    }

    public function tag(): string
    {
        return 'x-dl::lookup-grid';
    }

    public function source(): string
    {
        return $this->source ?? 'tabel lookup (daftar bantu)';
    }
}
