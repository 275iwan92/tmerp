---
name: db_mst
description: Database master netral lintas-modul (tm2mst) — customer, supplier, employee/HR, geo wilayah, produk & part baku, material, organisasi, satuan, gudang, dan sistem approval flow generik.
---

# Database: tm2mst (koneksi Eloquent: `mst`)

**Sumber DDL**: `docs/ddl/tm2mst_20260904.sql` (dump terbaru, dipakai sbg sumber kebenaran — menggantikan `docs/ddl/tm2mst.sql` lama kalau ada). Tabel `geo_city` juga ada di dump terpisah `docs/ddl/geo_city.sql` (36MB, isinya data lengkap kota/kabupaten Indonesia) — skemanya IDENTIK dengan yang di dump utama, jadi file itu hanya sumber DATA tambahan, bukan skema berbeda.
**Jumlah tabel**: 88

## Daftar Tabel

### `cust_category`
Kategori tracking pembayaran customer (LANCAR/KURANG LANCAR/dst).
- PK: `category_code` (varchar)
- Kolom: `category_code`, `description`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: tidak ada (direferensikan `cust_customer.category`)

### `cust_customer`
Master customer (+prospek). Tabel customer paling sentral, dipakai lintas-modul (Sales, Finance).
- PK: `id_customer`
- Kolom (ringkas — 68 kolom): `id_customer`, `customer_code` (unique), `customer_name`, `trade_name`, `parent_group`, `industry_category`, `tax_code` (NPWP), `mr_ms`, `contact_name`, `default_address`, `default_area_id`, `default_area_level` (CITY/DISTRICT/VILLAGE), `postal_code`, `email`, `phone`, `website`, `customer_type` (local/import), `payment_term`, `currency_code`, `credit_limit`, `current_balance`, `is_prospect`, `activate`, `created_at/by`, `updated_at/by`, `legacy_vendor_code` (unique, trace tm2old), `nickname`, `alias_code`, `category`, `is_blacklisted`, `approval_status` (DRAFT/CREDIT_CHECK/APPROVED/BLOCKED), `phone_2`, `fax`, `tax_purchase_value`, `tax_service_value`, `note`, `primary_product`, `secondary_product`, `default_substrate`, `color_standard`, `proofing_method`, `artwork_approval`, `retained_sample`, `barcode_verification`, `finishing_preference`, `die_owner`, `packing_instruction`, `quality_notes`, `sales_division_id`, `price_list_code`, `tax_profile` (PPN_11/EXEMPT), `credit_check_enabled`, `credit_rating` (A/B/C), `tax_validated`, `kyc_status` (PENDING/APPROVED/REJECTED)
- FK internal: `category -> cust_category`, `payment_term -> fin_payment_term`, `sales_division_id -> org_division`
- Kemungkinan FK logis lintas-DB: `default_area_id` -> geo_city/geo_district/geo_village (sama-sama di tm2mst, jadi ini sebenarnya FK internal logis, bukan lintas-DB)

### `cust_customer_bank`
Rekening bank customer.
- PK: `id_customer_bank`
- Kolom: `id_customer_bank`, `customer_id`, `bank_code`, `bank_account_no`, `bank_account_name`, `activate`, `legacy_vendor_bank_id` (unique), `created_at/by`, `updated_at/by`
- FK internal: `customer_id -> cust_customer`

### `cust_customer_branch`
Cabang/gudang customer (multi-alamat kirim).
- PK: `id_customer_branch`
- Kolom: `id_customer_branch`, `customer_id`, `branch_name`, `address`, `area_id`, `area_level` (CITY/DISTRICT/VILLAGE), `postal_code`, `phone`, `is_default`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: `customer_id -> cust_customer`

### `cust_customer_contact`
Kontak person customer.
- PK: `id_customer_contact`
- Kolom: `id_customer_contact`, `customer_id`, `contact_name`, `position`, `phone`, `email`, `is_default`, `activate`, `note`, `created_at/by`, `updated_at/by`
- FK internal: `customer_id -> cust_customer`

### `emp_badge_photo_request`
Request foto badge karyawan (approval sebelum jadi foto profil resmi).
- PK: `request_id`
- Kolom: `request_id`, `employee_id`, `photo_path`, `width_px`, `height_px`, `status` (PENDING/APPROVED/REJECTED), `requested_at`, `reviewed_by/at`
- FK internal: `employee_id -> emp_employee`

### `emp_bank_account`
Rekening bank karyawan (untuk payroll, meski modul payroll belum dibangun).
- PK: `bank_account_id`
- Kolom: `bank_account_id`, `employee_id`, `bank_name`, `account_no`, `account_holder_name`, `is_primary`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: `employee_id -> emp_employee`

### `emp_bpjs_kesehatan`
Data BPJS Kesehatan karyawan (1:1).
- PK: `employee_id`
- Kolom: `employee_id`, `no_kepesertaan`, `kelas_rawat` (I/II/III), `activate`, `dependents_count`, `updated_at/by`
- FK internal: `employee_id -> emp_employee`

### `emp_bpjs_ketenagakerjaan`
Data BPJS Ketenagakerjaan karyawan (1:1), termasuk program yang diikuti.
- PK: `employee_id`
- Kolom: `employee_id`, `no_kepesertaan`, `activate`, `program_jkk`, `program_jkm`, `program_jht`, `program_jp`, `program_jkp`, `updated_at/by`
- FK internal: `employee_id -> emp_employee`

### `emp_contract`
Kontrak kerja karyawan (histori). Hanya 1 aktif per employee (enforced app-level).
- PK: `contract_id`
- Kolom: `contract_id`, `employee_id`, `contract_no`, `employment_type` (TETAP/KONTRAK/HARIAN/BORONGAN), `start_date`, `end_date` (NULL hanya TETAP), `salary_type` (PER_MONTH/PER_DAY/PER_UNIT), `salary_amount`, `is_probation`, `probation_end_date`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: `employee_id -> emp_employee`

### `emp_correction_request`
Request koreksi data profil karyawan (self-service, butuh approval HR).
- PK: `request_id`
- Kolom: `request_id`, `employee_id`, `field_name`, `old_value`, `new_value`, `reason`, `attachment_path`, `requires_hr_approval`, `status` (PENDING/APPROVED/REJECTED), `requested_at`, `reviewed_by/at`, `review_note`
- FK internal: `employee_id -> emp_employee`

