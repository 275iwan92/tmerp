<x-dl::ribbon title="PO-2609-0031 · Purchase Order — Telasih Makmur"
    :quick="[['key' => 'save', 'label' => 'Simpan (Ctrl+S)', 'icon' => 'save'], ['key' => 'undo', 'label' => 'Urungkan (Ctrl+Z)', 'icon' => 'undo'], ['key' => 'redo', 'label' => 'Ulangi (Ctrl+Y)', 'icon' => 'fa-rotate-right']]"
    :file="[['key' => 'f-new', 'label' => 'Baru', 'icon' => 'new'], ['key' => 'f-open', 'label' => 'Buka', 'icon' => 'fa-folder-open'], ['key' => 'f-saveas', 'label' => 'Simpan Sebagai', 'icon' => 'save'], ['key' => 'f-print', 'label' => 'Cetak', 'icon' => 'print'], ['key' => 'f-export', 'label' => 'Ekspor', 'icon' => 'export'], ['key' => 'f-close', 'label' => 'Tutup', 'icon' => 'close']]"
    :tabs="[
        ['key' => 'home', 'label' => 'Beranda', 'tip' => 'H', 'groups' => [
            ['label' => 'Papan Klip', 'items' => [
                ['key' => 'paste', 'label' => 'Tempel', 'icon' => 'fa-paste', 'size' => 'lg', 'split' => true, 'tip' => 'V', 'menu' => [
                    ['key' => 'paste', 'label' => 'Tempel', 'icon' => 'fa-paste', 'shortcut' => 'Ctrl+V'], ['key' => 'paste-values', 'label' => 'Tempel Nilai', 'icon' => 'fa-hashtag'],
                    '-', ['label' => 'Tempel Khusus', 'icon' => 'fa-wand-magic', 'menu' => [['key' => 'paste-format', 'label' => 'Hanya Format', 'icon' => 'fa-paintbrush'], ['key' => 'paste-transpose', 'label' => 'Transpos', 'icon' => 'fa-arrows-rotate'], ['key' => 'paste-link', 'label' => 'Tempel Tautan', 'icon' => 'fa-link']]],
                ]],
                ['key' => 'cut', 'label' => 'Potong', 'icon' => 'fa-scissors', 'shortcut' => 'Ctrl+X', 'tip' => 'X'],
                ['key' => 'copy', 'label' => 'Salin', 'icon' => 'copy', 'split' => true, 'tip' => 'C', 'menu' => [['key' => 'copy', 'label' => 'Salin', 'icon' => 'copy'], ['key' => 'copy-pic', 'label' => 'Salin sebagai Gambar', 'icon' => 'fa-image']]],
                ['key' => 'painter', 'label' => 'Kuas Format', 'icon' => 'fa-paintbrush', 'toggle' => true],
            ]],
            ['label' => 'Huruf', 'items' => [
                ['key' => 'font', 'label' => 'Instrument Sans', 'icon' => 'fa-font', 'menu' => [['key' => 'font-a', 'label' => 'Instrument Sans'], ['key' => 'font-b', 'label' => 'Segoe UI'], ['key' => 'font-c', 'label' => 'Consolas']]],
                ['row' => [['key' => 'bold', 'label' => 'Tebal', 'icon' => 'fa-bold', 'toggle' => true, 'on' => true, 'shortcut' => 'Ctrl+B', 'tip' => 'B'], ['key' => 'italic', 'label' => 'Miring', 'icon' => 'fa-italic', 'toggle' => true, 'shortcut' => 'Ctrl+I', 'tip' => 'I'], ['key' => 'underline', 'label' => 'Garis Bawah', 'icon' => 'fa-underline', 'toggle' => true, 'shortcut' => 'Ctrl+U', 'tip' => 'U'], ['key' => 'fill', 'label' => 'Warna Isi', 'icon' => 'fa-fill-drip'], ['key' => 'color', 'label' => 'Warna Huruf', 'icon' => 'fa-palette']]],
                ['row' => [['key' => 'al-left', 'label' => 'Rata Kiri', 'icon' => 'fa-align-left', 'radio' => 'align', 'on' => true], ['key' => 'al-center', 'label' => 'Rata Tengah', 'icon' => 'fa-align-center', 'radio' => 'align'], ['key' => 'al-right', 'label' => 'Rata Kanan', 'icon' => 'fa-align-right', 'radio' => 'align']]],
            ]],
            ['label' => 'Dokumen', 'items' => [
                ['key' => 'new-doc', 'label' => 'Baru', 'icon' => 'new', 'size' => 'lg', 'tip' => 'N', 'menu' => [['key' => 'new-po', 'label' => 'Purchase Order', 'icon' => 'fa-cart-shopping'], ['key' => 'new-pr', 'label' => 'Purchase Requisition', 'icon' => 'fa-clipboard-list'], ['label' => 'Dari Template', 'icon' => 'fa-copy', 'menu' => [['key' => 'tpl-kertas', 'label' => 'PO Kertas Bulanan'], ['key' => 'tpl-tinta', 'label' => 'PO Tinta']]]]],
                ['key' => 'save-doc', 'label' => 'Simpan', 'icon' => 'save', 'size' => 'lg', 'tip' => 'S'],
                ['key' => 'confirm-doc', 'label' => 'Confirm', 'icon' => 'confirm', 'size' => 'lg'],
            ]],
            ['label' => 'Cetak', 'items' => [
                ['key' => 'print', 'label' => 'Cetak', 'icon' => 'print', 'size' => 'lg', 'split' => true, 'tip' => 'P', 'menu' => [['key' => 'print-nota', 'label' => 'Nota PO', 'icon' => 'print'], ['key' => 'print-ppn', 'label' => 'Nota PO (PPN)', 'icon' => 'print'], '-', ['key' => 'pdf', 'label' => 'Simpan PDF', 'icon' => 'fa-file-pdf'], ['key' => 'xls', 'label' => 'Ekspor Excel', 'icon' => 'fa-file-excel']]],
            ]],
        ]],
        ['key' => 'insert', 'label' => 'Sisipkan', 'tip' => 'N', 'groups' => [
            ['label' => 'Baris', 'items' => [['key' => 'row-add', 'label' => 'Tambah Baris', 'icon' => 'fa-table-list', 'size' => 'lg'], ['key' => 'row-dup', 'label' => 'Gandakan Baris', 'icon' => 'copy'], ['key' => 'row-del', 'label' => 'Hapus Baris', 'icon' => 'delete']]],
            ['label' => 'Lampiran', 'items' => [['key' => 'att-file', 'label' => 'Berkas', 'icon' => 'attachment', 'size' => 'lg'], ['key' => 'att-img', 'label' => 'Gambar', 'icon' => 'fa-image', 'size' => 'lg']]],
        ]],
        ['key' => 'data', 'label' => 'Data', 'tip' => 'A', 'groups' => [
            ['label' => 'Urut & Saring', 'items' => [['key' => 'sort-az', 'label' => 'A → Z', 'icon' => 'fa-arrow-down-a-z'], ['key' => 'sort-za', 'label' => 'Z → A', 'icon' => 'fa-arrow-down-z-a'], ['key' => 'filter', 'label' => 'Saring', 'icon' => 'filter', 'size' => 'lg', 'toggle' => true]]],
            ['label' => 'Alat Data', 'items' => [['key' => 'refresh', 'label' => 'Segarkan', 'icon' => 'refresh', 'size' => 'lg'], ['key' => 'import', 'label' => 'Impor', 'icon' => 'import'], ['key' => 'export', 'label' => 'Ekspor', 'icon' => 'export']]],
        ]],
        ['key' => 'view', 'label' => 'Tampilan', 'tip' => 'W', 'groups' => [
            ['label' => 'Tampilkan', 'items' => [['key' => 'grid-lines', 'label' => 'Garis Kisi', 'icon' => 'fa-border-all', 'toggle' => true, 'on' => true], ['key' => 'headings', 'label' => 'Judul Kolom', 'icon' => 'fa-heading', 'toggle' => true, 'on' => true], ['key' => 'formula', 'label' => 'Bilah Rumus', 'icon' => 'fa-square-root-variable', 'toggle' => true]]],
            ['label' => 'Perbesar', 'items' => [['key' => 'zoom', 'label' => 'Zoom', 'icon' => 'fa-magnifying-glass-plus', 'size' => 'lg', 'menu' => [['key' => 'z75', 'label' => '75%'], ['key' => 'z100', 'label' => '100%'], ['key' => 'z125', 'label' => '125%']]], ['key' => 'z-100', 'label' => '100%', 'icon' => 'fa-1', 'size' => 'lg']]],
        ]],
    ]">
    <h3>Info Dokumen</h3>
    <p class="dl-note">Backstage (tab File): panel penuh untuk perintah tingkat berkas — Baru, Buka, Simpan Sebagai, Cetak, Ekspor, Tutup.</p>
</x-dl::ribbon>
<p class="dl-note">Ribbon ala Excel: grup berlabel, tombol besar/kecil (3 bertumpuk), dropdown + submenu bertingkat (Tempel ▸ Tempel Khusus ▸ …), split button, toggle (Tebal) & toggle satu grup (Rata). Dbl-klik tab / tombol ^ / Ctrl+F1 = ciutkan; Alt = KeyTips; kotak "Cari perintah" menjalankan perintah dari tab mana pun.</p>
