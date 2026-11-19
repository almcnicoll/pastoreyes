import { randomBytes } from 'node:crypto';
import { z } from 'zod';
import type { CallToolResult } from '@modelcontextprotocol/sdk/types.js';
import type { Api, Envelope } from './api.ts';
import { present, problem } from './present.ts';

export type Scope = 'read' | 'write' | 'delete';
export const SCOPE_RANK: Record<Scope, number> = { read: 1, write: 2, delete: 3 };

export interface Tool {
  name: string;
  title: string;
  scope: Scope;
  /** "METHOD /path" exactly as in app/Services/Api/Spec.php. A test keeps these in step with the API. */
  endpoints: string[];
  description: string;
  schema: z.ZodRawShape;
  run: (api: Api, a: any, tools: ToolState) => Promise<CallToolResult>;
}

/** Deletes are two steps; the API's single-use token stays in here and Claude gets a short handle. */
export class ToolState {
  private pending = new Map<string, { path: string; token: string; expires: number }>();

  hold(path: string, token: string, seconds: number): string {
    const handle = 'del-' + randomBytes(3).toString('hex');
    this.pending.set(handle, { path, token, expires: Date.now() + seconds * 1000 });
    return handle;
  }

  take(handle: string) {
    const held = this.pending.get(handle);
    this.pending.delete(handle);                       // single use, even when it has expired
    return held && held.expires > Date.now() ? held : undefined;
  }
}

// ---------------------------------------------------------------------------------------------------------
// Shared input pieces
// ---------------------------------------------------------------------------------------------------------

const PersonRef = z.union([
  z.number().int(),
  z.string(),
  z.object({
    name: z.string().optional(),
    born: z.string().optional().describe('1984-07-13, 1984 or 07-13 (month-day when the birth year is unknown)'),
    city: z.string().optional().describe('A town where they live or have lived'),
    gender: z.enum(['male', 'female', 'unknown']).optional(),
    related_to: z.object({ person: z.union([z.string(), z.number().int()]), type: z.string().optional() }).optional()
      .describe('Someone they are related to, e.g. { person: "Caroline McNicoll", type: "spouse" }'),
    id: z.number().int().optional(),
  }),
]).describe('Who: a name like "Al McNicoll" (any part of any of their names, any order), an id from an earlier result (best), or { name, born, city, gender, related_to } to narrow down. Never guess between people.');

const People = z.array(PersonRef).min(1).describe('The people involved. The first is the primary person. Each is a person reference as above.');

const EntryId = z.string().regex(/^(note|prayer_need|goal|outcome|key_date)_\d+$/).describe('An entry id from an earlier result, such as note_5, prayer_need_12, goal_3');
const Ymd = (what: string) => z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Use YYYY-MM-DD').describe(`${what} as YYYY-MM-DD`);
const Significance = z.number().int().min(1).max(5).describe('1 (low) to 5 (high). Defaults to 3');
const Page = { page: z.number().int().min(1).optional(), per_page: z.number().int().min(1).max(100).optional().describe('Default 25') };
const DryRun = { dry_run: z.boolean().optional().describe('If true, check and describe what would happen but save nothing. Use it for anything uncertain or large') };
const KEY_DATE_TYPES = ['birthday', 'wedding_anniversary', 'death', 'bereavement', 'other'];

// ---------------------------------------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------------------------------------

const compact = (o: Record<string, any>) => Object.fromEntries(Object.entries(o).filter(([, v]) => v !== undefined));

/** A reference as it goes in a query string: ids and names as they are, objects as JSON. */
const refParam = (r: any) => (r !== null && typeof r === 'object' ? JSON.stringify(r) : r);

type Resolved = { id: number; warnings: string[] } | { fail: Envelope };

