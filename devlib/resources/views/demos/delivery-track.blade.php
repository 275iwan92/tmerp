<div class="dl-demo">
    <x-dl::delivery-track stage="draft" title="Draft" subtitle="Truck waits at the warehouse" :times="['draft' => raw('07-Oct-2026')]" />
    <x-dl::delivery-track stage="confirmed" title="Confirmed" subtitle="Ready to ship" :times="['draft' => raw('07-Oct-2026')]" style="margin-top:16px" />
    <x-dl::delivery-track stage="shipped" title="Shipped" subtitle="On the way (position by stage, no GPS)" to="PT Maju Jaya" :times="['draft' => raw('05-Oct-2026'), 'shipped' => raw('07-Oct-2026')]" style="margin-top:16px" />
    <x-dl::delivery-track stage="delivered" title="Delivered" subtitle="Proof of delivery received" to="PT Maju Jaya" :times="['draft' => raw('05-Oct-2026'), 'shipped' => raw('07-Oct-2026'), 'delivered' => raw('07-Oct-2026')]" style="margin-top:16px" />
    <x-dl::delivery-track stage="incident" title="Accident" subtitle="Stopped on the route, recorded in Proof of Delivery" to="PT Maju Jaya" :times="['draft' => raw('05-Oct-2026'), 'shipped' => raw('07-Oct-2026')]" style="margin-top:16px" />
    <x-dl::delivery-track stage="cancelled" title="Cancelled" subtitle="Route stops at the warehouse" style="margin-top:16px" />
</div>