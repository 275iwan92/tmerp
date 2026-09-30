<?php

namespace Devlib\Components;

// Chat ala WhatsApp. Daftar kiri = orang yang SEDANG LOGIN (titik hijau) + percakapan lain yang sudah dibuka;
// tombol "+" = chat baru dengan orang yang tidak sedang login ("terakhir dilihat ...").
// Foto profil (klik = perbesar; tanpa foto -> inisial), kirim foto (lampiran + keterangan), centang terkirim/dibaca, jam WIB.
// me: ['id','name','photo'] ; people: [['id','name','role','photo','online' => bool,'seen' => '10:24']]
// convos: [personId => ['unread' => n, 'msgs' => [['me' => bool,'t' => teks,'img' => url,'at' => '09:12','day' => 'Hari ini','st' => sent|delivered|read]]]]
// Kirim -> event dl:chat-send {to, text, image}. compact = versi drawer (1 kolom bergantian daftar/percakapan).
class Chat extends DevlibComponent
{
    public function __construct(
        public array $me = [],
        public array $people = [],
        public array $convos = [],
        public bool $compact = false,
        public string $height = '560px',
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::chat';
    }

    public function source(): string
    {
        return $this->source ?? 'sys_chat_conversations / sys_chat_messages + sesi login (rencana)';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-wa', 'is-compact' => $compact]) }} {!! $dl() !!} data-dl-chat style="--dl-wa-h: {{ $height }}"
                data-me="{{ json_encode($me) }}" data-people="{{ json_encode($people) }}" data-convos="{{ json_encode((object) $convos) }}">
                <section class="dl-wa__side">
                    <div class="dl-wa__bar">
                        <span class="dl-wa__av" data-wa-meav></span>
                        <b>Chat</b>
                        <span class="dl-print__spacer"></span>
                        <button type="button" class="dl-wa__ib" data-wa-new title="{{ \Devlib\Lang::text('New chat (people who are not logged in)') }}" aria-label="{{ \Devlib\Lang::text('New chat') }}"><i class="fa-solid fa-plus"></i></button>
                    </div>
                    <div class="dl-wa__search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="{{ \Devlib\Lang::text('Search name…') }}" data-wa-q aria-label="{{ \Devlib\Lang::text('Search name') }}"></div>
                    <div class="dl-wa__list" data-wa-list></div>
                    <div class="dl-wa__new" data-wa-newpanel hidden>
                        <div class="dl-wa__bar"><button type="button" class="dl-wa__ib" data-wa-newx aria-label="Kembali"><i class="fa-solid fa-arrow-left"></i></button><b>Chat baru</b></div>
                        <div class="dl-wa__search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="{{ \Devlib\Lang::text('Search people…') }}" data-wa-nq aria-label="{{ \Devlib\Lang::text('Search people') }}"></div>
                        <div class="dl-wa__cap">Tidak sedang login</div>
                        <div class="dl-wa__list" data-wa-offline></div>
                    </div>
                </section>
                <section class="dl-wa__main">
                    <div class="dl-wa__bar dl-wa__head" data-wa-head></div>
                    <div class="dl-wa__msgs" data-wa-msgs></div>
                    <div class="dl-wa__preview" data-wa-preview hidden></div>
                    <div class="dl-wa__in" data-wa-in>
                        <button type="button" class="dl-wa__ib" data-wa-attach title="{{ \Devlib\Lang::text('Send photo') }}" aria-label="{{ \Devlib\Lang::text('Send photo') }}"><i class="fa-solid fa-paperclip"></i></button>
                        <input type="file" accept="image/*" hidden data-wa-file>
                        <div class="dl-wa__box"><input type="text" placeholder="Ketik pesan" data-wa-text aria-label="Pesan"><button type="button" class="dl-field__trigger dl-case" data-wa-case title="Ganti huruf: kecil / Kapital Tiap Kata / BESAR">aa</button></div>
                        <button type="button" class="dl-wa__send" data-wa-send aria-label="{{ \Devlib\Lang::text('Send') }}"><i class="fa-solid fa-paper-plane"></i></button>
                    </div>
                </section>
                <div class="dl-wa__lb" data-wa-lb hidden><button type="button" class="dl-wa__ib" data-wa-lbx aria-label="{{ \Devlib\Lang::text('Close') }}"><i class="fa-solid fa-xmark"></i></button><img alt="" data-wa-lbimg><div class="dl-wa__lbcap" data-wa-lbcap></div></div>
            </div>
            BLADE;
    }
}
