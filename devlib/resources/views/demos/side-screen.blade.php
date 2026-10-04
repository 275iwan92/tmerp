<div class="dl-demo">
    {{-- Side Screen: jendela LAYAR PENUH (bawaan) yang bisa mengambang ala OS Windows (seret kepala, tarik tepi / sudut; posisi
         dan ukuran diingat per browser). Dipakai Shell untuk Help & support dan Edit profile (prop drawers; isi = url di iframe).
         Di sini isinya slot: x-dl::side-screen.layout = rel bagian vertikal + judul bagian lengket + kaki aksi lengket. --}}
    <x-dl::action-bar align="start">
        <x-dl::button icon="fa-circle-question" label="Open Help & support (demo)" onclick="document.querySelector('[data-dl-side-screen=demo-help]').dlComponent.open()" />
    </x-dl::action-bar>

    <x-dl::side-screen id="demo-help" title="Help & support" subtitle="F1 · guides, document flows and tickets" icon="fa-circle-question">
        <x-dl::side-screen.layout :sections="['topics' => ['Help Topics', 'fa-life-ring', 148], 'guide' => ['User\'s Guide', 'fa-book-open'], 'flow' => ['Application\'s Flow', 'fa-diagram-project'], 'tickets' => ['Tickets', 'fa-ticket', 2]]" active="flow"
            :hints="['topics' => 'Every screen has its help: purpose, steps and an explanation of each field.', 'guide' => 'Read the guides from top to bottom the first time.', 'flow' => 'Point the mouse at a block to see its short rule; click a block to open its screen.', 'tickets' => 'Send a ticket with screenshots; the reply appears here.']"
            :links="['Guides' => [['ERP Rules Guide', 'fa-book', '#'], ['Master Product & Revision', 'fa-book-open', '#']]]" :crumbs="['Account' => null, 'Help' => null, 'Help & support' => '#']">
            <x-slot:topics><x-dl::card title="Help Topics" icon="fa-life-ring">Grid Help Center (Part 3: Grid).</x-dl::card></x-slot:topics>
            <x-slot:guide>
                <x-dl::card title="1. Opening screen" description="What you see after login and how to read the widgets." icon="fa-circle-check">
                    <x-dl::table :columns="['n' => 'Step', 'text' => 'What to do']" :rows="[['n' => 1, 'text' => 'Log in with your account.'], ['n' => 2, 'text' => 'Read the widgets of your board.'], ['n' => 3, 'text' => 'Open a module from the Layer Menu.']]" />
                </x-dl::card>
            </x-slot:guide>
            <x-slot:flow>
                <x-dl::card title="Sales: from Inquiry to Invoice" description="One document creates the next; the status of each block follows its confirm flag." icon="fa-bag-shopping">
                    <x-dl::flow :title="''" :steps="[['key' => 's1', 'label' => 'Inquiry Product', 'note' => 'NEW · REVISE · RECALC'], ['key' => 's2', 'label' => 'Pro-Dev', 'note' => 'layout & dieline'], ['key' => 's3', 'label' => 'Pro-Cost', 'note' => 'HPP & price ladder'], ['key' => 's4', 'label' => 'Quotation', 'note' => 'published'], ['key' => 's5', 'label' => 'Customer Order', 'note' => 'Confirmed → CPDEL'], ['key' => 's6', 'label' => 'Delivery Order'], ['key' => 's7', 'label' => 'Customer Invoice', 'note' => 'AR']]" />
                </x-dl::card>
            </x-slot:flow>
            <x-slot:tickets>
                <x-dl::card title="Send a Ticket" icon="fa-paper-plane">
                    <x-dl::field.text name="screen_context" label="Screen / menu" placeholder="E.g. Customer Order" />
                    <x-dl::field.text name="description" label="Problem description" multiline />
                </x-dl::card>
                <x-dl::side-screen.foot hint="The developer team answers in Setup > Support & Ticket">
                    <x-dl::button icon="fa-paper-plane" label="Send ticket" />
                    <x-dl::button variant="secondary" label="Back" />
                </x-dl::side-screen.foot>
            </x-slot:tickets>
        </x-dl::side-screen.layout>
    </x-dl::side-screen>
</div>
