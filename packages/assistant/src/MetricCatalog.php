<?php

namespace Assistant;

use Assistant\Contracts\Metric;
use Illuminate\Support\Facades\DB;

// Katalog pertanyaan angka = kelas metric milik aplikasi (config assistant.metrics) + baris tabel katalog yang dirawat user
// (judul, frasa pemicu, modul, level minimum, aktif). Kelas baru otomatis mendapat baris dari nilai awal kelasnya.
class MetricCatalog
{
    /** @var ?array<int, array{key: string, metric: Metric, title: string, phrases: array<int, string>, module: ?string, min_level: int}> */
    private ?array $rows = null;

    /** @return array<int, array{key: string, metric: Metric, title: string, phrases: array<int, string>, module: ?string, min_level: int}> metric aktif */
    public function all(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }
        $table = DB::connection(config('assistant.connection'))->table(config('assistant.tables.metric'));
        $saved = $table->get()->keyBy('metric_key');
        $this->rows = [];
        foreach (config('assistant.metrics', []) as $class) {
            /** @var Metric $metric */
            $metric = app($class);
            $row = $saved->get($metric->key());
            if (! $row) {
                $table->insert(['metric_key' => $metric->key(), 'title' => $metric->title(), 'phrases' => implode(', ', $metric->phrases()),
                    'module_code' => $metric->module(), 'min_level' => $metric->minLevel(), 'activate' => 1, 'sort_order' => (count($this->rows) + 1) * 10,
                    'created_at' => now(), 'updated_at' => now(), 'created_by' => 'assistant', 'updated_by' => 'assistant']);
                $row = (object) ['title' => $metric->title(), 'phrases' => implode(', ', $metric->phrases()), 'module_code' => $metric->module(),
                    'min_level' => $metric->minLevel(), 'activate' => 1];
            }
            if ($row->activate) {
                $this->rows[] = ['key' => $metric->key(), 'metric' => $metric, 'title' => Assistant::text($row->title),
                    'phrases' => array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', (string) $row->phrases)))),
                    'module' => $row->module_code ?: null, 'min_level' => (int) $row->min_level];
            }
        }

        return $this->rows;
    }

    // Metric yang frasa pemicunya ada di pertanyaan (semua kata frasa ditemukan). Frasa terpanjang menang; perintah
    // (Contracts\Command) menang atas pertanyaan angka, sepanjang apa pun frasa angka yang ikut cocok.
    public function match(string $question): ?array
    {
        $query = Text::tokens($question);
        $best = null;
        $bestLen = 0;
        $bestCommand = false;
        foreach ($this->all() as $m) {
            $command = $m['metric'] instanceof Contracts\Command;
            foreach ($m['phrases'] as $phrase) {
                $words = Text::tokens($phrase);
                if (! $words || Text::hits($words, $query) !== count($words)) {
                    continue;
                }
                if (($command && ! $bestCommand) || ($command === $bestCommand && count($words) > $bestLen)) {
                    $best = $m;
                    $bestLen = count($words);
                    $bestCommand = $command;
                }
            }
        }

        return $best;
    }

    /** @return array<int, string> judul metric yang boleh ditanyakan si penanya (contoh pertanyaan di layar) */
    public function titlesFor(Contracts\Asker $asker): array
    {
        return array_values(array_map(fn ($m) => $m['title'], array_filter($this->all(),
            fn ($m) => $m['metric']->suggest() && (! $m['module'] || $asker->can($m['module'])) && $asker->level() >= $m['min_level'])));
    }
}
