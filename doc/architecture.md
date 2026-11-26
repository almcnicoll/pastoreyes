# PastorEyes — Purpose & Architecture

## Purpose

PastorEyes helps a pastor (or anyone doing relational/pastoral care at scale) remember
the people they look after: who they are, how they're connected to each other, what's
been going on for them, and when significant dates are coming up. Phone contact notes
don't scale to hundreds of relationships — this app adds structured, searchable,
visual memory on top of that.

Multi-user: each user has a fully separate dataset (no shared data between users).
Given the sensitivity of pastoral notes, the app is built security-first — all
free-text and personal fields are encrypted at rest, decrypted transparently via
Eloquent casts rather than in controller/view code.

## Tech stack

- **Laravel 12** (PHP 8.2+), SQLite by default (`database/database.sqlite`)
- **Livewire 4** + **Alpine.js** for interactive server-rendered UI (no SPA). A separate personal-token JSON API for AI agents lives under `/api/v1` (see below)
- **Tailwind CSS** for styling
- **Cytoscape.js** for the relationship graph/genogram view
- **Laravel Socialite** for Google OAuth (login + Contacts/Calendar API access)

## Core domain model

| Model | Purpose |
|---|---|
| `User` | One per person using the app. Holds `encryption_salt` used to derive their personal encryption key, plus app settings. |
| `Person` | A pastoral contact. Root entity that everything else hangs off. |
| `PersonName` | Supports multiple names per person (e.g. maiden name), unknown surnames, and an "unknown spelling" flag. |
| `Address` | Tracked with a date-added field so staleness is visible. |
| `RelationshipType` | Preset or user-defined relationship labels (e.g. "father of" / inverse "son of"), flagged directional or not. |
| `Relationship` | Links two `Person` records via a `RelationshipType`; directional types resolve to the correct label depending on which end you view from (`Relationship::labelForPerson`). |
| `Note` | Dated, free-text record of a meeting/event, with a significance rating. |
| `PrayerNeed` | A prayer need for one or more people; open until resolved (`resolved_at`), with optional resolution details. |
| `Goal` / `Outcome` | Mentoring goals and their outcomes, dated and rated for significance. |
| `KeyDate` | Birthdays (year optional), deaths, anniversaries, etc. (`KeyDate::TYPES` is the single list of types). Recurring by default or one-off; `recurrence_count` limits a recurring date to N anniversaries (a **Death** is a memorial for its first 3). Can sync to Google Calendar. A person's birthday and date of death are key dates, not person fields. |
| `PersonPhoto` | Photo attached to a person. |
| `Task` | Follow-up/to-do items. |
| `ContactSyncState` / `ContactSyncReview` | Track Google Contacts import/sync state and surface conflicts for manual review. |
| `ApiToken` | A personal API token (hashed, scoped read/write/delete, optional expiry, revocable). `api_audit_log` records what each did; `api_idempotency_keys` makes retried POSTs safe. |
| `TimelineEntry` | A DB view unifying notes/goals/outcomes/key dates into one chronological, filterable feed (see `2024_01_01_000013_create_timeline_entries_view.php`). |

## Terminology

Use these terms consistently in UI text, messages and docs.

- **Entry** — the general term for anything on the Timeline: a **Note**, **Prayer Need**, **Goal**, **Outcome** or **Key Date**
  (`config/entry_types.php`). An entry can be linked to several people. The Dashboard lists *Recent Entries*.
- **Timeline** — the chronological list of entries (the page, and a tab on each person).
- **Note** — only the single entry type. A person's free-text field is **About**; names and relationships have their
  own scoped **Notes** field.
- **Prayer Need** — never "prayer request". The database and code keep `prayer_need` / `PrayerNeed`.
- **Contact Sync** — the feature that compares linked Google contacts with PastorEyes; its review queue is headed *Pending reviews*.
  **Add from Google Contacts** creates a new person; **Link to Google Contact** attaches an existing person.
- **Administrator** — the admin role label (never "Admin"). Dates are shown as `14 Mar 2026`.
- **Open / closed words** — Prayer Needs and Goals: *Active* / *Resolved*; Tasks: *Active* / *Completed*;
  contact sync differences: *Pending* / *Resolved*.

## Encryption at rest

`App\Casts\EncryptedCast` (used on `notes`, `label`, dates, etc.) transparently
encrypts/decrypts field values as an Eloquent attribute cast:

- Each user has a unique `encryption_salt`.
- The actual encryption key is derived per-request as
  `HMAC-SHA256(app.key, user.encryption_salt)` — so neither `APP_KEY` alone nor
  a stolen database dump is sufficient to decrypt data; you need both.
- For models without a direct `user_id` (e.g. `PersonName`, `Address`), the cast
  walks `person_id → Person → user_id` to find the right user.
- Because this lives in the cast layer, controllers/Livewire components just read
  and write plain PHP values — encryption is invisible to application code.

## Relationship graph (genogram)

`app/Livewire/People/PersonShow/RelationshipsTab.php` builds a Cytoscape.js graph
(`resources/js/relationshipGraph.js`) centered on the current person, with each
node clickable to re-center or navigate to that person's own page. Directional
relationships render with the correct label depending on viewing direction.

## Google integration

`app/Services/Google/` (`GoogleClient`, `GoogleContactsService`, `GoogleCalendarService`)
plus `app/Actions/ImportPersonFromGoogle.php` and `SyncKeyDateToCalendar.php`:

