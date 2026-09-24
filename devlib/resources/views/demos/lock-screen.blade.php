<div class="dl-forms-row">
    <x-dl::button icon="lock" label="Kunci layar sekarang" data-dl-lock />
    <span class="dl-note">atau tekan <kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>L</kbd>. Di aplikasi: <code>idle="15"</code> = terkunci otomatis setelah 15 menit tanpa aktivitas.</span>
</div>
<x-dl::lock-screen :user="['name' => 'Demo Admin', 'photo' => \App\Support\DemoData::avatarPhoto('#e2e8f0', '#475569')]" :idle="0" logout-action="#" />
<p class="dl-note">Pratinjau: password apa pun yang terisi membuka kunci. Aplikasi mencegah event <code>dl:unlock</code>, memeriksa password ke server, lalu memanggil <code>dlLock.unlock()</code> atau <code>dlLock.fail()</code>. Terkunci tetap terkunci walau halaman dimuat ulang.</p>
