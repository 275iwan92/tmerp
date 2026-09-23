<div class="dl-demo">
    <x-dl::field.text name="nama" label="Nama Lengkap" tooltip="Teks + tombol huruf aa/Aa/AA" placeholder="Masukkan nama..." />
    <x-dl::field.text name="alamat" label="Alamat (banyak baris)" multiline />
    <x-dl::field.text name="login" label="Username (besar, berikon)" icon="fa-user" size="lg" :case-toggle="false" />
</div>
