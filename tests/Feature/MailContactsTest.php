<?php

namespace Tests\Feature;

use App\Models\Master\SupSupplier;
use App\Models\Sys\SecUser;
use App\Support\V2\Data\MailContacts;
use Tests\TestCase;

// Pencarian kontak untuk dialog Email x-dl::doc-share (kontak customer, kontak supplier, user). Hanya membaca data.
class MailContactsTest extends TestCase
{
    public function test_short_or_wildcard_query_returns_nothing(): void
    {
        $this->assertSame([], MailContacts::search(''));
        $this->assertSame([], MailContacts::search('a'));
        // "%" dan "_" diperlakukan sebagai huruf biasa, bukan wildcard LIKE.
        $this->assertSame([], MailContacts::search('%%'));
    }

    public function test_search_finds_a_supplier_by_its_email_and_rows_are_unique_valid_addresses(): void
    {
        $supplier = SupSupplier::query()->where('activate', 1)->whereNotNull('email')->where('email', 'like', '%@%.%')->first();
        if (! $supplier || ! filter_var(trim($supplier->email), FILTER_VALIDATE_EMAIL)) {
            $this->markTestSkipped('Tidak ada supplier aktif ber-e-mail sah di database ini.');
        }

        $rows = MailContacts::search(trim($supplier->email));
        $emails = array_map('mb_strtolower', array_column($rows, 'email'));

        $this->assertContains(mb_strtolower(trim($supplier->email)), $emails);
        $this->assertSame($emails, array_values(array_unique($emails)));
        $this->assertLessThanOrEqual(MailContacts::LIMIT, count($rows));
        foreach ($rows as $r) {
            $this->assertSame(['name', 'email', 'group'], array_keys($r));
            $this->assertNotFalse(filter_var($r['email'], FILTER_VALIDATE_EMAIL));
        }
    }

    public function test_route_needs_login_and_returns_rows_as_json(): void
    {
        $this->get('/v2/mail-contacts?q=pt')->assertRedirect();

        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail())
            ->getJson('/v2/mail-contacts?q=pt')->assertOk()->assertJsonStructure(['rows']);
    }
}
