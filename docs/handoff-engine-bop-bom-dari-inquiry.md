# Handoff sesi baru: ENGINE penyusun BOP dan BOM dari data Inquiry Product (2026-10-16)

Sesi ini DISKUSI dulu (narasi, tanpa kode) dengan Jo. ProDev adalah zona beku: tiap perubahan wajib narasi rinci sebelum dikerjakan, lalu preview, lalu izin Jo.

## Arahan Jo (verbatim inti)
- ProDev: engine BOM dan BOP berawal di sini; memastikan sesuai produk yang diminta.
- ProCost: memastikan biaya BOP dan BOM; di sisi BOM ada modul MRP (cek ketersediaan otomatis oleh Engine+ML, usulan substitusi).
- Jo 2026-10-16: "tambahkan ENGINE untuk mempermudah define BOP dan BOM dari data inquiry product". Dibahas di sesi tersendiri.

## Aturan substitusi yang sudah disetujui (dipakai MRP, bukan Form WO)
1. ML hanya mengusulkan dan meranking; officer memutuskan (A.7).
2. Kandidat pengganti: kategori sama, gramatur TIDAK berubah; ukuran lembar boleh lebih besar (dipotong di proses potong bahan).
3. ProDev tidak berubah oleh substitusi; BOM/BOP awal tetap dari ProDev.
4. PR dan PO otomatis (tombol Buy per bahan) tetap lewat approval biasa (PR: OPM; PO: Product Costing lalu OPM), PQ dilewati. PR yang sudah terbit saat substitusi: dibahas per kasus nanti.
Form WO (PPIC) tidak punya substitusi lagi.

## Sudah ada (jangan dibangun ulang)
- MRP: app/Services/Planning/MaterialPlanningEngine.php; layar Material Planning (app/Support/V2/Data/MaterialPlanning.php); tombol Buy (PR+PO Draft) = MaterialPlanning::buy.
- ProCost ENGINE+ML: app/Services/Costing/CostingAdvisor.php (memori project_procost_advisor_built_2026_10_16).
- Fondasi spec change (OTP, dampak biaya, bahan cadangan): app/Services/Production/SpecChange/*, tabel spc_change_request, prod_material_alt.
- ProDev derive BOP/BOM/layout/Master Card: baca docs/ENGINE-ATTENTION.md, docs/claude_vital_flows.md, docs/mastercard-okt-analysis.md (12 gap).

## Yang perlu dibahas (urutan usulan)
1. Peta: data Inquiry Product apa saja (ukuran, bahan, finishing, jumlah) yang sekarang sudah diterjemahkan engine ke BOP/BOM, dan mana yang masih diisi manual. Cari celahnya di kode (ProDev engine) dan laporkan.
2. Bentuk ENGINE baru: menyarankan BOP (urutan proses, mesin atau SUBCON) dan BOM (bahan, qty, waste) dari Inquiry; petugas memutuskan.
3. Pemeriksa kecocokan "sesuai produk yang diminta" (ukuran, gramatur, proses vs spesifikasi Inquiry).
4. Hubungan dengan ProCost (biaya) dan MRP (ketersediaan).
Mulai dengan membaca dokumen di atas, jawab Jo dalam bahasa Indonesia, pendek, jujur; tanya dulu bila ragu.
