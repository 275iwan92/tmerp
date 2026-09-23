<x-dl::approval title="PO Menunggu Approval Saya" me="Operational Manager" approve-label="Setujui & Kirim ke Vendor" :docs="\App\Support\DemoData::approvals()"
    :line-columns="['item_label' => 'Item', 'qty' => 'Qty', 'uom' => 'Satuan', 'price:money' => 'Harga/Unit', 'subtotal:money' => 'Subtotal']" />
