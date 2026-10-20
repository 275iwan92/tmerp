<?php

/*
 * REMARK
 * Tujuan       : Penanganan tiket Bantuan & dukungan (sys_support_ticket) oleh tim support dari layar v2 support-ticket-form: balas pelapor (balasan tersimpan di sys_support_replies, tiket OPEN otomatis menjadi IN_PROGRESS) dan ubah status OPEN / IN_PROGRESS / DONE / CLOSED dengan catatan penyelesaian. Tiket baru dibuat pelapor dari menu akun (routes/v2.php support.ticket), bukan dari sini.
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::setup.support-tickets (diarsipkan di tmerp_unused/setup_support_tickets_v1_2026-10-20.blade.php): balasan staff memajukan OPEN ke IN_PROGRESS dan tidak menimpa DONE / CLOSED, DONE / CLOSED mengisi resolved_by / resolved_at, catatan penyelesaian opsional. Baru (Jo 2026-10-20, mode full speed): balasan wajib berisi dan maks 5000, tiket CLOSED tidak bisa dibalas, status harus dari daftar, membuka kembali tiket (OPEN / IN_PROGRESS) mengosongkan penanda selesai, semua dalam transaksi tm2sys.
 * Input        : tiket, isi balasan atau status baru, catatan penyelesaian, user admin yang bertindak.
 * Output       : tm2sys.sys_support_reply dan sys_support_ticket berubah; ValidationException bila aturan dilanggar.
 * Dipakai oleh : routes/v2_setup.php (v2.setup.ticket.save), layar support-ticket-form, tes SupportTicketServiceTest.
 * Status       : Aktif (T8 Setup, 2026-10-20).
 */

namespace App\Services\Support;

use App\Models\Sys\SecUser;
use App\Models\Sys\SysSupportReply;
use App\Models\Sys\SysSupportTicket;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SupportTicketService
{
    public const STATUSES = ['OPEN' => 'Open', 'IN_PROGRESS' => 'In progress', 'DONE' => 'Done', 'CLOSED' => 'Closed'];

    /** Balas pelapor. Tiket OPEN menjadi IN_PROGRESS (sinyal "sedang ditangani"); DONE tetap DONE. */
    public function reply(SysSupportTicket $t, string $body, SecUser $by): SysSupportReply
    {
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 5000) {
            throw ValidationException::withMessages(['reply' => 'Write a reply of 1 to 5000 characters.']);
        }

        return DB::connection('tm2sys')->transaction(function () use ($t, $body, $by) {
            $fresh = SysSupportTicket::whereKey($t->getKey())->lockForUpdate()->firstOrFail();
            if ($fresh->status === 'CLOSED') {
                throw ValidationException::withMessages(['document' => 'A closed ticket cannot be replied to; reopen it first.']);
            }
            $reply = SysSupportReply::create(['ticket_id' => $fresh->getKey(), 'author' => trim((string) $by->name) ?: $by->login, 'body' => $body]);
            if ($fresh->status === 'OPEN') {
                $fresh->update(['status' => 'IN_PROGRESS']);
            }

            return $reply;
        });
    }

    /** Ubah status; DONE / CLOSED menandai penyelesai dan waktunya, status terbuka mengosongkannya. */
    public function setStatus(SysSupportTicket $t, string $status, ?string $note, SecUser $by): SysSupportTicket
    {
        if (! array_key_exists($status, self::STATUSES)) {
            throw ValidationException::withMessages(['document' => 'Choose a status from the list.']);
        }
        $note = trim((string) $note);
        if (mb_strlen($note) > 2000) {
            throw ValidationException::withMessages(['resolution_note' => 'The resolution note can be at most 2000 characters.']);
        }

        return DB::connection('tm2sys')->transaction(function () use ($t, $status, $note, $by) {
            $fresh = SysSupportTicket::whereKey($t->getKey())->lockForUpdate()->firstOrFail();
            $closing = in_array($status, ['DONE', 'CLOSED'], true);
            $fresh->update(['status' => $status, 'resolution_note' => $note !== '' ? $note : $fresh->resolution_note,
                'resolved_by' => $closing ? $by->login : null, 'resolved_at' => $closing ? now() : null]);

            return $fresh;
        });
    }

    /** Riwayat tab History: dikirim, tiap balasan dan penyelesaian, terbaru di atas. */
    public function history(SysSupportTicket $t): array
    {
        $rows = [['at' => $t->created_at?->toDateTimeString(), 'by' => (string) ($t->creator?->name ?: $t->creator?->login), 'action' => 'Sent', 'note' => '']];
        foreach ($t->replies as $r) {
            $rows[] = ['at' => $r->created_at?->toDateTimeString(), 'by' => (string) $r->author, 'action' => 'Reply', 'note' => mb_strimwidth((string) $r->body, 0, 80, '…')];
        }
        if ($t->resolved_at) {
            $rows[] = ['at' => $t->resolved_at->toDateTimeString(), 'by' => (string) $t->resolved_by, 'action' => ucfirst(strtolower($t->status)), 'note' => (string) $t->resolution_note];
        }
        usort($rows, fn ($a, $b) => strcmp((string) $b['at'], (string) $a['at']));

        return $rows;
    }
}
