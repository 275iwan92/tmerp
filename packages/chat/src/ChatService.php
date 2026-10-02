<?php

namespace Chat;

use Chat\Contracts\Directory;
use Chat\Models\Conversation;
use Chat\Models\Message;
use Chat\Models\Participant;
use Chat\Models\Presence;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Chat antar user, satu lawan satu ("cara kerja seperti WhatsApp"). Tanpa websocket: layar bertanya ke poll() tiap
// beberapa detik, dan pertanyaan itu sekaligus denyut "sedang login" (sys_user_presence). Siapa usernya ditanyakan ke
// Directory milik aplikasi pemakai; kelas ini tidak mengenal tabel user mana pun.
class ChatService
{
    private const PAGE = 50;

    // Hanya jenis ini yang ditampilkan langsung di browser; selain itu selalu diunduh (file kiriman user tidak dijalankan).
    private const INLINE_IMAGES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(private Directory $directory) {}

    // Isi awal layar chat: orang, percakapan (pesan terakhir + jumlah belum dibaca), alamat API. Hanya membaca;
    // denyut online pertama datang dari poll() yang dipanggil layar begitu terbuka.
    public function boot(Authenticatable $me): array
    {
        $key = $this->directory->key($me);
        $threads = $this->threads($me);
        $last = Message::query()->whereIn('id', Message::query()->selectRaw('MAX(id)')
            ->whereIn('conversation_id', $threads->keys())->groupBy('conversation_id'))->get()->keyBy('conversation_id');
        $unread = $this->unread($key, $threads);
        $convos = [];
        foreach ($threads as $id => $t) {
            if ($m = $last->get($id)) {
                $convos[$t['peer']] = ['unread' => (int) ($unread[$id] ?? 0), 'read' => $t['peer_read'], 'msgs' => [$this->payload($m, $key)]];
            }
        }
        $presence = $this->presence();

        return [
            'me' => ['id' => $me->getAuthIdentifier(), 'name' => $this->directory->name($me)],
            'people' => array_map(fn (array $p) => ['id' => $p['id'], 'name' => $p['name'], 'role' => $p['role'] ?? '', 'photo' => $p['photo'] ?? null,
                'online' => $presence[$p['id']][0] ?? false, 'seen' => $presence[$p['id']][1] ?? null], $this->directory->people($me)),
            'convos' => $convos,
            'api' => [
                'poll' => route('chat.poll'), 'messages' => route('chat.messages'), 'send' => route('chat.send'), 'upload' => route('chat.upload'),
                'csrf' => csrf_token(), 'last_id' => (int) Message::max('id'), 'chunk' => (int) config('chat.chunk_bytes'),
                'poll_seconds' => max(2, (int) Chat::setting('poll_seconds')), 'max_mb' => Chat::setting('attachment_max_mb'),
            ],
        ];
    }

    // Satu halaman pesan (terbaru dulu, $before = id pesan tertua yang sudah tampil). Membuka percakapan = sudah dibaca.
    public function messages(Authenticatable $me, int $peerId, ?int $before = null): array
    {
        $key = $this->directory->key($me);
        $c = $this->conversation($me, $this->peer($me, $peerId), false);
        if (! $c) {
            return ['msgs' => [], 'more' => false, 'read' => 0];
        }
        $rows = Message::where('conversation_id', $c->id)->when($before, fn ($q) => $q->where('id', '<', $before))
            ->orderByDesc('id')->limit(self::PAGE + 1)->get();
        if (! $before) {
            $this->markRead($key, $c->id);
        }

        return [
            'msgs' => $rows->take(self::PAGE)->reverse()->values()->map(fn ($m) => $this->payload($m, $key))->all(),
            'more' => $rows->count() > self::PAGE,
            'read' => (int) Participant::where('conversation_id', $c->id)->where('user_id', '!=', $key)->value('last_read_message_id'),
        ];
    }

    public function send(Authenticatable $me, int $peerId, string $text, ?string $uploadId = null, ?string $fileName = null): array
    {
        $key = $this->directory->key($me);
        $peer = $this->peer($me, $peerId);
        $text = trim($text);
        if ($text === '' && ! $uploadId) {
            throw ValidationException::withMessages(['text' => Chat::text('Type a message or attach a file.')]);
        }
        $file = $uploadId ? $this->claimUpload($me, $uploadId, (string) $fileName) : [];
        $c = $this->conversation($me, $peer, true);
        $m = Message::create(['conversation_id' => $c->id, 'sender' => $key, 'body' => $text] + $file);
        Participant::where('conversation_id', $c->id)->where('user_id', $key)->update(['last_read_message_id' => $m->id, 'last_read_at' => now()]);

        return $this->payload($m, $key);
    }

