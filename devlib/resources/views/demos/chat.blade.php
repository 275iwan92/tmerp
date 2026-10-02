@php
    $chat = \App\Support\DemoData::chat();
@endphp
<x-dl::chat :me="$chat['me']" :people="$chat['people']" :convos="$chat['convos']" height="600px" />
<p class="dl-note">Daftar kiri = orang yang sedang login (avatar berwarna cerah + cincin dan titik hijau); yang tidak login abu-abu. Tombol + = chat baru dengan orang yang tidak sedang login. Klik foto profil / foto pesan untuk memperbesar; klik 📎 untuk melampirkan file apa saja (gambar tampil langsung, file lain jadi kartu unduh). Halaman ini pratinjau tanpa server; di aplikasi prop <code>api</code> menyambungkannya ke database (pesan tersimpan, online dari denyut login, bunyi saat pesan masuk). Versi ringkas (1 kolom) dipakai di Layer Menu / drawer Chat pada Shell.</p>
