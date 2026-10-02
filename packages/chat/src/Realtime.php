<?php

namespace Chat;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

// Dorongan seketika lewat WebSocket. Opsional: tanpa server WebSocket (config chat.realtime.key kosong), atau saat servernya
// mati, chat tetap jalan dengan bertanya berkala (poll) -- hanya lebih lambat.
// Servernya = Laravel Reverb (atau server lain berprotokol Pusher) yang berjalan TERPISAH dari aplikasi. Kelas ini berbicara
// protokol itu sendiri -- menandatangani kabar yang dikirim dan izin langganan channel -- sehingga aplikasi pemakai tidak
// perlu memasang paket broadcast apa pun.
// Channel privat: chat.<scope>.user.<id> (kabar untuk satu user) dan chat.<scope>.everyone (siapa yang online).
// <scope> memisahkan aplikasi / database yang memakai satu server WebSocket yang sama (id user-nya bisa kembar).
final class Realtime
{
    public static function enabled(): bool
    {
        return filled(config('chat.realtime.key')) && filled(config('chat.realtime.secret')) && filled(config('chat.realtime.app_id'));
    }

    public static function scope(): string
    {
        return config('chat.scope') ?? DB::connection(config('chat.connection'))->getDatabaseName();
    }

    public static function userChannel(int $id): string
    {
        return 'private-chat.'.self::scope().'.user.'.$id;
    }

    public static function everyoneChannel(): string
    {
        return 'private-chat.'.self::scope().'.everyone';
    }

    // Kirim satu kabar ke browser yang sedang berlangganan channel itu. Server WebSocket mati / lambat = dicatat di log;
    // pekerjaan utamanya (pesan sudah tersimpan) tidak ikut gagal dan tidak menunggu lama.
    public static function push(array $channels, string $event, array $data): void
    {
        if (! self::enabled()) {
            return;
        }
        $c = config('chat.realtime');
        $path = '/apps/'.$c['app_id'].'/events';
        $body = json_encode(['name' => $event, 'channels' => array_values($channels), 'data' => json_encode($data)]);
        $query = ['auth_key' => $c['key'], 'auth_timestamp' => time(), 'auth_version' => '1.0', 'body_md5' => md5($body)];
        ksort($query);
        $query['auth_signature'] = hash_hmac('sha256', "POST\n{$path}\n".urldecode(http_build_query($query)), $c['secret']);
        try {
            Http::connectTimeout(1)->timeout(2)->withBody($body, 'application/json')
                ->post($c['scheme'].'://'.$c['host'].':'.$c['port'].$path.'?'.http_build_query($query))->throw();
        } catch (Throwable $e) {
            report($e);
        }
    }

    // Yang diperlukan browser untuk menyambung. host null = host halaman yang sedang dibuka.
    public static function client(Authenticatable $me): ?array
    {
        $c = config('chat.realtime');

        return self::enabled() ? [
            'key' => $c['key'], 'host' => $c['client_host'], 'port' => (int) ($c['client_port'] ?? $c['port']), 'tls' => ($c['client_scheme'] ?? $c['scheme']) === 'https',
            'auth' => route('chat.auth'), 'channels' => [self::userChannel((int) $me->getAuthIdentifier()), self::everyoneChannel()],
        ] : null;
    }

    // Izin langganan channel privat (tanda tangan protokol Pusher): user hanya boleh mendengar channel miliknya sendiri
    // dan channel bersama di scope aplikasi ini.
    public static function authorize(Authenticatable $me, string $socketId, string $channel): array
    {
        abort_unless(self::enabled() && in_array($channel, [self::userChannel((int) $me->getAuthIdentifier()), self::everyoneChannel()], true), 403);

        return ['auth' => config('chat.realtime.key').':'.hash_hmac('sha256', $socketId.':'.$channel, config('chat.realtime.secret'))];
    }
}
