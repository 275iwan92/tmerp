<?php

namespace App\Livewire\Concerns;

use Illuminate\Validation\ValidationException;

// Fasilitas grid spreadsheet (adopsi perilaku "AutoFill" DataTables.net --
// klik sel jadi editable, drag titik pojok sel utk copy nilai ke bawah, baris
// baru langsung dari tabel) -- keputusan user 2026-08-30, DIBATASI hanya utk
// master kecil/sederhana (kolom scalar, tanpa approval/snapshot), lihat
// docs/spreadsheetgridstandard.md. BUKAN pengganti griduistandard.md untuk
// grid Master biasa -- pola terpisah, opt-in per grid.
//
// Beda sengaja dari HasCustomizableGrid: SEMUA baris dirender sekaligus (tanpa
// pagination -- spreadsheet-edit tak masuk akal kalau baris terpotong), dan
// SEMUA render sel/edit terjadi di client (Alpine, resources/js/spreadsheet-grid.js)
// -- server cuma dipanggil 1x saat "Simpan Semua" (mode batch, bukan autosave
// per sel -- keputusan user 2026-08-30, alasan: lebih sedikit request & ada
// jeda konfirmasi sebelum masuk DB).
//
// Kontrak yang WAJIB diimplementasikan komponen pemakai:
// - spreadsheetKey(): string                              -- identitas unik grid ini
// - spreadsheetColumns(): array                            -- ['col_key' => ['label'=>.., 'type'=>'text'|'number'|'boolean'|'select', 'options'=>[] (utk select), 'editableWhenExisting'=>bool default true, 'required'=>bool]]
// - spreadsheetQuery(): Builder                             -- query semua baris (TANPA paginate), eager-load kalau perlu
// - spreadsheetRowId($row): int|string                      -- nilai identitas baris (PK atau natural key, konsisten dgn query update)
// - spreadsheetNewRowDefaults(): array                      -- nilai default kolom utk baris baru (tombol "+ Baris")
// - spreadsheetValidateRow(array $data, $existingId): array -- validasi 1 baris, throw ValidationException, return data bersih
// - spreadsheetPersistRow(array $data, $existingId): void   -- create/update 1 baris (existingId null = baris baru)
trait HasSpreadsheetGrid
{
    // Naik tiap kali saveSpreadsheetBatch() dipanggil -- dipakai sbg wire:key
    // pembungkus <x-grid.spreadsheet-table> supaya Alpine di-reinit total
    // sesudah simpan (state dirty client otomatis bersih, data segar dari
    // server ke-load ulang) -- lebih sederhana drpd sinkronisasi manual 2 arah.
    public int $spreadsheetVersion = 0;

    // [rowId => ['col_key' => 'pesan error']] -- diisi kalau ADA baris yg
    // gagal validasi saat batch save, dipetakan ke sel yg salah di UI.
    public array $spreadsheetErrors = [];

    // Kalau batch save barusan ADA yg gagal, tampilkan balik persis apa yg
    // disubmit user (bukan re-query DB) supaya editan yg belum sempat
    // tersimpan tidak hilang. null = tampilkan data segar dari spreadsheetQuery().
    protected ?array $spreadsheetSubmittedRows = null;

    abstract protected function spreadsheetKey(): string;

    abstract protected function spreadsheetColumns(): array;

    abstract protected function spreadsheetQuery();

    abstract protected function spreadsheetRowId($row): int|string;

    abstract protected function spreadsheetNewRowDefaults(): array;

    abstract protected function spreadsheetValidateRow(array $data, int|string|null $existingId): array;

    abstract protected function spreadsheetPersistRow(array $data, int|string|null $existingId): void;

    public function spreadsheetRows(): array
    {
        if ($this->spreadsheetSubmittedRows !== null) {
            return $this->spreadsheetSubmittedRows;
        }

        $keys = array_keys($this->spreadsheetColumns());

        return $this->spreadsheetQuery()->get()->map(function ($row) use ($keys) {
            $data = ['id' => $this->spreadsheetRowId($row)];
            foreach ($keys as $key) {
                $data[$key] = $row->{$key};
            }

            return $data;
        })->all();
    }

    // Dipanggil dari Alpine ($wire.saveSpreadsheetBatch(rows)) -- $rows berisi
    // SEMUA baris yg lagi tampil di client (bukan cuma yg diedit), id baris
    // baru berformat string "new:-1", "new:-2", dst (lihat spreadsheet-grid.js).
    public function saveSpreadsheetBatch(array $rows): void
    {
        $keys = array_keys($this->spreadsheetColumns());
        $errors = [];
        $submitted = [];
        $anyError = false;

        foreach ($rows as $row) {
            $rawId = $row['id'] ?? null;
            $isNew = is_string($rawId) && str_starts_with($rawId, 'new:');
            $existingId = $isNew ? null : $rawId;

            $data = [];
            foreach ($keys as $key) {
                $data[$key] = $row[$key] ?? null;
            }

            try {
                $clean = $this->spreadsheetValidateRow($data, $existingId);
                $this->spreadsheetPersistRow($clean, $existingId);
                $submitted[] = array_merge(['id' => $existingId ?? $rawId], $clean);
            } catch (ValidationException $e) {
                $anyError = true;
                $errors[(string) $rawId] = $e->errors();
                $submitted[] = $row;
            }
        }

        $this->spreadsheetErrors = $errors;
        $this->spreadsheetSubmittedRows = $anyError ? $submitted : null;
        $this->spreadsheetVersion++;
    }
}
