<?php

namespace Assistant;

use Assistant\Contracts\Asker;
use Assistant\Contracts\Knowledge;
use Assistant\Contracts\LanguageModel;
use Assistant\Contracts\Navigator;

// Mesin jawab. Urutan: (1) angka terdaftar yang frasanya cocok, (2) bantuan (arti isian / kegunaan / cara pakai) bila ada
// kata tanyanya, (3) layar, (4) bantuan tanpa kata tanya, (5) model bahasa lokal memilih dari calon terdekat, (6) tidak tahu.
// Prinsip: angka hanya dari kelas metric, teks bantuan hanya kutipan; model bahasa tidak pernah menyusun isi jawaban.
final class Engine
{
    // Di atas angka ini sebuah sasaran dianggap cocok; di bawahnya hanya jadi calon (saran / pilihan model bahasa).
    private const SURE = 0.7;

    // Kata tanya "cara ..." sudah menyatakan niatnya, jadi topik bantuan terdekat cukup lebih longgar.
    private const SURE_HOW = 0.45;

    private const MAYBE = 0.34;

    public bool $modelUsed = false;

    public function __construct(
        private MetricCatalog $metrics,
        private Navigator $navigator,
        private Knowledge $knowledge,
        private LanguageModel $model,
    ) {}

    public function ask(Asker $asker, string $question): Reply
    {
        $this->modelUsed = false;
        $cues = Text::cues($question);
        $query = Text::tokens($question, true);
        $screens = $this->navigator->screens($asker);
        $topics = $this->knowledge->topics($asker);

        // "Buka laporan sales" / "cara ..." = mencari layar atau bantuan, bukan meminta angkanya.
        $wantsScreen = ($cues['how'] || $cues['where']) && ! $cues['number'];
        $metric = $this->metrics->match($question);
        // Perintah (Contracts\Command, mis. "buka dashboard sales") tetap dijalankan walau kalimatnya memakai kata "buka" /
        // "tampilkan"; kalimat "cara ..." tetap mencari bantuan.
        $command = $metric && $metric['metric'] instanceof Contracts\Command && ! $cues['how'];
        if ($metric && (! $wantsScreen || $command)) {
            return $this->number($asker, $metric, $question);
        }
        if (! $query) {
            return $this->unknown([]);
        }

        $screen = $this->best($query, $screens, fn ($s) => [$s['label'], ($s['path'] ?? '').' '.($s['words'] ?? '')]);
        $topic = $this->best($query, $topics, fn ($t) => [$t['title'], $t['words'] ?? '']);

        if ($cues['field'] && ($reply = $this->fields($query, $topics))) {
            return $reply;
        }
        if ($cues['how'] && $topic['score'] >= self::SURE_HOW) {
            return $this->howTo($topic['item'], $screens);
        }
        if ($cues['purpose'] && ! $cues['where'] && $topic['score'] >= self::SURE) {
            return $this->about($topic['item'], $screens);
        }
        if ($screen['score'] >= self::SURE && $screen['score'] >= $topic['score']) {
            return $this->screen($screen['item']);
        }
        if ($topic['score'] >= self::SURE) {
            return $this->about($topic['item'], $screens);
        }
        // Tanpa kata tanya, penjelasan isian hanya dijawab bila pertanyaannya memang (hampir) nama isian itu saja.
        if ($reply = $this->fields($query, $topics, 0.66)) {
            return $reply;
        }

        $near = $this->near($query, $screens, $topics);
        if ($near && $this->model->available()) {
            $this->modelUsed = true;
            $pick = $this->model->choose($question, array_map(fn ($c) => $c['say'], $near));
            if ($pick !== null && isset($near[$pick])) {
                return $near[$pick]['kind'] === 'screen' ? $this->screen($near[$pick]['item']) : $this->howTo($near[$pick]['item'], $screens);
            }
        }

        return $this->unknown(array_filter($near, fn ($c) => $c['kind'] === 'screen'));
    }

    // Bantuan satu topik (tombol "How to use" di jawaban layar).
    public function topic(Asker $asker, string $key): Reply
    {
        foreach ($this->knowledge->topics($asker) as $t) {
            if ($t['key'] === $key) {
                return $this->howTo($t, $this->navigator->screens($asker));
            }
        }

        return $this->unknown([]);
    }

    private function number(Asker $asker, array $metric, string $question): Reply
    {
        if (($metric['module'] && ! $asker->can($metric['module'])) || $asker->level() < $metric['min_level']) {
            return new Reply(Reply::DENIED, Assistant::text('Not available for your account'),
                Assistant::text(':title follows the access rights of your account: it needs access to its module and a high enough security level.', ['title' => $metric['title']]),
                key: $metric['key']);
        }
        $reply = $metric['metric']->answer($asker, $question);
        $reply->key ??= $metric['key'];

        return $reply;
    }

    /**
     * @param  array<int, string>  $query
     * @param  array<int, array<string, mixed>>  $items
     * @return array{score: float, item: ?array<string, mixed>}
     */
    private function best(array $query, array $items, callable $text): array
    {
        $best = ['score' => 0.0, 'item' => null];
        foreach ($items as $item) {
            [$label, $extra] = $text($item);
            $score = Text::match($query, $label, $extra);
            if ($score > $best['score']) {
                $best = ['score' => $score, 'item' => $item];
            }
        }

        return $best;
    }

