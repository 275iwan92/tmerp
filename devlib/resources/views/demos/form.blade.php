<x-dl::device-frame>
    <x-dl::form title="Full Mockup Form & Live QR Code" extra-action="Generate QR Code Data">
        <x-dl::form.col>
            <x-dl::field.text name="nama" label="Nama Lengkap" tooltip="sample input string (Auto-height & Case Toggle)" placeholder="Masukkan nama..." multiline />
            <x-dl::field.select name="domisili" label="Lokasi Domisili (Kota, Provinsi, Negara)" tooltip="sample typed searchable location selector"
                placeholder="Pilih atau Ketik Lokasi..." search-placeholder="Ketik Kota, Provinsi, atau Negara..." :options="$locations" />
            <x-dl::form.row>
                <x-dl::field.password name="password" label="Password" tooltip="sample password input with eye toggle" placeholder="Password..." />
                <x-dl::field.color name="warna" label="Warna Favorit" tooltip="sample color picker (HEX display)" value="#2563eb" />
            </x-dl::form.row>
            <x-dl::form.row>
                <x-dl::field.radio name="gender" label="Jenis Kelamin" tooltip="sample radio button (Single choice)" :options="['Pria', 'Wanita']" value="Pria" />
                <x-dl::field.toggle name="status" label="Status Akun" tooltip="sample toggle switch (Active state)" :value="true" />
            </x-dl::form.row>
            <x-dl::field.checkboxes name="hobi" label="Hobi / Minat" tooltip="sample multi-select checkbox group" :options="['Membaca', 'Olahraga', 'Traveling', 'Coding']" :value="['Membaca', 'Coding']" />
            <x-dl::field.slider name="pengeluaran" label="Pengeluaran Bulanan" tooltip="sample interactive range slider" :max="100000000" :step="1000000" :value="15000000" format="Rp {v} / bulan" />
            <x-dl::field.date-range name="kerja_awal" name-end="kerja_akhir" label="Masa Kerja (Awal s.d. Akhir)" tooltip="sample range date picker (Tenure calculation)" value="2020-01-01" value-end="2026-08-31" />
        </x-dl::form.col>
        <x-dl::form.col>
            <x-dl::field.multi-select name="bahasa" label="Kemampuan Bahasa" tooltip="sample dropdown with tick marks (Multi-select)" empty-text="Pilih Bahasa (0 Terpilih)" noun="Bahasa Terpilih"
                :options="$languages" :value="['Bahasa Indonesia', 'Bahasa Inggris']" />
            <x-dl::field.otp name="otp" label="OTP Code (6 Digit)" tooltip="sample 6-digit OTP code auto-focus boxes" />
            <x-dl::field.rating name="rating" label="Rating Pengalaman" tooltip="sample star rating 1-5 (Interactive)" />
            <x-dl::form.row>
                <x-dl::field.masked name="nik" label="No. KTP" tooltip="sample masking format NIK (6.6.4 digit)" placeholder="357801.150598..." />
                <x-dl::field.phone name="hp" label="No. HP" tooltip="sample phone with country code selector" />
            </x-dl::form.row>
            <x-dl::field.money name="valuasi" label="Valuasi Investasi" tooltip="sample valuation format with country specific spelling" currency="USD" value="78.034.949.443.447.400" />
            <x-dl::form.row>
                <x-dl::field.date name="tgl_lahir" label="Tanggal Lahir" tooltip="sample date picker" />
                <x-dl::field.time name="jam_lahir" label="Jam Lahir" tooltip="sample time picker" />
            </x-dl::form.row>
            <x-dl::form.row>
                <x-dl::field.decimal name="berat" label="Berat Badan" tooltip="sample decimal input (Auto .00 on blur)" unit="kg" />
                <x-dl::field.number name="anak" label="Jumlah Anak" tooltip="sample integer input (Spin Up & Down)" />
            </x-dl::form.row>
            <x-dl::field.upload name="dokumen" label="Upload Pas Foto / Dokumen" tooltip="sample drag & drop file upload" />
        </x-dl::form.col>
    </x-dl::form>
</x-dl::device-frame>
