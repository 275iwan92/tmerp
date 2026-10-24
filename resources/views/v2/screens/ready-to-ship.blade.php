{{--
 REMARK
 Tujuan       : Antrean memilih Customer Order yang sudah bisa diproses menjadi Delivery Order: filter status, centang beberapa CO yang boleh digabung, satu tombol membuat satu DO Draft.
 Sumber hulu  : App\Support\V2\Data\ReadyToShip (kuota sisa dari v_co_delivery_outstanding, Post to Production dari co_order_delivery); objek DevLib x-dl::pick-queue.
 Input        : Session v2_flash dan errors.
 Output       : Form POST ke route v2.do.from-queue (ids[] = id CO); DO Draft yang terbentuk dibuka di layar Delivery Order, barisnya ditarik lewat Pull Lines.
 Dipakai oleh : Rute v2.screen dengan slug 'ready-to-ship' (menu Sales > Delivery), dirender view layar generik v2.
 Status       : Aktif (2026-10-24). Kesiapan produksi / QC belum diperiksa (sengaja): siap = sisa kuota kirim ada dan sudah Posted to Production.
--}}
@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::pick-queue title="Ready to Ship" :rows="\App\Support\V2\Data\ReadyToShip::rows()" :action="route('v2.do.from-queue')"
    :states="['ready' => ['label' => 'Fully ready'], 'partial' => ['label' => 'Partly ready'], 'waiting' => ['label' => 'Waiting for production']]"
    :filters="[
        ['key' => 'ready', 'label' => 'Ready to ship', 'states' => ['ready']],
        ['key' => 'partial', 'label' => 'Partial', 'states' => ['partial']],
        ['key' => 'waiting', 'label' => 'Waiting for production', 'states' => ['waiting']],
        ['key' => 'all', 'label' => 'All', 'states' => null],
    ]"
    button="Create 1 DO from :n CO" button-one="Create DO" button-partial="Create DO (:have of :want)"
    together="same address, can be merged" mismatch="Choose CO from the same customer" selected=":n selected"
    row-head="Customer Order" qty-head="Goods ready / ordered" due-head="Due" empty="No Customer Order in this list." />
