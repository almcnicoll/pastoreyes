import { randomUUID } from 'node:crypto';

/** The API's one response shape (see doc/api.md). */
export interface Envelope {
  status: string;
  message: string;
  data?: any;
  warnings?: string[];
  next?: { hint: string; method: string; path: string }[];
  meta?: Record<string, any>;
  http: number;
}

export interface ApiOptions {
  url: string;
  token: string;
  timeoutMs?: number;
  fetch?: typeof fetch;
}

/** Accept the site root or the API address; return the .../api/v1 base. Plain http is only for loopback. */
export function apiBase(raw: string): string {
  const u = new URL(raw);
  const loopback = ['localhost', '127.0.0.1', '[::1]'].includes(u.hostname) || u.hostname.endsWith('.localhost') || u.hostname.endsWith('.test');
  if (u.protocol !== 'https:' && !(u.protocol === 'http:' && loopback)) {
    throw new Error('PASTOREYES_URL must be https (plain http is only allowed for localhost).');
  }
  const path = u.pathname.replace(/\/+$/, '');
  return u.origin + (path.endsWith('/api/v1') ? path : path + '/api/v1');
}

type Query = Record<string, string | number | boolean | string[] | number[] | undefined>;

export class Api {
  readonly base: string;
  private token: string;
  private timeoutMs: number;
  private doFetch: typeof fetch;

  constructor(o: ApiOptions) {
    this.base = apiBase(o.url);
    this.token = o.token;
    this.timeoutMs = o.timeoutMs ?? 15000;
    this.doFetch = o.fetch ?? fetch;
  }

  get(path: string, query: Query = {}) { return this.call('GET', path, undefined, query); }

  /** POST gets a fresh Idempotency-Key so a retried call cannot write twice. */
  send(method: 'POST' | 'PATCH' | 'PUT' | 'DELETE', path: string, body?: unknown, query: Query = {}, key = true) {
    return this.call(method, path, body, query, key);
  }

  private async call(method: string, path: string, body: unknown, query: Query, key = true): Promise<Envelope> {
    const qs = new URLSearchParams();
    for (const [k, v] of Object.entries(query)) {
      if (v === undefined || v === '') continue;
      if (Array.isArray(v)) v.forEach(x => qs.append(`${k}[]`, String(x)));
      else qs.append(k, String(v));
    }
    const url = this.base + path + (qs.size ? `?${qs}` : '');
    const headers: Record<string, string> = { Authorization: `Bearer ${this.token}`, Accept: 'application/json' };
    if (body !== undefined) headers['Content-Type'] = 'application/json';
    if (method === 'POST' && key) headers['Idempotency-Key'] = randomUUID();

    // Retry only what is safe to repeat: reads, PATCH/PUT, and POSTs (the key makes them safe). Never DELETE.
    const attempts = method === 'DELETE' ? 1 : 3;
    let last = 'no response';
    for (let i = 0; i < attempts; i++) {
      try {
        const res = await this.doFetch(url, {
          method, headers, body: body === undefined ? undefined : JSON.stringify(body),
          signal: AbortSignal.timeout(this.timeoutMs),
        });
        if ([502, 503, 504].includes(res.status) && i < attempts - 1) { last = `HTTP ${res.status}`; await sleep(400 * (i + 1)); continue; }
        return await toEnvelope(res);
      } catch (e: any) {
        last = e?.name === 'TimeoutError' ? 'timed out' : (e?.cause?.code ?? e?.message ?? 'network error');
        if (i < attempts - 1) await sleep(400 * (i + 1));
      }
    }
    return { status: 'error', http: 0, message: `Could not reach PastorEyes (${last}). Check that PASTOREYES_URL is right and the site is up.` };
  }
}

async function toEnvelope(res: Response): Promise<Envelope> {
  const text = await res.text();
  try {
    const j = JSON.parse(text);
    if (j && typeof j.status === 'string' && typeof j.message === 'string') return { ...j, http: res.status };
  } catch { /* fall through */ }
  const retry = res.headers.get('retry-after');
  return { status: 'error', http: res.status, message: `PastorEyes answered HTTP ${res.status} without a usable reply${retry ? ` (retry after ${retry}s)` : ''}.` };
}

const sleep = (ms: number) => new Promise(r => setTimeout(r, ms));
