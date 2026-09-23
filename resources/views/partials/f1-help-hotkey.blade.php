{{-- F1 = Bantuan & Support (instruksi user 2026-08-30: "F1 as Help and
     Support" di setiap grid/menu). Listener PLAIN JS di tiap layout (bukan
     cuma shell terluar): hampir semua layar dirender di <iframe>, dan keydown
     di dalam iframe tidak bubble ke window induk. Dipasang di
     layouts/app.blade.php + layouts/shell.blade.php.

     Sejak 2026-09-23 (semua menu profil = drawer kanan, keputusan user) F1
     membuka DRAWER Bantuan di shell, bukan tab browser baru: dari iframe lewat
     postMessage ke induk; di halaman shell sendiri lewat window.tmOpenHelp
     (profileDrawers() di resources/js/app-shell.js). Tab baru hanya cadangan
     bila halaman dibuka di luar shell. --}}
<script>
    window.addEventListener('keydown', function (e) {
        if (e.key !== 'F1') return;
        e.preventDefault();
        // Konteks = judul layar (#[Title('Nama Layar — ...')]), bagian sebelum ' — '.
        var ctx = (document.title || '').split(' — ')[0] || 'Aplikasi';
        if (window.parent !== window) {
            window.parent.postMessage({ tmerp: 'openHelp', ctx: ctx }, window.location.origin);
        } else if (typeof window.tmOpenHelp === 'function') {
            window.tmOpenHelp(ctx);
        } else {
            window.open('{{ route('help.index') }}?ctx=' + encodeURIComponent(ctx), '_blank');
        }
    });
</script>
