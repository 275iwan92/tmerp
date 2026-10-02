<?php

namespace Chat\Http;

use Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

// API yang dipanggil layar chat (objek DevLib x-dl::chat, prop api). Bentuk jawabannya: lihat Devlib\Components\Chat.
class ChatController
{
    public function __construct(private ChatService $chat) {}

    public function poll(Request $r): JsonResponse
    {
        return response()->json($this->chat->poll($r->user(), (int) $r->query('after', 0), $r->filled('open') ? (int) $r->query('open') : null));
    }

    public function messages(Request $r): JsonResponse
    {
        $d = $r->validate(['peer' => ['required', 'integer'], 'before' => ['nullable', 'integer']]);

        return response()->json($this->chat->messages($r->user(), (int) $d['peer'], isset($d['before']) ? (int) $d['before'] : null));
    }

    public function upload(Request $r): JsonResponse
    {
        $d = $r->validate(['uid' => ['required', 'uuid'], 'offset' => ['required', 'integer', 'min:0'],
            'chunk' => ['required', 'file', 'max:'.(int) ceil(config('chat.chunk_bytes') / 1024)]]);

        return response()->json(['received' => $this->chat->storeChunk($r->user(), $d['uid'], (int) $d['offset'], $r->file('chunk'))]);
    }

    public function send(Request $r): JsonResponse
    {
        $d = $r->validate(['to' => ['required', 'integer'], 'text' => ['nullable', 'string', 'max:4000'], 'upload' => ['nullable', 'uuid'], 'name' => ['nullable', 'string', 'max:255']]);

        return response()->json(['msg' => $this->chat->send($r->user(), (int) $d['to'], (string) ($d['text'] ?? ''), $d['upload'] ?? null, $d['name'] ?? null)]);
    }

    public function file(Request $r, int $id): StreamedResponse
    {
        return $this->chat->attachment($r->user(), $id);
    }
}
