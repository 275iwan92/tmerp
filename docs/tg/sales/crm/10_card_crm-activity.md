# Concept card: CRM Activity (Sales activities and follow-ups)

> Written 2026-10-16 (group A). FACT = mockup DB (`ddt*` through `tm2*`, `--env=mockup`) and code with line numbers, seen this session. Test `CrmTest` run with `--env=mockup`: 5 passed, 52 assertions; the number of `crm_*` rows is the same before and after.

- **Document type**: concept card. **Module/sub-module**: Sales > CRM > Activities & Follow-up (`CRM_ACTIVITY`, `v2:crm-activity`, FACT `sys_app_menu`).
- **Canonical name**: CRM Activity = `tm2sdi.crm_activity`: one record of a Sales contact with a customer (phone call, visit, WhatsApp, e-mail, meeting), with its follow-up.
- **Synonyms**: Sales activity (aktivitas Sales), customer contact (kontak customer), follow-up (tindak lanjut), call log, `QuoActivity` (old class name for quotation follow-up).
- **NOT the same as**: Complaint (card `11_card_crm-complaint.md`), document status log, `doc_state_log`.

## Facts
| Item | Content | Evidence |
|---|---|---|
| Table | `crm_activity`: 15 rows, 8 customers. Columns: `customer_id` (required), `contact_id`, `quotation_id` (FK to `quo_quotation`, may be empty), `activity_type`, `topic`, `activity_date`, `contact_person`, `result`, `notes`, `next_action_date`, `next_action`, `next_done`, `next_done_at`, `next_done_by`. PK `id_activity`. | FACT, `show columns`; `key_column_usage` |
| Values | `activity_type`: CALL, VISIT, WHATSAPP, EMAIL, MEETING, OTHER. `topic`: QUOTATION, ORDER, PROSPECT, COMPLAINT, PAYMENT, RELATION. `result`: POSITIVE, NEUTRAL, NEGATIVE, NO_ANSWER. | FACT, `CrmActivity.php:14-18` |
| Open follow-up | `next_action_date` is filled and `next_done = 0`. Data: 12 rows have a follow-up date. Finished with the Follow-up Done button, or by recording a new activity from that follow-up (Log Result). | FACT, `CrmActivity.php:59-62`; `CrmActivities.php:11-13,114-155`; query |
| Due-date color | Working days: overdue = red ("Late n days"), today = orange, D-1..D-n = yellow (n = constant `CRM_FOLLOWUP_SOON_DAYS`, value 2), further = plain; finished = "Done". Same pattern as CPDEL. | FACT, `CrmActivities.php:46-74`; `sys_constant` |
| Edit right | Only the creator of the record or an admin may change/delete; otherwise 403. | FACT, `CrmActivities.php:106-111,156-160` |
| Input filtering | Type, topic, result outside the list are replaced by OTHER / RELATION / NULL; contact and quotation only from the choices belonging to that customer. | FACT, `CrmActivities.php:114-135` |
| Quotation origin | The Quotation screen uses the subclass `QuoActivity` on the SAME table; `customer_id` is taken automatically from the quotation if not sent. | FACT, `QuoActivity.php:5-7`; `CrmActivity.php:33-41` |
| Price | Holds no price. | FACT, `show columns` |

## Relations
- **source**: Sales input; customer master and contacts (`cust_customer`, `cust_customer_contact`, cross-DB separate query); `quo_quotation` (FK inside `tm2sdi`).
- **used_by**: Customer 360 (card `12_card_customer-360.md`), Quotation form (follow-up), Win-Rate Sales (NOT checked whether it uses activities).
- **flows**: record activity -> if there is a follow-up it becomes an open follow-up -> finished (Done or Log Result) -> Customer 360 shows the last contact and the next follow-up.
- **frozen_zone**: document flow (the rule freezes all flows). An activity is not a document with a status; there is no `stat_confirm`.

## Findings (recorded, not fixed)
1. **Table `quo_activity` no longer exists** (error `Base table or view not found` from `ddtsdi`), but class `QuoActivity` and the code document ("Perluasan quo_activity") still mention it. The subclass has its own icon-label list that differs from `CrmActivity::TYPES` (two lists of activity types). FACT.
2. **Two lists of activity types**: `QuoActivity::TYPES` (icon and label pairs) and `CrmActivity::TYPES` (labels only) hold the same types (CALL, VISIT, WHATSAPP, EMAIL, MEETING, OTHER); duplication. FACT (code).
3. **No change trail**: an activity is changed directly (no edit history); only `updated_by/at`. FACT (no log table).
4. **Dummy data**: 6 of 15 activities have no contact, 9 have no quotation (valid, optional columns). No other integrity finding. FACT.
5. **Rule A.21b** ("`crm_activity` per customer") is met: `customer_id` is NOT NULL. FACT.

## Already exists? Searched where
Searched: `crm_*`, `quo_activity`, `QuoActivity`, `CrmActivit*`, menu Sales > CRM, routes `v2.crm.*`. Result: ALREADY EXISTS; no new table needed. Related tests: `CrmTest::test_follow_up_is_coloured_by_due_date_and_closed_by_logging_its_result`, `test_quotation_follow_up_takes_the_customer_of_its_quotation` (passed).
