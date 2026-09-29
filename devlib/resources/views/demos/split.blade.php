<div class="dl-demo">
    <x-dl::split aside="300px">
        <x-dl::card title="Isi dokumen">Header, tab baris item, jadwal, dst.</x-dl::card>
        <x-slot:side>
            <x-dl::card title="Summary &amp; Tax">Subtotal, diskon, DPP, PPN, total.</x-dl::card>
        </x-slot:side>
    </x-dl::split>
</div>
