-- ═══════════════════════════════════════════════════════════════════
-- seed_uom.sql — Seed Master Satuan + Konversi  (tm2mst)
-- TMERP · PT. Telasih Makmur
-- Target : tm2mst.uom_unit  &  tm2mst.uom_conversion
-- Delta  : + kolom dimension (VARCHAR 20) + is_base (TINYINT) di uom_unit
--          (grouping & pencegah konversi lintas-dimensi). Drop bila tak dipakai.
-- Aturan konversi: 1 <uom_src> = conversion_value × <uom_dst> (WAJIB 1 dimensi).
-- ═══════════════════════════════════════════════════════════════════

-- ── DELTA kolom (jalankan sekali; abaikan bila sudah ada) ──
ALTER TABLE `uom_unit`
  ADD COLUMN `dimension` VARCHAR(20) NULL DEFAULT NULL COMMENT 'PANJANG/LUAS/MASSA/VOLUME/JUMLAH/WAKTU/SPEC' AFTER `uom_name`,
  ADD COLUMN `is_base`   TINYINT(1)  NOT NULL DEFAULT 0 COMMENT 'satuan dasar per dimensi' AFTER `dimension`;

-- ════════════════════════════════════════════
-- 1) uom_unit  — satuan internasional + offset printing
-- ════════════════════════════════════════════
INSERT INTO `uom_unit` (`uom_code`,`uom_name`,`dimension`,`is_base`,`is_active`,`created_by`) VALUES
-- PANJANG
('MM'   ,'Milimeter'                 ,'PANJANG',1,1,'SEED'),
('CM'   ,'Sentimeter'                ,'PANJANG',0,1,'SEED'),
('M'    ,'Meter'                     ,'PANJANG',0,1,'SEED'),
('INCH' ,'Inci'                      ,'PANJANG',0,1,'SEED'),
('KM'   ,'Kilometer'                 ,'PANJANG',0,1,'SEED'),
-- LUAS
('MM2'  ,'Milimeter persegi'         ,'LUAS'   ,1,1,'SEED'),
('CM2'  ,'Sentimeter persegi'        ,'LUAS'   ,0,1,'SEED'),
('M2'   ,'Meter persegi'             ,'LUAS'   ,0,1,'SEED'),
-- MASSA
('MG'   ,'Miligram'                  ,'MASSA'  ,0,1,'SEED'),
('GR'   ,'Gram'                       ,'MASSA'  ,1,1,'SEED'),
('KG'   ,'Kilogram'                  ,'MASSA'  ,0,1,'SEED'),
('KW'   ,'Kuintal'                   ,'MASSA'  ,0,1,'SEED'),
('TON'  ,'Ton'                        ,'MASSA'  ,0,1,'SEED'),
-- VOLUME (tinta/varnish/coating)
('ML'   ,'Mililiter'                 ,'VOLUME' ,1,1,'SEED'),
('L'    ,'Liter'                      ,'VOLUME' ,0,1,'SEED'),
('M3'   ,'Meter kubik'               ,'VOLUME' ,0,1,'SEED'),
('GALON','Galon'                      ,'VOLUME' ,0,1,'SEED'),
('DRUM' ,'Drum (200 L)'              ,'VOLUME' ,0,1,'SEED'),
-- JUMLAH (umum + offset printing)
('PCS'  ,'Buah / Pieces'             ,'JUMLAH' ,1,1,'SEED'),
('LBR'  ,'Lembar'                     ,'JUMLAH' ,0,1,'SEED'),
('SET'  ,'Set'                        ,'JUMLAH' ,0,1,'SEED'),
('PSG'  ,'Pasang'                     ,'JUMLAH' ,0,1,'SEED'),
('LUSIN','Lusin (12)'                ,'JUMLAH' ,0,1,'SEED'),
('KODI' ,'Kodi (20)'                 ,'JUMLAH' ,0,1,'SEED'),
('GROS' ,'Gros (144)'                ,'JUMLAH' ,0,1,'SEED'),
('RIM'  ,'Rim (500 lembar)'          ,'JUMLAH' ,0,1,'SEED'),
('PLANO','Lembar Plano'              ,'JUMLAH' ,0,1,'SEED'),
('UP'   ,'Up (naik cetak)'           ,'JUMLAH' ,0,1,'SEED'),
('EKS'  ,'Eksemplar'                 ,'JUMLAH' ,0,1,'SEED'),
('PAK'  ,'Pak'                        ,'JUMLAH' ,0,1,'SEED'),
('BOX'  ,'Box / Dus'                 ,'JUMLAH' ,0,1,'SEED'),
('KARTON','Karton'                    ,'JUMLAH' ,0,1,'SEED'),
('BAL'  ,'Bal'                        ,'JUMLAH' ,0,1,'SEED'),
('ROLL' ,'Rol / Gulungan'            ,'JUMLAH' ,0,1,'SEED'),
('PALET','Palet'                      ,'JUMLAH' ,0,1,'SEED'),
-- WAKTU (kapasitas produksi)
('MNT'  ,'Menit'                      ,'WAKTU'  ,1,1,'SEED'),
('JAM'  ,'Jam'                        ,'WAKTU'  ,0,1,'SEED'),
('HARI' ,'Hari'                       ,'WAKTU'  ,0,1,'SEED'),
-- SPEC (atribut non-konvertibel; TIDAK ikut uom_conversion)
('GSM'  ,'Gramatur (g/m²)'           ,'SPEC'   ,0,1,'SEED'),
('WARNA','Warna cetak'               ,'SPEC'   ,0,1,'SEED'),
('PLATE','Plat cetak'                ,'SPEC'   ,0,1,'SEED'),
('FILM' ,'Film'                       ,'SPEC'   ,0,1,'SEED');

