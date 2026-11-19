# PastorEyes API (v1)

A personal-token HTTP API so an AI agent (Claude Code, or an MCP server wrapping this API) can **look people up**,
**read** their records and **add** to them on the user's instruction. Every response carries a plain-English
`message` as well as structured `data`, and ambiguity ("which Al McNicoll?") is a normal outcome, never a guess.

The API describes itself: `GET /api/v1` (endpoints, scopes, enums, glossary) and `GET /api/v1/openapi.json`.
The endpoint list lives in one place, `App\Services\Api\Spec`, which drives the routes, the self-description and the
OpenAPI document (a test fails if they drift apart).

## Getting a token

Settings → **API**: name it, choose **Read** (recommended), **Write** or **Delete** access, an expiry (default 90 days),
tick the acknowledgement, and copy the token. It is shown once; only its SHA-256 hash is stored. Revoke it in the same place.

```
Authorization: Bearer pe_...
```

Scopes include the ones before them: `delete` ⊃ `write` ⊃ `read`. A token only ever sees its owner's data; another
user's ids answer `404`, never `403`. A disabled account's tokens stop working.

## The response envelope

```json
{ "status": "ok", "message": "Added a Note dated 19 Nov 2026 for Al McNicoll (13 Jul 1984).",
  "data": {}, "warnings": [], "next": [], "meta": { "request_id": "01...", "dry_run": false } }
```

Branch on `status`, not the HTTP code alone:

| `status` | HTTP | Meaning |
|---|---|---|
| `ok` | 200 / 201 | Done (or found) |
| `ambiguous` | 200 on reads, **409 on writes** | A person reference matches several people. Nothing was written. `data.candidates` says who, `distinguishing_fields` what tells them apart, `how_to_specify` how to resend |
| `not_found` | 404 (200 for a search) | No such person/entry; `data.suggestions` lists close names |
| `needs_confirmation` | 409 | A delete: repeat it with the returned `confirmation_token` |
| `possible_duplicate` | 409 | Probably already recorded; resend with `confirm_duplicate: true` |
| `invalid` | 422 | Validation failed; `data.errors[]` has `field`, `code`, `message` |
| `forbidden` | 401 / 403 | Bad, expired or revoked token / token lacks the scope |
| `error` | 400 / 405 / 429 / 500 | HTTPS required, wrong method, rate limited, server fault |

## Referring to people (PersonRef)

Wherever a person is accepted: an id (`42`), a name (`"Al McNicoll"`), or an object
`{ "name": "Al McNicoll", "born": "1984", "city": "Reading", "gender": "male", "related_to": {"person": "Caroline McNicoll", "type": "spouse"}, "id": 42 }`.

- Every word must match somewhere in **any** of the person's names (first, middle, last, preferred, maiden/married…), any order; case and accents are ignored.
- A full-name match beats partial ones ("Pat Lane" over "Patrick Lane"; the runner-up is mentioned in `warnings`). A lone *partial* match is used but flagged in `warnings`.
- Hints then narrow. `born` is `1984-07-13`, `1984` or `07-13` (unknown birth years match on day and month). `city` matches a current or past address (city, county or postcode prefix).
- **Writes never guess**: several matches → `ambiguous` (409), nothing written. With `people: [...]` every reference is resolved first and *all* the problems come back together.
- `POST /people/resolve` checks who a reference means, with no side effects.

## Endpoints

| | | Scope |
|---|---|---|
| GET | `/` , `/openapi.json` | none |
| GET | `/me`, `/briefing`, `/search`, `/people`, `/people/by`, `/people/{id}`, `/people/{id}/briefing`, `/entries`, `/entries/{entry_id}`, `/tasks`, `/relationship-types` | read |
| POST | `/people/resolve` | read |
| POST | `/people`, `/entries`, `/tasks`, `/people/{id}/names`, `/people/{id}/addresses`, `/people/{id}/relationships`, `/entries/{id}/resolve`, `/entries/{id}/reopen`, `/entries/{id}/outcomes`, `/tasks/{id}/complete`, `/tasks/{id}/reopen` | write |
| PATCH / PUT | `/people/{id}`, `/entries/{entry_id}`, `/tasks/{id}`, `PUT /people/{id}/birthday` | write |
| DELETE | `/entries/{entry_id}`, `/tasks/{id}`, `/relationships/{id}` | delete |

