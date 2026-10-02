# PastorEyes — instructions for Claude

See `doc/architecture.md` for what the app is and how it is structured.

## Workflow

- Day-to-day development happens on the `working` branch. The repo's main branch is named `master` (there is no `main`).
- Commit in small, meaningful steps with descriptive messages. Only commit or push when asked.
- Local development: `/dev-login` (local only, see `doc/architecture.md`) signs in without Google. Run `npm run build` after adding new Tailwind classes — `public/build` is compiled output (committed, see Deploying), so new utility classes won't appear until it's rebuilt.

## Deploying

The live host (same Plesk hosting as the foxhole and opus projects) pulls from git via a Plesk "Git" webhook — hitting the webhook URL tells Plesk to pull and deploy the latest commit on the tracked branch. **Only deploy when the user asks.** This repo is not auto-deployed on push, and a push to `working` never deploys anything.

When asked to deploy, in order:

1. Run `npm run build`. Make sure the working tree is clean afterwards (any change to `public/build` needs committing first) and `php artisan test` passes. If either isn't true, stop and tell the user.
2. Check out `master`, merge `working` into it, and resolve any conflicts (ask if anything is non-trivial).
3. `git push origin master`.
4. Check for a `.deploy-webhook-url` file at the repo root and POST to it — no separate confirmation needed for that POST itself, the user has already authorised the mechanism:

   ```bash
   curl -sk -X POST "$(cat .deploy-webhook-url)"
   ```

   A successful trigger returns **HTTP 204** with no body — that's success, not an error. (Add `-w "%{http_code}"` to see it.)
5. Switch back to the `working` branch.

If `.deploy-webhook-url` is missing **or empty**, don't guess at a URL or skip silently — tell the user the push happened but no deploy was triggered, and ask them to put the webhook URL in it.

The file holds a bare URL, nothing else:

```
https://shared-uk.man-1.vm.plesk-server.com:8443/modules/git/public/web-hook.php?uuid=...
```

Two details that matter, confirmed live against this same setup on the other Plesk-hosted projects in this workspace — don't drop either if you're reimplementing this call:

- **POST, not GET** — a GET returns without triggering a pull.
- **TLS verification must be disabled** (`curl -k`) — the deploy host's certificate doesn't validate against the calling environment's CA bundle. This tradeoff is specific to this one webhook call; don't disable TLS verification anywhere else in this codebase without the same explicit reasoning.

The webhook URL contains a bearer-style secret (anyone with it can trigger a deploy), so treat `.deploy-webhook-url` like `.env` — it is gitignored; never commit it, never paste its contents into a chat, PR, issue, or log.

### What the webhook does and doesn't do

On each trigger the Plesk webhook pulls the latest commit, then runs `npm install` and `composer install`. It does **not** run `npm run build` or `php artisan migrate`. So after a deploy:

- **Migrations:** if the deployed commits include new files in `database/migrations/`, tell the user `php artisan migrate` still has to be run on the server (it won't have happened).
- **Compiled assets:** the webhook doesn't build them, so `public/build` (compiled CSS/JS) is **committed to git** and arrives via the pull. Always run `npm run build` and commit the result before deploying if anything under `resources/` changed — a stale build means new Tailwind classes and JS silently don't exist on the live site. Vite replaces the whole directory on each build (hashed filenames, so old files disappear from git too); commit it all together.
- **First deploy that includes `public/build`:** the server already has an untracked `public/build` (the site needs it to render), and git refuses to pull when untracked files would be overwritten (`manifest.json` at least). The user must delete the server's `public/build` folder once, before that first deploy. After that it's tracked and needs no manual handling.

### Not yet established (ask the user rather than assuming)

- The server cron entry that runs `php artisan schedule:run` every minute (needed for the contact sync and the prayer reminder emails), and a production `APP_URL` that is correct (it is used for the links in emails).
