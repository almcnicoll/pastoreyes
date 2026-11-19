# PastorEyes MCP server

Lets Claude (the Claude desktop app, or Claude Code) work with PastorEyes through its [API](../doc/api.md): look people up,
read their records, and add Notes, Prayer Needs, Goals, Key Dates and Tasks when you ask. It runs on your computer, holds your
token itself (so the token never appears in the conversation), and makes the API's safety rules the only way to use it.

It is a thin wrapper: no data of its own, no database, no network access except your PastorEyes address.

## Set up

You need Node 22.18 or newer.

1. In PastorEyes, **Settings → API**, create a token. Choose **Read** unless you want Claude to add things (see Privacy).
2. Build the server once:

   ```powershell
   cd mcp
   npm install
   npm run build
   ```

3. Tell Claude about it.

   **Claude desktop app.** Settings → Developer → Edit Config (`%APPDATA%\Claude\claude_desktop_config.json`), then restart the app:

   ```json
   {
     "mcpServers": {
       "pastoreyes": {
         "command": "node",
         "args": ["C:\\laragon\\www\\pastoreyes\\mcp\\dist\\index.js"],
         "env": {
           "PASTOREYES_URL": "https://your-pastoreyes-address",
           "PASTOREYES_TOKEN": "pe_..."
         }
       }
     }
   }
   ```

   If the app cannot find `node`, put the full path to `node.exe` in `command`.

   **Claude Code:**

   ```powershell
   claude mcp add pastoreyes --env PASTOREYES_URL=https://your-pastoreyes-address --env PASTOREYES_TOKEN=pe_... -- node C:\laragon\www\pastoreyes\mcp\dist\index.js
   ```

`PASTOREYES_URL` is the site address (the `/api/v1` is added for you). Plain `http://` is accepted only for `localhost`, for local development.
Optional: `PASTOREYES_TIMEOUT_MS` (default 15000) and `PASTOREYES_MAX_CHARS` (default 50000, the most data one tool result may carry).

The local server only works in the **desktop app and Claude Code**, not in claude.ai in a browser.

Try it with: *"Who has active prayer needs?"* or *"Add a note for Al McNicoll: visited at home today, in good spirits."*

## What Claude can do

Claude sees only the tools your token allows (a Read token never even sees the write tools).

| Access | Tools |
|---|---|
| Read | `whoami`, `find_person`, `search`, `list_people`, `get_person`, `get_person_briefing`, `get_briefing`, `list_entries`, `get_entry`, `list_tasks`, `list_relationship_types` |
| Write | `add_entry` (note, prayer_need, goal, key_date), `update_entry`, `set_prayer_need_status`, `record_outcome`, `add_task`, `update_task`, `set_task_status`, `add_person`, `update_person`, `add_person_name`, `add_person_address`, `set_birthday`, `add_relationship` |
| Delete | `delete`, `confirm_delete` |

What it enforces, so Claude cannot get it wrong:

- **Never guesses between people.** If a name matches several people, nothing is written and Claude is told to ask you which one.
- **Every write can be a dry run** (`dry_run`), and every POST carries an idempotency key, so a retry never saves twice.
- **Deletes are two steps.** `delete` only describes what would go; `confirm_delete` runs after you have said yes. People cannot be deleted at all.
- Probable duplicates are refused until you confirm them.
- Claude is told that text inside your records is data, never instructions.

## Privacy

Your records are encrypted in the database, but the API hands them to this server **decrypted**, and Claude (and the company behind it)
processes whatever it reads. Prefer a **Read-only token**, and revoke tokens you no longer use (Settings → API shows what each did).
The server writes nothing to disk, and logs only to stderr, and never the token.

## Development

```powershell
npm run check   # types
npm test        # unit tests against a stub API, including a check that every API endpoint has a tool
```

`test/mcp.test.ts` reads `app/Services/Api/Spec.php`, so adding an API endpoint without a tool fails the tests.
