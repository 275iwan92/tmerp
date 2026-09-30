<x-dl::year-calendar :year="2026" :off-days="[0]" new-url="#new={date}"
    :legend="['danger' => 'National holiday', 'warn' => 'Collective leave', 'info' => 'Company holiday']"
    :marks="[
        '2026-01-01' => ['label' => 'New Year', 'tone' => 'danger'],
        '2026-03-20' => ['label' => 'Collective leave Eid al-Fitr', 'tone' => 'warn'],
        '2026-03-21' => ['label' => 'Eid al-Fitr', 'tone' => 'danger'],
        '2026-03-22' => ['label' => 'Eid al-Fitr', 'tone' => 'danger'],
        '2026-03-23' => ['label' => 'Collective leave Eid al-Fitr', 'tone' => 'warn'],
        '2026-07-10' => ['label' => 'Company anniversary', 'tone' => 'info'],
        '2026-08-17' => ['label' => 'Independence Day', 'tone' => 'danger'],
    ]" />
