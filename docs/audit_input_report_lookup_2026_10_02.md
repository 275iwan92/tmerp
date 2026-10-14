# Audit 2026-10-02 (ringkas): input ke DB, grid/laporan dari DB, lookup tanpa layar input

> Diringkas 2026-10-14 dari 287 baris (keputusan Jo 13-Okt: ringkas). Salinan penuh: `tmerp_unused/docs/audit_input_report_lookup_2026_10_02.md` dan `php artisan md:library search "audit input"`.
> Audit pemeriksaan saja. **Status butir belum diverifikasi ulang setelah 02-Okt**; cek ke kode sebelum memperbaiki. Zona beku = perlu izin Jo.

## Hasil pemeriksaan 02-Okt
196 item menu aktif dibuka = 196 OK; 111 grid / lookup v2 jalan ke database ddt = 0 error; 31 temuan `data:audit` lama (akun aktif milik pegawai nonaktif, stok dummy minus). Menu: 225 item daun, 6 aktif tanpa layar.

## Input yang tidak tersimpan / terbuang (B)
| # | Layar | Masalah | Status |
|---|---|---|---|
| 1-2 | Menu akun v2: Change password, Edit profil | Save hanya menutup dialog | selesai 02-Okt |
| 3 | Chat | data contoh | selesai (rulebook C.31) |
| 4 | Tema / kerapatan / bahasa | tersimpan per browser, bukan di `sec_user` | bahasa kini ikut akun (B.3); tema belum diputuskan |
| 5 | Customer Order | Non PPN tetap dihitung PPN 11% | selesai (tarif dari `sys_constant`) |
| 6 | Inquiry Revise + ProDev (zona beku) | dimensi berakhiran nol terpotong (`rtrim '0'`: 210 → 21) | menunggu OK Jo |
| 7-9 | Inquiry New / Revise / ProDev (zona beku) | kategori, standard size, `part_role`, `flat_manual`, susunan `layout_detail` dikirim tapi tidak dibaca lagi; scope PROCESS menghapus finishing induk; scope DIMENSION tidak hitung ulang `flat_w/h` | terbuka |
| 10 | Master Card Collection | kartu manual / foto tanpa isian bahan | terbuka |
| 11 | Petty Cash | pemilih mata uang diabaikan | terbuka |
| 12 | Subcon Delivery Note | Notes draft tertimpa alasan batal | terbuka |
| 13 | Maintenance | "Meter at Done" tidak tersimpan di Draft; Kind / Priority hilang saat Accept | terbuka |
| 14 | MPS Gantt v1 | tombol Submit hanya pesan | v1, diganti PPIC v2 |

Kolom ada tapi isian tidak ada: Maintenance `vendor_id` dan `mnt_meter_reading`; Setting Approval `flow_config.division_id`; tab Notes Delivery Order; catatan per baris PQ; Inquiry `tax_scheme` / `ppn_percent` / `pph_percent` / diskon dari konstanta.

## Tampil / dihitung bukan dari database (C)
- Notifikasi menu akun: selesai (membaca `sys_notifications`).
- **Laporan Manajemen** (Ringkasan Eksekutif, Rincian Penjualan, Rincian Pembelian) hanya membaca tabel `*_legacy`; CO / DO / PO baru tidak muncul. Terbuka. Kartu "Sisa Hutang Usaha" juga dari arsip lama.
- Angka mati di kode: PPN 11% di Quotation (selesai, kini konstanta) dan `MasterCardService` (`ppn_percent 11`, `pph_percent 2`); kode akun mati (±30 tempat: `SpecialReceiptService`, `PettyCashService`, `TaxEngine`, `CloseService`); jatah cuti 12 hari, H+7, maks 3 hari, 3 izin/bulan, batas lapor sakit 07:30 di Self-Service; ambang stok 90 hari, reject 5%, rasio lancar 1,5.
- MPS Spreadsheet / Kanban menampilkan customer "GENERAL" / "—" untuk WO dari CO INSTANT (nama dicari lewat quotation, padahal `co_order.customer_id` ada).

