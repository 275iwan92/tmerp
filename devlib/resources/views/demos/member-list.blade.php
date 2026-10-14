<div class="dl-demo" style="max-width:640px">
    {{-- rows: [id, role]; options: [id => label]; roles: pilihan peran; max: batas baris. Add aktif setelah seseorang dipilih; Remove (ikon) bertanya dulu. --}}
    <x-dl::form title="Crew" :actions="['Save' => 'fa-floppy-disk']" style="height:auto">
        <x-dl::member-list name="members" title="Choose user names · maximum 10 rows" :max="10"
            :options="['27' => 'TM-00027 · ACHMAD SYARIF', '43' => 'TM-00043 · AGI PURNOMO', '104' => 'TM-00104 · ADE MAHENDRA', '115' => 'TM-00115 · AGUS MUJIONO', '9' => 'TM-00009 · AGUS BUDI SUSANTO', '16' => 'TM-00016 · AHMAD ALWI NASTAIN']"
            :roles="['Main operator', 'Co-operator', 'Helper', 'Kabag']"
            :rows="[['id' => '27', 'role' => 'Main operator'], ['id' => '43', 'role' => 'Helper']]" />
    </x-dl::form>
    <p class="dl-note">Pilih seseorang lalu <b>Add</b>; tombol ikon silang/tong sampah mengeluarkan orang (dengan konfirmasi). Penghitung dan bar mengikuti jumlah baris. Undo form mengembalikan isi awal.</p>
</div>
