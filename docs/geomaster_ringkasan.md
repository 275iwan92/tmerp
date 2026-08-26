# RINGKASAN DOMAIN `geo_` — Master Wilayah
**ERP PT. Telasih Makmur · dibawa ke prompt baru**

---

## Keputusan terkunci
- **Prefix:** `geo_` (geografis), `fin_` (currency).
- **Konsep:** hierarki recursive ber-id; 1 ID = alamat lengkap otomatis (via VIEW).
- **User tidak isi manual** — data diisi dari sumber resmi (import), bukan ketik tangan.

---

## Struktur tabel (4-level, siap 5-level)

```
geo_country  (id_country PK, country_name, country_code, iso_code, currency_id FK, tax_percentage)
geo_region   (id_region PK, country_id FK, region_name)         -- provinsi/state
geo_city     (id_city PK, region_id FK, city_type, city_name)   -- kota/kabupaten
geo_district (id_district PK, city_id FK, district_name)        -- kecamatan
[opsional]   geo_village                                         -- kelurahan (5-level)
```

**Kunci desain penting:**
- **`city_type` (KOTA|KABUPATEN)** menyelesaikan kasus Malang — nama sama, jenis beda, id beda.
- FK antar-level pakai **id**, bukan teks (existing pakai teks — harus diperbaiki).
- **VIEW `v_geo_fulladdress`** → dropdown "1x pilih ID = INDONESIA > JAWA TIMUR > KOTA Malang > Klojen".
- Master (customer/supplier) simpan **1 kolom** `city_id` atau `district_id`.

---

## Currency ↔ Country (arah relasi DIBALIK)
- **`geo_country.currency_id` → FK ke `fin_currency`** (1 negara→1 currency; 1 currency→banyak negara).
- Existing `m_currency.country` = arah SALAH (bug: EUR diisi "EURO"). Buang setelah migrasi.
- **`tax_percentage` pindah** dari currency → country (pajak = properti negara, bukan mata uang).

---

## Data existing (sumber migrasi)
- `m_place_country` → 255 negara (FK ke country_name teks = lemah).
- `m_place_area` → 47.821 baris flat; region_name + country_name = **teks** (harus jadi FK id).
- ~244 provinsi unik; area_type hanya KABUPATEN/KOTA.
- `m_currency` → 21 mata uang.

---

## Sumber import (via GitHub — bisa diakses Claude)
- **Indonesia:** `emsifa/api-wilayah-indonesia` (provinsi→kota→kecamatan→kelurahan, sumber Kemendagri).
- **Global:** `dr5hn/countries-states-cities-database` (250 negara, ~5rb state, ~150rb kota).
- ⚠️ **Kecamatan TIDAK ada di data existing** — Claude tak boleh mengarang, wajib import.

---

## 3 KEPUTUSAN PENDING (belum dijawab)
1. Indonesia: **4-level (kecamatan)** atau **5-level (+kelurahan, ~80rb baris)**?
2. Luar negeri: sampai **kota** (~150rb global) atau cukup **negara+provinsi**?
3. Konfirmasi **sekali-download**, bukan live-sync API?

---

## Langkah berikutnya
Jawab 3 pending → Claude download sumber → cek struktur (no assumption) → generate SQL siap eksekusi (country + region + city + currency dari data existing/import).
