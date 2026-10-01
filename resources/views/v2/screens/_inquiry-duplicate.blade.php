{{-- Duplicate inquiry (spec sama, customer lain) -> NEW DRAFT baru (routes/v2.php v2.inquiry.duplicate).
     Dipakai tombol Duplicate di form Inquiry New / Recalc / Revise (user 2026-10-01: pindah dari baris grid ke form).
     Di luar form dokumen: form HTML tidak boleh bersarang. Parameter: $source (id inquiry), $label (keterangan asal). --}}
<x-dl::modal id="dup-modal" title="Duplicate Inquiry" size="md" icon="copy">
    <form method="POST" action="{{ route('v2.inquiry.duplicate') }}" id="dup-form" class="mp-dup">
        @csrf
        <input type="hidden" name="source_id" value="{{ $source }}">
        <p class="mp-dup__src">{{ $label }}</p>
        <x-dl::field.select name="customer_id" label="Target customer" required :options="\App\Support\V2\Data\CustomerOrder::customerOptions()" placeholder="Choose customer..."
            :source="\App\Support\V2\Src::of('tm2mst.cust_customer.customer_name')" />
        <x-dl::alert tone="info" message="Spec (parts, material, process, finishing, ink) is copied as is. Qty & price are not: the new inquiry is born as a DRAFT New Product and goes through Pro-Dev → Pro-Cost again." />
    </form>
    <x-slot:footer>
        <x-dl::button preset="cancel" data-dl-close-modal />
        <x-dl::button icon="copy" label="Duplicate" type="submit" form="dup-form" />
    </x-slot:footer>
</x-dl::modal>