### `emp_disciplinary`
Catatan disiplin karyawan (SP1/SP2/SP3/dst).
- PK: `disciplinary_id`
- Kolom: `disciplinary_id`, `employee_id`, `type` (TEGURAN_VERBAL/SP1/SP2/SP3/ALPHA), `incident_date`, `reason`, `related_leave_request_id` (logical), `sanksi_referensi`, `issued_by`, `created_at`
- FK internal: `employee_id -> emp_employee`

### `emp_document`
Dokumen kelengkapan karyawan (KTP/NPWP/SIM/KK/dst).
- PK: `document_id`
- Kolom: `document_id`, `employee_id`, `document_type` (KTP/NPWP/SIM/KK/SKCK/PASSPORT/IJAZAH/KONTRAK/LAINNYA), `document_no`, `file_path`, `expiry_date`, `uploaded_by_role` (SELF/HR), `activate`, `created_at/by`, `updated_at/by`
- FK internal: `employee_id -> emp_employee`

### `emp_education`
Riwayat pendidikan/sertifikasi/pelatihan karyawan.
- PK: `education_id`
- Kolom: `education_id`, `employee_id`, `education_type` (SERTIFIKASI/SEMINAR/PELATIHAN/PENDIDIKAN_FORMAL), `activity_name`, `organizer`, `activity_date`, `duration_days`, `cost`, `funded_by` (PERUSAHAAN/PRIBADI/SHARING), `certificate_path`, `valid_until`, `is_bonding`, `bonding_note`, `uploaded_by_role` (SELF/HR), `created_at/updated_at`
- FK internal: `employee_id -> emp_employee`

### `emp_employee`
Master karyawan — tabel HR paling sentral. 49 kolom.
- PK: `employee_id`
- Kolom: `employee_id`, `legacy_employee_id` (unique), `employee_code` (unique, auto {company_code}-{seq}), `company_id`, `position_id`, `full_name`, `nickname`, `gender` (L/P), `birth_place`, `birth_date`, `nik_ktp`, `no_kk`, `npwp_number`, `religion`, `marital_status`, `phone`, `email_personal`, `village_id_ktp` (logical -> geo_village), `address_detail_ktp`, `village_id_domisili` (NULL=sama KTP), `address_detail_domisili`, `join_date`, `resign_date`, `resign_reason`, `photo_path`, `work_status` (WORKING/DIRUMAHKAN/CUTI), `work_status_since`, `emergency_contact_name/relation/phone`, `default_machine_id`, `default_shift_id`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: `company_id -> org_company`, `position_id -> org_position`, `default_machine_id -> prod_machine`, `default_shift_id -> emp_shift`

### `emp_family`
Data keluarga karyawan (istri/anak/orang tua) — wajib 8-field (lihat memory `project_family_data_mandatory_fields`).
- PK: `family_id`
- Kolom: `family_id`, `employee_id`, `relation` (SUAMI/ISTRI/ANAK/ORANG_TUA), `full_name`, `birth_date`, `nik`, `is_deceased`, `deceased_date`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: `employee_id -> emp_employee`

### `emp_family_request`
Request perubahan data keluarga (lahir/meninggal/pasangan baru), approval-gated.
- PK: `request_id`
- Kolom: `request_id`, `employee_id`, `request_type` (LAHIR/MENINGGAL/PASANGAN), `family_id`, `relation`, `full_name`, `birth_date`, `deceased_date`, `attachment_path`, `status` (PENDING/APPROVED/REJECTED), `requested_at`, `reviewed_by/at`
- FK internal: `employee_id -> emp_employee`, `family_id -> emp_family`

### `emp_leave_balance`
Saldo cuti tahunan per karyawan per tahun.
- PK: `balance_id`
- Kolom: `balance_id`, `employee_id`, `year`, `entitlement_days`, `used_days`, `carried_over_days`, `created_at/updated_at`
- FK internal: `employee_id -> emp_employee`
- Unique: (`employee_id`, `year`)

### `emp_leave_request`
Pengajuan cuti/sakit/izin karyawan. Banyak sub-tipe & attachment berbeda per kategori.
- PK: `request_id`
- Kolom: `request_id`, `employee_id`, `leave_type` (CUTI/SAKIT/IZIN/CUTI_KHUSUS), `khusus_event_type`, `izin_category`, `izin_relasi_pemohon`, `izin_instansi_asal`, `date_from`, `date_to`, `total_days`, `reason`, `attachment_surat_cuti/surat_dokter/kwitansi_resep/jkn_screenshot/path`, `sakit_lapor_jam`, `converted_to_izin`, `is_paid`, `deducts_leave_balance`, `status` (PENDING/APPROVED/REJECTED), `requested_at`, `reviewed_by/at`, `review_note`
- FK internal: `employee_id -> emp_employee`

### `emp_out_permit_request`
Izin keluar sementara (jam kerja) dengan approval berlapis atasan+HRD.
- PK: `request_id`
- Kolom: `request_id`, `employee_id`, `permit_date`, `planned_out_time`, `planned_return_time`, `reason`, `fingerprint_out_at`, `fingerprint_in_at`, `atasan_approved_by/at`, `hrd_approved_by/at`, `status` (PENDING/APPROVED/REJECTED), `requested_at`
- FK internal: `employee_id -> emp_employee`

### `emp_resign_request`
Pengajuan resign karyawan.
- PK: `request_id`
- Kolom: `request_id`, `employee_id`, `effective_date`, `reason`, `status` (PENDING/PROCESSED), `requested_at`, `processed_by/at`
- FK internal: `employee_id -> emp_employee`

### `emp_shift`
Master shift kerja per department.
- PK: `shift_id`
- Kolom: `shift_id`, `department_id`, `shift_name`, `description`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: `department_id -> org_department`

### `emp_shift_detail`
Jadwal jam per hari dalam 1 shift (Senin-Minggu).
- PK: `shift_detail_id`
- Kolom: `shift_detail_id`, `shift_id`, `day_of_week` (enum Monday..Sunday), `start_time`, `break_out`, `break_in`, `end_time`, `description`, `created_at/by`, `updated_at/by`
- FK internal: `shift_id -> emp_shift`
- Unique: (`shift_id`, `day_of_week`)

