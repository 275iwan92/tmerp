---
name: screen_hrm_shift_roster
description: Papan alokasi shift 2 mode — PPIC (drag-drop mesin Offset Printing, draft->confirm) dan Grid HR (klik-siklus, Digital Printing/Indostry).
---

# Shift Roster & Alokasi Mesin

**File**: `resources/views/pages/hrm/shift-roster.blade.php`
**Route**: `hrm.shift-roster` (`/hrm/shift-roster`)
**Koneksi DB utama**: mst (tm2mst) — lihat catatan penamaan di bawah
**Modul**: HRM

## Tujuan
Layar penjadwalan shift pegawai dengan 2 papan berbeda mode, mengacu mockup `mockup_shift_roster_v2.html` dan `masteremployee_tier2.md §A.7/A.8`:
- **Papan PPIC (Offset Printing)** — drag-drop pegawai ke sel (mesin × shift × tanggal), lifecycle `Simpan Draft` (DRAFT) → `Publish & Confirm` (CONFIRMED, terkunci) → `Buka Kunci` (kembali DRAFT untuk revisi).
- **Grid HR (Digital Printing/Indostry)** — tanpa dimensi mesin, klik sel untuk menyiklus ganti shift (langsung tersimpan, tanpa alur draft/confirm).

Drag & drop dieksekusi via Alpine (`$wire`) di Blade; state `$assign` (array in-memory) menyimpan perubahan sampai tombol Simpan/Publish ditekan — meniru mockup asli di mana drag hanya mengubah state di memori, "tersimpan" baru terjadi saat tombol ditekan. Bentrok HARD divalidasi saat Confirm (bukan saat drop — drop tetap diterima dan ditandai visual). Aktor: PPIC (papan mesin) dan HR (papan grid non-mesin).

## Field & Kolom

### Kontrol umum
- `tab` — `ppic` | `hr`
- `dateFrom` / `dateTo` — periode 7 hari default (Senin–Minggu minggu berjalan), live update memicu `loadPeriod()`
- Stat box (4): Total Terjadwal, Jumlah Lembur (OT), Potensi Bentrok, Pegawai di Pool

### Papan PPIC
- `currentMachineId` — mesin aktif yang ditampilkan (tombol switch per mesin, sumber `ProdMachine::where('division_id', offsetDivisionId())->where('activate',true)`)
- `poolSearch` — cari nama pegawai di Pool
- `assign[machineId][date][shiftId] = [employeeId, ...]` — state penugasan in-memory
- `otAssign["machineId|date|shiftId|employeeId"] = bool` — flag lembur per penugasan
- `batchStatus` — DRAFT | CONFIRMED (dari `EmpShiftRosterBatch`)
- Badge bentrok: normal (biru) / soft — dobel shift hari sama tapi beda mesin (oranye) / hard — 2 mesin berbeda, shift+tanggal sama (merah, wajib diperbaiki sebelum Confirm)

### Grid HR
- `hrDivisionId` — pilih divisi non-OFFSET (`OrgDivision::where('division_code','!=','OFFSET')`)
- Klik sel → siklus shift aktif divisi tsb → kosong (langsung `EmpShiftRoster::create()`/`delete()`, tanpa draft)

## Aksi / Method

### Umum
- `mount()` — set periode default minggu berjalan, `hrDivisionId` default divisi kode `DIGITAL`, panggil `loadPeriod()`
- `updatedDateFrom()` / `updatedDateTo()` — reload data periode
- `loadPeriod()` — private, load `EmpShiftRosterBatch` (division OFFSET) + baris `EmpShiftRoster` (`machine_id IS NOT NULL`) ke state `$assign`/`$otAssign`

