{{-- Contoh x-dl::feed: umpan narasi gaya blog (markdown dibaca penuh, yang disematkan di atas). --}}
<div class="dl-demo">
    <x-dl::feed :posts="[
        ['title' => 'Why the old screen documents were removed', 'meta' => 'Agent Iwan · Report · 07-Oct-2026', 'badge' => 'Unread', 'tone' => 'warn', 'pinned' => true,
            'body' => \"The per-screen documents described screens that were replaced.\n\n- Replaced by v2 screens\n- Still in git history\n\n| File | Reason |\n|---|---|\n| screen_a.md | Replaced |\"],
        ['title' => 'Second story', 'meta' => 'Agent Jo · Note · 06-Oct-2026', 'body' => 'A **short** note.'],
    ]" />
</div>
