# Status berkas .md (gabungan md_pasti_usang.md dan md_inventory.md, ringkas)

> Dibuat 2026-10-13, disetujui Jo ("gabungan itu ok"). Menggantikan `docs/md_pasti_usang.md` (16 baris) dan `docs/md_inventory.md` (258 baris) setelah Jo setuju menghapus keduanya.
> Tabel lengkap per berkas dengan usulan dan alasan: `docs/ringkas/md_review_tabel.md` (juga layar Setup > Others > MD Review).

## Sudah dipindah ke `docs/usang_untuk_dipindah/` (belum dihapus, menunggu OK Jo)

| Berkas | Baris | Alasan usang |
|---|---|---|
| themestandard.md | 198 | Tema v1 (`sys_theme`) dimatikan; v2 memakai tema DevLib |
| checklistmockuperp.md | 288 | Foto kondisi 2026-08-20 layar v1; diganti papan Build Progress (`build.json`) |
| catatanauditmenyeluruh.md | 74 | Catatan audit 2026-09-02 sekali jalan; backlog sudah dipecah ke backlog01-10 |
| telma_onboarding.md | 227 | Panduan kerja paralel 2026-08-28; Inventory dan Delivery sudah dibangun ulang di v2 |
| security_brief.md | 175 | Brief awal login + main menu; sudah jadi dan jauh berkembang |
| System Architecture & Specification Blueprint ... .md | 777 | Blueprint generik buatan AI; arsitektur nyata ada di `globalarchitecture_tier0.md` dan kode |
| Blueprint_Arsitektur_dan_Enterprise_Resource_Planning__ERP__Industri_Offset_Printing_dan_Packaging.md | 241 | Esai riset industri umum, bukan spesifik proyek |
| dispatch_tier4.md | 148 | Spec layar v1; Delivery Order / Notes kini v2 (Tier 1-3 tetap) |
| procurement_tier4.md | 256 | Spec layar v1; PR / PO / PQ / Receive kini v2 (Tier 1-3 tetap) |

## Ragu (Jo yang memutuskan)

- Standar komponen v1: `griduistandard.md`, `FORM_UI_STANDARD.md`, `componentstandard.md`, `printnotastandard.md` (v2 memakai DevLib `x-dl::*`, tetapi v1 masih hidup).
- `attendancepayroll_tier4.md` (spec UI v1; layar v2 belum diverifikasi), `selfservice_panduan.md` (fungsi sama dengan profil DevLib), `sales_imposition_enhancement_backlog.md` (ide imposition, zona beku).

## Pola

Dokumen yang paling cepat usang: Tier 4 (spec layar v1), brief atau onboarding awal, dan standar komponen v1. Tier 1-3 dan standar bisnis umumnya masih benar.

## Tidak dihapus (aturan D.13)

`docs/md_prompt_rel*`, `docs/legacy_scriptcase/`, `docs/ddl/`, `docs/database/`: bahan diskusi Jo dan pagar pembanding sistem lama.
