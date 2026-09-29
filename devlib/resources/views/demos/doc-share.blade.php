{{-- Print + Email: 1 tag per form/dokumen, tunjuk area dokumennya lewat target. Di tmerp endpoint = /v2/doc-share (config devlib.doc_share_url)
     -> HTML online + QR + Email aktif; di situs DevLib (tanpa database) hanya PDF / JPEG / Printer. --}}
<x-dl::card id="demo-doc" title="Inquiry Rekalkulasi · IQ-2609-0012" description="Contoh form: tombol Print ▾ dan Email di kanan atas">
    <x-dl::action-bar align="between">
        <x-dl::badge label="DRAFT" />
        <x-dl::doc-share target="#demo-doc" type="demo" doc-id="12" number="IQ-2609-0012" title="Inquiry Rekalkulasi" email-to="purchasing@customer.test" />
    </x-dl::action-bar>
    <x-dl::form.row cols="3">
        <x-dl::field.text name="product" label="Product" value="KS Power N120 MF" />
        <x-dl::field.text name="customer" label="Customer" value="PT Contoh Baterai" />
        <x-dl::field.number name="qty" label="Qty order" value="20000" />
    </x-dl::form.row>
    <x-dl::table :columns="['date' => 'Tanggal', 'qty' => 'Qty', 'price' => 'Harga jual /pcs']"
        :rows="[['date' => '23 Sep 2026', 'qty' => '10.000', 'price' => 'Rp 1.250'], ['date' => '02 Jun 2026', 'qty' => '15.000', 'price' => 'Rp 1.180']]" />
</x-dl::card>