    private function screen(array $s): Reply
    {
        $reply = (new Reply(Reply::SCREEN, Assistant::text('Screen found'), $s['label'], note: $s['about'] ?? null, key: $s['key']))
            ->open(Assistant::text('Open :screen', ['screen' => $s['label']]), $s['url'], $s['key'], $s['icon'] ?? null);
        $reply->rows = [[Assistant::text('Menu'), (string) ($s['path'] ?? '')]];

        return empty($s['help']) ? $reply : $reply->topic(Assistant::text('How to use'), $s['help']);
    }

    private function howTo(array $t, array $screens): Reply
    {
        $reply = new Reply(Reply::HELP, Assistant::text('How to use: :screen', ['screen' => $t['title']]), $t['purpose'] ?? null, steps: $t['steps'] ?? [],
            note: Assistant::text('Source: Help, :screen', ['screen' => $t['title']]), key: $t['key']);

        return $this->helpActions($reply, $t, $screens);
    }

    private function about(array $t, array $screens): Reply
    {
        $reply = new Reply(Reply::HELP, $t['title'], $t['purpose'] ?? null, note: Assistant::text('Source: Help, :screen', ['screen' => $t['title']]), key: $t['key']);

        return $this->helpActions($reply->topic(Assistant::text('How to use'), $t['key']), $t, $screens);
    }

    private function helpActions(Reply $reply, array $t, array $screens): Reply
    {
        foreach ($screens as $s) {
            if (($s['help'] ?? null) === $t['key']) {
                $reply->open(Assistant::text('Open :screen', ['screen' => $s['label']]), $s['url'], $s['key'], $s['icon'] ?? null);

                break;
            }
        }
        if ($url = $this->knowledge->url()) {
            $reply->open(Assistant::text('Open Help'), $url, 'G-HELP', 'fa-solid fa-life-ring');
        }

        return $reply;
    }

    // Penjelasan isian: semua kata label isian ada di pertanyaan. Kata lain di pertanyaan memilih layarnya.
    /**
     * @param  array<int, string>  $query
     * @param  array<int, array<string, mixed>>  $topics
     */
    private function fields(array $query, array $topics, float $minShare = 0.0): ?Reply
    {
        $found = [];
        foreach ($topics as $t) {
            foreach ($t['fields'] ?? [] as $f) {
                $label = Text::tokens($f['label']);
                if ($label && Text::hits($label, $query) === count($label) && count($label) / count($query) >= $minShare) {
                    $rest = array_values(array_filter($query, fn ($w) => ! Text::hits([$w], $label)));
                    $found[] = ['rank' => count($label) + ($rest ? Text::match($rest, $t['title'], $t['words'] ?? '') : 0), 'topic' => $t, 'field' => $f];
                }
            }
        }
        if (! $found) {
            return null;
        }
        usort($found, fn ($a, $b) => $b['rank'] <=> $a['rank']);
        $top = array_slice($found, 0, 3);

        return new Reply(Reply::HELP, Assistant::text('Field: :field', ['field' => $top[0]['field']['label']]),
            rows: array_map(fn ($x) => [$x['topic']['title'], $x['field']['text']], $top),
            note: Assistant::text('Source: Help, :screen', ['screen' => implode(', ', array_map(fn ($x) => $x['topic']['title'], $top))]), key: $top[0]['topic']['key']);
    }

    // Calon terdekat yang belum cukup yakin: saran di jawaban "tidak tahu" dan pilihan untuk model bahasa.
    /** @return array<string, array{kind: string, item: array<string, mixed>, say: string}> */
    private function near(array $query, array $screens, array $topics): array
    {
        $out = [];
        foreach ([['screen', $screens, fn ($s) => [$s['label'], ($s['path'] ?? '').' '.($s['words'] ?? '')]], ['topic', $topics, fn ($t) => [$t['title'], $t['words'] ?? '']]] as [$kind, $items, $text]) {
            $scored = [];
            foreach ($items as $item) {
                [$label, $extra] = $text($item);
                if (($score = Text::match($query, $label, $extra)) >= self::MAYBE) {
                    $scored[] = [$score, $item];
                }
            }
            usort($scored, fn ($a, $b) => $b[0] <=> $a[0]);
            foreach (array_slice($scored, 0, 4) as [, $item]) {
                $out[$kind.':'.$item['key']] = ['kind' => $kind, 'item' => $item,
                    'say' => $kind === 'screen' ? 'Open the screen "'.$item['label'].'" ('.($item['about'] ?? $item['path'] ?? '').')' : 'Explain how to use "'.$item['title'].'" ('.($item['purpose'] ?? '').')'];
            }
        }

        return $out;
    }

    private function unknown(array $near): Reply
    {
        $reply = new Reply(Reply::UNKNOWN, Assistant::text('I do not have the answer yet'),
            Assistant::text('This question is not in the Help and not in the list of numbers I know. I will not guess.'),
            note: Assistant::text('Your question is recorded so the team can add the answer.'));
        foreach (array_slice($near, 0, 2) as $c) {
            $reply->open(Assistant::text('Open :screen', ['screen' => $c['item']['label']]), $c['item']['url'], $c['item']['key'], $c['item']['icon'] ?? null);
        }

        $page = config('assistant.ticket_page');

        return $page ? $reply->page(Assistant::text('Send as Support Ticket'), $page) : $reply;
    }
}
