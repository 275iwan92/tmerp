<div class="dl-demo dl-demo--row">
    <x-dl::button variant="success" label="Toast sukses" onclick="Devlib.toast('Purchase Order tersimpan.', 'success', 'Berhasil')" />
    <x-dl::button variant="danger" label="Toast gagal" onclick="Devlib.toast('Koneksi ke server terputus.', 'danger', 'Gagal menyimpan')" />
    <x-dl::button variant="secondary" label="Toast peringatan" onclick="Devlib.toast('Stok Kertas Duplex di bawah minimum.', 'warn')" />
    <x-dl::button variant="ghost" label="Toast info" onclick="Devlib.toast('Data diperbarui 5 menit lalu.')" />
</div>
