<?php

namespace Assistant;

// Satu jawaban asisten, dalam bentuk data (layar yang menggambarnya). kind menentukan arti:
//   SCREEN  layar ditemukan            HELP    kutipan bantuan (langkah / penjelasan isian)
//   NUMBER  angka dari kelas metric    DENIED  di luar hak si penanya        UNKNOWN  tidak ada jawabannya
// actions: ['label', 'url', 'code', 'icon'] = buka sebagai tab | ['label', 'topic'] = tanya bantuan topik itu |
//          ['label', 'page'] = buka halaman profil aplikasi (mis. tiket bantuan).
final class Reply
{
    public const SCREEN = 'SCREEN';

    public const HELP = 'HELP';

    public const NUMBER = 'NUMBER';

    public const DENIED = 'DENIED';

    public const UNKNOWN = 'UNKNOWN';

    /**
     * @param  array<int, array{0: string, 1: string}>  $rows  rincian [label, nilai]
     * @param  array<int, string>  $steps  langkah berurutan
     * @param  array<int, array<string, ?string>>  $actions
     */
    public function __construct(
        public string $kind,
        public string $title,
        public ?string $text = null,
        public ?string $big = null,
        public array $rows = [],
        public array $steps = [],
        public ?string $note = null,
        public array $actions = [],
        // Kunci sumber jawaban (metric / topik / layar) untuk log.
        public ?string $key = null,
        // Bagian layar yang perlu memuat ulang isinya sesudah jawaban ini (mis. 'board' = papan widget): layar menyiarkan
        // event dl:refresh dengan nilai ini. Hanya untuk jawaban yang MENGUBAH sesuatu; kosong = tidak ada.
        public ?string $refresh = null,
    ) {}

    public function open(string $label, string $url, string $code, ?string $icon = null): self
    {
        $this->actions[] = ['label' => $label, 'url' => $url, 'code' => $code, 'icon' => $icon];

        return $this;
    }

    public function topic(string $label, string $topicKey): self
    {
        $this->actions[] = ['label' => $label, 'topic' => $topicKey];

        return $this;
    }

    public function page(string $label, string $page): self
    {
        $this->actions[] = ['label' => $label, 'page' => $page];

        return $this;
    }

    public function answered(): bool
    {
        return $this->kind !== self::UNKNOWN;
    }

    public function toArray(): array
    {
        return array_filter(['kind' => $this->kind, 'title' => $this->title, 'text' => $this->text, 'big' => $this->big, 'rows' => $this->rows,
            'steps' => $this->steps, 'note' => $this->note, 'actions' => $this->actions, 'refresh' => $this->refresh], fn ($v) => $v !== null && $v !== []);
    }
}
