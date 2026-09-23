<x-dl::kanban me="Johannes Iwan" :cards="\App\Support\DemoData::kanbanCards()"
    :columns="[
        ['key' => 'queue', 'label' => 'Antrian', 'tone' => 'neutral'],
        ['key' => 'print', 'label' => 'Cetak', 'tone' => 'accent', 'limit' => 2],
        ['key' => 'finish', 'label' => 'Finishing', 'tone' => 'warn', 'limit' => 3],
        ['key' => 'qc', 'label' => 'QC', 'tone' => 'violet'],
        ['key' => 'done', 'label' => 'Selesai', 'tone' => 'success', 'done' => true],
    ]"
    :transitions="['queue' => ['print'], 'print' => ['finish', 'queue'], 'finish' => ['qc', 'print'], 'qc' => ['done', 'finish'], 'done' => []]" />