/** The person a reference means, or the envelope explaining why not (ambiguous, not found...). Nothing is written. */
async function resolve(api: Api, ref: any): Promise<Resolved> {
  if (typeof ref === 'number') return { id: ref, warnings: [] };
  const env = await api.send('POST', '/people/resolve', typeof ref === 'string' ? { name: ref } : ref, {}, false);
  const id = env.data?.person?.id;
  return env.status === 'ok' && id ? { id, warnings: env.warnings ?? [] } : { fail: env };
}

/** Resolve a person first, then run the call for them; an unresolved person ends the tool call without a write. */
async function onPerson(api: Api, ref: any, write: boolean, then: (id: number) => Promise<Envelope>): Promise<CallToolResult> {
  const r = await resolve(api, ref);
  if ('fail' in r) return present(r.fail, { write });
  return present(await then(r.id), { write, warnings: r.warnings });
}

const body = (a: any, fields: string[]) => compact({ ...Object.fromEntries(fields.map(f => [f, a[f]])), dry_run: a.dry_run });

const ENTRY_FIELDS = ['people', 'date', 'title', 'body', 'significance', 'target_date', 'key_date_type', 'label', 'is_recurring', 'recurrence_count', 'year_unknown', 'sync_to_calendar'];

const entryShape = {
  people: People,
  date: Ymd('The date it happened or applies to (a Key Date: the date itself)').optional().describe('YYYY-MM-DD. Defaults to today (the user\'s today)'),
  title: z.string().max(500).optional().describe('Short heading. Required for a goal; optional for a note or prayer_need'),
  body: z.string().optional().describe('The text of the note, prayer need or goal. Required for those three. Record only what the user told you'),
  significance: Significance.optional(),
  target_date: Ymd('Goal only: when it is hoped to be achieved').optional(),
  key_date_type: z.enum(KEY_DATE_TYPES as [string, ...string[]]).optional().describe('Key Date only (required): what kind of date (bereavement = the loss of someone close)'),
  label: z.string().max(500).optional().describe('Key Date only: a short name such as "Retirement"'),
  is_recurring: z.boolean().optional().describe('Key Date only: yearly? Defaults to true'),
  recurrence_count: z.number().int().min(1).max(100).optional().describe('Key Date only: the number of years it recurs for, if limited'),
  year_unknown: z.boolean().optional().describe('Key Date only: the year is not known'),
  sync_to_calendar: z.boolean().optional().describe('Key Date only: also put it in the user\'s Google Calendar. Leave out unless asked'),
};

// ---------------------------------------------------------------------------------------------------------
// The tools
// ---------------------------------------------------------------------------------------------------------