### `emp_shift_roster`
Penugasan shift harian per karyawan (jadwal kerja aktual).
- PK: `roster_id`
- Kolom: `roster_id`, `batch_id`, `employee_id`, `division_id`, `machine_id`, `shift_id`, `roster_date`, `assigned_by_role` (PPIC/HR), `is_overtime`, `overtime_type` (REGULER/HARI_MINGGU/HARI_BESAR), `created_at/by`, `updated_at/by`
- FK internal: `batch_id -> emp_shift_roster_batch`, `division_id -> org_division`, `employee_id -> emp_employee`, `machine_id -> prod_machine`, `shift_id -> emp_shift`
- Unique: (`employee_id`, `roster_date`, `shift_id`, `machine_id`)

### `emp_shift_roster_batch`
Batch/periode publish roster (draft->confirmed per rentang tanggal per divisi).
- PK: `batch_id`
- Kolom: `batch_id`, `division_id`, `date_from`, `date_to`, `status` (DRAFT/CONFIRMED), `confirmed_by/at`, `created_at/by`, `updated_at/by`
- FK internal: `division_id -> org_division`

### `emp_shift_swap_request`
Request tukar shift antar-karyawan, approval 2 tahap (partner + PPIC).
- PK: `request_id`
- Kolom: `request_id`, `requester_employee_id`, `requester_roster_id`, `target_employee_id`, `target_roster_id`, `reason`, `submitted_on_time`, `partner_status` (PENDING/APPROVED/REJECTED), `ppic_status` (PENDING/APPROVED/REJECTED), `requested_at`, `partner_reviewed_at`, `ppic_reviewed_by/at`
- FK internal: `requester_employee_id -> emp_employee`, `requester_roster_id -> emp_shift_roster`, `target_employee_id -> emp_employee`, `target_roster_id -> emp_shift_roster`

### `fin_currency`
Master mata uang (ISO 4217). Direferensikan `geo_country.currency_id`.
- PK: `id_currency`
- Kolom: `id_currency`, `currency_code` (unique, mis. IDR/USD/EUR), `currency_name`, `symbol`, `counted_word` (terbilang), `activate`, `created_at/by`, `updated_at/by`
- FK internal: tidak ada

### `fin_exchange_rate`
Kurs historis per mata uang terhadap base (IDR).
- PK: `id_exchange_rate`
- Kolom: `id_exchange_rate`, `currency_id`, `rate_to_base`, `effective_date`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: `currency_id -> fin_currency`

### `fin_payment_term`
Master termin pembayaran.
- PK: `payment_term_code` (varchar)
- Kolom: `payment_term_code`, `term_label`, `days` (NULL kalau tak relevan), `sort_order`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: tidak ada (direferensikan `cust_customer.payment_term`, `sup_supplier.payment_term`)

### `flow_config`
Konfigurasi approval flow generik per modul dokumen (PR/PO/PV/PRT/CO/DO dst), bisa per-divisi.
- PK: `flow_config_id`
- Kolom: `flow_config_id`, `module_code`, `division_id` (NULL=company-wide), `total_step`, `notes`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: `division_id -> org_division`
- Unique: (`module_code`, `division_id`, `activate`)

### `flow_config_step`
Langkah-langkah approval per flow_config (siapa approve, kondisi amount, dsb).
- PK: `flow_step_id`
- Kolom: `flow_step_id`, `flow_config_id`, `step_no`, `step_type` (APPROVAL/AUTO/NOTIFY_ONLY), `status_from`, `status_to`, `status_to_reject`, `position_id` (NULL bila AUTO), `min_amount`, `max_amount`, `label` (mis. Membuat/Mengetahui/Menyetujui), `created_at/by`, `updated_at/by`
- FK internal: `flow_config_id -> flow_config`, `position_id -> org_position`

### `flow_log`
Audit trail transisi approval flow — dokumen bersifat polymorphic (`doc_id` bukan FK fisik, dokumen bisa tersebar lintas-DB).
- PK: `flow_log_id`
- Kolom: `flow_log_id`, `module_code`, `doc_id` (polymorphic, bukan FK fisik), `flow_config_id`, `flow_step_id`, `status_from`, `status_to`, `actor_position_id` (snapshot jabatan), `actual_user_id` (LOGICAL -> tm2sys.sec_user), `is_acting`, `action` (SUBMITTED/APPROVED/REJECTED/AUTO_ADVANCED/CANCELLED), `amount_snapshot`, `notes`, `acted_at`, `created_by`
- FK internal: `flow_config_id -> flow_config`, `flow_step_id -> flow_config_step`
- Kemungkinan FK logis lintas-DB: `actual_user_id` -> tm2sys.sec_user

### `flow_position_acting`
Penugasan pelaksana-tugas (acting) sementara untuk 1 posisi (mis. cuti atasan).
- PK: `acting_id`
- Kolom: `acting_id`, `position_id`, `acting_user_id` (LOGICAL -> tm2sys.sec_user), `start_date`, `end_date`, `reason`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: `position_id -> org_position`
- Kemungkinan FK logis lintas-DB: `acting_user_id` -> tm2sys.sec_user

### `geo_city`
Master kota/kabupaten (Level 2). `city_type` membedakan KOTA vs KABUPATEN. Tabel sangat besar (~152.700 baris data, lihat `docs/ddl/geo_city.sql`).
- PK: `id_city`
- Kolom: `id_city`, `region_id`, `city_code`, `city_type` (KOTA/KABUPATEN/CITY), `city_name`, `post_code`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: `region_id -> geo_region`
- Unique: (`region_id`, `city_type`, `city_name`)

### `geo_country`
Master negara (Level 0 hierarki geo).
- PK: `id_country`
- Kolom: `id_country`, `country_code` (unique, ISO alpha-2), `iso_code` (ISO alpha-3), `flag_emoji`, `country_name` (unique), `capital_name`, `call_country_no`, `land_area_km2`, `currency_id`, `tax_percentage`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: `currency_id -> fin_currency`

### `geo_district`
Master kecamatan (Level 3). Diisi via import resmi, bukan input manual.
- PK: `id_district`
- Kolom: `id_district`, `city_id`, `district_code`, `district_name`, `post_code`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: `city_id -> geo_city`
- Unique: (`city_id`, `district_name`)

