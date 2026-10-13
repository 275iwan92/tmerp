{{-- Contoh x-dl::review-feed: review gaya blog (baca kartu, putuskan dengan tombol besar). Simpan nyata hanya bila save-url diisi aplikasi pemakai. --}}
<div class="dl-demo">
    <x-dl::review-feed :choices="['KEEP' => 'Keep', 'CONDENSE' => 'Condense', 'DISCARD' => 'Discard']" :choice-tones="['KEEP' => 'success', 'CONDENSE' => 'warn', 'DISCARD' => 'danger']"
        :filters="[['label' => 'All'], ['label' => 'Not decided', 'where' => ['decision' => ['']], 'tone' => 'warn'], ['label' => 'Proposed discard', 'where' => ['proposal' => 'DISCARD'], 'tone' => 'danger']]"
        :posts="[
            ['id' => 1, 'title' => 'Old audit of the input screens', 'meta' => 'docs/audit_2026_10_02.md · 287 lines · changed 07-Oct-2026 · 2 references', 'badge' => 'Discard', 'tone' => 'danger',
             'summary' => 'A one-time audit of whether every input is connected to the database. The fixes it listed were already done. Nothing else refers to it.', 'note' => 'One-off audit; the fixes are finished.',
             'decision' => '', 'data' => ['proposal' => 'DISCARD', 'decision' => '']],
            ['id' => 2, 'title' => 'Inquiry to Master Product flow', 'meta' => 'docs/flow_product_master.md · 120 lines · changed 01-Oct-2026 · 9 references', 'badge' => 'Keep', 'tone' => 'success',
             'summary' => 'Explains how an approved inquiry becomes a master product. Still the reference for the ProDev flow.', 'note' => 'Still correct and referenced often.',
             'decision' => 'KEEP', 'state' => 'jo · 13-Oct-2026', 'data' => ['proposal' => 'KEEP', 'decision' => 'KEEP']],
        ]" />
</div>
