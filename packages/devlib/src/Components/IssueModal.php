<?php

namespace Devlib\Components;

/*
 * REMARK
 * Tujuan       : Popup peringatan yang harus terlihat: judul + penjelasan + tabel masalah (WO, proses, alasan), terbuka saat halaman dimuat.
 * Sumber hulu  : diisi aplikasi (mis. ScheduleGuard::issues); DevLib hanya menampilkan.
 * Input        : id, title, message, columns (key => label), rows (list of array key => teks), tone (default danger), size, open.
 * Output       : x-dl::modal bertabel; tombol Close di footer, slot "footer" menambah tombol lain.
 * Dipakai oleh : layar PPIC (pengaman jadwal di Work Order dan Scheduling); layar lain yang butuh peringatan berdaftar.
 * Status       : Aktif. Turunan Modal (kepala berwarna lewat tone), tabel memakai x-dl::table.
 */
class IssueModal extends Modal
{
    /**
     * @param  array<string, string>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(
        string $id = 'issues',
        ?string $title = null,
        string $size = 'lg',
        bool $open = true,
        ?string $icon = 'fa-triangle-exclamation',
        ?string $tone = 'danger',
        public ?string $message = null,
        public array $columns = [],
        public array $rows = [],
    ) {
        parent::__construct($id, $title, $size, $open, $icon, $tone);
    }

    public function tag(): string
    {
        return 'x-dl::issue-modal id='.$this->id;
    }

    public function render()
    {
        return <<<'BLADE'
            <x-dl::modal :id="$id" :title="$title" :size="$size" :open="$open && count($rows) > 0" :icon="$icon" :tone="$tone" {{ $attributes }}>
                @if ($message)<x-dl::box as="p"><x-dl::box as="b">{{ $message }}</x-dl::box></x-dl::box>@endif
                <x-dl::table :columns="$columns" :rows="$rows" />
                <x-slot:footer>
                    {{ $slot }}
                    <x-dl::button preset="close" data-dl-close-modal />
                </x-slot:footer>
            </x-dl::modal>
            BLADE;
    }
}
