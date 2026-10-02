<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use Chat\ChatService;
use Chat\Models\Conversation;
use Chat\Models\Message;
use Chat\Models\Participant;
use Chat\Models\Presence;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

// Chat antar user: paket packages/chat (Chat\ChatService, rute chat.*) + App\Services\Chat\UserDirectory. Memakai tiga user uji yang dibuat sendiri dan dihapus lagi
// di tearDown (bukan akun orang sungguhan); lampiran ditulis ke disk palsu.
class ChatTest extends TestCase
{
    private const LOGINS = ['zz_chat_test_a', 'zz_chat_test_b', 'zz_chat_test_c'];

    private SecUser $a;

    private SecUser $b;

    private SecUser $c;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        // User uji tidak punya jawaban pertanyaan keamanan: lewati gerbangnya seperti di UAT (config, bukan data).
        config(['uat.security_setup_skip' => implode(',', self::LOGINS)]);
        $this->cleanup();
        [$this->a, $this->b, $this->c] = array_map(fn (string $login) => SecUser::create(['login' => $login, 'name' => 'Chat Test '.strtoupper(substr($login, -1)),
            'activate' => true, 'approval_status' => 'approved', 'created_by' => 'test']), self::LOGINS);
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        $conversations = Participant::whereIn('user_id', self::LOGINS)->pluck('conversation_id');
        Message::whereIn('conversation_id', $conversations)->delete();
        Participant::whereIn('conversation_id', $conversations)->delete();
        Conversation::whereIn('id', $conversations)->delete();
        $users = SecUser::whereIn('login', self::LOGINS)->pluck('id_user');
        Presence::whereIn('user_id', $users)->delete();
        SecUser::whereIn('id_user', $users)->delete();
    }

    private function chat(): ChatService
    {
        return app(ChatService::class);
    }

    private function poll(SecUser $as, int $after, ?int $open = null): array
    {
        return $this->actingAs($as)->getJson('/v2/chat/poll?after='.$after.($open ? '&open='.$open : ''))->assertOk()->json();
    }

    public function test_message_reaches_the_other_user_with_unread_count_and_read_receipt(): void
    {
        $start = (int) Message::max('id');

        $sent = $this->actingAs($this->a)->postJson('/v2/chat/send', ['to' => $this->b->id_user, 'text' => '  Hello B  '])->assertOk()->json('msg');
        $this->assertTrue($sent['me']);
        $this->assertSame('Hello B', $sent['t']);
        $this->assertSame(min($this->a->id_user, $this->b->id_user).':'.max($this->a->id_user, $this->b->id_user),
            Conversation::find(Message::find($sent['id'])->conversation_id)->pair_key);

        // B: satu belum dibaca di isi awal, pesannya datang lewat poll, dan B tercatat online.
        $this->assertSame(1, $this->chat()->boot($this->b)['convos'][$this->a->id_user]['unread']);
        $got = $this->poll($this->b, $start);
        $this->assertSame([$sent['id']], array_column($got['msgs'], 'id'));
        $this->assertSame($this->a->id_user, $got['msgs'][0]['peer']);
        $this->assertFalse($got['msgs'][0]['me']);
        $this->assertTrue($got['presence'][$this->b->id_user][0]);

        // A belum melihat centang dibaca sampai B membuka percakapan.
        $this->assertSame(0, $this->poll($this->a, $sent['id'])['read'][$this->b->id_user]);
        $this->actingAs($this->b)->getJson('/v2/chat/messages?peer='.$this->a->id_user)->assertOk()->assertJsonPath('msgs.0.id', $sent['id']);
        $this->assertSame($sent['id'], $this->poll($this->a, $sent['id'])['read'][$this->b->id_user]);
        $this->assertSame(0, $this->chat()->boot($this->b)['convos'][$this->a->id_user]['unread']);

        // Balasan B memakai percakapan yang sama; poll dengan open = langsung dibaca.
        $reply = $this->actingAs($this->b)->postJson('/v2/chat/send', ['to' => $this->a->id_user, 'text' => 'Hi A'])->assertOk()->json('msg');
        $this->assertSame(1, Conversation::whereIn('id', Participant::where('user_id', $this->a->login)->select('conversation_id'))->count());
        $this->poll($this->a, $sent['id'], $this->b->id_user);
        $this->assertSame($reply['id'], $this->poll($this->b, $reply['id'])['read'][$this->a->id_user]);
    }

    public function test_attachment_is_uploaded_in_chunks_and_only_the_two_users_can_open_it(): void
    {
        $uid = (string) Str::uuid();
        $parts = [str_repeat('A', 700), str_repeat('B', 300)];
        $offset = 0;
        foreach ($parts as $part) {
            $this->actingAs($this->a)->post('/v2/chat/upload', ['uid' => $uid, 'offset' => $offset, 'chunk' => UploadedFile::fake()->createWithContent('chunk', $part)],
                ['Accept' => 'application/json'])->assertOk()->assertJson(['received' => $offset += strlen($part)]);
        }
        // Potongan yang tidak menyambung ditolak.
        $this->actingAs($this->a)->post('/v2/chat/upload', ['uid' => $uid, 'offset' => 5000, 'chunk' => UploadedFile::fake()->createWithContent('chunk', 'x')],
            ['Accept' => 'application/json'])->assertStatus(422);

        $msg = $this->actingAs($this->a)->postJson('/v2/chat/send', ['to' => $this->b->id_user, 'text' => '', 'upload' => $uid, 'name' => '..\\..\\Report Q3.pdf'])->assertOk()->json('msg');
        $this->assertSame(['name' => 'Report Q3.pdf', 'size' => 1000, 'image' => false], collect($msg['file'])->except('url')->all());

        $this->actingAs($this->b)->get($msg['file']['url'])->assertOk()->assertHeader('content-disposition', 'attachment; filename="Report Q3.pdf"');
        $this->assertSame(implode('', $parts), Storage::disk('local')->get(Message::find($msg['id'])->attachment_path));
        $this->actingAs($this->c)->get($msg['file']['url'])->assertForbidden();
        // Upload yang sama tidak bisa dipakai dua kali, dan upload orang lain tidak bisa diklaim.
        $this->actingAs($this->a)->postJson('/v2/chat/send', ['to' => $this->b->id_user, 'upload' => $uid, 'name' => 'x.pdf'])->assertStatus(422);
    }

    public function test_only_registered_users_can_be_messaged_and_sign_out_ends_online(): void
    {
        $this->actingAs($this->a)->postJson('/v2/chat/send', ['to' => $this->a->id_user, 'text' => 'me'])->assertStatus(422);
        $this->actingAs($this->a)->postJson('/v2/chat/send', ['to' => $this->b->id_user, 'text' => ''])->assertStatus(422);
        $this->c->update(['activate' => false]);
        $this->actingAs($this->a)->postJson('/v2/chat/send', ['to' => $this->c->id_user, 'text' => 'hi'])->assertNotFound();
        $this->assertNotContains($this->c->id_user, array_column($this->chat()->boot($this->a)['people'], 'id'));
        $this->assertContains($this->b->id_user, array_column($this->chat()->boot($this->a)['people'], 'id'));

        $this->poll($this->b, 0);
        $online = fn () => collect($this->chat()->boot($this->a)['people'])->firstWhere('id', $this->b->id_user)['online'];
        $this->assertTrue($online());
        event(new Logout('web', $this->b));
        $this->assertFalse($online());
    }

    public function test_home_hands_the_chat_api_to_the_component(): void
    {
        $this->actingAs($this->a)->get('/v2')->assertOk()->assertSee('data-dl-chat', false)->assertSee(e(json_encode(route('chat.poll'))), false);
        $this->assertFalse(Presence::where('user_id', $this->a->id_user)->exists(), 'Membuka halaman tidak menulis apa pun; denyut datang dari poll.');
    }
}
