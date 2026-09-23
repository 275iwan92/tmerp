<div class="dl-demo">
    <p class="dl-note">Saklar ini (atau <kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>D</kbd>) menyalakan Mode Developer: arahkan kursor ke objek untuk melihat nama komponen, rantai induknya, dan sumber datanya; objek yang bukan dari library diberi garis merah. Di situs DevLib selalu menyala; di tmerp hanya untuk admin.</p>
    <x-dl::dev-toggle :floating="false" />
    <x-dl::card title="Contoh objek">
        <x-dl::field.lookup name="supplier_id" lookup="supplier" label="Supplier" />
        <x-dl::button preset="save" />
    </x-dl::card>
</div>
