<div class="dl-demo">
    <x-dl::geo-clock title="Clock In / Out" subtitle="Demo: no server, the distance is a fixed 80 m (inside the 200 m radius)" data-demo-distance="80"
        :history="[['type' => 'IN', 'time' => raw('07:28:14'), 'status' => raw('AUTO_APPROVED'), 'tone' => 'success'], ['type' => 'OUT', 'time' => raw('16:35:02'), 'status' => raw('PENDING'), 'tone' => 'warn']]" />
    <x-dl::geo-clock title="Outside the radius" subtitle="Demo: a fixed 650 m, the badge turns red" data-demo-distance="650" style="margin-top:16px" />
</div>