    // Satu potong lampiran. $offset wajib sama dengan yang sudah diterima: potongan hilang / dobel ditolak, bukan disambung salah.
    public function storeChunk(Authenticatable $me, string $uploadId, int $offset, UploadedFile $chunk): int
    {
        $disk = $this->disk();
        $path = $this->tmpPath($me, $uploadId);
        if ($offset === 0) {
            $this->sweep();
            $disk->put($path, '');
        }
        if (! $disk->exists($path) || $disk->size($path) !== $offset) {
            throw ValidationException::withMessages(['chunk' => Chat::text('The upload was interrupted. Send the file again.')]);
        }
        $size = $offset + $chunk->getSize();
        $max = Chat::setting('attachment_max_mb');
        if ($size > $max * 1024 * 1024) {
            $disk->delete($path);
            throw ValidationException::withMessages(['chunk' => str_replace(':max', (string) $max, Chat::text('The file is larger than :max MB.'))]);
        }
        file_put_contents($disk->path($path), $chunk->getContent(), FILE_APPEND | LOCK_EX);

        return $size;
    }

    // Lampiran hanya untuk dua orang di percakapannya.
    public function attachment(Authenticatable $me, int $messageId): StreamedResponse
    {
        $m = Message::whereNotNull('attachment_path')->findOrFail($messageId);
        abort_unless(Participant::where('conversation_id', $m->conversation_id)->where('user_id', $this->directory->key($me))->exists(), 403);

        return $this->disk()->response($m->attachment_path, $m->attachment_name,
            ['Content-Type' => $m->attachment_mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=86400'],
            in_array($m->attachment_mime, self::INLINE_IMAGES, true) ? 'inline' : 'attachment');
    }

    // Dipanggil layar tiap beberapa detik: denyut online + pesan baru sesudah $after + penanda dibaca lawan bicara.
    // $openPeer = percakapan yang sedang tampil di layar user -> pesannya langsung dianggap dibaca.
    public function poll(Authenticatable $me, int $after, ?int $openPeer = null): array
    {
        $key = $this->directory->key($me);
        Presence::upsert([['user_id' => $me->getAuthIdentifier(), 'signed_in' => true, 'last_seen_at' => now()]], ['user_id'], ['signed_in', 'last_seen_at']);
        $threads = $this->threads($me);
        $rows = $threads->isEmpty() ? collect()
            : Message::whereIn('conversation_id', $threads->keys())->where('id', '>', $after)->orderBy('id')->limit(200)->get();
        $open = $openPeer ? $threads->search(fn ($t) => $t['peer'] === $openPeer) : false;
        if ($open !== false) {
            $this->markRead($key, $open);
        }

        return [
            'msgs' => $rows->map(fn ($m) => ['peer' => $threads[$m->conversation_id]['peer']] + $this->payload($m, $key))->all(),
            'last_id' => $rows->isEmpty() ? $after : $rows->last()->id,
            'read' => (object) $threads->mapWithKeys(fn ($t) => [$t['peer'] => $t['peer_read']])->all(),
            'presence' => (object) $this->presence(),
        ];
    }

    // Sign out = langsung tampil tidak online, tanpa menunggu denyutnya basi.
    public function signOut(Authenticatable $user): void
    {
        Presence::where('user_id', $user->getAuthIdentifier())->update(['signed_in' => false, 'last_seen_at' => now()]);
    }

    /** @return array<int, array{0: bool, 1: ?int}> id user => [online, terakhir terlihat (epoch)] */
    private function presence(): array
    {
        $since = now()->subSeconds(max(15, (int) Chat::setting('online_timeout_seconds')));

        return Presence::query()->get()->mapWithKeys(fn (Presence $p) => [$p->user_id => [
            $p->signed_in && $p->last_seen_at && $p->last_seen_at->gte($since), $p->last_seen_at?->timestamp]])->all();
    }

    private function peer(Authenticatable $me, int $peerId): array
    {
        abort_if($peerId === (int) $me->getAuthIdentifier(), 422);

        return $this->directory->person($peerId) ?? abort(404);
    }

    /** @return Collection<int, array{peer: int, peer_read: int}> percakapan satu-lawan-satu milik user, kunci = id percakapan */
    private function threads(Authenticatable $me): Collection
    {
        $key = $this->directory->key($me);
        $mine = Participant::where('user_id', $key)->pluck('conversation_id');
        if ($mine->isEmpty()) {
            return collect();
        }
        $pairs = Conversation::whereIn('id', $mine)->whereNotNull('pair_key')->pluck('pair_key', 'id');
        $peerRead = Participant::whereIn('conversation_id', $pairs->keys())->where('user_id', '!=', $key)->pluck('last_read_message_id', 'conversation_id');

        return $pairs->map(fn (string $pair, int $id) => [
            'peer' => (int) collect(explode(':', $pair))->first(fn ($u) => (int) $u !== (int) $me->getAuthIdentifier()),
            'peer_read' => (int) ($peerRead[$id] ?? 0),
        ]);
    }

    private function unread(string $key, Collection $threads): Collection
    {
        return Message::query()->from('sys_chat_messages as m')
            ->join('sys_chat_participants as p', fn ($j) => $j->on('p.conversation_id', '=', 'm.conversation_id')->where('p.user_id', '=', $key))
            ->whereIn('m.conversation_id', $threads->keys())->where('m.sender', '!=', $key)
            ->whereRaw('m.id > COALESCE(p.last_read_message_id, 0)')
            ->groupBy('m.conversation_id')->selectRaw('m.conversation_id, COUNT(*) AS n')->pluck('n', 'conversation_id');
    }

    private function conversation(Authenticatable $me, array $peer, bool $create): ?Conversation
    {
        $ids = [(int) $me->getAuthIdentifier(), (int) $peer['id']];
        $pair = min($ids).':'.max($ids);
        if (! $create) {
            return Conversation::where('pair_key', $pair)->first();
        }
        $c = Conversation::createOrFirst(['pair_key' => $pair], ['is_group' => false]);
        foreach ([$this->directory->key($me), (string) $peer['key']] as $key) {
            Participant::createOrFirst(['conversation_id' => $c->id, 'user_id' => $key]);
        }

        return $c;
    }

    private function markRead(string $key, int $conversationId): void
    {
        $max = Message::where('conversation_id', $conversationId)->max('id');
        if ($max) {
            Participant::where('conversation_id', $conversationId)->where('user_id', $key)
                ->where(fn ($q) => $q->whereNull('last_read_message_id')->orWhere('last_read_message_id', '<', $max))
                ->update(['last_read_message_id' => $max, 'last_read_at' => now()]);
        }
    }

    private function payload(Message $m, string $key): array
    {
        $p = ['id' => $m->id, 'me' => $m->sender === $key, 't' => (string) $m->body, 'ts' => $m->sent_at->timestamp];
        if ($m->attachment_path) {
            $p['file'] = ['name' => $m->attachment_name, 'size' => $m->attachment_size, 'url' => route('chat.file', $m->id),
                'image' => in_array($m->attachment_mime, self::INLINE_IMAGES, true)];
        }

        return $p;
    }

    private function disk()
    {
        return Storage::disk(config('chat.disk'));
    }

    private function tmpPath(Authenticatable $me, string $uploadId): string
    {
        return "chat/tmp/{$me->getAuthIdentifier()}-{$uploadId}.part";
    }

    // Upload yang ditinggalkan (tab ditutup di tengah jalan) dibuang setelah sehari.
    private function sweep(): void
    {
        $disk = $this->disk();
        foreach ($disk->files('chat/tmp') as $f) {
            if ($disk->lastModified($f) < now()->subDay()->timestamp) {
                $disk->delete($f);
            }
        }
    }

    // Upload selesai -> jadi lampiran pesan. Nama file dari user hanya untuk tampilan; di disk memakai nama acak.
    private function claimUpload(Authenticatable $me, string $uploadId, string $fileName): array
    {
        $disk = $this->disk();
        $tmp = $this->tmpPath($me, $uploadId);
        if (! $disk->exists($tmp) || ! ($size = $disk->size($tmp))) {
            throw ValidationException::withMessages(['upload' => Chat::text('The attached file did not arrive. Send it again.')]);
        }
        $path = 'chat/'.now()->format('Y/m').'/'.Str::uuid();
        $disk->move($tmp, $path);
        $name = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $fileName))) ?? '');

        return ['attachment_path' => $path, 'attachment_name' => Str::limit($name !== '' ? $name : 'file', 200, ''),
            'attachment_mime' => $disk->mimeType($path) ?: 'application/octet-stream', 'attachment_size' => $size];
    }
}
