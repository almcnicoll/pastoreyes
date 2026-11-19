import test from 'node:test';
import assert from 'node:assert/strict';
import { createServer, type IncomingMessage, type ServerResponse } from 'node:http';
import { readFileSync } from 'node:fs';
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { InMemoryTransport } from '@modelcontextprotocol/sdk/inMemory.js';
import { Api, apiBase } from '../src/api.ts';
import { buildServer } from '../src/server.ts';
import { SCOPE_RANK, tools, type Scope } from '../src/tools.ts';

const TOKEN = 'pe_secrettoken123';

type Call = { method: string; path: string; query: URLSearchParams; body: any; headers: IncomingMessage['headers'] };
type Reply = { http?: number; status?: string; message: string; data?: any; warnings?: string[]; next?: any[]; meta?: any };

/** A stand-in for the PastorEyes API: records every request and answers from `reply`. */
async function stub(reply: (c: Call, n: number) => Reply) {
  const calls: Call[] = [];
  const server = createServer(async (req: IncomingMessage, res: ServerResponse) => {
    const chunks: Buffer[] = [];
    for await (const c of req) chunks.push(c as Buffer);
    const u = new URL(req.url!, 'http://x');
    const call: Call = { method: req.method!, path: u.pathname.replace('/api/v1', ''), query: u.searchParams, body: chunks.length ? JSON.parse(Buffer.concat(chunks).toString()) : undefined, headers: req.headers };
    calls.push(call);
    const r = reply(call, calls.length);
    res.writeHead(r.http ?? 200, { 'content-type': 'application/json' });
    res.end(JSON.stringify({ status: r.status ?? 'ok', message: r.message, data: r.data ?? null, warnings: r.warnings ?? [], next: r.next ?? [], meta: r.meta ?? {} }));
  });
  await new Promise<void>(r => server.listen(0, '127.0.0.1', r));
  const url = `http://127.0.0.1:${(server.address() as any).port}`;
  return { calls, url, close: () => { server.closeAllConnections(); server.close(); } };
}

async function connect(url: string, scope: Scope | null) {
  const api = new Api({ url, token: TOKEN, timeoutMs: 2000 });
  const [a, b] = InMemoryTransport.createLinkedPair();
  await buildServer(api, scope).connect(a);
  const client = new Client({ name: 'test', version: '1' });
  await client.connect(b);
  return client;
}

const text = (r: any) => r.content.map((c: any) => c.text).join('\n');

const ALBERT = { id: 1, display_name: 'Al McNicoll' };
const resolveOk = (c: Call) => c.path === '/people/resolve' ? { message: 'Found Al McNicoll.', data: { person: ALBERT } } : undefined;

test('the tool list follows the token scope', async () => {
  const s = await stub(() => ({ message: 'ok' }));
  const names = async (scope: Scope | null) => (await (await connect(s.url, scope)).listTools()).tools.map(t => t.name);

  const read = await names('read');
  assert.ok(read.includes('find_person') && !read.includes('add_entry') && !read.includes('delete'));
  const write = await names('write');
  assert.ok(write.includes('add_entry') && !write.includes('delete'));
  const del = await names('delete');
  assert.ok(del.includes('delete') && del.includes('confirm_delete'));
  assert.equal((await names(null)).length, tools.length);
  s.close();
});