export const tools: Tool[] = [
  // ---- read ----
  {
    name: 'whoami', title: 'Who am I', scope: 'read', endpoints: ['GET /me'],
    description: 'Orientation. Returns whose PastorEyes this is, what access the token has, and TODAY\'S DATE in the user\'s time zone. Call it once at the start of a conversation before working with dates ("yesterday", "last Sunday").',
    schema: {},
    run: async api => present(await api.get('/me')),
  },
  {
    name: 'find_person', title: 'Find a person', scope: 'read', endpoints: ['POST /people/resolve'],
    description: 'Check who a name means, with no side effects. Use it whenever you are unsure which person the user is talking about, and before writing about someone you have not found yet. Returns the person (with id, birth date and family) or, if several people fit, the candidates and what tells them apart: then ask the user, never choose. Give `people` to check several names in one call.',
    schema: { person: PersonRef.optional(), people: z.array(PersonRef).min(1).optional().describe('Several people to check at once, instead of `person`') },
    run: async (api, a) => {
      if (!a.person && !a.people) return problem('Give either person or people.');
      return present(await api.send('POST', '/people/resolve', a.people ? { people: a.people } : typeof a.person === 'string' ? { name: a.person } : typeof a.person === 'number' ? { id: a.person } : a.person, {}, false));
    },
  },
  {
    name: 'search', title: 'Search', scope: 'read', endpoints: ['GET /search'],
    description: 'Free-text search across people and, optionally, entries. Use for a vague question ("who did I note something about hospital for?"). To find or confirm one named person use find_person instead. Searching entry text scans only the newest few thousand entries and says so if it was cut short.',
    schema: { q: z.string().min(1).describe('Words to look for'), scope: z.enum(['people', 'entries', 'all']).optional().describe('Default people'), ...Page },
    run: async (api, a) => present(await api.get('/search', compact({ q: a.q, scope: a.scope, page: a.page, per_page: a.per_page }))),
  },
  {
    name: 'list_people', title: 'List people', scope: 'read', endpoints: ['GET /people'],
    description: 'List or filter people: by name text, birth date, city, who has active Prayer Needs, or who is related to someone. For "who still has active prayer needs?" use has_active_prayer_needs: true. Each row has an id you can use in later calls.',
    schema: {
      q: z.string().optional().describe('Name text'), born: z.string().optional(), city: z.string().optional(), gender: z.enum(['male', 'female', 'unknown']).optional(),
      has_active_prayer_needs: z.boolean().optional(), related_to: PersonRef.optional().describe('Only people related to this person'),
      sort: z.enum(['name', 'recent']).optional().describe('recent = most recently written about first'), ...Page,
    },
    run: async (api, a) => {
      let related: number | undefined;
      if (a.related_to !== undefined) {
        const r = await resolve(api, a.related_to);
        if ('fail' in r) return present(r.fail);
        related = r.id;
      }
      return present(await api.get('/people', compact({ q: a.q, born: a.born, city: a.city, gender: a.gender, has_active_prayer_needs: a.has_active_prayer_needs, related_to: related, sort: a.sort, page: a.page, per_page: a.per_page })));
    },
  },
  {
    name: 'get_person', title: 'Get a person', scope: 'read', endpoints: ['GET /people/{id}', 'GET /people/by'],
    description: 'One person\'s record. By default a summary; ask for more with `include` (names, addresses, relationships, key_dates, entries, active_prayer_needs, goals, tasks, about, or * for everything). For "tell me about X" or preparing a visit prefer get_person_briefing, which is already written as a summary.',
    schema: { person: PersonRef, include: z.array(z.enum(['names', 'addresses', 'relationships', 'key_dates', 'entries', 'active_prayer_needs', 'goals', 'tasks', 'about', '*'])).optional() },
    run: async (api, a) => {
      const include = a.include?.join(',');
      const r = a.person;
      if (typeof r === 'number') return present(await api.get(`/people/${r}`, { include }));
      // One call for a plain name (or name plus born/city/gender); a related_to hint needs the resolver first
      if (typeof r === 'string' || !r.related_to) {
        const { name, born, city, gender, id } = typeof r === 'string' ? { name: r } as any : r;
        return present(await api.get('/people/by', compact({ name, born, city, gender, id, include })));
      }
      return onPerson(api, r, false, id => api.get(`/people/${id}`, { include }));
    },
  },
  {
    name: 'get_person_briefing', title: 'Briefing on a person', scope: 'read', endpoints: ['GET /people/{id}/briefing'],
    description: 'A narrative pastoral summary of one person: who they are, family, active Prayer Needs, Goals, open tasks, upcoming dates and when they were last written about. The best single call before a visit or when the user asks "what do I know about X?".',
    schema: { person: PersonRef },
    run: async (api, a) => onPerson(api, a.person, false, id => api.get(`/people/${id}/briefing`)),
  },
  {
    name: 'get_briefing', title: 'What needs attention', scope: 'read', endpoints: ['GET /briefing'],
    description: 'The dashboard in words: upcoming birthdays and key dates, active Prayer Needs, open tasks, active Goals and recent entries across everyone. Use for "what\'s coming up?", "who should I pray for / visit?", "what\'s on my plate?".',
    schema: { days: z.number().int().min(1).max(365).optional().describe('How far ahead to look for dates. Default as the app') },
    run: async (api, a) => present(await api.get('/briefing', compact({ days: a.days }))),
  },
  {
    name: 'list_entries', title: 'List entries', scope: 'read', endpoints: ['GET /entries'],
    description: 'The Timeline: Notes, Prayer Needs, Goals, Outcomes and Key Dates, newest first, filtered. Use for "what have I recorded about X?", "unresolved prayer needs", "notes from last month". `q` searches the text. Pass the person as a name or id; an ambiguous name is reported, not guessed.',
    schema: {
      person: PersonRef.optional(),
      type: z.array(z.enum(['note', 'prayer_need', 'goal', 'outcome', 'key_date'])).optional(),
      active: z.boolean().optional().describe('true = only unresolved Prayer Needs and unachieved Goals'),
      significance: z.array(z.number().int().min(1).max(5)).optional().describe('Only these significance levels'),
      min_significance: z.number().int().min(1).max(5).optional(),
      from: Ymd('Earliest date').optional(), to: Ymd('Latest date').optional(),
      q: z.string().optional().describe('Text to find in titles and bodies'),
      sort: z.enum(['newest', 'oldest']).optional(), ...Page,
    },
    run: async (api, a) => present(await api.get('/entries', compact({
      person: a.person === undefined ? undefined : refParam(a.person), type: a.type?.join(','), active: a.active, significance: a.significance,
      min_significance: a.min_significance, from: a.from, to: a.to, q: a.q, sort: a.sort, page: a.page, per_page: a.per_page,
    }))),
  },
  {
    name: 'get_entry', title: 'Get an entry', scope: 'read', endpoints: ['GET /entries/{entry_id}'],
    description: 'One entry in full, by its id (note_5, prayer_need_12, goal_3, outcome_7, key_date_9).',
    schema: { entry: EntryId },
    run: async (api, a) => present(await api.get(`/entries/${a.entry}`)),
  },
  {
    name: 'list_tasks', title: 'List tasks', scope: 'read', endpoints: ['GET /tasks'],
    description: 'The user\'s tasks (to-dos, optionally linked to people). Defaults to active ones. Use for "what do I need to do?" or "tasks for X".',
    schema: {
      status: z.enum(['active', 'completed', 'all']).optional(), person: PersonRef.optional(), due_before: Ymd('Due on or before').optional(), due_after: Ymd('Due on or after').optional(),
      sort: z.enum(['due', 'created']).optional(), ...Page,
    },
    run: async (api, a) => present(await api.get('/tasks', compact({ status: a.status, person: a.person === undefined ? undefined : refParam(a.person), due_before: a.due_before, due_after: a.due_after, sort: a.sort, page: a.page, per_page: a.per_page }))),
  },
  {
    name: 'list_relationship_types', title: 'Relationship types', scope: 'read', endpoints: ['GET /relationship-types'],
    description: 'The relationship types that exist (spouse, parent/child, sibling...) and which way round they run. Call before add_relationship if unsure of a type\'s name.',
    schema: {},
    run: async api => present(await api.get('/relationship-types')),
  },

  // ---- write ----
  {
    name: 'add_entry', title: 'Add an entry', scope: 'write', endpoints: ['POST /entries'],
    description: 'Add a Note, Prayer Need, Goal or Key Date for one or more people. Types: note (a record of a visit or conversation), prayer_need (something to pray for; never call it a prayer request), goal (needs a title; optional target_date), key_date (needs key_date_type; e.g. an anniversary). To record progress on a Goal use record_outcome. People can be names: if a name is ambiguous or unknown nothing is saved and you get the candidates to ask the user about. If a near-identical entry exists you get possible_duplicate: tell the user, and only resend with confirm_duplicate if they confirm.',
    schema: { type: z.enum(['note', 'prayer_need', 'goal', 'key_date']), ...entryShape, confirm_duplicate: z.boolean().optional().describe('Only after the user agrees it is not a duplicate'), ...DryRun },
    run: async (api, a) => present(await api.send('POST', '/entries', body(a, ['type', ...ENTRY_FIELDS, 'confirm_duplicate'])), { write: true }),
  },
  {
    name: 'update_entry', title: 'Edit an entry', scope: 'write', endpoints: ['PATCH /entries/{entry_id}'],
    description: 'Change fields of an existing entry (send only what changes). `people` replaces the whole linked set, first = primary. To resolve a Prayer Need use set_prayer_need_status; to record progress on a Goal use record_outcome.',
    schema: { entry: EntryId, ...Object.fromEntries(Object.entries(entryShape).map(([k, v]) => [k, k === 'people' ? People.optional() : v])), ...DryRun },
    run: async (api, a) => present(await api.send('PATCH', `/entries/${a.entry}`, body(a, ENTRY_FIELDS)), { write: true }),
  },
  {
    name: 'set_prayer_need_status', title: 'Resolve or reopen a Prayer Need', scope: 'write', endpoints: ['POST /entries/{entry_id}/resolve', 'POST /entries/{entry_id}/reopen'],
    description: 'Mark a Prayer Need resolved (answered or no longer needed), optionally with how it was resolved, or make a resolved one active again. Get the entry id from list_entries or get_person (include active_prayer_needs).',
    schema: {
      entry: z.string().regex(/^prayer_need_\d+$/).describe('A Prayer Need id such as prayer_need_12'), status: z.enum(['resolved', 'active']),
      resolved_at: Ymd('When it was resolved').optional().describe('YYYY-MM-DD, default today'), resolution_details: z.string().optional().describe('What happened'), ...DryRun,
    },
    run: async (api, a) => a.status === 'resolved'
      ? present(await api.send('POST', `/entries/${a.entry}/resolve`, body(a, ['resolved_at', 'resolution_details'])), { write: true })
      : present(await api.send('POST', `/entries/${a.entry}/reopen`, body(a, [])), { write: true }),
  },
  {
    name: 'record_outcome', title: 'Record an Outcome on a Goal', scope: 'write', endpoints: ['POST /entries/{entry_id}/outcomes'],
    description: 'Record progress or a result on a Goal. Set goal_complete: true if it means the Goal is achieved. Needs the Goal\'s id (from list_entries type goal, or get_person include goals).',
    schema: {
      goal: z.string().regex(/^goal_\d+$/).describe('A Goal id such as goal_3'), title: z.string().max(500), body: z.string(), significance: Significance.optional(),
      goal_complete: z.boolean().optional().describe('The Goal is now achieved'), achieved_at: Ymd('When it was achieved').optional(), ...DryRun,
    },
    run: async (api, a) => present(await api.send('POST', `/entries/${a.goal}/outcomes`, body(a, ['title', 'body', 'significance', 'goal_complete', 'achieved_at'])), { write: true }),
  },
  {
    name: 'add_task', title: 'Add a task', scope: 'write', endpoints: ['POST /tasks'],
    description: 'Add a to-do for the user, optionally linked to people and a due date. ("Remind me to call Al on Friday" is a task with a due_date, not a Note.) The app sends no reminders by itself.',
    schema: { title: z.string().max(500), narrative: z.string().optional().describe('More detail'), due_date: Ymd('Due date').optional(), people: z.array(PersonRef).optional(), ...DryRun },
    run: async (api, a) => present(await api.send('POST', '/tasks', body(a, ['title', 'narrative', 'due_date', 'people'])), { write: true }),
  },
  {
    name: 'update_task', title: 'Edit a task', scope: 'write', endpoints: ['PATCH /tasks/{id}'],
    description: 'Change a task\'s title, detail, due date or linked people (send only what changes; `people` replaces the set). To tick it off use set_task_status.',
    schema: { task: z.number().int().describe('Task id from list_tasks'), title: z.string().max(500).optional(), narrative: z.string().optional(), due_date: Ymd('Due date').optional(), people: z.array(PersonRef).optional(), ...DryRun },
    run: async (api, a) => present(await api.send('PATCH', `/tasks/${a.task}`, body(a, ['title', 'narrative', 'due_date', 'people'])), { write: true }),
  },
  {
    name: 'set_task_status', title: 'Complete or reopen a task', scope: 'write', endpoints: ['POST /tasks/{id}/complete', 'POST /tasks/{id}/reopen'],
    description: 'Mark a task completed, or make a completed one active again.',
    schema: { task: z.number().int().describe('Task id from list_tasks'), status: z.enum(['completed', 'active']), ...DryRun },
    run: async (api, a) => present(await api.send('POST', `/tasks/${a.task}/${a.status === 'completed' ? 'complete' : 'reopen'}`, body(a, [])), { write: true }),
  },
  {
    name: 'add_person', title: 'Add a person', scope: 'write', endpoints: ['POST /people'],
    description: 'Create a new person. Check with find_person first that they do not exist already. A probable duplicate (same name, compatible birth date) is refused with possible_duplicate: tell the user and only resend with confirm_duplicate if they say it is someone different. Needs at least a name.',
    schema: {
      first_name: z.string().optional(), last_name: z.string().optional(), middle_names: z.string().optional(), preferred_name: z.string().optional(),
      gender: z.enum(['male', 'female', 'unknown']).optional().describe('Only if the user said'), born: z.string().optional().describe('YYYY-MM-DD, or MM-DD with born_year_unknown'),
      born_year_unknown: z.boolean().optional(), about: z.string().optional().describe('Background in the user\'s words'),
      addresses: z.array(z.object({ line_1: z.string().optional(), line_2: z.string().optional(), line_3: z.string().optional(), city: z.string().optional(), county: z.string().optional(), postcode: z.string().optional(), country: z.string().optional() })).optional(),
      confirm_duplicate: z.boolean().optional().describe('Only after the user agrees it is a different person'), ...DryRun,
    },
    run: async (api, a) => present(await api.send('POST', '/people', body(a, ['first_name', 'last_name', 'middle_names', 'preferred_name', 'gender', 'born', 'born_year_unknown', 'about', 'addresses', 'confirm_duplicate'])), { write: true }),
  },
  {
    name: 'update_person', title: 'Update a person', scope: 'write', endpoints: ['PATCH /people/{id}'],
    description: 'Change a person\'s gender, About text (replaces it) or date of death. For names, addresses, birthday or relationships use the dedicated tools.',
    schema: { person: PersonRef, gender: z.enum(['male', 'female', 'unknown']).optional(), about: z.string().optional(), date_of_death: Ymd('Date of death').optional(), ...DryRun },
    run: async (api, a) => onPerson(api, a.person, true, id => api.send('PATCH', `/people/${id}`, body(a, ['gender', 'about', 'date_of_death']))),
  },
  {
    name: 'add_person_name', title: 'Add a name to a person', scope: 'write', endpoints: ['POST /people/{id}/names'],
    description: 'Give a person another name: a maiden, married or preferred name, or a nickname. Every name is searchable afterwards.',
    schema: {
      person: PersonRef, type: z.enum(['birth', 'married', 'preferred', 'other']), first_name: z.string().optional(), middle_names: z.string().optional(), last_name: z.string().optional(),
      preferred_name: z.string().optional(), is_primary: z.boolean().optional().describe('Make this their main name'), spelling_uncertain: z.boolean().optional(), notes: z.string().optional(), ...DryRun,
    },
    run: async (api, a) => onPerson(api, a.person, true, id => api.send('POST', `/people/${id}/names`, body(a, ['type', 'first_name', 'middle_names', 'last_name', 'preferred_name', 'is_primary', 'spelling_uncertain', 'notes']))),
  },
  {
    name: 'add_person_address', title: 'Add an address to a person', scope: 'write', endpoints: ['POST /people/{id}/addresses'],
    description: 'Add an address. Set is_current: false for a past address.',
    schema: {
      person: PersonRef, line_1: z.string().optional(), line_2: z.string().optional(), line_3: z.string().optional(), city: z.string().optional(), county: z.string().optional(),
      postcode: z.string().optional(), country: z.string().optional(), is_current: z.boolean().optional(), notes: z.string().optional(), ...DryRun,
    },
    run: async (api, a) => onPerson(api, a.person, true, id => api.send('POST', `/people/${id}/addresses`, body(a, ['line_1', 'line_2', 'line_3', 'city', 'county', 'postcode', 'country', 'is_current', 'notes']))),
  },
  {
    name: 'set_birthday', title: 'Set a birthday', scope: 'write', endpoints: ['PUT /people/{id}/birthday'],
    description: 'Set or replace a person\'s birthday. If only the day and month are known, use year_unknown: true with any year.',
    schema: { person: PersonRef, date: Ymd('Birthday'), year_unknown: z.boolean().optional(), ...DryRun },
    run: async (api, a) => onPerson(api, a.person, true, id => api.send('PUT', `/people/${id}/birthday`, body(a, ['date', 'year_unknown']))),
  },
  {
    name: 'add_relationship', title: 'Add a relationship', scope: 'write', endpoints: ['POST /people/{id}/relationships'],
    description: 'Record that `person` is the <type> of `other` (type "parent": person is the parent of other; "spouse"; "sibling"). Both are resolved by name, so ambiguity stops the write. Names of the types come from list_relationship_types; using the inverse name (child) reverses the direction.',
    schema: { person: PersonRef, type: z.string().describe('Relationship type name, e.g. spouse, parent, child, sibling'), other: PersonRef, notes: z.string().optional(), ...DryRun },
    run: async (api, a) => onPerson(api, a.person, true, id => api.send('POST', `/people/${id}/relationships`, body(a, ['type', 'other', 'notes']))),
  },

  // ---- delete ----
  {
    name: 'delete', title: 'Delete (step 1: ask)', scope: 'delete', endpoints: ['DELETE /entries/{entry_id}', 'DELETE /tasks/{id}', 'DELETE /relationships/{id}'],
    description: 'Start deleting an entry, task or relationship. Nothing is deleted by this call: it returns a warning describing exactly what would go (for example a Goal also deletes its Outcomes) and a handle. You MUST show the user that warning and get a clear yes before calling confirm_delete. People cannot be deleted through this connection.',
    schema: { kind: z.enum(['entry', 'task', 'relationship']), id: z.union([z.string(), z.number().int()]).describe('entry: such as note_5. task or relationship: the number') },
    run: async (api, a, state) => {
      const path = a.kind === 'entry' ? `/entries/${a.id}` : a.kind === 'task' ? `/tasks/${a.id}` : `/relationships/${a.id}`;
      const env = await api.send('DELETE', path);
      if (env.status !== 'needs_confirmation') return present(env, { write: true });
      const warning = env.message.split(" I haven't deleted anything yet")[0];
      const handle = state.hold(path, env.data.confirmation_token, env.data.expires_in_seconds ?? 300);
      return { content: [{ type: 'text', text: `${warning}\n\nNothing has been deleted. Show the user this warning. Only if they clearly say yes, call confirm_delete with handle "${handle}" (valid for ${Math.round((env.data.expires_in_seconds ?? 300) / 60)} minutes, one use).` }] };
    },
  },
  {
    name: 'confirm_delete', title: 'Delete (step 2: confirm)', scope: 'delete', endpoints: [],
    description: 'Carry out a deletion started with `delete`. Only call this after the user has seen the warning and said yes in this conversation.',
    schema: { handle: z.string().describe('The handle returned by delete') },
    run: async (api, a, state) => {
      const held = state.take(a.handle);
      if (!held) return problem('That handle is unknown, used or expired. Nothing was deleted. Call delete again to start over.');
      return present(await api.send('DELETE', held.path, undefined, { confirmation_token: held.token }), { write: true });
    },
  },
];
