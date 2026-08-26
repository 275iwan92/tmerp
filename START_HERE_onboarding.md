# MULAI DI SINI — Onboarding ke Claude Code (TMERP)

Panduan ringkas memindahkan pekerjaan dari chat ini ke **Claude Code**, agar
Claude membaca kode langsung (tak perlu screenshot lagi).

---

## LANGKAH 1 — Salin isi bundle ini ke proyek

Struktur bundle:
```
CLAUDE.md                 → salin ke ROOT proyek TMERP (sejajar artisan)
docs/                     → salin seluruh folder ke ROOT proyek TMERP
  ├── TIER_0..3A (.md/.sql)
  ├── prefixlist.md
  └── mockups/*.html
```

Hasil di proyek Anda:
```
TMERP/
├── artisan
├── composer.json
├── CLAUDE.md          ← peta & aturan proyek
├── docs/              ← sumber kebenaran (Tier + DDL + mockup)
├── app/ config/ ...
```

> DDL di `docs/` = **skema yang SUDAH terpasang** di MySQL lokal Anda. Bukan untuk
> dijalankan ulang; jadi acuan Claude Code saat bikin Model/Migration.

---

## LANGKAH 2 — Install Claude Code

Di terminal (butuh Node.js 18+):
```
npm install -g @anthropic-ai/claude-code
```
Masuk folder proyek, jalankan:
```
cd path/to/TMERP
claude
```
Login dengan akun Anthropic. Claude Code kini "melihat" seluruh proyek —
**tak perlu screenshot lagi.**

---

## LANGKAH 3 — Sesi pertama: verifikasi, jangan langsung generate

Perintah pertama yang efektif (tempel apa adanya):

> "Baca CLAUDE.md dan docs/. Konfirmasi pemahamanmu soal: (1) arsitektur multi-DB
> dan aturan FK lintas-DB, (2) prefix konvensi, (3) status Tier 3A. Lalu cek
> config/database.php — apakah 10 connection tm2* sudah lengkap & benar. JANGAN
> generate kode dulu, laporkan temuan saja."

Ini memastikan Claude Code paham konteks sebelum menulis kode. Kalau dia salah
paham di sini, betulkan dulu — jauh lebih murah daripada salah generate.

---

## LANGKAH 4 — Tutup gap kecil (bila belum)

Minta Claude Code:
> "Ada gap DDL F15 (estimasi kecepatan: est_days/speed/override/status) yang ada di
> mockup inquiry_new.html tapi belum di TIER_3A_INQUIRY.sql. Buat migration ALTER
> untuk menambah kolom itu di iqp_inquiry, sesuai mockup. Tunjukkan dulu sebelum jalan."

---

## LANGKAH 5 — Mulai modul Inquiry, layar pertama

Iterasi kecil. Jangan minta "buat semua modul". Mulai:

> "Buat Model Eloquent untuk semua tabel iqp_* (tm2sdi) dan master yang di-refer
> (tm2mst): set $connection, $table, $primaryKey, dan relasi antar-model sesuai
> DDL. FK lintas-DB pakai Eloquent relationship, bukan join. Ikuti aturan di CLAUDE.md."

Lalu:
> "Buat Livewire component untuk layar inquiry_new, mengikuti mockups/inquiry_new.html
> sebagai acuan tampilan & field, dan TIER_1/TIER_2 untuk aturan bisnis (state, pajak,
> material_source). Mulai dari header + tier dulu, part & costing menyusul."

---

## URUTAN GARAP MODUL INQUIRY (referensi)

1. Model (master + iqp) + relasi
2. inquiry_new — header + tier + diskon
3. part + material + process (dieline preview)
4. costing (HPP engine) — butuh rate versioned (tambah master dulu)
5. proof / prove versioning
6. quotation (modul quo_, borderline)

---

## ATURAN EMAS (ingatkan Claude Code bila melenceng)

- Baca DDL sebelum bikin skema — jangan tebak kolom.
- FK lintas-DB: JANGAN buat constraint. Validasi di aplikasi (`exists:conn.table,col`).
- Jangan `->join()` antar-DB. Pakai Eloquent `with()`.
- Model wajib `$connection`, `$table`, `$primaryKey` (PK bukan `id`).
- Iterasi kecil, konfirmasi keputusan arsitektural ke user.
- Jawab dalam Bahasa Indonesia, ringkas.

---

## CATATAN

- Modul selain inquiry (produksi/finance/dst) Tier-nya belum dibuat — normal.
  Garap per modul; tambah Tier & update CLAUDE.md saat masuk modul baru.
- Bila butuh diskusi desain arsitektur berat, kembali ke chat (bukan Claude Code) —
  Claude Code untuk eksekusi kode, chat untuk perencanaan.
