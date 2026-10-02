<?php

namespace Devlib\Components;

// Chat ala WhatsApp. Daftar kiri = orang yang SEDANG LOGIN (avatar berwarna cerah + titik hijau) + percakapan lain yang
// sudah ada; tombol "+" = chat baru dengan orang yang tidak sedang login ("terakhir dilihat ...", avatar abu-abu).
// Foto profil (klik = perbesar; tanpa foto -> inisial), lampiran file apa saja (gambar tampil langsung, lainnya kartu unduh),
// centang terkirim/diterima/dibaca, jam WIB, bunyi saat pesan masuk.
// me: ['id','name','photo'] ; people: [['id','name','role','photo','online' => bool,'seen' => epoch | teks]]
// convos: [personId => ['unread' => n, 'read' => id pesan terakhir yang dibaca lawan, 'msgs' => [MSG]]]
// MSG = ['id','me' => bool,'t' => teks,'ts' => epoch,'file' => ['name','size','url','image' => bool]]  (pratinjau tanpa server
//       boleh memakai 'at' => '09:12', 'day' => 'Hari ini', 'img' => url, 'st' => sent|delivered|read).
// api (aplikasi; kosong = pratinjau tanpa server, kirim -> event dl:chat-send {to, text, file}):
//   poll     GET  ?after=<id>&open=<personId>  -> {msgs: [MSG + peer], last_id, read: {personId: id}, presence: {personId: [online, seen]}}
//   messages GET  ?peer=<id>&before=<id>       -> {msgs: [MSG], more: bool, read: id}
//   upload   POST uid, offset, chunk           -> potongan lampiran (ukuran potong = api.chunk)
//   send     POST to, text, upload, name       -> {msg: MSG}
//   typing   POST to                           -> kabari lawan "sedang mengetik" (hanya saat WebSocket tersambung)
//   + csrf, last_id, poll_seconds, max_mb.
//   realtime (opsional) = ['key','host','port','tls','auth' => url izin channel,'channels' => [..]] + realtime_poll_seconds:
//     WebSocket berprotokol Pusher (Laravel Reverb). Kabar: chat.message {from,to,msg}, chat.read {reader,id},
//     chat.typing {from}, chat.presence {id,online,seen}. Tanpa ini / saat putus, layar memakai poll saja.
// compact = versi drawer (1 kolom bergantian daftar/percakapan).
class Chat extends DevlibComponent
{
    public function __construct(
        public array $me = [],
        public array $people = [],
        public array $convos = [],
        public array $api = [],
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
        return $this->source ?? 'sys_chat_conversations / sys_chat_messages + sys_user_presence';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-wa', 'is-compact' => $compact]) }} {!! $dl() !!} data-dl-chat style="--dl-wa-h: {{ $height }}"
                data-me="{{ json_encode($me) }}" data-people="{{ json_encode($people) }}" data-convos="{{ json_encode((object) $convos) }}" data-api="{{ json_encode((object) $api) }}">
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
                        <div class="dl-wa__bar"><button type="button" class="dl-wa__ib" data-wa-newx aria-label="{{ \Devlib\Lang::text('Back') }}"><i class="fa-solid fa-arrow-left"></i></button><b>{{ \Devlib\Lang::text('New chat') }}</b></div>
                        <div class="dl-wa__search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="{{ \Devlib\Lang::text('Search people…') }}" data-wa-nq aria-label="{{ \Devlib\Lang::text('Search people') }}"></div>
                        <div class="dl-wa__cap is-off">{{ \Devlib\Lang::text('Not signed in') }}</div>
                        <div class="dl-wa__list" data-wa-offline></div>
                    </div>
                </section>
                <section class="dl-wa__main">
                    <div class="dl-wa__bar dl-wa__head" data-wa-head></div>
                    <div class="dl-wa__msgs" data-wa-msgs></div>
                    <div class="dl-wa__preview" data-wa-preview hidden></div>
                    <div class="dl-wa__in" data-wa-in>
                        <button type="button" class="dl-wa__ib" data-wa-attach title="{{ \Devlib\Lang::text('Attach file') }}" aria-label="{{ \Devlib\Lang::text('Attach file') }}"><i class="fa-solid fa-paperclip"></i></button>
                        <input type="file" hidden data-wa-file>
                        <div class="dl-wa__box"><input type="text" placeholder="{{ \Devlib\Lang::text('Type a message') }}" data-wa-text maxlength="4000" aria-label="{{ \Devlib\Lang::text('Message') }}"><button type="button" class="dl-field__trigger dl-case" data-wa-case title="{{ \Devlib\Lang::text('Change case: lower / Title Case / UPPER') }}">aa</button></div>
                        <button type="button" class="dl-wa__send" data-wa-send aria-label="{{ \Devlib\Lang::text('Send') }}"><i class="fa-solid fa-paper-plane"></i></button>
                    </div>
                </section>
                <div class="dl-wa__lb" data-wa-lb hidden><button type="button" class="dl-wa__ib" data-wa-lbx aria-label="{{ \Devlib\Lang::text('Close') }}"><i class="fa-solid fa-xmark"></i></button><a class="dl-wa__ib dl-wa__lbdl" data-wa-lbdl hidden title="{{ \Devlib\Lang::text('Download') }}" aria-label="{{ \Devlib\Lang::text('Download') }}"><i class="fa-solid fa-download"></i></a><img alt="" data-wa-lbimg><div class="dl-wa__lbcap" data-wa-lbcap></div></div>
            </div>
            BLADE;
    }
}