### `geo_region`
Master provinsi/state (Level 1).
- PK: `id_region`
- Kolom: `id_region`, `country_id`, `region_code`, `region_name`, `is_active` (catatan: nama kolom beda dari konvensi `activate` di tabel geo lain), `created_at/by`, `updated_at/by`
- FK internal: `country_id -> geo_country`
- Unique: (`country_id`, `region_name`)

### `geo_village`
Master kelurahan/desa (Level 4, level terbawah hierarki geo).
- PK: `id_village`
- Kolom: `id_village`, `district_id`, `village_code`, `village_name`, `post_code`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: `district_id -> geo_district`
- Unique: (`district_id`, `village_name`)

### `mat_category`
Kategori material self-referencing hierarkis (redesign dari 5 tabel `doc_rmprefix*` lama — lihat memory `project_mat_category_redesign`). Level=1 punya field akuntansi default.
- PK: `id_category`
- Kolom: `id_category`, `parent_id` (self-FK), `level`, `category_name`, `prefix_char`, `note`, `stock_class` (RAW/INDIRECT/SERVICE, hanya level=1), `default_coa_account_id` (LOGICAL -> tm2acc.fin_account, level=1), `default_stock_asset_account_id` (LOGICAL -> tm2acc.fin_account, level=1), `default_gr_ir_account_id` (LOGICAL -> tm2acc.fin_account, level=1), `activate`, `created_at/by`, `updated_at/by`
- FK internal: `parent_id -> mat_category` (self-referencing)
- Kemungkinan FK logis lintas-DB: `default_coa_account_id`/`default_stock_asset_account_id`/`default_gr_ir_account_id` -> tm2acc.fin_account

### `mat_coating_rate`
Rate coating multi-layer, versioned (pola "1 baris aktif per jenis" — CLAUDE.md §4).
- PK: `id_coating_rate`
- Kolom: `id_coating_rate`, `coating_type` (WB/UV/varnish/laminating), `index_coating` (rate per cm2), `effective_date`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: tidak ada

### `mat_flap_rule`
Aturan rentang lidah/flap valid per jenis bahan + rentang gramatur + jenis lem.
- PK: `id_flap_rule`
- Kolom: `id_flap_rule`, `material_type` (selaras `mat_grammage.paper_kind`), `grammage_range` (teks rentang, mis. "250-350"), `glue_type`, `flap_min_mm`, `flap_max_mm`, `activate`, `notes`, `created_at/by`, `updated_at/by`
- FK internal: tidak ada

### `mat_flute_allowance`
Allowance mentah K/L/M per tipe flute (corrugated), sumber derivasi geometri sheet di CostingEngine.
- PK: `id_flute_allowance`
- Kolom: `id_flute_allowance`, `flute_type` (unique, B/F|C/F|BC/F), `leb_panjang_mm`, `leb_lebar_mm`, `leb_tinggi_mm`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: tidak ada

### `mat_grammage`
Harga kertas duplex/ivory-AP + DC%, rate fluktuatif versioned (CLAUDE.md §4). 2 jalur harga: duplex (price_list+dc_pct) atau ivory/AP (price_per_kg).
- PK: `id_grammage`
- Kolom: `id_grammage`, `grammage_name`, `grammage_value` (gsm), `paper_kind` (DUPLEX/IVORY_AP), `duplex_price_list` (>0=jalur A), `duplex_dc_pct`, `ivory_ap_price_per_kg` (>0=jalur B), `effective_date`, `activate` (1 baris aktif per grammage_name), `created_at/by`, `updated_at/by`
- FK internal: tidak ada

### `mat_plano_size`
Master ukuran plano standar (mis. 65x100, 79x109).
- PK: `id_plano_size`
- Kolom: `id_plano_size`, `plano_name`, `width_mm`, `height_mm`, `sort_order`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: tidak ada

### `mat_raw`
Master bahan baku/item (RM). Tabel item master paling lengkap — termasuk field akuntansi & inventory. Nama fisik lama `m_raw_material` (lihat memory `project_table_prefix_cleanup`).
- PK: `material_id`
- Kolom (ringkas — 46 kolom): `material_id`, `material_code` (unique), `hs_code`, `material_name`, `item_type` (MATERIAL/SERVICE/FREE_SAMPLE), `category1..5`, `description`, `brand`, `color`, `gram_weight`, `cm_length`, `cm_width`, `mm_thickness`, `specification`, `qc_required`, `shelf_life_days`, `moisture_pct`, `uom_stock/purchase/usage` (LOGICAL -> uom_unit.uom_code), `purchasable`, `conversion_to_stock/usage`, `suggest_supplier_id` (LOGICAL -> sup_supplier), `tol_purchase`, `lead_time_days`, `purchase_tax_code` (PPN/NON_PPN), `free_note`, `min_stock`, `safety_stock`, `reorder_point`, `max_stock`, `cur_stock`, `activate`, `avg_unitprice`, `last_unitprice`, `def_location_id`, `costing_method` (AVG/FIFO/LIFO/STANDARD), `valuation_class`, `is_fx_sensitive`, `prefix5_id`, `create_log/by`, `update_log/by`, `old_item_code/name`, `stock_asset_account_id`/`cogs_account_id`/`gr_ir_account_id`/`cost_center_id`/`profit_center_id` (semua LOGICAL -> tm2acc), `stat_confirm` (kategori & material_code terkunci setelah true), `confirmed_at/by`
- FK internal: `prefix5_id -> mat_category`
- Kemungkinan FK logis lintas-DB: `suggest_supplier_id` -> sup_supplier (internal tm2mst sebenarnya), `stock_asset_account_id`/`cogs_account_id`/`gr_ir_account_id`/`cost_center_id`/`profit_center_id` -> tm2acc.fin_account/fin_cost_center/fin_profit_center

### `mat_sheet_discount_tier`
Tier diskon/surcharge running-meter per supplier sheet (dari Excel referensi).
- PK: `id_discount_tier`
- Kolom: `id_discount_tier`, `supplier_code` (default UJK), `rm_from`, `rm_to` (NULL=tak terbatas), `discount_pct` (negatif=surcharge), `activate`, `created_at/by`, `updated_at/by`
- FK internal: tidak ada

