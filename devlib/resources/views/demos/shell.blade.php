{{-- Menu utama sesuai pilihan user: Layer Menu (bawaan, x-dl::layer-shell) atau Menu Classic (x-dl::shell). --}}
<x-dynamic-component :component="\Devlib\Components\Shell::preferred()" :modules="$modules" :user="$user" :logout-action="route('logout')" :chat="\App\Support\DemoData::chat()" developer>
    {{-- Contoh slot profilePages: halaman profil "help" dibuka oleh F1 / menu akun Bantuan & dukungan (Back kembali ke Profile). --}}
    <x-slot:profilePages>
        <section data-lm-page="help" data-label="Bantuan & dukungan" data-icon="fa-circle-question">
            <p>Tekan <kbd>F1</kbd> di layar mana pun untuk membuka halaman ini. Aplikasi mengisinya dengan pedoman dan tiket bantuan.</p>
        </section>
    </x-slot:profilePages>
</x-dynamic-component>
