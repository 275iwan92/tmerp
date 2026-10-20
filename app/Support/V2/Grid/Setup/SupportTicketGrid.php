<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SysSupportTicket;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
 * REMARK
 * Tujuan       : Grid Setup > Support Tickets: tiket yang dikirim user dari Bantuan & dukungan (F1) dengan status, jumlah balasan dan lampiran; Open membuka form v2 support-ticket-form (balas, ubah status). Tiket baru dibuat pelapor dari menu akun, bukan dari sini.
 * Sumber hulu  : tm2sys.sys_support_ticket + sys_support_replies + sys_support_ticket_attachment + sec_user.
 * Input        : Pencarian, filter Screen / Status, kotak status.
 * Output       : Baris grid (hanya baca; penanganan lewat form).
 * Dipakai oleh : Menu Setup > Support & Ticket (v2:support-tickets), layar support-ticket-form (kembali ke daftar).
 * Status       : Aktif. Halaman v1 pages::setup.support-tickets sudah dipindah ke tmerp_unused (aturan D.25, T8 Setup).
 */
class SupportTicketGrid extends DbGrid
{
    private const STATUS_TONES = ['OPEN' => 'warn', 'IN_PROGRESS' => 'info', 'DONE' => 'success', 'CLOSED' => 'neutral'];

    public function slug(): string
    {
        return 'support-tickets';
    }

    public function title(): string
    {
        return 'Support Tickets';
    }

    public function module(): string
    {
        return 'SETUP';
    }

    public function moduleLabel(): string
    {
        return 'Setup';
    }

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    public function group(): string
    {
        return 'Support & Ticket';
    }

    public function icon(): string
    {
        return 'fa-solid fa-life-ring';
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'support-ticket-form').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return null;
    }

    public function search(): string
    {
        return 'Search ticket no, sender, problem...';
    }

    protected function query(): Builder
    {
        return SysSupportTicket::query()->with(['creator', 'attachments'])->withCount('replies')->orderByDesc('id_ticket');
    }

    public function columns(): array
    {
        return [
            ['key' => 'ticket_no', 'label' => 'Ticket No. / Sender', 'type' => 'doc', 'party' => 'sender'],
            ['key' => 'created', 'label' => 'Sent', 'type' => 'date'],
            ['key' => 'screen', 'label' => 'Screen'],
            ['key' => 'problem', 'label' => 'Problem'],
            ['key' => 'replies', 'label' => 'Replies', 'type' => 'number'],
            ['key' => 'files', 'label' => 'Files', 'type' => 'number'],
            ['key' => 'resolved_by', 'label' => 'Resolved By', 'visible' => false],
            ['key' => 'resolved', 'label' => 'Resolved', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => self::STATUS_TONES],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'ticket_no' => $m->ticket_no, 'sender' => $m->creator?->name ?: $m->creator?->login, 'created' => $m->created_at?->toDateString(),
            'screen' => $m->screen_context, 'problem' => Str::limit((string) $m->description, 140), 'replies' => (int) $m->replies_count,
            'resolved_by' => $m->resolved_by, 'resolved' => $m->resolved_at?->toDateString(), 'status' => $m->status ?: 'OPEN',
            'files' => $m->attachments->count(),
            'attachments' => $m->attachments->map(fn ($a) => ['name' => basename($a->file_path), 'url' => Storage::disk('public')->url($a->file_path)])->all(),
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Open', 'where' => ['status' => 'OPEN'], 'tone' => 'warn'],
            ['label' => 'In Progress', 'where' => ['status' => 'IN_PROGRESS'], 'tone' => 'info'],
            ['label' => 'Done', 'where' => ['status' => ['DONE', 'CLOSED']], 'tone' => 'success'],
        ];
    }

    public function filters(): array
    {
        return ['screen' => 'Screen', 'status' => 'Status'];
    }

    public function detail(): array
    {
        return ['key' => 'attachments', 'title' => 'Attachments', 'columns' => [
            ['key' => 'name', 'label' => 'File', 'strong' => true],
            ['key' => 'url', 'label' => 'Link'],
        ]];
    }
}