Entry ids are type-prefixed like the Timeline's: `note_5`, `prayer_need_12`, `goal_3`, `outcome_7`, `key_date_9`.
Significance is 1–5 (default 3). Dates are `YYYY-MM-DD`; "today" is in the user's configured time zone (the Prayer Emails one).
The API says **Prayer Need**, never "prayer request". Narratives use names, never pronouns inferred from gender.

Creating an entry:

```json
POST /api/v1/entries
{ "type": "note", "people": ["Al McNicoll", 42], "title": "Hospital visit", "body": "Visited on the ward.", "date": "2026-11-18" }
```

`type` is `note`, `prayer_need`, `goal` (needs a `title`; optional `target_date`) or `key_date` (`key_date_type`, `label`, `is_recurring`,
`recurrence_count`, `year_unknown`, `sync_to_calendar` — the API never touches Google Calendar unless asked). The first person is the primary one.
An Outcome is recorded on its Goal: `POST /entries/goal_3/outcomes {title, body, goal_complete}`.

Listing the Timeline: `GET /entries?person=...&type=note,goal&active=true&significance[]=4&from=&to=&q=&sort=oldest&page=&per_page=`.
`person` may be an id, a name, or JSON `{"name":"Al McNicoll","born":"1984"}`. `q` searches titles and bodies by decrypting them, so it
scans at most the newest 2,000 entries (`api.text_search_scan_cap`) and says so in `message` and `meta.truncated`.

## Safety for agents

- **`?dry_run=true`** (or `"dry_run": true`) on any write does all the resolving and validating and writes nothing, describing what *would* happen.
- **`Idempotency-Key: <uuid>`** on a POST: the same key and request within 24 h replays the first response and writes once; the same key with a different request is a 422. Only successful, non-dry-run writes are remembered.
- **Duplicates**: an entry with the same type, primary person and text within 7 days, or a person with the same name and compatible birth date, is `possible_duplicate` until `confirm_duplicate: true`.
- **Two-step deletes** (`delete` scope): `DELETE` returns `needs_confirmation` with the UI's warning ("Delete this Goal? Its 1 outcome is deleted too. This cannot be undone.") and a single-use `confirmation_token` valid for 5 minutes; repeat with `?confirmation_token=...`. People cannot be deleted over the API.
- **Unknown request fields are refused** (422), so a typo can't silently drop data. Writes are all-or-nothing.
- Entries and tasks the API creates are marked `source: "api"` with the token's name (shown as a small note on the Timeline).
- Rate limits per token: 120 reads and 30 writes per minute (`config/api.php`), then `429` with `Retry-After`.
- HTTPS only in production (`API_REQUIRE_HTTPS`); responses are `Cache-Control: no-store`.

## Privacy

Data is encrypted at rest but the API returns it **decrypted** over TLS; with a hosted AI model it is processed by that provider.
Token creation requires an acknowledgement, and the Privacy Policy (§2.5, §8) and Terms (§4.4) say so. The audit log
(`api_audit_log`, shown in Settings → API) records the token, method, path, status and a count-style summary only —
never bodies, query strings, names or text. Idempotency responses are stored encrypted per user.

## Using it from Claude

**The Claude desktop app, or Claude Code with tools:** use the MCP server in `mcp/` (see `mcp/README.md`). It holds the token,
offers only the tools the token's scope allows, and enforces the rules below itself.

**Calling the API directly** (for example from a script or a Claude Code session without the MCP server):
suggested instructions for a project `CLAUDE.md` or skill:

```
PastorEyes API: base https://<host>/api/v1, token in $PASTOREYES_TOKEN (read-only unless stated).
- Always read `message`; relay it to the user when it needs a decision.
- If status is "ambiguous": STOP. Ask the user which candidate (use the distinguishing fields in the message),
  then resend with the chosen PersonRef. Never pick for them. Also check `warnings` for partial matches.
- Before a multi-write job, use ?dry_run=true. Send an Idempotency-Key on every POST.
- Deletes are two-step: show the user the warning text, only then send the confirmation_token.
- Prayer reminders and Google sync are out of scope.
```

## Not in v1

A remote (hosted) MCP server with OAuth, so Claude on the web could connect without a local install (the local one is in `mcp/`); contact-sync review
actions; prayer-reminder settings; photos; bulk import; webhooks; a free-text "known as" note on a person for telling same-named people apart.