### `mat_sheet_rate`
Index harga sheet per substance x flute (dari Excel "PERHITUNGAN INDEX HARGA SHEET").
- PK: `id_sheet_rate`
- Kolom: `id_sheet_rate`, `substance_name`, `flute_type` (B/F|C/F|BC/F), `index_substance` (harga/m2), `activate`, `created_at/by`, `updated_at/by`
- FK internal: tidak ada
- Unique: (`substance_name`, `flute_type`)

### `org_company`
Master perusahaan (badan hukum, level tertinggi struktur organisasi).
- PK: `company_id`
- Kolom: `company_id`, `company_code` (unique), `company_name`, `npwp_number`, `address`, `phone`, `email`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: tidak ada

### `org_department`
Master departemen di bawah company (opsional di bawah division).
- PK: `department_id`
- Kolom: `department_id`, `legacy_department_id` (unique), `division_id`, `company_id`, `department_code`, `department_name`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: `division_id -> org_division`, `company_id -> org_company`
- Unique: (`company_id`, `department_code`)

### `org_division`
Master divisi (SBU) di bawah company.
- PK: `division_id`
- Kolom: `division_id`, `company_id`, `division_code`, `division_name`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: `company_id -> org_company`
- Unique: (`company_id`, `division_code`)

### `org_level`
Master level jabatan/jenjang (untuk hirarki approval & rank).
- PK: `level_id`
- Kolom: `level_id`, `level_code` (unique), `level_name`, `level_rank`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: tidak ada

### `org_position`
Master posisi/jabatan — bagian dari struktur organisasi 49-posisi (lihat memory `project_org_chart_structure`). Self-referencing untuk hirarki laporan.
- PK: `position_id`
- Kolom: `position_id`, `legacy_position_id`, `department_id`, `reports_to_position_id` (self-FK), `level_id`, `position_code`, `position_name`, `salary_hidden_from_hr` (1=gaji selalu disembunyikan dari HR Officer), `legacy_skill_rate` (referensi historis saja, belum ada tabel rate payroll V2), `activate`, `created_at/by`, `updated_at/by`
- FK internal: `department_id -> org_department`, `level_id -> org_level`, `reports_to_position_id -> org_position` (self-referencing)
- Unique: (`department_id`, `position_code`)

### `prod_category`
Kategori produk (level lama, sebelum sistem Kategori Produk Pasar baru — lihat memory `project_prodtype_multipart_category_system`).
- PK: `id_category`
- Kolom: `id_category`, `category_name`, `description`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: tidak ada

### `prod_crew`
Daftar crew/operator produksi, link opsional ke proses default & karyawan (rekonsiliasi manual dari data legacy).
- PK: `id_crew`
- Kolom: `id_crew`, `crew_name`, `process_id` (proses default), `employee_id` (link manual, belum otomatis), `legacy_employee_code` (jejak dari tm2old, format beda dari emp_employee.employee_code), `notes`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: `employee_id -> emp_employee`, `process_id -> prod_process`

### `prod_dieline_template`
Library pola dieline (F19) — dieline_spec DSL yang dikonsumsi engine render generik (TMDieline).
- PK: `id_dieline`
- Kolom: `id_dieline`, `dieline_code` (unique, mis. RTE-TUCK-01), `dieline_name`, `box_style` (REVERSE_TUCK/STRAIGHT_TUCK/SLEEVE/TRAY/...), `ecma_code`, `svg_generator` (default TMDieline), `dieline_spec` (json DSL: {canvas,vars,flat{w,h},elements[]}), `preview_sample`, `spec_version`, `notes`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: tidak ada (direferensikan `prod_type.dieline_template_id`)

### `prod_layout_group`
Header hasil ProDev Engine (`ImpositionEngine.php`) — layout imposisi gabungan multi-part per plano.
- PK: `id_layout_group`
- Kolom: `id_layout_group`, `product_id`, `mastercard_code` (placeholder MC-{id}), `plano_size_id`, `plano_w_mm`, `plano_h_mm`, `method` (TEGAK/PUTAR/CAMPUR/INTERLOCK), `up_count`, `efficiency_pct`, `waste_pct`, `die_rule_meter`, `layout_detail` (json, parameter engine + koordinat render), `status` (DRAFT/CONFIRMED), `confirmed_at/by`, `created_at/by`, `updated_at/by`
- FK internal: `plano_size_id -> mat_plano_size`, `product_id -> prod_product`

### `prod_layout_group_part`
Baris part yang tergabung dalam 1 layout_group, dengan berapa kali muncul per plano.
- PK: `id_layout_group_part`
- Kolom: `id_layout_group_part`, `id_layout_group`, `part_id`, `qty_up_in_group`, `created_at`
- FK internal: `id_layout_group -> prod_layout_group`, `part_id -> prod_part`
- Unique: (`id_layout_group`, `part_id`)

### `prod_machine`
Master mesin produksi.
- PK: `machine_id`
- Kolom: `machine_id`, `division_id`, `machine_code`, `machine_name`, `machine_type` (Cetak/Coating/Laminasi/Potong/Lainnya), `brand_model`, `year_acquired`, `serial_no`, `max_paper_size`, `color_count`, `max_speed_per_hour`, `capacity_per_shift`, `gsm_min`, `gsm_max`, `notes`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: `division_id -> org_division`
- Unique: (`division_id`, `machine_code`)

### `prod_mastercard_signoff`
Tanda tangan Master Card (5 role: DESAINER/MARKETING/PPIC/QA/PLANT_MANAGER). Approval kartu = semua role signed.
- PK: `id_signoff`
- Kolom: `id_signoff`, `product_id`, `role_name`, `signed_by`, `signed_at`
- FK internal: `product_id -> prod_product`
- Unique: (`product_id`, `role_name`)

