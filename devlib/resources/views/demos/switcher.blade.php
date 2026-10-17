<div class="dl-demo">
    <x-dl::switcher label="Role" :options="['a' => 'Maintenance Head', 'b' => 'Project Assistant']">
        <x-dl::switch-case value="a"><x-dl::alert tone="info" message="Job description and KPI of Maintenance Head." /></x-dl::switch-case>
        <x-dl::switch-case value="b"><x-dl::alert tone="info" message="Job description and KPI of Project Assistant." /></x-dl::switch-case>
    </x-dl::switcher>
</div>
