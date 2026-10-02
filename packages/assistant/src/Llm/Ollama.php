<?php

namespace Assistant\Llm;

use Assistant\Assistant;
use Assistant\Contracts\LanguageModel;
use Illuminate\Support\Facades\Cache;

// Model bahasa lokal lewat Ollama (http://127.0.0.1:11434, tanpa internet). Dipakai hanya sebagai pemilih: diberi pertanyaan
// dan daftar pilihan bernomor, menjawab satu nomor (JSON). Mati / lambat / jawaban tak terbaca = null, asisten lanjut tanpa dia.
final class Ollama implements LanguageModel
{
    public function available(): bool
    {
        if (! (int) Assistant::setting('model_enabled')) {
            return false;
        }

        // Cek hidup-matinya disimpan sebentar supaya tiap pertanyaan tidak menunggu server yang sedang mati.
        return (bool) Cache::remember('assistant:model-up', 60, fn () => $this->call('GET', '/api/tags', null, 2) !== null);
    }

    public function choose(string $question, array $options): ?string
    {
        if (! $options) {
            return null;
        }
        $keys = array_keys($options);
        $list = '';
        foreach (array_values($options) as $i => $say) {
            $list .= ($i + 1).'. '.$say."\n";
        }
        $out = $this->call('POST', '/api/chat', [
            'model' => (string) Assistant::setting('model_name'),
            'stream' => false,
            'format' => 'json',
            'options' => ['temperature' => 0],
            'messages' => [
                ['role' => 'system', 'content' => 'You route a question typed by a user of a business application (often in Indonesian) to ONE of the numbered options. '
                    .'Answer only with JSON: {"choice": <number>}. If no option fits the question, answer {"choice": 0}. Never invent an option.'],
                ['role' => 'user', 'content' => "Question: {$question}\n\nOptions:\n{$list}"],
            ],
        ], (int) Assistant::setting('model_timeout_seconds'));
        $choice = (int) (json_decode((string) ($out['message']['content'] ?? ''), true)['choice'] ?? 0);

        return $choice >= 1 && $choice <= count($keys) ? $keys[$choice - 1] : null;
    }

    private function call(string $method, string $path, ?array $body, int $timeout): ?array
    {
        $ch = curl_init(rtrim((string) Assistant::setting('model_url'), '/').$path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => max(1, $timeout),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $raw = curl_exec($ch);
        $ok = $raw !== false && curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200;
        curl_close($ch);

        return $ok ? (json_decode((string) $raw, true) ?: []) : null;
    }
}