### `prod_part`
Part/komponen produk baku (master, bukan snapshot inquiry). 60 kolom — geometri, warna, akuntansi, pisau pond.
- PK: `id_part`
- Kolom (ringkas): `id_part`, `product_id`, `part_code`, `mastercard_code` (placeholder MC-{id_part}), `part_name`, `part_type` (DC/DC+SF/DC+SHEET/KRAFT/SHEET), `qty_per_sku`, `description`, `uom_stock`, `cfg_color`, `cfg_ink`, `waste_default`, `spoilage_default`, `lc_per_plano`, `image_per_lc`, `printsize_p/l`, `dieline_ref`, `joint_type`, `ecma_code`, `cm_length/width/height`, `min_stock`, `max_stock`, `cur_stock`, `last_design_id` (FK logis iqp_inquiry), `last_costing_id` (FK logis iqp_costing), `activate`, `created_at/by`, `updated_at/by`, `finishing`, `up_per_print`, `pack_qty`, `stock_asset_account_id`/`cogs_account_id`/`cost_center_id`/`profit_center_id` (LOGICAL -> tm2acc), `die_code`, `die_w_cm/h_cm`, `sheet_raw_w_cm/h_cm`, `color_set_1/2` (json array warna), `flap_side` (TOP/BOTTOM/LEFT/RIGHT), `flap_length_mm`, `flap_min_mm/max_mm` (default dari mat_flap_rule, override per part), `flap_width_mm`, `flap_offset_mm` (Tingkat 2 interlock)
- FK internal: `product_id -> prod_product`
- Kemungkinan FK logis lintas-DB: `last_design_id` -> tm2sdi.iqp_inquiry, `last_costing_id` -> tm2sdi.iqp_costing; `stock_asset_account_id` dll -> tm2acc.fin_account

### `prod_part_artwork`
Versi artwork+proof per part (v1/v2/v3...). Part polos boleh 0 baris.
- PK: `id_artwork`
- Kolom: `id_artwork`, `part_id`, `version_no`, `artwork_path`, `artwork_thumb`, `proof_required`, `is_approved`, `approved_by/at`, `activate` (tepat 1 versi aktif per part), `created_at/by`
- FK internal: `part_id -> prod_part`
- Unique: (`part_id`, `version_no`)

### `prod_part_attachment`
Lampiran file part (JPEG wajib untuk preview inline, PDF/AI opsional referensi/download) — bisa terkait langsung ke part atau ke layout_group.
- PK: `id_attachment`
- Kolom: `id_attachment`, `part_id`, `id_layout_group`, `file_type` (JPEG/PDF/AI), `file_path`, `original_filename`, `uploaded_at`, `uploaded_by`
- FK internal: `id_layout_group -> prod_layout_group`, `part_id -> prod_part`

### `prod_part_finishing`
Finishing tambahan per part baku (urutan/seq), dengan estimasi biaya opsional.
- PK: `id_part_finishing`
- Kolom: `id_part_finishing`, `part_id`, `seq`, `finishing_type_id`, `notes`, `cost_estimate`, `created_at/by`, `updated_at/by`
- FK internal: `finishing_type_id -> ref_finishing_type`, `part_id -> prod_part`
- Unique: (`part_id`, `seq`)

### `prod_part_ink`
Warna per unit mesin per pass (CS/KS) — sumber jumlah plate & pass costing.
- PK: `id_part_ink`
- Kolom: `id_part_ink`, `part_id`, `pass_seq` (1=CS,2=KS), `pass_label`, `unit_no`, `ink_name` (CYAN/MAGENTA/YELLOW/BLACK/OLIVE/GREEN/PANTONE...), `plate_ref`, `created_at/by`
- FK internal: `part_id -> prod_part`
- Unique: (`part_id`, `pass_seq`, `unit_no`)

### `prod_part_material`
Layer bahan per part baku (multi-layer, mis. duplex+SF).
- PK: `id_part_material`
- Kolom: `id_part_material`, `part_id`, `layer_seq`, `material_id`, `rawmat_type` (utama/sekunder/SF/flute), `gram_weight`, `flute_type`, `size_p/l`, `is_primary`, `note`, `created_at/by`, `updated_at/by`
- FK internal: `material_id -> mat_raw`, `part_id -> prod_part`

### `prod_part_process`
Routing produksi per part baku (branch/flow SPLIT/MERGE) — versi master, dicopy jadi `iqp_part_process` saat inquiry.
- PK: `id_part_process`
- Kolom: `id_part_process`, `part_id`, `branch_code` (MAIN/L/R/BODY/LID), `step_seq`, `flow_type` (LINEAR/SPLIT/MERGE), `process_id`, `process_name` (snapshot), `machine_id`, `layer_ref` (FK -> prod_part_material), `qty_factor`, `note`, `created_at/by`, `updated_at/by`
- FK internal: `machine_id -> prod_machine`, `part_id -> prod_part`, `process_id -> prod_process`

### `prod_part_process_employee_rate`
Target rate produksi per jam, per kombinasi produk+part_code+proses+karyawan.
- PK: `rate_id`
- Kolom: `rate_id`, `product_id`, `part_code` (teks, bukan FK ke prod_part — lihat catatan di bawah), `process_id`, `employee_id`, `target_qty_per_hour`, `activate`, `created_by/at`, `updated_by/at`
- FK internal: `employee_id -> emp_employee`, `process_id -> prod_process`, `product_id -> prod_product`
- Unique: (`product_id`, `part_code`, `process_id`, `employee_id`)
- Catatan: `part_code` di sini teks biasa (bukan FK ke `prod_part.id_part`) — konsisten dengan gotcha yang dicatat di `production_scheduling_wo_generation_delta.md` (target rate dikunci ke `prod_product`+`part_code` teks karena `prod_part` ternyata tak pernah diisi konsisten di alur WO).

### `prod_part_process_machine_rate`
Target rate produksi per jam, per kombinasi produk+part_code+proses+mesin.
- PK: `rate_id`
- Kolom: `rate_id`, `product_id`, `part_code` (teks), `process_id`, `machine_id`, `target_qty_per_hour`, `activate`, `created_by/at`, `updated_by/at`
- FK internal: `machine_id -> prod_machine`, `process_id -> prod_process`, `product_id -> prod_product`
- Unique: (`product_id`, `part_code`, `process_id`, `machine_id`)

### `prod_part_type`
6 tipe part costing (pemilih blok formula CostingEngine). `is_engine_ready` menandai yang sudah diimplementasi.
- PK: `id_part_type`
- Kolom: `id_part_type`, `part_type_code` (unique: DC/DC+SF/DC+SHEET/KRAFT/SHEET/SHEET_DIGITAL), `part_type_label`, `pisau_rate_rp` (default rate pisau/knife), `pakai_laminasi`, `is_engine_ready`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: tidak ada

### `prod_process`
Master proses produksi (dipakai routing & Gantt scheduling).
- PK: `id_process`
- Kolom: `id_process`, `process_name`, `gantt_color`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: tidak ada

