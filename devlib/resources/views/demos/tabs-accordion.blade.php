<div class="dl-demo">
    <x-dl::tabs :tabs="['umum' => 'Informasi Umum', 'baris' => 'Baris Pesanan', 'riwayat' => 'Riwayat']">
        <x-slot:umum><x-dl::field.lookup name="supplier_id" lookup="supplier" label="Supplier" /></x-slot:umum>
        <x-slot:baris>Tabel baris pesanan (Part 5: Master-Detail).</x-slot:baris>
        <x-slot:riwayat>Riwayat approval (Part 5: Approval).</x-slot:riwayat>
    </x-dl::tabs>

</div>