- Google Sign-In is the auth mechanism (`GoogleAuthController`). Scopes requested there: `openid profile email`,
  `contacts` (read + push name/birthday/address back), `calendar.calendarlist.readonly` + `calendar.events.owned` (so only calendars the user owns are offered).
  Keep them to what the code actually calls — they must match the OAuth consent screen, the privacy policy
  (`resources/views/privacy.blade.php` §2.3) and the verification demo video.
- Disconnecting Google, or deleting an account, revokes the grant at Google (`GoogleClient::revokeToken`).
- Contacts can be imported/linked to `Person` records (`LinkGoogleContact`,
  `ContactSyncService`/`ContactSyncResolutionService` + the review queue for
  conflicts a human needs to resolve).
- Recurring `KeyDate`s are intended to sync with Google Contacts; one-off dates
  with Google Calendar. Sync direction is still being finalized.

## Scheduled jobs and email

Both scheduled commands (`routes/console.php`) depend on one server cron entry running
every five minutes: `*/5 * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1`.

- `pastoreyes:sync-contacts` — hourly Google contact sync.
- `pastoreyes:send-prayer-emails` — every 5 minutes. Each user opts in under Settings →
  Prayer Emails (days, time of day, timezone, stored in `users.settings['prayer_email']`).
  People with active prayer needs are spread evenly across the user's chosen days
  (`prayer_email_assignments`, balanced by `PrayerEmailScheduler::balance`, moving as few
  people as possible). The email lists names and counts only, linking to
  `/people/{id}?tab=goals_prayer` — so `APP_URL` must be correct in production.
- SMTP is configured by an admin under Settings → Mail (`app_settings`, password encrypted
  with `APP_KEY`) and overrides the `.env` mail config at boot (`AppServiceProvider`).
  Mail is sent synchronously.

## Routing / structure

- `/dev-login` (local only) — skips Google sign-in for development. Registered only when
  `APP_ENV=local` and refuses non-localhost hosts; logs in as the first admin or `?user=<id>`.

- `routes/web.php` — public routes (`/`, `/login`, `/terms`, `/privacy`, Google OAuth)
  and an authenticated group (`dashboard`, `people`, `people/{person}`, `timeline`,
  `tasks`, `contact-sync`, `settings`) gated by `auth` + `active` (`EnsureUserIsActive`)
  middleware.
- Pages are full-page Livewire components (`app/Livewire/*`); the browser talks to Livewire's own endpoints under the hood. The only REST surface is the agent API below.
- Person pickers: one `person-picker` Livewire component (single/multi, bind with `wire:model`) over `App\Services\PersonSearch`, which matches every name a person has. The nav search uses the same helper.
- `app/Actions/` holds import/export/sync operations too heavy for a controller method, and the write logic the web UI and the API share
  (`CreateEntry`, `RecordOutcome`, `ResolvePrayerNeed`, `SaveTask`, `SavePersonKeyDate`, plus `EntryRules`, the validation rules for entries).
  Change entry behaviour there, not in a Livewire component or an API controller.

## API for agents (`/api/v1`)

Full reference: `doc/api.md`. In short:

- `routes/api.php` registers routes from `App\Services\Api\Spec`, the single endpoint list that also produces `GET /api/v1` and `openapi.json`.
- `ApiGate` middleware: HTTPS, bearer token (`ApiToken`, SHA-256 hashed), scope, per-token rate limit, `Cache-Control: no-store`, then an audit row
  (method/path/status/summary only). It signs the token's owner in for the request because `EncryptedCast` needs the user. `ApiIdempotency` handles `Idempotency-Key`.
- Every response is the envelope `{status, message, data, warnings, next, meta}` (`ApiResponse`); exceptions under `/api/*` are mapped into it (`ApiExceptions`).
  `message` comes from deterministic templates in `Narrator` (no LLM, no pronouns inferred from gender).
- People are referred to by a *PersonRef* resolved by `PersonResolver` (all names, hints, exact-beats-partial, closest-name suggestions) over `PersonContext`,
  which batch-loads birthdays, counts, cities and relationships for one request. Names are encrypted, so matching happens in PHP (nothing is indexed or cached across requests).
  `PersonSearch` holds the shared word-matching rule that the nav search and person pickers also use. **Writes never choose between ambiguous people.**
- Controllers (`app/Http/Controllers/Api`) keep per-request state on the request, not on themselves. Entries and tasks created through the API carry `source = 'api'` and the token name.
- Tests: `tests/Feature/Api` (needs the SQLite test DB, so the MySQL-only `timeline_entries` view migrations are skipped there; the API itself reads the five entry tables directly)
  and `tests/Unit/Api/NarratorTest.php`. `tests/Feature/UiSharedActionsTest.php` checks the web UI still goes through the shared actions.
- `mcp/` is a separate TypeScript package (not part of the Laravel app or the Vite build): a local stdio MCP server that wraps this API as tools for Claude
  (see `mcp/README.md`). It keeps the token out of the conversation and enforces dry-run, idempotency, two-step deletes and "stop on ambiguity".
  Adding an endpoint to `Spec` without a matching tool in `mcp/src/tools.ts` fails `mcp/test/mcp.test.ts`, which parses `Spec.php`.
