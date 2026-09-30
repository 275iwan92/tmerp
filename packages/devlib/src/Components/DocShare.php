<?php

namespace Devlib\Components;

use Devlib\Theme;

// Print + Email untuk SETIAP form/dokumen (user 2026-09-29). Layar cukup menaruh 1 tag dan menunjuk area dokumennya:
//   <x-dl::doc-share target="#recalc-doc" type="inquiry-recalc" :doc-id="$id" :number="$no" title="Inquiry Rekalkulasi" :email-to="$emailCustomer" />
// Print ▾ : PDF (unduh), JPEG (unduh), HTML online (link + QR, dibuka tanpa login), Printer (dialog cetak browser).
// Email   : To/CC/BCC/Subjek/Pesan + pilihan isi: link online, dokumen (gambar) di isi e-mail, lampiran PDF.
// Setiap hasil membawa QR ke salinan online (snapshot saat dicetak, disimpan lewat endpoint -> sys_doc_share di tmerp).
// Tanpa endpoint (situs DevLib) PDF/JPEG/Printer tetap jalan, tanpa QR/online/email.
// Bahasa cetak (Indonesia | English | ...): beda dari bahasa layar -> halaman yang sama dirender ulang dengan ?print_lang=xx
// (iframe tersembunyi; Locale::lang() menghormatinya hanya untuk permintaan itu) -> label, angka & tanggal ikut bahasa cetak.
// Yang tercetak = data TERSIMPAN (perubahan belum di-Save tidak ikut, diberi peringatan).
// Elemen di dalam target bertanda data-dl-noprint (atau tombol/action-bar) tidak ikut tercetak.
class DocShare extends DevlibComponent
{
    public string $uid;

    public function __construct(
        public string $target = '',
        public string $type = 'doc',
        public int|string|null $docId = null,
        public ?string $number = null,
        public string $title = 'Dokumen',
        public ?string $emailTo = null,
        public ?string $subject = null,
        public ?string $message = null,
        public ?string $endpoint = null,
        public string $orientation = 'portrait',
        public bool $email = true,
        public string $size = 'md',
    ) {
        $this->endpoint ??= config('devlib.doc_share_url');
        $this->uid = 'docshare-'.substr(md5($type.$docId.$target.spl_object_id($this)), 0, 8);
    }

    public function tag(): string
    {
        return 'x-dl::doc-share';
    }

    public function source(): string
    {
        return $this->endpoint ? 'sys_doc_share / sys_doc_email' : 'tanpa penyimpanan (PDF/JPEG saja)';
    }

    // Bahasa cetak (user 2026-10-01): bebas dari bahasa layar; daftar = bahasa aktif (tmerp: sys_languages).
    public function langs(): array
    {
        return array_map(fn ($l) => $l['name'] ?? '', \Devlib\Locale::languages());
    }

    // Tema gelap -> tema cerah sewarna saat dokumen dirender (kertas selalu terang).
    public function printThemes(): array
    {
        $light = collect(Theme::ALL)->filter(fn ($t) => $t[1] === 'cerah')->mapWithKeys(fn ($t, $code) => [$t[2] => $code]);

        return collect(Theme::ALL)->filter(fn ($t) => in_array($t[1], ['dark', 'hitam'], true))->map(fn ($t) => $light[$t[2]] ?? Theme::DEFAULT)->all();
    }

    public function render()
    {
        return view('devlib::components.doc-share');
    }
}