### `prod_product`
Header produk baku (Master Card/Master Product) — sumber snapshot untuk Inquiry REKALKULASI/REVISI.
- PK: `id_product`
- Kolom: `id_product`, `product_code` (unique), `design_no` (DSxxxxxx pada Master Card), `product_name`, `customer_id`, `category_id`, `description`, `uom_stock`, `uom_sales`, `conversion_to_stock`, `qty_limit_min/max`, `cm_length/width/height`, `min_stock`, `max_stock`, `cur_stock`, `default_selling_price`, `minimum_selling_price`, `stat_confirm`, `card_status` (DRAFT/APPROVED — status Master Card), `activate`, `created_at/by`, `updated_at/by`, `stock_asset_account_id`/`cogs_account_id`/`cost_center_id`/`profit_center_id` (LOGICAL -> tm2acc)
- FK internal: `category_id -> prod_category`, `customer_id -> cust_customer`

### `prod_type`
Tipe produk + dieline DSL (sumber `dieline_spec` yang dicopy ke Inquiry). Sejak pemindahan 2026-09-04, dipilih di level Header Inquiry (bukan per-Part lagi).
- PK: `type_code` (varchar)
- Kolom: `type_code`, `type_name`, `category` (PACKAGING/COMMERCIAL_PRINT/DISPLAY/MISC), `sub_category` (TUCK_BOX/TRAY/CYLINDER/FLAT_PRINT...), `box_style` (REVERSE_TUCK/STRAIGHT_TUCK/SLEEVE...), `photo_path`, `svg_generator`, `dieline_sample` (fallback statis), `dieline_spec` (json DSL), `dieline_template_id`, `ecma_code`, `part_type_default`, `material_filter`, `has_multi_part`, `sort_order`, `spec_version`, `notes`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: `dieline_template_id -> prod_dieline_template`
- Catatan: `has_multi_part` & konsep "Peran Part"/MonoForm (lihat memory `project_prodtype_multipart_category_system`) sudah ada di kolom ini sejak awal, dipakai sistem kategori produk multi-part baru 2026-09-04.

### `prod_type_param`
Parameter dimensi per tipe produk (NUM/ENUM) — mendefinisikan variabel DSL dieline.
- PK: `id_type_param`
- Kolom: `id_type_param`, `type_code`, `param_key` (nama variabel DSL), `param_label`, `unit` (mm/deg/pcs/%/x), `param_type` (NUM/ENUM), `option_json`, `is_required`, `default_value`, `default_str`, `min_value`, `max_value`, `seq`, `activate`, `created_at/updated_at`
- FK internal: tidak ada (index ke `type_code`, tidak ber-CONSTRAINT eksplisit di dump ini meski secara logis -> prod_type)
- Unique: (`type_code`, `param_key`)

### `prod_type_size`
Ukuran pasaran per tipe produk (dropdown auto-fill dimensi, fitur "Ukuran Pasaran" InqProd Engine).
- PK: `id_type_size`
- Kolom: `id_type_size`, `type_code`, `size_code`, `size_label` (mis. "Small 8x5x2 cm"), `sort_order`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: `type_code -> prod_type`

### `prod_type_size_val`
Nilai parameter per ukuran pasaran (NUM/ENUM) — detail dari `prod_type_size`.
- PK: `id_size_val`
- Kolom: `id_size_val`, `type_size_id`, `param_key`, `value_num`, `value_str`, `created_at/updated_at`
- FK internal: tidak ada eksplisit (index ke `type_size_id`, logis -> prod_type_size)
- Unique: (`type_size_id`, `param_key`)

### `ref_finishing_type`
Lookup generik jenis finishing (dipakai `prod_part_finishing`).
- PK: `id_finishing_type`
- Kolom: `id_finishing_type`, `finishing_name`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: tidak ada

### `ref_param_option`
Opsi ENUM parameter (global, lintas tipe produk) — dipakai `prod_type_param.param_type=ENUM`.
- PK: `id_option`
- Kolom: `id_option`, `param_key`, `option_code`, `option_label`, `sort_order`, `activate`
- FK internal: tidak ada

### `sup_category`
Kategori jenis supplier (ATK/BAHAN BAKU/JASA/dst).
- PK: `category_code` (varchar)
- Kolom: `category_code`, `description`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: tidak ada (direferensikan `sup_supplier.category`)

### `sup_supplier`
Master supplier.
- PK: `id_supplier`
- Kolom: `id_supplier`, `supplier_code` (unique), `supplier_name`, `nickname`, `alias_code`, `category`, `is_subcon`, `contact_name`, `default_address`, `default_area_id`, `default_area_level` (CITY/DISTRICT/VILLAGE), `postal_code`, `email`, `phone`, `phone_2`, `fax`, `website`, `payment_term`, `tax_code`, `tax_purchase_value`, `tax_service_value`, `is_blacklisted`, `note`, `activate`, `legacy_vendor_code` (unique, trace tm2old), `created_at/by`, `updated_at/by`
- FK internal: `category -> sup_category`, `payment_term -> fin_payment_term`

### `sup_supplier_bank`
Rekening bank supplier.
- PK: `id_supplier_bank`
- Kolom: `id_supplier_bank`, `supplier_id`, `bank_code`, `bank_account_no`, `bank_account_name`, `activate`, `legacy_vendor_bank_id` (unique), `created_at/by`, `updated_at/by`
- FK internal: `supplier_id -> sup_supplier`

### `sup_supplier_branch`
Cabang/alamat pengiriman supplier.
- PK: `id_supplier_branch`
- Kolom: `id_supplier_branch`, `supplier_id`, `branch_name`, `address`, `area_id`, `area_level` (CITY/DISTRICT/VILLAGE), `postal_code`, `phone`, `is_default`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: `supplier_id -> sup_supplier`

### `sup_supplier_contact`
Kontak person supplier.
- PK: `id_supplier_contact`
- Kolom: `id_supplier_contact`, `supplier_id`, `contact_name`, `position`, `phone`, `email`, `is_default`, `activate`, `note`, `created_at/by`, `updated_at/by`
- FK internal: `supplier_id -> sup_supplier`