test('every API endpoint has a tool, at the right scope, and every tool endpoint exists', () => {
  const spec = readFileSync(new URL('../../app/Services/Api/Spec.php', import.meta.url), 'utf8');
  const api = [...spec.matchAll(/^\s*\['(GET|POST|PATCH|PUT|DELETE)',\s*'([^']+)',\s*'(\w+)'/gm)]
    .map(m => ({ key: `${m[1]} ${m[2]}`, scope: m[3] }))
    .filter(e => e.scope !== 'public');
  assert.ok(api.length > 25, 'parsed the endpoint list');

  const toolFor = new Map(tools.flatMap(t => t.endpoints.map(e => [e, t] as const)));
  for (const e of api) {
    const t = toolFor.get(e.key);
    assert.ok(t, `no tool covers ${e.key}`);
    assert.ok(SCOPE_RANK[t.scope] >= SCOPE_RANK[e.scope as Scope], `${t.name} is scoped below ${e.key}`);
  }
  const known = new Set(api.map(e => e.key));
  for (const t of tools) for (const e of t.endpoints) assert.ok(known.has(e), `${t.name} points at missing ${e}`);
});

test('an ambiguous person is a normal result that tells Claude to stop, and a write sends nothing', async () => {
  const s = await stub(c => c.path === '/people/resolve'
    ? { status: 'ambiguous', http: 200, message: 'Two people match "Al".', data: { candidates: [{ id: 1 }, { id: 2 }] } }
    : { message: 'should not happen' });
  const client = await connect(s.url, 'write');

  const found: any = await client.callTool({ name: 'find_person', arguments: { person: 'Al' } });
  assert.ok(!found.isError);
  assert.match(text(found), /STOP/);

  const wrote: any = await client.callTool({ name: 'update_person', arguments: { person: 'Al', about: 'x' } });
  assert.ok(!wrote.isError);
  assert.match(text(wrote), /Nothing was changed/);
  assert.deepEqual(s.calls.map(c => c.method + ' ' + c.path), ['POST /people/resolve', 'POST /people/resolve']);   // no PATCH ever
  s.close();
});

test('a person given by name is resolved first, then used by id; warnings are kept', async () => {
  const s = await stub(c => resolveOk(c) ? { ...resolveOk(c)!, warnings: ["'Al' is only a partial match"] } : { message: 'Updated.' });
  const client = await connect(s.url, 'write');

  const r: any = await client.callTool({ name: 'update_person', arguments: { person: 'Al McNicoll', about: 'Retired teacher', dry_run: true } });
  assert.equal(s.calls[1].method + ' ' + s.calls[1].path, 'PATCH /people/1');
  assert.deepEqual(s.calls[1].body, { about: 'Retired teacher', dry_run: true });
  assert.match(text(r), /partial match/);
  s.close();
});

test('POSTs carry a fresh Idempotency-Key, resolves and reads do not', async () => {
  const s = await stub(c => resolveOk(c) ?? { message: 'Added.' });
  const client = await connect(s.url, 'write');

  await client.callTool({ name: 'add_entry', arguments: { type: 'note', people: ['Al'], body: 'Visited' } });
  await client.callTool({ name: 'add_entry', arguments: { type: 'note', people: ['Al'], body: 'Visited again' } });
  await client.callTool({ name: 'find_person', arguments: { person: 'Al' } });
  await client.callTool({ name: 'whoami', arguments: {} });

  const keys = s.calls.filter(c => c.path === '/entries').map(c => c.headers['idempotency-key']);
  assert.equal(keys.length, 2);
  assert.ok(keys[0] && keys[1] && keys[0] !== keys[1]);
  assert.ok(s.calls.filter(c => c.path !== '/entries').every(c => !c.headers['idempotency-key']));
  assert.ok(s.calls.every(c => c.headers.authorization === `Bearer ${TOKEN}`));
  s.close();
});

test('a retried POST reuses its key; a delete is never retried', async () => {
  let n = 0;
  const s = await stub(() => (++n === 1 ? { http: 503, message: 'down' } : { message: 'Added.' }));
  const client = await connect(s.url, 'delete');

  const r: any = await client.callTool({ name: 'add_task', arguments: { title: 'Call Al', due_date: '2026-11-20' } });
  assert.ok(!r.isError);
  assert.equal(s.calls.length, 2);
  assert.equal(s.calls[0].headers['idempotency-key'], s.calls[1].headers['idempotency-key']);
  s.close();

  const dead = await stub(() => ({ http: 503, message: 'down' }));
  const c2 = await connect(dead.url, 'delete');
  await c2.callTool({ name: 'delete', arguments: { kind: 'task', id: 4 } });
  assert.equal(dead.calls.length, 1);
  dead.close();
});

test('deleting is two steps and Claude only ever sees a handle', async () => {
  const s = await stub(c => c.query.get('confirmation_token')
    ? { message: 'Deleted the task.' }
    : { status: 'needs_confirmation', http: 409, message: "Delete this Task? This cannot be undone. I haven't deleted anything yet: show the user this warning and, only if they agree, repeat the DELETE with ?confirmation_token=SECRET.",
        data: { confirmation_token: 'SECRETTOKEN', expires_in_seconds: 300 } });
  const client = await connect(s.url, 'delete');

  const first = text(await client.callTool({ name: 'delete', arguments: { kind: 'task', id: 4 } }));
  assert.match(first, /Delete this Task/);
  assert.ok(!first.includes('SECRET'));
  const handle = first.match(/handle "(del-[0-9a-f]+)"/)![1];

  const second: any = await client.callTool({ name: 'confirm_delete', arguments: { handle } });
  assert.ok(!second.isError);
  assert.equal(s.calls[1].query.get('confirmation_token'), 'SECRETTOKEN');
  assert.equal(s.calls[1].path, '/tasks/4');

  const again: any = await client.callTool({ name: 'confirm_delete', arguments: { handle } });
  assert.ok(again.isError);
  assert.equal(s.calls.length, 2);
  s.close();
});

test('invalid, duplicates and failures map to results Claude can act on', async () => {
  const replies: Reply[] = [
    { status: 'invalid', http: 422, message: "I couldn't add that entry: body is required.", data: { errors: [{ field: 'body', code: 'required', message: 'The body field is required.' }] } },
    { status: 'possible_duplicate', http: 409, message: 'This looks like an entry already recorded.', data: { existing: { id: 'note_5' } } },
    { status: 'forbidden', http: 403, message: 'This token cannot write.' },
  ];
  let i = 0;
  const s = await stub(c => resolveOk(c) ?? replies[i++]);
  const client = await connect(s.url, 'write');
  const add = () => client.callTool({ name: 'add_entry', arguments: { type: 'note', people: [1], body: 'x' } }) as Promise<any>;

  const invalid = await add();
  assert.ok(invalid.isError);
  assert.match(text(invalid), /body/);

  const dupe = await add();
  assert.ok(!dupe.isError);
  assert.match(text(dupe), /confirm_duplicate: true/);

  assert.ok((await add()).isError);
  s.close();
});

test('data is slimmed, in its own block, and the token never appears', async () => {
  const s = await stub(() => ({ message: 'Found 1.', data: { people: [{ id: 1, born: null, cities: [], name: 'Al', active: false }] }, meta: { pagination: { next_page: 2 } } }));
  const client = await connect(s.url, 'read');
  const r: any = await client.callTool({ name: 'list_people', arguments: {} });

  assert.equal(r.content.length, 2);
  assert.match(r.content[1].text, /\{"people":\[\{"id":1,"name":"Al","active":false\}\]\}/);
  assert.match(text(r), /page 2/);
  assert.ok(!text(r).includes(TOKEN));
  s.close();
});

test('list_entries sends filters in the API\'s format', async () => {
  const s = await stub(() => ({ message: 'ok' }));
  const client = await connect(s.url, 'read');
  await client.callTool({ name: 'list_entries', arguments: { person: { name: 'Al', born: '1984' }, type: ['note', 'goal'], significance: [4, 5], active: true } });

  const q = s.calls[0].query;
  assert.equal(q.get('person'), '{"name":"Al","born":"1984"}');
  assert.equal(q.get('type'), 'note,goal');
  assert.deepEqual(q.getAll('significance[]'), ['4', '5']);
  assert.equal(q.get('active'), 'true');
  s.close();
});

test('the address must be https unless it is local, and the site root works', () => {
  assert.throws(() => apiBase('http://example.org'), /https/);
  assert.equal(apiBase('https://example.org'), 'https://example.org/api/v1');
  assert.equal(apiBase('https://example.org/api/v1/'), 'https://example.org/api/v1');
  assert.equal(apiBase('http://localhost/pastoreyes/public'), 'http://localhost/pastoreyes/public/api/v1');
});
