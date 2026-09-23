<div class="dl-doc">
    <h1>Icons</h1>
    <p>Layar menyebut <b>arti</b> ikon, bukan nama Font Awesome: <code>&lt;x-dl::icon name="save" /&gt;</code>. Ganti ikon di <code>Devlib\Icon</code>, semua tombol ikut.</p>
    <div class="dl-icons">
        @foreach (\Devlib\Icon::MAP as $name => $fa)
            <div><x-dl::icon :name="$name" /> {{ $name }}</div>
        @endforeach
    </div>
</div>
