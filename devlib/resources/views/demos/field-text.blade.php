<div class="dl-demo">
    <x-dl::field.text name="nama" label="Nama Lengkap" tooltip="Nama & deskripsi: badge aa/Aa/AA" placeholder="Masukkan nama..." value="Budi Santoso" />
    <x-dl::field.text name="alamat" label="Alamat (banyak baris)" multiline />
    <x-dl::field.text name="kode" label="Kode Barang" tooltip="Kode: tanpa badge huruf, huruf mono" code value="COLOR_OFF" />
    <x-dl::field.text name="email" type="email" label="Email" tooltip="Email: tanpa badge huruf" />
    <x-dl::field.text name="no_dok" label="No. Dokumen (read-only)" readonly value="PO-2609-0001" />
    <x-dl::field.text name="login" label="Username (besar, berikon)" icon="fa-user" size="lg" :case-toggle="false" />
</div>
