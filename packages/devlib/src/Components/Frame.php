<?php

namespace Devlib\Components;

// Bingkai layar penuh untuk HP/tablet/laptop/desktop di resolusi apa pun.
// Dipakai halaman login dan nanti dashboard. Yang cukup disebut:
//   bg       = folder (atau file) di media/bg, mis. "login" atau "login/contoh-01-navy.jpg"
//   bg-order = random | sequence (urut, lanjut tiap kali halaman dibuka)
//   bg-interval = detik ganti gambar selama halaman terbuka (0 = tidak berganti)
//   box      = center | left | right | top | bottom | full   (di HP selalu penuh layar)
//   box-style = panel | card | glass   (default: panel untuk sisi, card untuk tengah)
//   color    = light | dark | navy | accent   (warna box)
//   widgets  = "brand@top-left, clock@top-right, quote@bottom-left, weather@top-right, greeting@..., date@..., announce@..."
//   quotes   = file teks di media/text (satu kata motivasi per baris: "teks | penulis")
class Frame extends DevlibComponent
{
    public const POSITIONS = ['top-left', 'top-center', 'top-right', 'bottom-left', 'bottom-center', 'bottom-right', 'middle-left', 'middle-right'];

    public function __construct(
        public string $bg = 'login',
        public string $bgOrder = 'random',
        public int $bgInterval = 0,
        public string $overlay = 'dark',
        public string $box = 'right',
        public ?string $boxStyle = null,
        public string $color = 'light',
        public ?string $boxWidth = null,
        public bool $resizable = false,
        public string $widgets = '',
        public string $quotes = 'motivasi.txt',
        public string $quoteOrder = 'random',
        public int $quoteInterval = 0,
        public string $announce = 'pengumuman.txt',
        public string $city = 'Surabaya',
        public float $lat = -7.2575,
        public float $lon = 112.7521,
        public string $brand = 'TELASIH MAKMUR',
        public string $tagline = 'packaging solution',
    ) {
        $this->boxStyle ??= $box === 'center' ? 'card' : 'panel';
    }

    public function tag(): string
    {
        return 'x-dl::frame box='.$this->box.' '.$this->boxStyle;
    }

    public function source(): string
    {
        return 'image: media/bg/'.$this->bg.' · text: media/text/'.$this->quotes;
    }

    private static function mediaPath(string $sub): string
    {
        return rtrim(config('devlib.media_path', public_path('media')), '/\\').DIRECTORY_SEPARATOR.$sub;
    }

    private static function mediaUrl(string $sub): string
    {
        return rtrim(config('devlib.media_url', '/media'), '/').'/'.str_replace('\\', '/', $sub);
    }

    /** @return array<int, array{src: string, portrait: ?string}> */
    public function images(): array
    {
        $path = self::mediaPath('bg'.DIRECTORY_SEPARATOR.$this->bg);
        $files = is_dir($path) ? glob($path.DIRECTORY_SEPARATOR.'*.{jpg,jpeg,JPG,JPEG}', GLOB_BRACE) : (is_file($path) ? [$path] : []);
        $files = array_values(array_filter($files, fn ($f) => ! str_contains(basename($f), '-portrait.')));
        sort($files);

        $root = self::mediaPath('bg').DIRECTORY_SEPARATOR;
        return array_map(function ($f) use ($root) {
            $rel = str_replace($root, '', $f);
            $portrait = preg_replace('/\.(jpe?g)$/i', '-portrait.$1', $f);

            return [
                'src' => self::mediaUrl('bg/'.$rel),
                'portrait' => is_file($portrait) ? self::mediaUrl('bg/'.str_replace($root, '', $portrait)) : null,
            ];
        }, $files);
    }

    /** @return array<int, array{t: string, a: string}> */
    public static function textLines(string $file): array
    {
        $path = self::mediaPath('text'.DIRECTORY_SEPARATOR.$file);
        if (! is_file($path)) {
            return [];
        }
        $out = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            [$t, $a] = array_map('trim', explode('|', $line, 2) + [1 => '']);
            $out[] = ['t' => $t, 'a' => $a];
        }

        return $out;
    }

    /** @return array<string, array<int, string>> posisi => [widget, ...] */
    public function widgetMap(): array
    {
        $map = [];
        foreach (array_filter(array_map('trim', explode(',', $this->widgets))) as $w) {
            [$name, $pos] = array_map('trim', explode('@', $w, 2) + [1 => 'top-left']);
            $map[in_array($pos, self::POSITIONS, true) ? $pos : 'top-left'][] = $name;
        }

        return $map;
    }

    public function render()
    {
        return view('devlib::components.frame');
    }
}
