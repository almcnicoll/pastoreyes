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
- **Livewire 4** + **Alpine.js** for interactive server-rendered UI (no SPA/API layer)
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
| `Goal` / `Outcome` | Mentoring goals and their outcomes, dated and rated for significance. |
| `KeyDate` | Birthdays (year optional), anniversaries, etc. Recurring by default or one-off; can sync to Google Calendar. |
| `PersonPhoto` | Photo attached to a person. |
| `Task` | Follow-up/to-do items. |
| `ContactSyncState` / `ContactSyncReview` | Track Google Contacts import/sync state and surface conflicts for manual review. |
| `TimelineEntry` | A DB view unifying notes/goals/outcomes/key dates into one chronological, filterable feed (see `2024_01_01_000013_create_timeline_entries_view.php`). |

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

- Google Sign-In is the auth mechanism (`GoogleAuthController`).
- Contacts can be imported/linked to `Person` records (`LinkGoogleContact`,
  `ContactSyncService`/`ContactSyncResolutionService` + the review queue for
  conflicts a human needs to resolve).
- Recurring `KeyDate`s are intended to sync with Google Contacts; one-off dates
  with Google Calendar. Sync direction is still being finalized.

## Routing / structure

- `routes/web.php` — public routes (`/`, `/login`, `/terms`, `/privacy`, Google OAuth)
  and an authenticated group (`dashboard`, `people`, `people/{person}`, `timeline`,
  `tasks`, `contact-sync`, `settings`) gated by `auth` + `active` (`EnsureUserIsActive`)
  middleware.
- Pages are full-page Livewire components (`app/Livewire/*`), not a REST API —
  the browser talks to Livewire's own endpoints under the hood.
- `app/Actions/` holds import/export/sync operations too heavy for a controller method.
