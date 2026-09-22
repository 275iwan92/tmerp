# ATURAN GIT: SATU JALUR, TANPA CABANG

> **Kategori:** GLOBAL_RULE · terdaftar di `tm2sys.sys_doc_reference`
> **Keputusan user 2026-09-22:** *"Saya memang tidak ingin ada branch. Hapus semua
> branch, agar jalannya tidak bercabang, tidak ada jebakan dan mudah di debug."*

---

## 1. ATURAN TETAP (LOCKED)

1. **Satu branch saja.** Semua pekerjaan langsung ke jalur utama. Tidak ada
   branch per agen, per fitur, atau per sesi.
2. **Jangan pernah mencabang dari `main`.** Lihat §3 — `main` pernah tertinggal
   357 commit dan itu sumber seluruh masalah yang dicatat di dokumen ini.
3. **Jangan pakai worktree** (`Agent` dgn `isolation: "worktree"`, atau sesi
   desktop yang membuka worktree). Worktree lahir dari `main`, bukan dari
   jalur yang sedang dipakai. Lihat juga `feedback_agent_worktree_isolation_risk`.
4. Kalau toh sebuah sesi terlanjur berada di worktree: **selaraskan dulu**
   (`git reset --hard <jalur-utama>` lalu `cherry-pick` commit sendiri)
   sebelum mengerjakan apa pun, dan gabungkan kembali di hari yang sama.

## 2. KENAPA — DATABASE CUMA SATU

MySQL lokal (`tm2mst`, `tm2sdi`, `tm2prd`, dst.) **tidak ikut bercabang**.
Migration yang dijalankan di satu branch langsung mengubah database yang
dipakai semua branch. Akibatnya kalau ada dua jalur hidup bersamaan:

- Kode lama bertemu database baru → layar/engine memanggil kolom atau tabel
  yang sudah berubah, atau sebaliknya melewatkan aturan baru.
- Nomor migration bertabrakan waktu digabung.
- Test hijau di satu jalur, merah di jalur lain, padahal kodenya "sama".

Dua agen (Jo & Iwan, lihat §5) bergantian hari, jadi tidak pernah bekerja
bersamaan. Satu jalur sudah cukup dan justru paling aman.

## 3. RIWAYAT MASALAH (2026-09-22) — INTISARI

Pemicu: test `ProductionWoDetailRebuildTest::test_plan_delivery_modal_generates_wo_and_redirects`
merah, dikira regresi Master Mesin. Ternyata bukan.

| Temuan | Rincian |
|---|---|
| `main` tertinggal | **357 commit** di belakang jalur utama `claude/bentuk-customer-order-clarity-jnehjg`; `main` hanya berisi 1 merge commit PR #1 (2026-08-29) |
| Worktree lahir dari `main` | Sesi bekerja dgn kode sebulan lalu, tapi menyambung ke database hari ini |
| Akibat nyata #1 | `WoGenerationService` di jalur utama punya guard "costing harus dipublish" (Jo, 2026-09-18, BACKLOG 19.3). Di salinan stale guard itu tidak ada → test hijau di worktree, merah di laptop user |
| Akibat nyata #2 | Mockup acuan `mockup_mastercard.jpg` & `_ver2.jpg` tidak ada di worktree (53 mockup vs 100 di jalur utama) |
| Akibat nyata #3 | `docs/BACKLOG.md` & `docs/LOG_JOB_DONE.md` sama sekali tidak ada di worktree |
| Akibat nyata #4 | Branch `claude/exciting-hypatia-1d0d5a` — migration-nya SUDAH jalan di database (tabel `prd_mrp_run`, `prd_mrp_requirement` ada), tapi **kodenya tidak pernah masuk jalur utama**. Lihat §4 |

## 4. STATUS BRANCH PER 2026-09-22

### Sudah dihapus (isinya 100% sudah ada di jalur utama, nol commit unik)
- `claude/festive-feynman-7f63e0` (lokal)
- `claude/inventory-delivery-telma` (lokal + remote)
- `claude/peaceful-moser-845855` (lokal)
- `origin/claude/finance-accounting-hr-payroll-docs-0ctbgl` (remote)

### Diarsipkan sebagai TAG lalu branch-nya dihapus

Tag tidak bercabang dan tidak bisa di-commit, jadi kodenya aman tanpa
melanggar aturan "satu jalur".

| Tag | Isi | Kenapa tidak digabung |
|---|---|---|
| `arsip/mrp-mps-2026-08-30` | Engine MRP + MPS + Kanban (commit `9e8bfc3`): MrpEngine, MpsEngine, WoCreationService, PrdMrpRun, PrdMrpRequirement, kanban.blade.php, 2 test, migration `2026_08_28_000013/000014/000015` | **Keputusan user 2026-09-22**: hasilnya tidak disukai, akan dirancang ulang meniru UI/UX ProDev & Inquiry. Lihat BACKLOG 26 |
| `arsip/smoke-test-login-2026-08-27` | "Perbaiki smoke test yang masih pakai login admin lama" (commit `8f96b48`) | 3 dari 5 file sudah identik dgn jalur utama; 2 sisanya sudah berubah jauh sesudahnya |

Catatan penting: ketiga migration di `arsip/mrp-mps-2026-08-30` **sudah
terlanjur dieksekusi** di database -- `tm2prd.prd_mrp_run` dan
`prd_mrp_requirement` betul-betul ada, tanpa kode yang memakainya.
Biarkan apa adanya; jangan dipakai, jangan di-drop tanpa perintah user.

### Tersisa -- tinggal 1 langkah

`claude/beautiful-heisenberg-2be009` (worktree) dan
`claude/bentuk-customer-order-clarity-jnehjg` (folder utama) sekarang
**isinya sudah seluruhnya ada di `main`**. Keduanya tidak bisa dihapus
selagi masih di-checkout. Lihat section 6.

## 5. DUA AGEN, SATU JALUR

User memakai dua agen Claude bergantian per hari (info user 2026-09-22):

- **Jo** — Rabu, Kamis, Jumat
- **Iwan** — Senin, Selasa, Sabtu, Minggu

Jadwalnya sering meleset karena laptop tidak selalu dibuka di hari itu, jadi
**jangan dipakai sebagai patokan siapa mengerjakan apa** — pakai
`git log --date=format:'%Y-%m-%d %a'`. Keduanya commit ke jalur yang sama dan
itu sudah berjalan benar sejak awal; yang salah hanya worktree.

Catatan: beberapa sesi dari agen yang sama juga bisa jalan bersamaan (tercatat
11 sesi pada 2026-09-22), jadi "sesi lain yang mengubah" sama mungkinnya
dengan "agen satunya yang mengubah".

## 6. LANGKAH TERAKHIR

`main` sudah berisi SEMUANYA (jalur utama + perbaikan test + dokumen ini),
commit `5a8162f`, sudah di-push. Sisa satu langkah, dijalankan saat tidak ada
sesi lain yang memakai folder utama:

```bash
git checkout main
git branch -D claude/bentuk-customer-order-clarity-jnehjg claude/beautiful-heisenberg-2be009
git push origin --delete claude/bentuk-customer-order-clarity-jnehjg claude/beautiful-heisenberg-2be009
git worktree remove .claude/worktrees/beautiful-heisenberg-2be009 --force
git worktree prune
```

Sesudah itu: **satu folder, satu branch `main`, nol worktree.** `main` sudah
jadi default branch di GitHub, jadi clone/worktree baru otomatis lahir dari
titik yang benar -- tidak perlu ubah setelan apa pun lagi.
