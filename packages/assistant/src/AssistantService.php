<?php

namespace Assistant;

// Pintu paket untuk aplikasi pemakai: isi awal drawer asisten (boot) dan satu pertanyaan (ask).
class AssistantService
{
    public function __construct(private Engine $engine, private History $history, private MetricCatalog $metrics) {}

    // Isi awal layar asisten (prop assistant x-dl::shell): alamat API, sapaan, contoh pertanyaan, riwayat terakhir.
    public function boot(mixed $user): array
    {
        $asker = Assistant::asker($user);

        return [
            'api' => ['ask' => route('assistant.ask'), 'feedback' => route('assistant.feedback'), 'csrf' => csrf_token()],
            'hello' => Assistant::text('Ask in plain words: where a screen is, how to use a screen, or a number you are allowed to see.'),
            'chips' => array_slice($this->metrics->titlesFor($asker), 0, 4),
            'history' => $this->history->recent($asker, (int) Assistant::setting('history_show')),
            'placeholder' => Assistant::text('Type your question...'),
            'thinking' => Assistant::text('Looking for the answer...'),
            'failed' => Assistant::text('The assistant could not answer just now. Try again.'),
        ];
    }

    /** @return array{id: int, reply: array<string, mixed>} */
    public function ask(mixed $user, string $question, ?string $topic = null): array
    {
        $asker = Assistant::asker($user);
        $start = microtime(true);
        $reply = $topic ? $this->engine->topic($asker, $topic) : $this->engine->ask($asker, $question);
        $id = $this->history->store($asker, $question, $reply, (int) round((microtime(true) - $start) * 1000), $this->engine->modelUsed);

        return ['id' => $id, 'reply' => $reply->toArray()];
    }

    public function feedback(mixed $user, int $id, int $value): bool
    {
        return $this->history->feedback(Assistant::asker($user), $id, $value);
    }
}
