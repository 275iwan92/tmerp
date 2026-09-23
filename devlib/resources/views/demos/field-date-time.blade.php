<div class="dl-demo">
    <x-dl::field.date-time name="rapat" label="Jadwal Rapat" tooltip="Tanggal & jam dalam satu popup, zona WIB" />
    <x-dl::field.date-time name="kirim" label="Jadwal Kirim" value="2026-09-25 08:30" />
    <x-dl::field.date-time name="shift" label="Mulai Shift (per 15 menit)" :minute-step="15" value="2026-09-24 07:00" />
</div>
