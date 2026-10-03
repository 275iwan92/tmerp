<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SysSupportTicket;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

// Support Tickets (v1: pages::setup.support-tickets): tiket yang dikirim user dari Bantuan & dukungan (F1). Buka = layar v1
// (jawab, ubah status). Tiket baru hanya dibuat dari halaman profil user, bukan dari sini.
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
        return route('setup.support-tickets').'?open={id}';
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
        return SysSupportTicket::query()->with('creator')->withCount('replies')->orderByDesc('id_ticket');
    }

    public function columns(): array
    {
        return [
            ['key' => 'ticket_no', 'label' => 'Ticket No. / Sender', 'type' => 'doc', 'party' => 'sender'],
            ['key' => 'created', 'label' => 'Sent', 'type' => 'date'],
            ['key' => 'screen', 'label' => 'Screen'],
            ['key' => 'problem', 'label' => 'Problem'],
            ['key' => 'replies', 'label' => 'Replies', 'type' => 'number'],
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
}
