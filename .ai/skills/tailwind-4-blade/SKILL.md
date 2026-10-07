---
name: tailwind-4-blade
description: Aturan Tailwind CSS 4 untuk Blade / Livewire / komponen DevLib di TMERP. Pakai saat menambah class Tailwind atau token warna. Prioritas di bawah devlib-oo dan tmerp-stack-rules; di atas tailwindcss-development generik.
license: Apache-2.0
metadata:
  adapted-from: Gentleman-Programming/Gentleman-Skills curated/tailwind-4 (v1.1), disesuaikan untuk Blade (contoh aslinya React / cn())
---

# Tailwind 4 di Blade / DevLib

Prioritas: `devlib-oo` > `tmerp-stack-rules` > skill ini > `tailwindcss-development` (Boost). Layar v2 tidak menulis class Tailwind sendiri (komponen `x-dl::*` yang menanggungnya); skill ini untuk **komponen / objek** yang memang memegang markup.

## Pohon keputusan
```
Class Tailwind ada?           -> class="..." (statis)
Nilai dinamis dari PHP/data?  -> style="width: {{ $pct }}%" atau CSS custom property (--progress)
Kondisional?                  -> @class([...]) / $attributes->class([...]) (setara cn() di React)
Pustaka tak menerima class?   -> konstanta var(--color-...) di PHP/JS, hanya untuk prop pustaka
```

## Aturan wajib
1. **Tanpa hex di class / style** (`bg-[#1e293b]` dilarang). Warna = token `@theme` (`--color-accent`, `--color-field-*`, `bg-accent-soft`) supaya ikut tema `sys_theme`.
2. **Tanpa `var()` di class** (`bg-[var(--x)]`). Daftarkan token di `@theme` lalu pakai class semantiknya (`bg-accent`).
3. **Tidak ada `tailwind.config.js`**: Tailwind 4 = konfigurasi CSS-first (`@import "tailwindcss"; @theme { ... }` di `resources/css/app.css`).
4. **Class dinamis jangan dirangkai** (`"bg-{$tone}-500"`): Tailwind tidak melihatnya. Pakai peta lengkap (mis. `Tone`) berisi string utuh.
5. **Arbitrary value** (`w-[327px]`) hanya untuk ukuran satu kali; warna tidak boleh.
6. **Dark / mode**: pakai variabel tema (`sys_theme`), bukan `dark:` bertebaran.
7. **Selesai ubah class: `npm run build`** (Vite production build, bukan hot reload).
8. Tidak ada `cn()` / `clsx` / `twMerge` (itu React); gabung class di Blade dengan `@class` / `$attributes->merge()`.

## Pola umum (referensi cepat)
- Flex: `flex items-center justify-between gap-4`; kolom: `flex flex-col gap-2`
- Grid responsif: `grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6`
- Spasi: `p-4`, `px-4 py-2`, `mx-auto`, `mt-8 mb-4`
- State: `hover:` `focus:` `active:` `group-hover:` `disabled:`
- Responsif mobile-first: `w-full md:w-1/2 lg:w-1/3`, `hidden md:block`
