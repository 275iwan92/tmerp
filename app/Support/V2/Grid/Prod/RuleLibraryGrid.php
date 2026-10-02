<?php

namespace App\Support\V2\Grid\Prod;

use App\Models\Master\ProdMachine;
use App\Models\Prd\PrdSchedulingRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Rule Library penjadwalan (v1: pages::production.rule-library, form modal). Aktivasi aturan tetap lewat approval v1.
class RuleLibraryGrid extends ProdGrid
{
    public function slug(): string
    {
        return 'prod-rule-library';
    }

    public function title(): string
    {
        return 'Rule Library';
    }

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    public function group(): string
    {
        return 'Planning';
    }

    public function icon(): string
    {
        return 'fa-solid fa-book';
    }

    protected function v1Route(): ?string
    {
        return 'production.rule-library';
    }

    protected function models(): array
    {
        return [PrdSchedulingRule::class, ProdMachine::class];
    }

    public function search(): string
    {
        return 'Search rule, machine, process...';
    }

    protected function query(): Builder
    {
        return PrdSchedulingRule::query()->with('machine')->orderByDesc('rule_id');
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Rule / Machine', 'type' => 'doc', 'party' => 'machine'],
            ['key' => 'type', 'label' => 'Type', 'type' => 'badge', 'tones' => ['BATCHING' => 'info', 'SEQUENCE' => 'accent']],
            ['key' => 'process', 'label' => 'Process'],
            ['key' => 'condition', 'label' => 'Condition'],
            ['key' => 'from', 'label' => 'Effective From', 'type' => 'date'],
            ['key' => 'to', 'label' => 'Effective To', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['DRAFT' => 'neutral', 'ACTIVE' => 'success', 'INACTIVE' => 'danger']],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'name' => $m->rule_name, 'type' => $m->rule_type, 'machine' => $m->machine?->machine_name ?? t('All machines'),
            'process' => $m->applies_to_process ?: t('All processes'), 'condition' => trim($m->condition_field.' '.$m->condition_operator),
            'from' => $m->effective_from?->toDateString(), 'to' => $m->effective_to?->toDateString(), 'status' => $m->rule_status,
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Active', 'where' => ['status' => 'ACTIVE'], 'tone' => 'success'],
            ['label' => 'Draft', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'],
        ];
    }

    public function filters(): array
    {
        return ['type' => 'Type', 'machine' => 'Machine', 'status' => 'Status'];
    }
}
