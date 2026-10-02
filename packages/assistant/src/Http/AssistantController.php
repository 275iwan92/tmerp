<?php

namespace Assistant\Http;

use Assistant\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// API yang dipanggil drawer asisten (x-dl::shell prop assistant). Bentuk jawabannya: Assistant\Reply::toArray().
class AssistantController
{
    public function __construct(private AssistantService $assistant) {}

    public function ask(Request $r): JsonResponse
    {
        $d = $r->validate(['q' => ['required', 'string', 'max:500'], 'topic' => ['nullable', 'string', 'max:100']]);

        return response()->json($this->assistant->ask($r->user(), trim($d['q']), $d['topic'] ?? null));
    }

    public function feedback(Request $r): JsonResponse
    {
        $d = $r->validate(['id' => ['required', 'integer'], 'value' => ['required', 'integer', 'in:-1,0,1']]);

        return response()->json(['ok' => $this->assistant->feedback($r->user(), (int) $d['id'], (int) $d['value'])]);
    }
}
