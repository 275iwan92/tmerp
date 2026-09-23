<div class="dl-demo">
    <p class="dl-note">Atribut <code>can="modul.aksi"</code> berlaku di <b>semua</b> komponen library. Tanpa hak, komponen tidak dirender sama sekali (bukan sekadar disembunyikan). Hak akun <b>admin</b> DevLib: <code>{{ implode(', ', config('devlib.abilities')) }}</code>.</p>
    <x-dl::card title="Tombol di layar PO">
        <div class="dl-row">
            <x-dl::button preset="approve" can="po.approve" />
            <x-dl::button preset="reject" can="po.approve" />
            <x-dl::button preset="delete" can="finance.delete" />
            <x-dl::icon-button icon="print" title="Cetak" can="po.print" />
        </div>
        <x-dl::alert tone="info" message="Tombol Hapus (can=finance.delete) tidak muncul karena akun ini tidak punya hak finance." />
    </x-dl::card>
</div>
