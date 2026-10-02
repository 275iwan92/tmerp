<?php

namespace App\Assistant\Metrics;

use App\Models\Sys\SysConstant;
use Assistant\Assistant;
use Assistant\Contracts\Asker;
use Assistant\Contracts\Metric;
use Assistant\Contracts\Navigator;
use Assistant\Reply;
use Assistant\Text;

// Penjaga pertanyaan gaji (user 2026-10-02): "pertanyaan gaji hanya ada di level GM dan Direksi; gaji sendiri bisa dilihat di
// user profile dia". Di bawah level itu asisten tidak menjawab apa pun tentang gaji dan menunjuk ke profil user.
// GM / Direksi: asisten belum punya angka gaji (definisinya belum ditetapkan), jadi menunjuk ke layar payroll yang boleh dibuka.
// Menanyakan LETAK / CARA PAKAI layar payroll ("buka slip gaji") bukan urusan kelas ini: itu dijawab pencarian layar biasa.
final class SalaryQuestion implements Metric
{
    public function __construct(private Navigator $navigator) {}

    public function key(): string
    {
        return 'salary';
    }

    public function title(): string
    {
        return 'Salary';
    }

    public function phrases(): array
    {
        return ['gaji', 'upah', 'take home pay', 'thp', 'salary', 'salaries', 'payroll', 'wage', 'wages'];
    }

    public function module(): ?string
    {
        return null;
    }

    public function minLevel(): int
    {
        return 0;
    }

    public function suggest(): bool
    {
        return false;
    }

    // Level keamanan terendah yang boleh bertanya soal gaji: General Manager (grup level 4) dan Direksi (level 5).
    public static function level(): int
    {
        return (int) SysConstant::number('ASSISTANT_SALARY_MIN_LEVEL', 4);
    }

    public function answer(Asker $asker, string $question): Reply
    {
        $query = Text::tokens($question, true);
        $screens = collect($this->navigator->screens($asker))
            ->map(fn ($s) => $s + ['score' => Text::match($query, $s['label'], ($s['path'] ?? '').' '.($s['words'] ?? ''))])
            ->where('score', '>=', 0.5)->sortByDesc('score')->take(2);

        $reply = $asker->level() >= self::level()
            ? new Reply(Reply::HELP, Assistant::text('Salary'), Assistant::text('Salary figures are not in my list of numbers yet. They are on the payroll screens you are allowed to open.'))
            : new Reply(Reply::DENIED, Assistant::text('Not available for your account'),
                Assistant::text('Salary questions are answered only for the General Manager and the Directors.'),
                note: Assistant::text('Your own salary is shown in your user profile.'));
        foreach ($screens as $s) {
            $reply->open(Assistant::text('Open :screen', ['screen' => $s['label']]), $s['url'], $s['key'], $s['icon'] ?? null);
        }

        return $reply;
    }
}
