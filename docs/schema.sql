-- =====================================================================
--  TELASIH MAKMUR — ERP  |  Skema tambahan (MySQL)
--  Tabel: quotes, login_attempts, languages, translations
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) QUOTES  — kata-kata penyemangat di halaman utama
--    Semakin banyak baris, semakin acak yang bisa tampil.
-- ---------------------------------------------------------------------
CREATE TABLE quotes (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  quote_text  VARCHAR(500) NOT NULL,           -- kata-kata
  author      VARCHAR(150) NOT NULL,           -- penulis
  is_active   TINYINT(1)  NOT NULL DEFAULT 1,  -- nonaktifkan tanpa hapus
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO quotes (quote_text, author) VALUES
('Cara memulai adalah berhenti bicara dan mulai melakukan.', 'Walt Disney'),
('Kesuksesan adalah hasil dari persiapan, kerja keras, dan belajar dari kegagalan.', 'Colin Powell'),
('Disiplin adalah jembatan antara tujuan dan pencapaian.', 'Jim Rohn'),
('Kerja keras mengalahkan bakat ketika bakat tidak bekerja keras.', 'Tim Notke'),
('Sedikit demi sedikit, lama-lama menjadi bukit.', 'Peribahasa'),
('Fokus pada progres, bukan kesempurnaan.', 'Anonim'),
('Jangan menunggu waktu yang tepat. Mulailah, dan jadikan waktumu tepat.', 'Anonim'),
('Hari ini harus lebih baik daripada kemarin.', 'Anonim');

-- Ambil satu quote acak (dipakai halaman utama):
--   SELECT quote_text, author FROM quotes
--   WHERE is_active = 1 ORDER BY RAND() LIMIT 1;

-- ---------------------------------------------------------------------
-- 2) LOGIN_ATTEMPTS  — audit setiap percobaan login (berhasil/gagal)
--    Diisi SERVER-SIDE setiap kali user coba masuk.
-- ---------------------------------------------------------------------
CREATE TABLE login_attempts (
  id           BIGINT AUTO_INCREMENT PRIMARY KEY,
  username     VARCHAR(100) NOT NULL,
  success      TINYINT(1)   NOT NULL,           -- 1 = berhasil, 0 = gagal
  ip_address   VARCHAR(45),                     -- mendukung IPv6
  user_agent   VARCHAR(255),
  attempted_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_time (username, attempted_at),
  INDEX idx_success   (success)
);

-- Contoh saat login gagal:
--   INSERT INTO login_attempts (username, success, ip_address, user_agent)
--   VALUES ('budi', 0, '192.168.1.10', 'Mozilla/5.0 ...');
-- Cek 5 percobaan gagal terakhir sebuah akun (deteksi brute force):
--   SELECT * FROM login_attempts
--   WHERE username = 'budi' AND success = 0
--   ORDER BY attempted_at DESC LIMIT 5;

-- ---------------------------------------------------------------------
-- 3) LANGUAGES + TRANSLATIONS  — multi bahasa
-- ---------------------------------------------------------------------
CREATE TABLE languages (
  code       VARCHAR(5)  PRIMARY KEY,           -- 'id', 'en'
  name       VARCHAR(50) NOT NULL,
  is_default TINYINT(1)  DEFAULT 0
);

INSERT INTO languages (code, name, is_default) VALUES
('id', 'Bahasa Indonesia', 1),
('en', 'English',          0);

CREATE TABLE translations (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  lang_code   VARCHAR(5)   NOT NULL,
  trans_key   VARCHAR(100) NOT NULL,            -- kunci teks, mis. 'login_heading'
  trans_value VARCHAR(500) NOT NULL,            -- teks per bahasa
  UNIQUE KEY uq_lang_key (lang_code, trans_key),
  FOREIGN KEY (lang_code) REFERENCES languages(code)
);

INSERT INTO translations (lang_code, trans_key, trans_value) VALUES
('id','brand_tagline','Sistem ERP terintegrasi.'),        ('en','brand_tagline','Integrated ERP system.'),
('id','login_heading','Masuk'),                            ('en','login_heading','Sign in'),
('id','login_sub','Gunakan akun terdaftar untuk masuk.'), ('en','login_sub','Use your registered account to sign in.'),
('id','label_username','Username'),                        ('en','label_username','Username'),
('id','label_password','Password'),                        ('en','label_password','Password'),
('id','remember','Ingat saya'),                            ('en','remember','Remember me'),
('id','forgot','Lupa password?'),                          ('en','forgot','Forgot password?'),
('id','btn_login','Masuk'),                                ('en','btn_login','Sign in'),
('id','err_empty','Isi username dan password.'),           ('en','err_empty','Enter your username and password.'),
('id','err_wrong','Username atau password salah.'),        ('en','err_wrong','Incorrect username or password.'),
('id','fp_heading','Lupa password'),                       ('en','fp_heading','Forgot password'),
('id','fp_sub','Masukkan email akun Anda. Kami kirim tautan untuk atur ulang password.'),
                                                           ('en','fp_sub','Enter your account email. We will send a reset link.'),
('id','label_email','Email'),                              ('en','label_email','Email'),
('id','btn_send','Kirim tautan reset'),                    ('en','btn_send','Send reset link'),
('id','back_login','Kembali ke halaman masuk'),            ('en','back_login','Back to sign in'),
('id','fp_done','Jika email terdaftar, tautan reset sudah dikirim.'),
                                                           ('en','fp_done','If the email is registered, a reset link has been sent.');

-- Ambil semua teks satu bahasa (di-cache aplikasi):
--   SELECT trans_key, trans_value FROM translations WHERE lang_code = 'id';
