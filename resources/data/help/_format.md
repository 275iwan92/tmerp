# Help content files (`resources/data/help/*.json`)

Source of the Help Center and of the local AI assistant. One file per module, loaded into
`tm2sys.sys_help_topic` + `sys_help_line` by `php artisan help:import` (Indonesian text goes to the
translation dictionary, context `help`). After the first import the text is maintained on screen
(Setup > Help Topics / Help Lines); these files are only the starting content.

Check a file before handing it in: `php resources/data/help/_check.php resources/data/help/<file>.json`

## Shape

```json
[
  {
    "key": "customer-order",
    "type": "INPUT",
    "module": "SALES",
    "title": "Customer Order",
    "purpose": {"en": "...", "id": "..."},
    "keywords": "co, customer order, sales order, pesanan, order pelanggan",
    "steps": [
      {"en": "...", "id": "..."}
    ],
    "fields": [
      {"label": "Customer", "en": "...", "id": "..."}
    ]
  }
]
```

| Key | Meaning |
|---|---|
| `key` | v2 screen slug (`sys_app_menu.target` without `v2:`), or `guide-...` for a general guide. Unique. |
| `type` | `INPUT` = screen where data is entered (grid + form, master, lookup, approval), `REPORT` = read-only report, `GUIDE` = general how-to that is not one screen. |
| `module` | `sec_module` code that owns the screen (`SALES`, `COSTING`, `PROC`, `INV`, `PROD`, `MAINT`, `HRM`, `FINACC`, `MDATA`, `SETUP`, `GENERAL`). |
| `title` | Screen name in English, as the user sees it in the menu. One English name only (no "Indonesian / English" pairs). |
| `title_id` | Optional. The title in Indonesian, shown in the User's Guide and Help Topics when the language is Indonesian (Jo 2026-10-18). Leave it out when the English title is also what Indonesian users read in the menu. The Technical Guide stays English. |
| `purpose` | What the screen or report is FOR and when someone opens it. One or two sentences. |
| `keywords` | Words a user may type when looking for this screen: English and Indonesian, abbreviations, old paper-form names. Lowercase, comma separated, at most 255 characters. |
| `steps` | How to USE the screen, in order, 3 to 8 steps. One action per step. |
| `fields` | INPUT: every field the user fills in (header and line items), in screen order. REPORT: every column, filter and grouping choice. `label` = the label exactly as written on the screen. |

## Rules

1. Every `en` and every `id` text is **at most 255 characters** (the dictionary column is 255). Split a long thought into two steps.
2. `en` is plain English. `id` is everyday Indonesian a factory staff member understands; it says the same thing as `en`.
3. Button names, status names and field labels stay in English in both languages (Jo 2026-10-18; the Help screen shows field labels
   untranslated): New, Save, Undo, Delete, Confirm, Cancel, Duplicate, Back, Print, Email; Draft, Confirmed, Cancelled, Closed; `+ New Record`.
   Never write an Indonesian button name (Simpan, Batal, Hapus ...) in a step.
4. Describe only what the code really does. Read the screen first. When something is not in the code, leave it out; never guess.
5. A field explanation says what to enter, where the value comes from (typed, picked from a master, filled by the system),
   and what it is used for later, when the code shows it. Skip audit fields (created by / at, updated by / at).
6. Do not write costing formulas, margin values, salary figures or passwords. Say that the value exists and who may see it.
7. No screen-layout descriptions ("on the left", "blue button"); say what to click and what happens.
8. No emoji, no markdown, no line breaks inside a text.
