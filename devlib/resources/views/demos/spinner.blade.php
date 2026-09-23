<div class="dl-demo">
    <div class="dl-row"><x-dl::spinner size="sm" /><x-dl::spinner label="Memuat data..." /><x-dl::spinner size="lg" /></div>
    <x-dl::card title="Spinner menutupi kartu selama proses" style="position:relative;min-height:140px">
        Isi kartu tetap terlihat samar di belakang.
        <x-dl::spinner overlay label="Menghitung HPP..." />
    </x-dl::card>
</div>
