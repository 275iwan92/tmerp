<div class="dl-demo" style="max-width:720px">
    <p class="dl-note">Ubah salah satu isian, lalu klik <b>Back</b> atau tautan lain: muncul pilihan <b>Save</b> / <b>Undo</b> / <b>Cancel</b>. Menutup tab juga diperingatkan browser. Semua form <code>x-dl::form</code> otomatis dijaga (matikan dengan <code>:guard="false"</code>); form lain cukup diberi atribut <code>data-dl-guard</code>.</p>
    <x-dl::form title="Edit Supplier" :actions="['Save' => 'fa-floppy-disk']" style="height:auto">
        <x-dl::form.col>
            <x-dl::field.text name="nama" label="Nama Supplier" value="PT Contoh Kertas Jaya" />
            <x-dl::field.text name="kota" label="Kota" value="Surabaya" />
        </x-dl::form.col>
        <x-dl::form.col>
            <x-dl::back-button fallback="#" />
            <x-dl::button variant="link" href="#tautan-lain" label="Tautan ke layar lain" />
        </x-dl::form.col>
    </x-dl::form>
</div>
