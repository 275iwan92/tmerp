# PANDUAN PENGEMBANGAN LARAVEL 13 + LIVEWIRE 4 + PHP 8.4
## Offset Packaging ERP — Modul New Product Inquiry & Multi-Part CAD

Dokumentasi ini dibuat khusus untuk **Developer Team** dalam mengimplementasikan dan menjalankan modul Inquiry Kemasan Offset ke dalam ekosistem Laravel 13, Livewire v4, dan Tailwind CSS.

---

### 1. Spesifikasi Stack & Dependensi
- **PHP**: v8.4+ (Typed properties, readonly classes, match expressions, strict types)
- **Framework**: Laravel 13
- **Reactive UI**: Livewire v4 (dengan Alpine.js)
- **CSS Styling**: Tailwind CSS v4 (`@tailwindcss/vite`)
- **CAD Vector Engine**: Standalone PHP Service (`App\Services\DielineSvgGenerator`)
- **Database**: SQLite / MySQL / PostgreSQL dengan migrasi lengkap

---

### 2. Langkah Cepat Instalasi (Quick Start)

Jalankan perintah berikut di terminal:

```bash
# 1. Install dependensi PHP via Composer
composer install

# 2. Install dependensi frontend & icon
npm install

# 3. Salin environment configuration
cp .env.example .env
php artisan key:generate

# 4. Jalankan Database Migration & Sample Data Seeder (Dus Sepatu 2-Pc)
php artisan migrate --seed --seeder=InquiryShoeBoxSeeder

# 5. Jalankan server lokal Vite & Laravel
npm run dev &
php artisan serve
```

Akses aplikasi di browser: `http://localhost:8000`

---

### 3. Arsitektur Komponen Utama

| File | Fungsi |
| :--- | :--- |
| `app/Livewire/InquiryForm.php` | State management Livewire v4: Commercial header, Tier Qty, Multi-part state, Dieline split-screen mode, 3D exploded view elevation |
| `app/Services/DielineSvgGenerator.php` | Kalkulator CAD garis pisau pon (Merah = Cut, Hijau = Crease, Biru = Dimensi) untuk Badan & Tutup |
| `resources/views/livewire/inquiry-form.blade.php` | Blade template lengkap dengan kontrol split view, tombol unduh SVG CAD, simulator 3D interaktif |
| `app/Http/Controllers/InquiryController.php` | Controller download endpoint untuk SVG CAD murni |
| `database/migrations/...` | Skema database relasional: Inquiries, Parts, Tiers, Toolings |

---

### 4. Fitur Khusus: 2-Piece Multi-Part Shoe Box
1. **Dua Dieline Terpisah**: Badan (`320x200x120 mm`) dan Tutup (`325x205x35 mm`) memiliki bentangan pisau pon masing-masing.
2. **Export SVG CAD Standalone**: Developer / Prepress CAD dapat langsung mengunduh file `.svg` asli untuk di-import ke software Illustrator, ArtiosCAD, atau mesin cutting plotter.
3. **Exploded 3D Mockup**: Slider `lidElevation` (0px - 100px) memungkinkan visualisasi tutup terangkat secara interaktif.
