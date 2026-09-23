@php
    $chat = \App\Support\DemoData::chat();
@endphp
<x-dl::chat :me="$chat['me']" :people="$chat['people']" :convos="$chat['convos']" height="600px" />
<p class="dl-note">Daftar kiri = orang yang sedang login (titik hijau). Tombol + = chat baru dengan orang yang tidak sedang login. Klik foto profil / foto pesan untuk memperbesar; klik 📎 untuk mengirim foto. Versi ringkas (1 kolom) dipakai di drawer Chat pada Shell.</p>
