<div class="dl-demo dl-demo--row">
    <x-dl::button preset="delete" data-dl-confirm="Hapus PO draft ini? Alokasi PR yang sudah ditarik akan dilepas kembali." onclick="Devlib.toast('PO dihapus.', 'success')" />
    <x-dl::button preset="submit" data-dl-confirm="Ajukan PO ini untuk approval? Nomor dokumen akan diterbitkan." data-dl-confirm-tone="info" onclick="Devlib.toast('PO diajukan.', 'success')" />
    <x-dl::icon-button icon="delete" title="Hapus baris" variant="danger" confirm="Hapus baris ini?" onclick="Devlib.toast('Baris dihapus.')" />
</div>
