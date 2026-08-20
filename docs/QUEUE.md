# QUEUE — TMERP

> Claude Code rule: after finishing the current task, check this file.
> Take the TOP-MOST item still marked `[ ]` (FIFO), work on it, then mark it
> `[x]` when done (do not delete the line — keep it as history).
> If you find something worth noting (decision, blocker, finding) while working
> on an item, write it as a "Note:" sub-bullet under that item — do not stop to
> wait for confirmation unless it truly requires a business decision (not a
> technical one).
> Always add new items at the BOTTOM (end of queue), unless marked 🔺PRIORITY.

---

- [ ] 1. Work on all mockups that are print-out type (nota, quotation, CO, DO/DN, Master Card, etc). Check color consistency/palette against the TMERP palette (`--navy: #2c3e69`, `--accent: #2f6fed`) across all these print-out files — if any are inconsistent, align them to the same palette.
      Note (2026-08-20): Nota Inquiry ✅ and Quotation ✅ done (both use their own print palette `--accent:#0d5c46` etc., NOT `--navy`/`--accent` TMERP tokens — these are print documents meant to look like formal paper documents, not app screens, so the "align to TMERP palette" instruction doesn't cleanly apply here; flagging for user call rather than silently forcing navy/blue onto a print letterhead). Still not done: CO print, DO/DN print, Master Card print — no mockup exists yet for any of these (see checklist_mockup_erp.md).

- [x] 2. Add DB migration per `module_status_system` in GLOBAL_RULES.yaml: ALTER `tm2sys.sys_app_menu` (add `status_code`/`status_note`/`status_updated_by`/`status_updated_at`) + CREATE `tm2sys.sys_app_menu_color` (seeded with NOT_BUILT/READY_FOR_QC/LOCKED). Wire `layouts.shell` to JOIN these tables so menu color/clickability is read from DB, not hardcoded. Audit which menus have no form/grid built yet — those stay `status_code='NOT_BUILT'` (default), shown disabled/gray.
      Note (2026-08-20): done via migration `2026_08_20_000006_add_module_status_system.php`. Wired into `main-menu.blade.php` `menuTree()`/`flatItems()` (not `layouts.shell` directly — that layout is just the HTML shell; the actual menu tree/Alpine data lives in `pages::main-menu`). Module/group status is DERIVED (worst-case of children), not stored. `app-shell.js` `openTab()` now blocks non-clickable (NOT_BUILT) items with a toast instead of opening the old fake-placeholder pane. Backfilled `READY_FOR_QC` for all 18 codes with a real Laravel route today (`SysAppMenu::markReadyForQc()` helper added for future use — call it right after finishing + pushing each new item, per rule #4). Added 2 new menu rows (`SALES_IMPO`, `SALES_NOTA`) that didn't exist before Imposition/Nota were built. Tested end-to-end in browser: gray items don't open (toast fires, no tab), yellow items open normally, module-level dot correctly shows gray the moment ANY child is unbuilt.

- [ ] 3. Check that everything consistently uses Livewire + Alpine.js + Tailwind (no loose jQuery/vanilla JS or custom CSS outside Tailwind unless truly necessary). Make sure every section/code block has a clear comment explaining its purpose, for easy tracing.

- [ ] 4. Always push to GitHub `275iwan92/tmerp` every time ONE mockup/component is finished (don't batch and wait until everything is done). Commit message must be clear and specific to the mockup just completed. Also UPDATE `tm2sys.sys_app_menu.status_code = 'READY_FOR_QC'` (with `status_updated_by='claude_code'`, `status_updated_at=NOW()`) for that menu row right after the push — per `module_status_system` in GLOBAL_RULES.yaml.

- [ ] 5. Always create dummy data that is relationally linked across modules (not isolated per table) — so cross-module flows (e.g. Inquiry → Imposition → Costing → Master Card → CO) can be tested end-to-end with connected data, not standalone random data.

- [ ] 6. After pushing to GitHub, immediately continue to the next mockup without stopping to ask for confirmation. Keep working through the queue autonomously. The user will interrupt directly if the work order/sequence is wrong.

- [ ] 7. Stop asking for prompts/permission from the user — especially when it's just a site/tool permission prompt with no real ambiguity. Just proceed and get it done, always work without asking. CONSTRAINT: Claude Code is strictly forbidden from deleting/rewriting a menu/module whose `sys_app_menu.status_code = 'LOCKED'` (see `module_status_system` in GLOBAL_RULES.yaml) without asking the user first — this is the ONE exception to "don't ask." Claude Code must NEVER set `status_code` to `LOCKED` itself — only the user does that. Menus at `READY_FOR_QC` or `NOT_BUILT` are fully editable/deletable without asking — that's normal dev work (bug fixes, refactors, corrections found during testing).

- [ ] 8. Audit ALL color theming in the app to make sure it's fully DB-driven via the EXISTING `tm2sys.sys_theme` table (code, name, primary_color, sidebar_color, is_default, is_active, sort_order — already seeded with navy/emerald/indigo/plum/slate/teal). Do NOT create a new theme table — this one already exists, just wasn't fully wired in yet. Find every hardcoded color reference across mockups/components (Tailwind config, CSS custom properties like `--navy`/`--accent`, inline Blade colors) and replace with values pulled from `sys_theme` at runtime (per active user's `sec_user.theme_code`, falling back to `sys_setting.active_theme`). Goal: switching the whole app's color scheme (e.g. Navy → Dark Green) becomes a data change in `sys_theme`, not a code change. Note: this is separate from `sys_app_menu_color` (job #2) — that one is for menu-readiness status colors (gray/yellow/green), not the app's overall color theme.

- [ ] 9. Let users pick their theme from the profile dropdown menu (`tm2sys.sys_user_menu`, section 'Akun'). Both `sec_user.theme_code` (default `'navy'`) and `tm2sys.sys_theme` (6 presets) ALREADY EXIST — this job is just the missing UI + wiring:
      a. INSERT a new row into `sys_user_menu`: section='Akun', code='UM_THEME', label='Tema tampilan', icon='fa-solid fa-palette', action_type='modal', action_target='mTheme', sort_order between UM_PASS(50) and UM_SETUP(60) e.g. 55, is_active=1.
      b. Build the `mTheme` modal (Livewire): grid/list of active themes from `sys_theme` (WHERE is_active=1 ORDER BY sort_order), each showing name + a small swatch of primary_color/sidebar_color. Clicking a theme UPDATEs `sec_user.theme_code` for the logged-in user and applies it immediately (no page reload) — same runtime mechanism built in job #8.
      c. Selected theme must persist across sessions (read from `sec_user.theme_code` on every page load, not just client-side state).
      d. If `sec_user.theme_code` is NULL or doesn't match any active `sys_theme.code`, fall back to `sys_setting.active_theme`, then to the theme with `is_default=1`.

---

## Completed history

- 2026-08-20 — Item #2 (module_status_system): migration + menu wiring + click-gating, tested in browser. Commit: "Add module_status_system: menu status colors + gating (QUEUE #2)".
- 2026-08-20 — Catch-up commit `80a6f9f` for all unpushed work from 2026-08-18 through 2026-08-20 (Master Data v2, Finance, HRM, Sales printouts incl. Imposition/Nota/Quotation) — user chose one combined commit over splitting by feature, since prior sessions jumped between areas non-sequentially.