-- ════════════════════════════════════════════
-- 2) uom_conversion  — 1 uom_src = conversion_value × uom_dst  (satu-dimensi)
-- ════════════════════════════════════════════
INSERT INTO `uom_conversion` (`uom_src`,`uom_dst`,`conversion_value`,`is_active`,`created_by`) VALUES
-- PANJANG (basis MM)
('CM'   ,'MM' ,       10.000000,1,'SEED'),
('M'    ,'CM' ,      100.000000,1,'SEED'),
('M'    ,'MM' ,     1000.000000,1,'SEED'),
('INCH' ,'MM' ,       25.400000,1,'SEED'),
('KM'   ,'M'  ,     1000.000000,1,'SEED'),
-- LUAS (basis MM2)
('CM2'  ,'MM2',      100.000000,1,'SEED'),
('M2'   ,'CM2',    10000.000000,1,'SEED'),
('M2'   ,'MM2', 1000000.000000,1,'SEED'),
-- MASSA (basis GR)
('GR'   ,'MG' ,     1000.000000,1,'SEED'),
('KG'   ,'GR' ,     1000.000000,1,'SEED'),
('KW'   ,'KG' ,      100.000000,1,'SEED'),
('TON'  ,'KG' ,     1000.000000,1,'SEED'),
('TON'  ,'KW' ,       10.000000,1,'SEED'),
-- VOLUME (basis ML)
('L'    ,'ML' ,     1000.000000,1,'SEED'),
('M3'   ,'L'  ,     1000.000000,1,'SEED'),
('GALON','L'  ,        3.785412,1,'SEED'),
('DRUM' ,'L'  ,      200.000000,1,'SEED'),
-- JUMLAH (basis PCS/LBR)
('LUSIN','PCS',       12.000000,1,'SEED'),
('KODI' ,'PCS',       20.000000,1,'SEED'),
('GROS' ,'PCS',      144.000000,1,'SEED'),
('GROS' ,'LUSIN',     12.000000,1,'SEED'),
('RIM'  ,'LBR',      500.000000,1,'SEED'),
-- WAKTU (basis MNT)
('JAM'  ,'MNT',       60.000000,1,'SEED'),
('HARI' ,'JAM',       24.000000,1,'SEED');

-- CATATAN:
-- • PLANO→LBR & konversi berbasis GSM (kg↔lembar) TIDAK di-seed: item-specific
--   (tergantung belah plano / gramatur), dihitung di engine costing, bukan master.
-- • Konversi transitif (mis. TON→GR) diturunkan runtime via basis dimensi, tak perlu di-seed.
