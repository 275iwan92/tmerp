<?php

namespace Devlib\Components;

// Ikon support kecil; diklik -> popup daftar akun uji coba (UAT). HAPUS dari login sebelum rilis produksi.
class UatAccounts extends DevlibComponent
{
    public function __construct(
        public array $accounts = [
            'Level 1 — Operator' => 'operator', 'Level 2 — Supervisor' => 'supervs', 'Level 3 — Manager Sales' => 'mgr-sal',
            'Level 3 — Manager Purchasing' => 'mgr-pur', 'Level 3 — Manager Inventory' => 'mgr-inv',
            'Level 3 — Manager Finance & Acc.' => 'mgr-acc', 'Level 4 — Direksi' => 'direksi',
        ],
        public string $password = 'contoh!23',
    ) {}

    public function tag(): string
    {
        return 'x-dl::uat-accounts';
    }

    public function source(): string
    {
        return 'sec_user akun contoh UAT (tmerp)';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-uat') }} {!! $dl() !!} data-dl-uat>
                <button type="button" class="dl-uat__icon" data-dl-uat-toggle title="{{ \Devlib\Lang::text('Help: test accounts (UAT)') }}"><i class="fa-solid fa-headset"></i></button>
                <div class="dl-uat__panel">
                    <div class="dl-uat__head">🧪 {{ \Devlib\Lang::text('Test Accounts (UAT)') }}</div>
                    <p class="dl-uat__note">{{ \Devlib\Lang::text('All passwords are the same:') }} <code>{{ $password }}</code></p>
                    <table class="dl-uat__table"><tbody>
                        @foreach ($accounts as $label => $login)
                            <tr><td>{{ $label }}</td><td><code>{{ $login }}</code></td></tr>
                        @endforeach
                    </tbody></table>
                </div>
            </div>
            BLADE;
    }
}
