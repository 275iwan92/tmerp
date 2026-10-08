<div class="dl-demo">
    <x-dl::timer :seconds="2380" label="run time" />
    <x-dl::timer :seconds="1080" tone="warn" label="setup time" style="margin-top:12px" />
    <x-dl::timer :seconds="840" :running="false" tone="muted" label="paused (material wait)" style="margin-top:12px" />
</div>
