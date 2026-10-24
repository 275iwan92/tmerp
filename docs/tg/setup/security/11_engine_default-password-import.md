# Engine card: Import Old Passwords (TEMPORARY, UAT only)

> Written 2026-10-24. FACT = code seen this session; DB facts = column and aggregate queries on the mockup DB (`ddt*`), no password value was printed.

- **Document type**: engine card (artisan command). **Module/sub-module**: Setup > Security.
- **Canonical name**: Import Old Passwords: copy each employee's old-system password into the default password of the mockup account, matched by NAME.
- **Synonyms**: salin password lama, password lama jadi bawaan, security:import-old-passwords.
- **NOT the same as**: the Default Passwords screen (`10_card_default-passwords.md`) which only shows and resets; this command writes the values it shows.

## Facts
| Item | Content | Evidence |
|---|---|---|
| Command | `php artisan security:import-old-passwords [--apply] --env=mockup`; no `--apply` = dry-run (counts and name lists only). | FACT, `app/Console/Commands/ImportOldPasswords.php` |
| Source | `tm2old.tm3sec_users` (columns `login`, `pswd`, `name`, ...), SELECT only. `pswd` is PLAIN text (Jo): 108 rows, lengths 6-15, no NULL/empty, none looks like a hash. | FACT, command; aggregate query 2026-10-24 |
| Target | `tm2sys.sec_user` which resolves to `ddtsys` with `--env=mockup`. The command REFUSES to run unless the database name starts with `ddt`; it never writes to any `tm2*` database. | FACT, `ImportOldPasswords.php` guard at top of `handle` |
| Matching | By NAME only: `LegacyNameMatcher::normalize` = lowercase + collapse spaces + trim (punctuation and titles stay). Applied only when exactly one source and one target share the normalized name. Similar but not equal names (spelling, extra word, punctuation, order), duplicate names: listed as DOUBTFUL, never applied. | FACT, `app/Services/Security/LegacyNameMatcher.php` |
| Write (per matched row, one transaction) | `default_password` := old password (plain), `pswd` := `Hash::make(old)`, `must_change_password` := 0 (Jo, "for now"), `pswd_last_updated` and `updated_at` := now. Rows already equal are skipped, so a re-run changes nothing. A matched row whose source is NULL/empty is left unchanged and listed. | FACT, command |
| Verify | After writing it re-reads the changed rows and checks plain value equal, `Hash::check` true, `must_change_password` = 0; exit code fails otherwise. | FACT, command |
| Live target | `--target=tm2sys` (default is `ddt`): allowed only when the connection database is exactly `tm2sys` (run WITHOUT `--env=mockup`); dry-run needs nothing more, writing needs `--apply --confirm-live`. Jo allowed this exception for this job on 2026-10-24. | FACT, `ImportOldPasswords.php` guards at top of `handle` |
| Audit | One line to log channel `password_audit` (counts only, actor `artisan`, never a password). | FACT, command; `config/logging.php:69` |
| Approval | `--approve=file`, one line per pair `target name|source name`, accepted only for pairs listed DOUBTFUL; twin names (two targets or two sources with the same normalized name) are always rejected (`LegacyNameMatcher::approve`). The automatic rule is unchanged. | FACT, `LegacyNameMatcher.php`, `ImportOldPasswords.php` |
| Result 2026-10-24 | stage 1: targets 159, sources 108, exact 35 (34 changed + 1 already equal), doubtful 22, no partner 102, source NULL 0. Stage 2 (Jo approved 18 non-twin pairs): 18 changed and verified; now exact 53, doubtful 4 (the twins Annisa Kusumaningtyas and Wiwik Ardiatno, undecided). Backups before each stage: `C:SERSIWANPBACKUPSDDTSYS_SEBELUM-IMPORT-PASSWORD-LAMA_2026-10-24.SQL` AND `..._TAHAP2_2026-10-24.SQL`. | FACT, RUNS |
| Tests | `tests/Unit/LegacyNameMatcherTest.php` (pure, no DB, fictional names). | FACT, file |

## Relations
- **source**: `tm2old.tm3sec_users`, `tm2sys.sec_user` (`ddtsys`).
- **used_by**: Jo (owner) during UAT, run by hand.
- **frozen_zone**: none.

## After UAT (must do)
The old passwords of employees are now stored PLAIN as defaults. After UAT: re-encrypt `default_password`, remove the Default Passwords screen, make every employee set a new password (see `docs/backlog09_platform_facility.md`, PASCA-UAT), then delete this command, `LegacyNameMatcher` and its test.

## Already exists? Searched where
Searched: `default_password`, `tm3sec_users`, `resetToDefault`. Result: no earlier import; the hash rule is reused from `PasswordResetService` (`Hash::make`).
