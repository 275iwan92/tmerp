---
name: screen_master_shift
description: Master Shift (emp_shift + emp_shift_detail) — header + jam kerja per hari, replika struktur legacy m_shifts
---

# Master Shift

**File**: `resources/views/pages/master/shift.blade.php`
**Route**: `master.shift` (`/master/shift`)
**Koneksi DB utama**: mst (tm2mst)
**Modul**: Master Data

## Tujuan
CRUD grid+form-modal untuk `emp_shift` + `emp_shift_detail` — replika struktur legacy ScriptCase (`m_shifts` + `m_shifts_details`): 1 shift = 1 header + jam kerja per hari (Senin-Minggu bisa beda per hari), termasuk jam istirahat. `department_id` sekarang FK ke `org_department` (bukan lagi teks bebas seperti versi lama).

## Field & Kolom
Form header: `shift_name` (required, max:150), `department_id` (required, FK `org_department`), `shift_activate` (checkbox → `activate`).

Form detail per hari (array `days`, 7 baris tetap Senin-Minggu): `day_of_week`, `start_time` (jam masuk), `break_out` (istirahat mulai), `break_in` (istirahat selesai), `end_time` (jam pulang) — semua input `type="time"`, format `H:i`. Tombol 🗑 per baris untuk `clearDay()`. **Hari dengan `start_time`/`end_time` kosong = "tidak beroperasi"**.

Grid: kolom Nama Shift, Departemen, Ringkasan Jam (`$s->scheduleSummary()` — method di model), Status, Aksi (Edit+Toggle). Search (`search`) di `shift_name` atau nama departemen (relasi). Stat box: Total Shift, Aktif, Departemen Terpakai (distinct `department_id` aktif), Nonaktif.

## Aksi / Method
- `shifts()` — computed list + search, eager-load `details` & `department`
- `departmentOptions()` — computed dropdown departemen aktif
- `blankDays()` — helper, isi array `days` kosong 7 hari sesuai `$dayOrder`
- `openModal(mode, id)` — buka form; kalau edit, isi `days` dari relasi `details` (match by `day_of_week`, format waktu `H:i`)
- `showGrid()`, `clearDay(index)` — navigasi & kosongkan 1 baris hari
- `save()` — validasi header + `days` (array size:7, tiap elemen `date_format:H:i` nullable); simpan header lalu loop tiap hari: kalau `start_time`&`end_time` kosong → **hapus** baris detail hari itu (`EmpShiftDetail::where(...)->delete()`), kalau ada isinya → `updateOrCreate` by `shift_id`+`day_of_week`
- `toggleActive(id)` — toggle `activate`
- `statTotal/statActive/statInactive/statDepartments()` — computed stat box

## Tabel & Model yang Disentuh
- `App\Models\Master\EmpShift` → `tm2mst.emp_shift` (koneksi `mst`, PK `shift_id`)
- `App\Models\Master\EmpShiftDetail` → `tm2mst.emp_shift_detail` (FK dalam-DB `shift_id`)
- `App\Models\Master\OrgDepartment` → `tm2mst.org_department` (dropdown `department_id`)

## Alur / Business Logic Penting
- Hari tanpa jam masuk+pulang otomatis dianggap "libur/tidak beroperasi" — baris detailnya **dihapus** dari `emp_shift_detail`, bukan disimpan sebagai baris kosong.
- Editing existing shift: `days` diisi ulang dari data tersimpan berdasar index `array_search($detail->day_of_week, $dayOrder)`.

## Catatan
- Tidak ada TODO eksplisit di komentar kode.
- Konsumen: modul HRM (Time Attendance, Shift Roster — lihat memory `project_hrm_module_status.md`) dan Master Mesin/produksi terkait perhitungan kapasitas per shift.