### Papan PPIC
- `dropOnSlot(int $machineId, string $date, int $shiftId, array $payload)` — assign pegawai ke sel; kalau `payload['fromKey']` ada (pindah dari sel lain), detach dulu dari sel asal (termasuk carry status OT); tidak berlaku jika `batchStatus=CONFIRMED`
- `dropOnPool(array $payload)` — lepas pegawai dari sel, kembali ke Pool
- `removeAssignment(...)` — hapus penugasan via tombol ✕
- `detachFromCell(...)` — private, helper hapus dari cell + carry OT flag jika dipindah
- `toggleOt(...)` — tandai/batalkan status lembur pada 1 penugasan
- `switchMachine(int $machineId)` — ganti mesin yang ditampilkan
- `cellConflict(int $employeeId, string $date, int $shiftId, int $excludeMachineId)` — private, hitung bentrok hard/soft untuk 1 pegawai+tanggal terhadap semua mesin lain
- `conflictSet` (computed) — kumpulan semua bentrok di seluruh state `$assign`
- `chipClass(...)` — kelas CSS chip berdasar hasil `cellConflict`
- `saveDraft()` → `persist('DRAFT')`
- `confirmBatch()` — tolak (addError) jika ada bentrok HARD (`conflictSet` yang `hard=true`), tampilkan daftar nama+tanggal bentrok; jika bersih → `persist('CONFIRMED')`
- `unlockBatch()` — set batch kembali ke DRAFT, hapus `confirmed_by`/`confirmed_at`
- `persist(string $status)` — private, `firstOrNew` `EmpShiftRosterBatch` by (division_id, date_from, date_to), hapus semua `EmpShiftRoster` milik batch lalu insert ulang dari state `$assign`/`$otAssign` (`assigned_by_role='PPIC'`)

### Grid HR
- `cycleHrCell(int $employeeId, string $date)` — siklus index shift aktif (shift ke-1 → ke-2 → ... → kosong → shift ke-1), hapus baris lama lalu buat baru (`assigned_by_role='HR'`) jika index masih dalam rentang shift tersedia

## Tabel & Model yang Disentuh
- `App\Models\Master\EmpShiftRoster` — tabel `emp_shift_roster` (koneksi **`tm2mst`**, PK `roster_id`)
- `App\Models\Master\EmpShiftRosterBatch` — batch lifecycle draft/confirm per (division, date_from, date_to), koneksi `tm2mst`
- `App\Models\Master\EmpShift` — master shift (koneksi `tm2mst`), difilter lewat relasi department→division
- `App\Models\Master\EmpEmployee` — pool pegawai per divisi (koneksi `tm2mst`)
- `App\Models\Master\OrgDivision` — resolve `division_id` OFFSET/DIGITAL dsb. (koneksi `tm2mst`)
- `App\Models\Master\ProdMachine` — daftar mesin aktif per divisi (koneksi `tm2mst`)
- Tidak ada join lintas-DB eksplisit terlihat di file ini — semua model di namespace `Master` (asumsi konsisten koneksi `tm2mst`, TBD verifikasi eksplisit per model kalau dibutuhkan presisi 100%).

## Alur / Business Logic Penting
- **2 model bentrok berbeda tingkat keparahan**: HARD (1 orang di 2 mesin berbeda pada shift+tanggal SAMA — tabrakan fisik tidak mungkin) memblokir Confirm; SOFT (dobel shift hari yang sama tapi shift/mesin beda — kelelahan potensial) hanya peringatan visual, tidak memblokir.
- **Drag hanya mengubah state in-memory sampai eksplisit disimpan** — desain sengaja meniru interaksi mockup HTML asli (bukan auto-save per drag), supaya user bisa bereksperimen susunan tanpa efek permanen sampai klik Simpan Draft/Publish.
- **Confirm terkunci penuh**: semua method drag/drop/toggle OT/switch (`dropOnSlot`, `dropOnPool`, `removeAssignment`, `toggleOt`) menolak aksi (`return` awal) jika `batchStatus === 'CONFIRMED'` — harus Unlock dulu untuk revisi.
- **Grid HR tanpa lifecycle**: berbeda total dari papan PPIC — setiap klik langsung menulis/menghapus `EmpShiftRoster` seketika, tidak ada `EmpShiftRosterBatch` yang dipakai untuk divisi non-OFFSET.
- **`persist()` selalu hapus-lalu-insert-ulang seluruh batch** — bukan diff/update selektif, sehingga setiap Simpan Draft/Confirm menulis ulang seluruh baris `EmpShiftRoster` untuk periode+divisi tsb dari nol berdasar state `$assign` saat itu.

## Catatan
- **Ketidaksesuaian penamaan koneksi vs CLAUDE.md** (sama seperti layar HRM lain di batch ini): model-model di atas berada di namespace `App\Models\Master`, mengindikasikan koneksi `tm2mst` — bukan `tm2hrm` meski folder/route "hrm". Konsisten dengan pola prefix `emp_`/`org_`/`prod_` di CLAUDE.md §3 (Master).
- Overtime type disimpan hardcode `'REGULER'` saat `is_overtime=true` (`persist()`) — tidak ada pilihan jenis lembur lain di UI ini.
- Tidak ada validasi jam kerja/lembur maksimal per hari/minggu di file ini — hanya validasi bentrok penugasan (hard conflict).
