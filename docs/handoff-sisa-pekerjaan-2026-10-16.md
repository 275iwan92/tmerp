# Handoff sisa pekerjaan sesi 2026-10-16 (selain MRP dan ENGINE BOP/BOM, yang punya sesi sendiri)

Aturan: jawab Jo dalam bahasa Indonesia, pendek, jujur; diskusi satu per satu; perubahan flow/layar = narasi, preview, izin Jo, lalu commit + push (`Agent: Jo` Rabu-Sabtu, `Agent: Iwan` Senin-Selasa).

## A. Perubahan spesifikasi sesudah CO Confirm (tersisa)
Fondasi ada: `app/Services/Production/SpecChange/*`, tabel `spc_change_request`, `spc_otp`, `prod_material_alt`, tes `SpecChangeSubstitutionTest`. Substitusi bahan kini milik MRP (lihat design note ppic/18), bukan Form WO.
1. Kelas 1 (ukuran, flat, gramatur, artwork): terkunci saat CO Confirm (`spec_hash`, `spec_locked_at`), hanya Director yang boleh ubah; ACC Digital dicatat ulang; CO ditandai.
2. Kelas 3 (routing/BOP): perubahan dengan OTP OPM/GM/Director, ditulis balik ke master ProDev untuk order berikutnya; PPIC boleh menambah atau mengganti proses, tidak mengurangi; tambah rework sesudah QC.
3. Akhir kunci: operator pertama menekan Play ATAU bahan utama (kertas/sheet) mulai dikeluarkan; perbaiki status WO menjadi IN_PROGRESS.
4. Belum jelas: "CO Close per Delivery Note approve" (Jo: boleh didiskusikan lagi).

## B. Gap hasil analisis Guides (diskusi satu per satu)
Enforcement pintasan DO 30 menit; output produksi menjadi stok FG; progres status WO; stok FG berkurang saat DO Shipped; laporan "Shipped Without Production Output" belum ada. Opsional: format kontrol tanggal CO dd-Mmm-yyyy.

## C. Dokumentasi
- Guides untuk Inventory dan Gudang (modul berikutnya); tiap panduan wajib memuat "ke mana informasi mengalir dan mengapa".
- Impor `guides:import` ke tm2 bila perlu; catatan design note baru (ppic/18) belum diimpor.
- Ringkasan md batch 4 (~17 dokumen panjang) dan 26 baris MD Review non-keep yang belum diputuskan; salin keputusan ke tabel tm2.

## D. Kegagalan lama yang bukan dari sesi ini
UiRatchetTest (807 > 805), ProductionWoGenerationTest (FK), EngineCatalogTest (MasterCardDummyEngine belum terdaftar). Perubahan agen lain yang belum di-commit ada di `git status` (CostingAdvisor, EngineRegistry, dll.).
