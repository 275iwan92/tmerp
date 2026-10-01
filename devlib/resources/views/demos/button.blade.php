<div class="dl-demo">
    <x-dl::card title="Preset (label baku, LOCKED)" description="Satu kata sama di semua layar, ikut bahasa">
        <div class="dl-row">
            <x-dl::button preset="save" /> <x-dl::button preset="cancel" /> <x-dl::button preset="close" /> <x-dl::button preset="confirm" />
            <x-dl::button preset="delete" /> <x-dl::button preset="new" /> <x-dl::button preset="print" /> <x-dl::button preset="submit" />
            <x-dl::button preset="approve" /> <x-dl::button preset="reject" /> <x-dl::button preset="hold" /> <x-dl::back-button fallback="#" />
        </div>
    </x-dl::card>
    <x-dl::card title="Variant & size">
        <div class="dl-row">
            <x-dl::button label="Primary" /> <x-dl::button variant="secondary" label="Secondary" /> <x-dl::button variant="success" label="Success" />
            <x-dl::button variant="danger" label="Danger" /> <x-dl::button variant="ghost" label="Ghost" /> <x-dl::button variant="link" href="#" label="Link" />
            <x-dl::button size="sm" label="Kecil" /> <x-dl::button size="lg" label="Besar (login)" />
        </div>
    </x-dl::card>
    <x-dl::card title="Split button">
        <x-dl::split-button />
    </x-dl::card>
    <x-dl::card title="Menu button" description="Satu tombol, beberapa pilihan (mis. Print / Email untuk form yang punya lebih dari satu dokumen). Item ber-act memancarkan event dl:menu.">
        <div class="dl-row">
            <x-dl::menu-button preset="print" :items="[['label' => 'Master Card', 'icon' => 'fa-id-card', 'sub' => 'A4 landscape', 'act' => 'print-mc'], ['label' => 'ACC Digital', 'icon' => 'fa-stamp', 'sub' => 'Proof sheet', 'act' => 'print-acc']]" />
            <x-dl::menu-button preset="email" :items="[['label' => 'Master Card', 'icon' => 'fa-id-card', 'act' => 'email-mc'], ['label' => 'ACC Digital', 'icon' => 'fa-stamp', 'disabled' => true, 'title' => 'Belum ada dokumen']]" />
            <x-dl::menu-button label="More" icon="fa-ellipsis" :items="[['label' => 'Open DevLib', 'icon' => 'link', 'href' => '#']]" />
        </div>
    </x-dl::card>
</div>
