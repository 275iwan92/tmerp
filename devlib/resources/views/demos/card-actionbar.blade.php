<div class="dl-demo" style="max-width:720px">
    <x-dl::card title="Informasi Umum" icon="file" description="Data utama dokumen">
        <x-slot:actions><x-dl::button preset="print" size="sm" /></x-slot:actions>
        <x-dl::form.row>
            <x-dl::field.lookup name="supplier_id" lookup="supplier" label="Supplier" />
            <x-dl::field.date name="tgl" label="Tanggal PO" value="2026-09-23" />
        </x-dl::form.row>
        <x-dl::field.text name="catatan" label="Catatan" multiline />
        <x-dl::action-bar>
            <x-dl::button preset="cancel" />
            <x-dl::button preset="save" />
        </x-dl::action-bar>
    </x-dl::card>
</div>
