{{-- REMARK
Tujuan: halaman login devlib.test (varian 1, kotak di kanan), dengan gambar latar khas devlib supaya mudah dibedakan dari mockup.test.
Sumber hulu: folder devlib/public/media/bg/devlib (dua wallpaper: laptop berisi kode Laravel dan ruangan minimalis, dibuat oleh tools/import-bg.php).
Input: variabel action, error, username dari route login (routes/web.php).
Output: x-dl::frame + x-dl::login-box; gambar latar dipilih acak dari dua wallpaper di atas.
Dipakai oleh: view login.blade.php (config devlib.login_variant = 1, bawaan).
Status: aktif; varian 2 dan 3 tetap memakai kumpulan gambar bg/login.
--}}
<x-dl::frame bg="devlib" bg-order="random" box="right" color="light" resizable widgets="brand@top-left, clock@top-right, quote@bottom-left">
    <x-dl::login-box show="support, lang, title, username, password, forgot, button, register" :action="$action" :error="$error" :username="$username" />
</x-dl::frame>
