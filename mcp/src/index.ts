#!/usr/bin/env node
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { Api } from './api.ts';
import { buildServer } from './server.ts';
import type { Scope } from './tools.ts';

const { PASTOREYES_URL: url, PASTOREYES_TOKEN: token, PASTOREYES_TIMEOUT_MS: timeout } = process.env;

// stdout belongs to the protocol: log to stderr only, and never the token
const log = (m: string) => console.error(`[pastoreyes-mcp] ${m}`);

if (!url || !token) {
  log('Set PASTOREYES_URL and PASTOREYES_TOKEN (create a token in PastorEyes: Settings, API).');
  process.exit(1);
}

let api: Api;
try {
  api = new Api({ url, token, timeoutMs: timeout ? Number(timeout) : undefined });
} catch (e: any) {
  log(e.message);
  process.exit(1);
}

// One call decides which tools to offer: a read-only token never sees the write tools
let scope: Scope | null = null;
const me = await api.get('/me');
if (me.status === 'ok') {
  scope = me.data?.token?.scope ?? null;
  log(`connected to ${api.base} with ${scope} access`);
} else {
  log(`could not confirm the token (${me.message}); offering all tools, the API will refuse what it must`);
}

await buildServer(api, scope).connect(new StdioServerTransport());
