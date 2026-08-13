-- ============================================================================
-- PATCH — geo_region: pemekaran Papua 2022 (34 -> 38 provinsi)
-- TMERP · PT. Telasih Makmur
-- Target: skema dump asli (fin_currency.sql, sumber TM188/erptest) — kolom
--         geo_region(id_region, country_id, region_name, source_code)
--         geo_city(id_city, region_id, city_type, city_name, source_code)
-- ============================================================================
--
-- CATATAN JUJUR soal source_code:
-- Kode numerik BPS resmi utk 4 provinsi baru (Papua Selatan/Tengah/Pegunungan/
-- Papua Barat Daya) TIDAK saya isi (dibiarkan NULL) karena sumber yang bisa
-- saya akses saling kontradiksi (tabel gaya Kemendagri/ISO vs nomor katalog
-- publikasi BPS sendiri kasih urutan beda). Daripada nebak dan kelihatan
-- meyakinkan padahal salah, mending kosong dulu.
-- Rujukan resmi utk isi belakangan: "Keputusan Menteri Dalam Negeri Nomor
-- 300.2.2-2138 Tahun 2025 tentang Pemberian dan Pemutakhiran Kode, Data
-- Wilayah Administrasi Pemerintahan, dan Pulau" (Kemendagri).
--
-- Yang SUDAH diverifikasi silang dari beberapa sumber independen (aman
-- dipakai) = daftar kabupaten/kota per provinsi baru di bawah ini. Totalnya
-- pas: Papua lama (29 kab/kota) = sisa Papua baru (9) + Papua Selatan (4) +
-- Papua Tengah (8) + Papua Pegunungan (8). Papua Barat lama (13 kab) = sisa
-- Papua Barat baru (7) + Papua Barat Daya (6).
--
-- WAJIB sebelum jalan: backup tabel geo_region + geo_city (mysqldump atau
-- export Navicat). Script ini pakai TRANSACTION — kalau preview di STEP 3
-- keliatan aneh, ROLLBACK, jangan COMMIT.
-- ============================================================================

START TRANSACTION;

-- ----------------------------------------------------------------------------
-- STEP 1 — Insert 4 provinsi baru (country_id=101 = Indonesia di dump ini;
-- cek dulu id_country Indonesia di DB kamu kalau beda, lihat query verifikasi
-- di bagian bawah file)
-- ----------------------------------------------------------------------------
INSERT INTO `geo_region` (`country_id`, `region_name`, `source_code`) VALUES
  (101, 'PAPUA SELATAN', NULL),
  (101, 'PAPUA TENGAH', NULL),
  (101, 'PAPUA PEGUNUNGAN', NULL),
  (101, 'PAPUA BARAT DAYA', NULL);

-- Ambil id yang baru dibuat (urutan sesuai INSERT di atas -> LAST_INSERT_ID
-- = id baris pertama, +1/+2/+3 utk baris berikutnya, karena AUTO_INCREMENT)
SET @id_papua_selatan   = (SELECT id_region FROM geo_region WHERE country_id=101 AND region_name='PAPUA SELATAN');
SET @id_papua_tengah    = (SELECT id_region FROM geo_region WHERE country_id=101 AND region_name='PAPUA TENGAH');
SET @id_papua_pegunungan= (SELECT id_region FROM geo_region WHERE country_id=101 AND region_name='PAPUA PEGUNUNGAN');
SET @id_papua_barat_daya= (SELECT id_region FROM geo_region WHERE country_id=101 AND region_name='PAPUA BARAT DAYA');

-- id provinsi lama (dicari by nama, BUKAN hardcode angka — lebih aman kalau
-- id_region di DB kamu beda dari dump referensi)
SET @id_papua_lama       = (SELECT id_region FROM geo_region WHERE country_id=101 AND region_name='PAPUA');
SET @id_papua_barat_lama = (SELECT id_region FROM geo_region WHERE country_id=101 AND region_name='PAPUA BARAT');

-- ----------------------------------------------------------------------------
-- STEP 2 — Remap geo_city.region_id kabupaten/kota yang pindah provinsi
-- (geo_district & geo_village TIDAK perlu disentuh — mereka nempel ke
-- city_id, bukan region_id, jadi otomatis ikut pindah bareng city induknya)
-- ----------------------------------------------------------------------------

-- Papua Selatan (4 kab, ibukota Merauke)
UPDATE geo_city SET region_id = @id_papua_selatan
WHERE region_id = @id_papua_lama
  AND city_name IN ('MERAUKE','BOVEN DIGOEL','MAPPI','ASMAT');

-- Papua Tengah (8 kab, ibukota Nabire)
UPDATE geo_city SET region_id = @id_papua_tengah
WHERE region_id = @id_papua_lama
  AND city_name IN ('NABIRE','PUNCAK JAYA','PANIAI','MIMIKA','PUNCAK','DOGIYAI','INTAN JAYA','DEIYAI');

-- Papua Pegunungan (8 kab, ibukota Jayawijaya/Wamena)
UPDATE geo_city SET region_id = @id_papua_pegunungan
WHERE region_id = @id_papua_lama
  AND city_name IN ('JAYAWIJAYA','LANNY JAYA','MAMBERAMO TENGAH','NDUGA','TOLIKARA','YAHUKIMO','YALIMO','PEGUNUNGAN BINTANG');

-- Papua Barat Daya (5 kab + 1 kota, ibukota Kota Sorong)
UPDATE geo_city SET region_id = @id_papua_barat_daya
WHERE region_id = @id_papua_barat_lama
  AND city_name IN ('SORONG','SORONG SELATAN','RAJA AMPAT','TAMBRAUW','MAYBRAT');
  -- 'SORONG' cocok 2 baris (KOTA & KABUPATEN, dibedakan city_type) — keduanya
  -- memang pindah ke Papua Barat Daya, jadi tidak perlu filter city_type.

-- ----------------------------------------------------------------------------
-- STEP 3 — PREVIEW sebelum commit. Cek manual jumlah & nama sebelum lanjut.
-- ----------------------------------------------------------------------------
SELECT r.region_name, r.id_region, COUNT(c.id_city) AS jumlah_kab_kota
FROM geo_region r
LEFT JOIN geo_city c ON c.region_id = r.id_region
WHERE r.country_id = 101
  AND r.region_name IN ('PAPUA','PAPUA BARAT','PAPUA SELATAN','PAPUA TENGAH','PAPUA PEGUNUNGAN','PAPUA BARAT DAYA')
GROUP BY r.region_name, r.id_region
ORDER BY r.region_name;
-- Ekspektasi: PAPUA=9 · PAPUA BARAT=7 · PAPUA SELATAN=4 · PAPUA TENGAH=8 ·
--             PAPUA PEGUNUNGAN=8 · PAPUA BARAT DAYA=6  (total tetap 42, gak
--             ada kab/kota yang hilang atau dobel)

-- Kalau angka di atas COCOK -> lanjut:
COMMIT;

-- Kalau angka di atas GANJIL (ada yang 0, atau total gak 42) -> jangan commit:
-- ROLLBACK;
-- (lalu cek: mungkin ejaan city_name di DB kamu beda dari yang saya tulis,
-- misal spasi/singkatan berbeda — jalankan dulu:
--   SELECT city_name FROM geo_city WHERE region_id=@id_papua_lama;
-- buat lihat ejaan aslinya, sesuaikan daftar IN (...) di STEP 2, ulangi.)
