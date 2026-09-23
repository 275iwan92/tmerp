<div class="dl-demo" style="max-width:720px">
    <x-dl::card title="Purchase Order PO-2609-0001">
        <x-dl::form.row>
            <x-dl::field.display label="Supplier" value="PT Contoh Kertas Jaya" />
            <x-dl::field.display label="Total" :value="12500000" format="money" />
        </x-dl::form.row>
        <x-dl::meta created-by="Andi Pratama" created-at="2026-09-20 08:15" updated-by="Siti Rahma" updated-at="2026-09-23 14:02" origin="DUMMY" table="tm2pur.po_order" :record-id="1" />
    </x-dl::card>
    <p class="dl-note">Nama tabel & ID rekaman hanya tampil saat Mode Developer menyala.</p>
</div>
