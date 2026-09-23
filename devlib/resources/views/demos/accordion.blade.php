<div class="dl-demo">
    <p class="dl-note">Vertikal · satu terbuka</p>
    <x-dl::accordion-group>
        <x-dl::accordion title="Data Pajak" icon="fa-file-invoice" open>NPWP, PPN, PPh 23.</x-dl::accordion>
        <x-dl::accordion title="Alamat Kirim" icon="fa-truck" badge="2">Gudang Rungkut · Gudang Sidoarjo.</x-dl::accordion>
        <x-dl::accordion title="Lampiran" icon="attachment">Belum ada lampiran.</x-dl::accordion>
    </x-dl::accordion-group>
    <p class="dl-note">Vertikal · banyak terbuka</p>
    <x-dl::accordion-group multiple>
        <x-dl::accordion title="Spesifikasi Bahan" open>Ivory 310 gsm, plano 65×100.</x-dl::accordion>
        <x-dl::accordion title="Finishing" open>Laminasi doff, pond, lem samping.</x-dl::accordion>
    </x-dl::accordion-group>
    <p class="dl-note">Horizontal · panel melebar ke samping (HP: otomatis vertikal)</p>
    <x-dl::accordion-group direction="horizontal" height="240px">
        <x-dl::accordion title="1 · Order" icon="fa-file-signature" open><b>Customer Order</b><br>CO-2609-0031 dikonfirmasi, due date 14 Okt 2026.</x-dl::accordion>
        <x-dl::accordion title="2 · Produksi" icon="fa-industry"><b>Work Order</b><br>Cetak 4 warna, KOMORI Lithrone 1, 2 shift.</x-dl::accordion>
        <x-dl::accordion title="3 · QC" icon="fa-clipboard-check"><b>Quality Control</b><br>Sampling 1%, cek warna & potong.</x-dl::accordion>
        <x-dl::accordion title="4 · Kirim" icon="fa-truck"><b>Delivery Order</b><br>2 tahap sesuai Plan Delivery.</x-dl::accordion>
    </x-dl::accordion-group>
</div>