### `sys_constant_migrated_20260902`
Konstanta pabrik key-value dipakai CostingEngine (mis. PLANO_REF_AREA, RIM_SHEETS, FILM_RATE_DEFAULT). Tabel hasil migrasi 2026-09-02 dari `sys_constant` versi tm2mst lama — nama tabel menyiratkan mungkin duplikat/legacy dari `tm2sys.sys_constant` yang jadi tabel kanonik saat ini; perlu klarifikasi/cross-check sebelum dipakai, jangan asumsikan ini yang aktif dipakai kode.
- PK: `id_constant`
- Kolom: `id_constant`, `constant_key` (unique), `constant_group` (mis. COSTING), `constant_number`, `constant_text`, `description`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: tidak ada

### `sys_holiday`
Kalender hari libur nasional/perusahaan (dipakai perhitungan hari kerja, mis. due date +14 HK).
- PK: `id_holiday`
- Kolom: `id_holiday`, `holiday_date` (unique), `holiday_name`, `holiday_type` (NATIONAL/COMPANY), `activate`, `created_at/by`, `updated_at/by`
- FK internal: tidak ada

### `uom_conversion`
Konversi antar satuan (mis. Rim ke Lembar).
- PK: `id_conversion`
- Kolom: `id_conversion`, `uom_src`, `uom_dst`, `conversion_value`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: tidak ada eksplisit (index ke `uom_src`, logis -> uom_unit.uom_code)

### `uom_unit`
Master satuan.
- PK: `id_uom`
- Kolom: `id_uom`, `uom_code` (unique), `uom_name`, `dimension` (PANJANG/LUAS/MASSA/VOLUME/JUMLAH/WAKTU/SPEC), `is_base` (satuan dasar per dimensi), `activate`, `created_at/by`, `updated_at/by`
- FK internal: tidak ada

### `wh_loc`
Lokasi/bin fisik dalam gudang (row-col-stack). Nama fisik lama `m_wh_location` (lihat memory `project_table_prefix_cleanup`/`project_warehouse_master_status`).
- PK: `location_id`
- Kolom: `location_id`, `warehouse_id`, `row_code`, `col_code`, `stack_code`, `location_code` (unique, generated {warehouse_code}-{row}-{col}-{stack}), `location_name`, `location_type` (STORAGE/WASTE — kategori tampilan, terpisah dari `is_usable`), `is_usable` (1=stok boleh dipakai produksi/dijual, 0=waste/karantina — masuk saldo tapi tidak masuk qty_available), `capacity_kg`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: `warehouse_id -> wh_warehouse`
- Unique: (`warehouse_id`, `row_code`, `col_code`, `stack_code`)

### `wh_warehouse`
Master gudang (header, lintas modul).
- PK: `id_warehouse`
- Kolom: `id_warehouse`, `warehouse_code` (unique), `warehouse_name`, `warehouse_type` (enum RM/SFG/FG/MIX/WASTE/TRANSIT), `activate`, `created_at/by`, `updated_at/by`
- FK internal: tidak ada

## Catatan
- Ini "Master netral" sesuai CLAUDE.md §2 — single source of truth utk customer, product, material, geo, currency, uom, employee/HR, organisasi, dan gudang (struktur, bukan stok — stok live di tm2inv).
- Prefix domain: `cust_` (customer), `emp_` (employee/HR), `fin_` (finance dasar: currency/kurs/payment_term), `flow_` (approval flow generik lintas-modul), `geo_` (wilayah, 5 level: country->region->city->district->village), `mat_` (material/harga bahan/spesifikasi fisik termasuk `mat_plano_size`), `org_` (struktur organisasi: company->division->department->position), `prod_` (produk & proses baku — bukan transaksi, itu di `iqp_*` tm2sdi), `ref_` (lookup generik lintas-domain), `sup_` (supplier), `sys_` (sistem/parameter — tapi lihat catatan duplikasi di bawah), `uom_` (satuan), `wh_` (struktur gudang).
- Rantai geo 5-level: `geo_country` -> `geo_region` -> `geo_city` -> `geo_district` -> `geo_village`, semua FK fisik dalam-DB (sama-sama tm2mst). Customer/supplier/employee menunjuk ke salah satu dari city/district/village lewat pasangan kolom `*_area_id` + `*_area_level` (bukan FK tunggal — level ditentukan runtime).
- Duplikasi/legacy yang perlu diwaspadai: tabel `sys_constant_migrated_20260902` ada di tm2mst, sementara tabel kanonik `sys_constant`/`sys_constant_category` ada di tm2sys (lihat `docs/database/db_sys.md`). Nama tabel ini ("_migrated_") menyiratkan hasil migrasi satu-kali yang mungkin sudah tidak dipakai aktif — perlu verifikasi ke kode (`grep -r sys_constant_migrated app/`) sebelum mengasumsikan mana yang benar-benar dibaca `CostingEngine.php`.
- Banyak kolom akuntansi (`stock_asset_account_id`, `cogs_account_id`, `gr_ir_account_id`, `cost_center_id`, `profit_center_id`) tersebar di `mat_category`, `mat_raw`, `prod_part`, `prod_product` — semuanya FK LOGIS ke `tm2acc.fin_account`/`fin_cost_center`/`fin_profit_center` (tidak ada constraint fisik, sesuai aturan lintas-DB CLAUDE.md §2).
- Pola "snapshot" terlihat jelas antara master (`prod_part`, `prod_part_material`, `prod_part_process`) dan transaksi inquiry (`iqp_part`, `iqp_part_material`, `iqp_part_process` di tm2sdi) — struktur kolom nyaris paralel, sesuai prinsip "Inquiry deep-copy struktur produk" di CLAUDE.md §4.
- `prod_part_process_employee_rate`/`prod_part_process_machine_rate` sengaja pakai `part_code` teks (bukan FK `id_part`) — dicatat sebagai gotcha nyata di `production_scheduling_wo_generation_delta.md` karena `prod_part.id_part` ternyata tak pernah diisi konsisten sepanjang alur WO.
- Tabel `flow_*` (flow_config, flow_config_step, flow_log, flow_position_acting) adalah sistem approval generik yang dipakai lintas-modul (PR/PO/PV/PRT/CO/DO dst) — desain polymorphic (`flow_log.doc_id` tanpa FK fisik) karena dokumen tersebar di banyak database berbeda.
- Tabel geo (terutama `geo_city`, `geo_district`, `geo_village`) berisi data sangat besar (ratusan ribu baris) — hasil import resmi data wilayah Indonesia, bukan input manual.
