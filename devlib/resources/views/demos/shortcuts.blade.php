<div class="dl-demo">
    <p class="dl-note">Tekan <kbd>?</kbd> untuk melihat semua pintasan di layar ini. Tombol preset <b>Save</b> otomatis bisa ditekan dengan <kbd>Ctrl</kbd>+<kbd>S</kbd>, <b>+ New Record</b> dengan <kbd>Alt</kbd>+<kbd>N</kbd>; tombol lain cukup diberi <code>data-dl-key="alt+p"</code>.</p>
    <div class="dl-row">
        <x-dl::button preset="save" type="button" onclick="Devlib.toast('Ctrl+S / klik: Simpan', 'success')" />
        <x-dl::button preset="new" onclick="Devlib.toast('Alt+N / klik: data baru')" />
        <x-dl::button preset="print" data-dl-key="alt+p" onclick="Devlib.toast('Alt+P / klik: Cetak')" />
        <x-dl::button variant="secondary" label="Lihat daftar pintasan (?)" onclick="document.dispatchEvent(new KeyboardEvent('keydown', {key: '?'}))" />
    </div>
</div>