## Lookup tanpa layar input (D)
Dari 82 tabel lookup bukan master grid: 53 hanya dibaca, 18 bisa diisi user. **Selesai 02-Okt:** Sales Person, Payment Terms, Department. **Terbuka:**
| Kelompok | Tabel |
|---|---|
| Organisasi / sales | `org_division`, `org_level`, `fin_tax_scheme`, `cust_category` |
| Payroll | `pyr_bpjs_rate`, `pyr_denda_rule`, `pyr_overtime_rate`, `pyr_pph21_ter_bracket`, `pyr_status_rule` |
| Finance | `fin_asset_category`, `fin_cost_center`, `fin_profit_center`, `fin_partner_account`, `fin_period` (tahun baru tak bisa dibuat), `inv_period` (tak bisa dibuat / ditutup) |
| HR / produksi | `emp_work_center`, `emp_employee_work_center_skill`, `prod_process_precedence`, `sys_geofence_location`, `emp_leave_balance` |
| Recruitment | `app_pipeline_stage_config`, `rcr_approval_matrix`, `rcr_approver_assignment`, `rcr_offer_approval_matrix`, `rcr_track` |
| Sistem | `sec_module`, `sec_recovery_question`, `sys_setting`, `sys_theme`, `sys_login_hero`, `sys_quotes`, `sys_user_menu`, `sys_app_menu_color`, `geo_*` |
| Pro-Cost / Inquiry (zona beku, izin Jo) | `mat_coating_rate`, `mat_film_rate`, `mat_flute_allowance`, `mat_grammage`, `mat_sheet_rate`, `mat_sheet_discount_tier`, `mat_sheet_supplier`, `mat_join_type`, `mat_packing_type`, `prod_part_type`, `prod_type`, `prod_type_param`, `ref_finishing_type`, `mat_plano_size` |

- Master Product: form v1 tidak terbuka dari menu mana pun; v2 hanya ubah nama; `prod_part` tanpa editor. Product baru lahir dari Quotation WIN, Master Card Collection, atau Pro-Cost Instant.
- Item menu aktif tanpa layar: Print Pallet Label, Print Item Label, Label Print History, Scan Transaction, Track via QR/Barcode (Inventory); Lembur (HR).
- Pilihan dropdown ditulis mati di kode (bukan tabel): Part Type / Source / tooling Inquiry, satuan BOM + peran bahan + nama tinta ProDev, follow-up Quotation, kategori biaya lain CO, saluran CPDEL, sebab janji meleset POD (padahal `co_delay_reason` ada), jenis bahan Material Usage Standard, prioritas / SLA Maintenance, jenis cuti / izin Self-Service, pecahan uang Roster kas.
- Diketik bebas padahal master ada: satuan (`uom_unit`), syarat bayar (`fin_payment_term`), proses (`prod_process`), lokasi rak (`wh_loc`), kurs, Ship To, penerima Kas Kecil, kontak customer, "Drawing by", departemen peminta Material Planning.

## Urutan perbaikan yang disetujui (02-Okt)
1 shell v2 (selesai) → 2 PPN CO / Quotation dari konstanta (selesai) → 3 dimensi terpotong Inquiry Revise (menunggu OK) → 4 Laporan Manajemen gabung data baru + arsip → 5 Grid Lookup tabel tanpa layar (mulai Division, Level, Tax Scheme, Customer Category, lalu Payroll, Finance, Recruitment) → 6 isian bebas jadi lookup → 7 pilihan mati jadi tabel.

## Arsip dokumen 02-Okt
39 berkas dipindah ke `tmerp_unused` (onboarding lama, queue, Opening App, Filament, layar LEGACY, backups, eksperimen, log impor) dengan alasan di `MANIFEST.md`; kini bisa dicari lewat `md:library`. Yang sengaja tidak dipindah: `docs/md_prompt_rel*`, `docs/legacy_scriptcase/`, `docs/ddl/`, `docs/database/` (rulebook D.13).
