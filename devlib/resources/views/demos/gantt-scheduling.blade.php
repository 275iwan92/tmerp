<x-dl::gantt :unscheduled="3"
    :categories="['cetak' => ['label' => 'Cetak', 'tone' => 'accent'], 'laminasi' => ['label' => 'Laminasi', 'tone' => 'violet'], 'plong' => ['label' => 'Plong', 'tone' => 'warn'], 'packing' => ['label' => 'Packing', 'tone' => 'success']]"
    :rows="[
        ['key' => 'lithrone', 'label' => 'Lithrone G40', 'sub' => 'Budi (utama) + Joko'],
        ['key' => 'laminasi', 'label' => 'Laminasi L1', 'sub' => 'Rudi (utama)'],
        ['key' => 'plong', 'label' => 'Plong P2', 'sub' => '— menunggu reroute'],
        ['key' => 'siti', 'label' => 'Siti', 'sub' => 'Packing', 'section' => 'MANUAL JOB (per orang, bukan mesin)'],
        ['key' => 'rina', 'label' => 'Rina', 'sub' => 'Packing', 'section' => 'MANUAL JOB (per orang, bukan mesin)'],
    ]"
    :tasks="[
        ['id' => 231, 'row' => 'lithrone', 'label' => 'WO-0231', 'process' => 'cetak', 'start' => '08:00', 'end' => '12:00', 'progress' => 65, 'status' => 'Running'],
        ['id' => 245, 'row' => 'lithrone', 'label' => 'WO-0245', 'process' => 'cetak', 'start' => '14:00', 'end' => '17:00', 'progress' => 5, 'state' => 'hot', 'risk' => true],
        ['id' => 228, 'row' => 'laminasi', 'label' => 'WO-0228', 'process' => 'laminasi', 'start' => '11:00', 'end' => '14:00', 'progress' => 40],
        ['id' => 230, 'row' => 'plong', 'label' => 'WO-0230', 'process' => 'plong', 'start' => '08:00', 'end' => '10:00', 'progress' => 90],
        ['id' => 900, 'row' => 'plong', 'label' => 'BREAKDOWN', 'process' => 'plong', 'start' => '10:00', 'end' => '13:00', 'progress' => 20, 'state' => 'breakdown', 'risk' => true],
        ['id' => 225, 'row' => 'siti', 'label' => 'WO-0225', 'process' => 'packing', 'start' => '10:00', 'end' => '13:00', 'progress' => 55],
        ['id' => 227, 'row' => 'rina', 'label' => 'WO-0227', 'process' => 'packing', 'start' => '12:00', 'end' => '16:00', 'progress' => 85, 'state' => 'qchold', 'status' => 'QC Hold'],
    ]">
    <x-slot:actions><x-dl::button variant="danger" icon="🔥" label="Sisip Hot Job" onclick="Devlib.toast('Sisip Hot Job (layar pemakai yang menentukan)')" /></x-slot:actions>
</x-dl::gantt>
