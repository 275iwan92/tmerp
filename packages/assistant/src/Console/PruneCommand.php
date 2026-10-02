<?php

namespace Assistant\Console;

use Assistant\History;
use Illuminate\Console\Command;

// Dijadwalkan harian oleh aplikasi pemakai: riwayat percakapan yang lewat masa simpan (menurut level penanya) dibuang.
class PruneCommand extends Command
{
    protected $signature = 'assistant:prune';

    protected $description = 'Delete assistant chat history that is older than the retention of the asker level';

    public function handle(History $history): int
    {
        $this->info($history->prune().' assistant messages deleted.');

        return self::SUCCESS;
    }
}
