<div class="dl-demo">
    <x-dl::profile-banner name="Dewi Rahmawati" subtitle="TM-00002 · Prepress Planning Specialist · Prepress & CTP" :progress="46" progress-text="46% complete · 7 items to go"
        :groups="[
            ['label' => 'Status', 'badges' => [['label' => 'Working', 'tone' => 'success'], ['label' => 'Non-staff', 'tone' => 'info', 'icon' => 'fa-lock'], ['label' => 'Monthly', 'tone' => 'info', 'icon' => 'fa-lock']]],
            ['label' => 'Employment', 'badges' => [['label' => 'TETAP'], ['label' => 'Joined 01-Jun-2021']]],
        ]">
        <x-slot:photo><x-dl::photo-box name="demo_banner_photo" label="Dewi Rahmawati" /></x-slot:photo>
        <x-slot:kpis>
            <x-dl::kpi-row>
                <x-dl::kpi label="Tenure" value="5y 4m" icon="fa-hourglass-half" tone="success" />
                <x-dl::kpi label="Leave days left" value="12" icon="fa-umbrella-beach" />
                <x-dl::kpi label="Attendance" value="0" icon="fa-fingerprint" />
                <x-dl::kpi label="Discipline" value="Verbal 6/6" icon="fa-gavel" tone="danger" />
            </x-dl::kpi-row>
        </x-slot:kpis>
        <x-slot:notice><x-dl::alert tone="warn" message="To complete: Family card (Profile), Father, Mother (Family)" /></x-slot:notice>
    </x-dl::profile-banner>
</div>
