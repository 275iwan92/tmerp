{{-- F1 = buka Help Center (docs/... instruksi user 2026-08-30: "F1 as Help
     and Support" di setiap grid/menu). Listener PLAIN JS di tiap layout
     (BUKAN cuma di shell terluar) -- hampir semua grid/menu di app ini
     dirender lewat <iframe> terpisah (main-menu.blade.php t.url), jadi 1
     listener global di shell TIDAK CUKUP: keydown di dalam iframe tidak
     bubble ke window parent (batasan browser antar-document). @include ini
     dipasang di layouts/app.blade.php (dipakai default oleh semua screen
     Livewire full-page yang di-iframe-kan) + layouts/shell.blade.php (frame
     terluar sendiri) supaya F1 benar2 kepencet di mana pun fokus keyboard
     berada. Buka tab baru (bukan navigasi keluar dari kerjaan yg sedang
     berjalan) -- konsisten dgn link "Panduan Penggunaan" di profile dropdown. --}}
<script>
    window.addEventListener('keydown', function (e) {
        if (e.key === 'F1') {
            e.preventDefault();
            // Bawa konteks layar otomatis ke tab Tiket Bantuan -- document.title
            // tiap screen Livewire full-page diisi #[Title('Nama Layar — TELASIH...')],
            // ambil bagian sebelum ' — ' saja.
            var ctx = (document.title || '').split(' — ')[0] || 'Aplikasi';
            window.open('{{ route('help.index') }}?ctx=' + encodeURIComponent(ctx), '_blank');
        }
    });
</script>
