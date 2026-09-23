<div class="dl-demo">
    <p class="dl-note">Pilih bahasa; semua teks kamus (<code>x-dl::lang-text</code>) dan label tombol preset langsung berganti tanpa muat ulang. Pilihan diingat (cookie) sehingga halaman berikutnya dari server juga memakai bahasa itu.</p>
    <x-dl::lang-switch />
    <x-dl::card title="Contoh">
        <div class="dl-row">
            <x-dl::button preset="save" type="button" /> <x-dl::button preset="cancel" /> <x-dl::button preset="delete" />
        </div>
        <p class="dl-note"><x-dl::lang-text key="welcome" />! · <x-dl::lang-text key="language" />: <b><x-dl::lang-text key="dirty_title" /></b></p>
    </x-dl::card>
</div>
