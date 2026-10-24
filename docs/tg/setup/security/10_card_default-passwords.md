# Concept card: Default Passwords screen (TEMPORARY, UAT only)

> Written 2026-10-24. FACT = code with line numbers, seen this session. This screen exists only for the UAT stage and is removed after it (see "After UAT").

- **Document type**: concept card. **Module/sub-module**: Setup > Security > Default Passwords (`SET_DEFAULT_PW`, target `/v2/setup/default-passwords`, menu row from migration `2026_10_24_000100_menu_default_passwords.php`).
- **Canonical name**: Default Passwords screen: one list of ALL users with their stored default password and a Reset to Default button per row.
- **Synonyms**: password bawaan, default password list, password viewer, UAT password list.
- **NOT the same as**: the Account form (`security-account-form`): that one shows ONE account's default password through its View Password button; this screen lists all and is limited to named logins.

## Facts
| Item | Content | Evidence |
|---|---|---|
| Who | Only logins listed in `config('uat.password_viewers')` (env `UAT_PASSWORD_VIEWERS`, default `johiwan`), compared case-insensitive and trimmed; empty list = nobody; the account must be active. | FACT, `config/uat.php:22`; `DefaultPasswordViewer.php:26-36` |
| Server gate | Every route aborts 403 for any other login (list, reveal, reset), on top of the `admin` middleware; hiding the menu is NOT the only protection. | FACT, `routes/v2_setup.php:100-129` |
| Menu | The menu row exists for all, but `PayrollAccess::menuAllowed` hides it from every login outside the list. | FACT, `PayrollAccess.php:88` |
| Data | `sec_user`: `login`, `name`, `birth_date`, `phone`, `default_password` (stored PLAIN until UAT ends, Jo 2026-10-22). The list HTML carries NO password: only `has_default`. | FACT, `DefaultPasswordViewer.php:40-47`; `SecUser.php:93-100` |
| Show password | Eye, copy and the "Show all passwords" toggle ask the server (`POST .../reveal`, JSON id => password); each shown account is audited. Masked by default. | FACT, `DefaultPasswordViewer.php:49-60`; `routes/v2_setup.php:114-121` |
| Reset | `POST .../{id}/reset` reuses `SecurityAccountService::resetToDefault` (sets `pswd` from the default, `must_change_password=1`), then shows the login card once (`v2_login_card`). An account without a default password is refused. | FACT, `DefaultPasswordViewer.php:63-69`; `SecurityAccountService.php:72-80` |
| Audit | Log channel `password_audit` (daily file `storage/logs/password-audit-*.log`, 365 days): events `open`, `reveal`, `reset` with actor login, target login and IP. The password itself is never logged. | FACT, `config/logging.php:69-75`; `DefaultPasswordViewer.php:71-74` |
| Cache | Responses carry `Cache-Control: no-store, no-cache, must-revalidate, private`. | FACT, `routes/v2_setup.php:107` |
| Tests | Gate: `tests/Unit/DefaultPasswordViewerGateTest.php` (pure, no DB). Screen, reveal, reset, audit and 403: `tests/Feature/DefaultPasswordsScreenTest.php` (uses `tm2sys` inside a rolled-back transaction, mockup environment only). | FACT, files |

## Relations
- **source**: `tm2sys.sec_user`, `SecurityAccountService`, `SecurityRecoveryService::resetToDefaultPassword`.
- **used_by**: Jo (owner and administrator) during UAT.
- **flows**: open list -> (eye / copy / show all -> reveal, audited) -> Reset to Default -> login card once.
- **frozen_zone**: none.

## After UAT (must do)
Default passwords go back to encrypted storage (rollback migration `2026_10_22_000300_default_password_plain.php` and restore the `SecUser` accessor), empty `UAT_PASSWORD_VIEWERS`, then delete this screen: `DefaultPasswordViewer`, view `v2/default-passwords.blade.php`, routes `v2.setup.default-passwords.*`, the `PayrollAccess` line, migration `2026_10_24_000100` (rollback removes the menu row) and the `password_audit` channel after the audit files are archived.

## Findings (recorded, not fixed)
1. **Plain-text passwords in a list are a deliberate UAT compromise** (Jo 2026-10-22 / 2026-10-24); the mitigations are the single-login gate, on-demand reveal with audit, no-store and the removal plan above. FACT.
2. **The Feature test was written but not run** in this session: tests of this project use the working MySQL database (`tm2sys`), so only the pure gate test was run. FACT.

## Already exists? Searched where
Searched: `default_password`, `resetToDefault`, `reveal`, routes `v2.setup.account.save`. Result: single-account reveal and reset exist (Account form); no all-users list existed; the reset rule is reused, not copied.
