<div class="dl-demo">
    <x-dl::card title="Daftar Purchase Order">
        <x-dl::empty-state icon="file" title="Belum ada Purchase Order" message="PO dibuat dari alokasi PR yang sudah disetujui. Mulai dengan membuat PO baru.">
            <x-dl::button preset="new" />
        </x-dl::empty-state>
    </x-dl::card>
    <x-dl::card title="Hasil pencarian"><x-dl::empty-state icon="search" title="Tidak ada yang cocok" message="Coba kata kunci lain atau lepas filter." /></x-dl::card>
</div>
