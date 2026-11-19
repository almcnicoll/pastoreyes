<?php

namespace App\Services\Api;

use App\Models\KeyDate;

/**
 * The single list of API endpoints. routes/api.php registers routes from it, and `GET /api/v1` and
 * `GET /api/v1/openapi.json` describe them, so the routes and the documentation cannot drift apart.
 *
 * Each endpoint: [method, path (under /api/v1), scope, summary, 'Controller@action', fields (docs)].
 * Scopes: public (no token) | read | write | delete.
 */
class Spec
{
    /** Entry ids look like `prayer_need_12`: a type, an underscore and a number. */
    public const ENTRY_ID = '(note|prayer_need|goal|outcome|key_date)_[0-9]+';

    public static function endpoints(): array
    {
        return [
            ['GET',    '/',                          'public', 'Self-description: endpoints, scopes, enums and the glossary', 'MetaController@index', ''],
            ['GET',    '/openapi.json',              'public', 'OpenAPI 3 description of this API', 'MetaController@openapi', ''],
            ['GET',    '/me',                        'read',   'Who the token is, its scope and expiry', 'MetaController@me', ''],
            ['GET',    '/briefing',                  'read',   'What needs attention (the Dashboard): upcoming key dates, prayer needs, tasks, goals, recent entries', 'BriefingController@index', 'days'],
            ['GET',    '/search',                    'read',   'Search people (and optionally entries) by text', 'BriefingController@search', 'q, scope=people|entries|all, page, per_page'],

            ['POST',   '/people/resolve',            'read',   'Resolve person reference(s) without side effects', 'PeopleController@resolve', 'a PersonRef (name, born, city, gender, related_to, id) or { people: [PersonRef...] }'],
            ['GET',    '/people',                    'read',   'List or search people', 'PeopleController@index', 'q, born, city, gender, has_active_prayer_needs, related_to, sort=name|recent, page, per_page'],
            ['GET',    '/people/by',                 'read',   'Find one person by name and hints; ambiguity is reported, not guessed', 'PeopleController@by', 'name, born, city, gender, id, include'],
            ['GET',    '/people/{id}',               'read',   'One person, with optional sub-resources', 'PeopleController@show', 'include=names,addresses,relationships,key_dates,entries,active_prayer_needs,goals,tasks,about or *'],
            ['GET',    '/people/{id}/briefing',      'read',   'Narrative pastoral summary of one person', 'PeopleController@briefing', ''],
            ['POST',   '/people',                    'write',  'Create a person (refuses probable duplicates unless confirm_duplicate)', 'PeopleController@store', 'first_name, last_name, middle_names, preferred_name, gender, born, born_year_unknown, about, addresses[], confirm_duplicate'],
            ['PATCH',  '/people/{id}',               'write',  'Update gender, about or date of death', 'PeopleController@update', 'gender, about, date_of_death'],
            ['POST',   '/people/{id}/names',         'write',  'Add a name (maiden, married, preferred...)', 'PeopleController@addName', 'type, first_name, middle_names, last_name, preferred_name, is_primary, spelling_uncertain, notes'],
            ['POST',   '/people/{id}/addresses',     'write',  'Add an address', 'PeopleController@addAddress', 'line_1, line_2, line_3, city, county, postcode, country, is_current, notes'],
            ['PUT',    '/people/{id}/birthday',      'write',  'Set or replace the birthday (a Key Date)', 'PeopleController@setBirthday', 'date, year_unknown'],

            ['GET',    '/entries',                   'read',   'The Timeline: entries of any type, filtered', 'EntriesController@index', 'person, type[], active, significance[], min_significance, from, to, q, sort=newest|oldest, page, per_page'],
            ['GET',    '/entries/{entry_id}',        'read',   'One entry', 'EntriesController@show', ''],
            ['POST',   '/entries',                   'write',  'Create a Note, Prayer Need, Goal or Key Date', 'EntriesController@store', 'type, people[], date, title, body, significance, target_date, key_date_type, label, is_recurring, recurrence_count, year_unknown, sync_to_calendar, confirm_duplicate'],
            ['PATCH',  '/entries/{entry_id}',        'write',  'Edit an entry (people replaces the linked set; the first is primary)', 'EntriesController@update', 'as for POST /entries, all optional'],
            ['DELETE', '/entries/{entry_id}',        'delete', 'Delete an entry (two steps: confirmation_token)', 'EntriesController@destroy', 'confirmation_token'],
            ['POST',   '/entries/{entry_id}/resolve', 'write', 'Resolve a Prayer Need', 'EntriesController@resolve', 'resolved_at, resolution_details'],
            ['POST',   '/entries/{entry_id}/reopen', 'write',  'Make a resolved Prayer Need active again', 'EntriesController@reopen', ''],
            ['POST',   '/entries/{entry_id}/outcomes', 'write', 'Record an Outcome on a Goal, optionally achieving it', 'EntriesController@outcome', 'title, body, significance, goal_complete, achieved_at'],

            ['GET',    '/tasks',                     'read',   'List tasks', 'TasksController@index', 'status=active|completed|all, person, due_before, due_after, sort=due|created, page, per_page'],
            ['POST',   '/tasks',                     'write',  'Create a task', 'TasksController@store', 'title, narrative, due_date, people[]'],
            ['PATCH',  '/tasks/{id}',                'write',  'Edit a task', 'TasksController@update', 'title, narrative, due_date, people[]'],
            ['POST',   '/tasks/{id}/complete',       'write',  'Mark a task completed', 'TasksController@complete', ''],
            ['POST',   '/tasks/{id}/reopen',         'write',  'Make a completed task active again', 'TasksController@reopen', ''],
            ['DELETE', '/tasks/{id}',                'delete', 'Delete a task (two steps: confirmation_token)', 'TasksController@destroy', 'confirmation_token'],

            ['GET',    '/relationship-types',        'read',   'Relationship types available, with their directions', 'RelationshipsController@types', ''],
            ['POST',   '/people/{id}/relationships', 'write',  'Add a relationship from this person to another', 'RelationshipsController@store', 'type, other (PersonRef), notes'],
            ['DELETE', '/relationships/{id}',        'delete', 'Remove a relationship; neither person is deleted (two steps: confirmation_token)', 'RelationshipsController@destroy', 'confirmation_token'],
        ];
    }

    /** `GET /api/v1`: everything an agent needs to use the API without prior knowledge. */
    public static function describe(): array
    {
        return [
            'name'      => 'PastorEyes API',
            'version'   => 'v1',
            'base_url'  => url('/api/v1'),
            'auth'      => 'Authorization: Bearer <token>. Create a token in Settings > API (read-only unless you need to write).',
            'response'  => 'Every response is { status, message, data, warnings, next, meta }. Branch on status; relay message when a decision is needed.',
            'scopes'    => ['read' => 'All GET endpoints', 'write' => 'Create and update; includes read', 'delete' => 'Delete; includes write. Not granted by default'],
            'enums'     => [
                'entry_type'       => ApiPresenter::ENTRY_TYPES,
                'key_date_type'    => array_keys(KeyDate::TYPES),
                'gender'           => ['male', 'female', 'unknown'],
                'name_type'        => ['birth', 'married', 'preferred', 'other'],
                'task_status'      => ['active', 'completed', 'all'],
                'response_status'  => ['ok', 'ambiguous', 'not_found', 'needs_confirmation', 'possible_duplicate', 'invalid', 'forbidden', 'error'],
                'significance'     => '1 (low) to 5 (high), default 3',
            ],
            'conventions' => [
                'dates'        => 'YYYY-MM-DD; timestamps ISO 8601 UTC. "Today" is in the user\'s configured time zone.',
                'entry_ids'    => 'Type-prefixed strings such as note_5, prayer_need_12, goal_3, outcome_7, key_date_9.',
                'person_ref'   => 'An id (42), a name ("Al McNicoll") or { name, born, city, gender, related_to, id }. Every word must match one of the person\'s names. Writes never guess between people: ambiguity is returned as status "ambiguous" with candidates and how to specify one.',
                'safety'       => 'Add ?dry_run=true to any write to see what would happen. Send an Idempotency-Key header on POSTs. Deletes need a second call with the returned confirmation_token. Unknown request fields are rejected.',
                'pagination'   => '?page=1&per_page=25 (max 100); see meta.pagination.',
            ],
            'glossary'  => [
                'Entry'       => 'Anything on the Timeline: a Note, Prayer Need, Goal, Outcome or Key Date.',
                'Prayer Need' => 'Never "prayer request". Active or Resolved.',
                'Goal'        => 'Active or Resolved (achieved); an Outcome records progress on a Goal.',
                'Task'        => 'Active or Completed.',
                'About'       => 'A person\'s free-text background field.',
            ],
            'endpoints' => array_map(fn($e) => [
                'method' => $e[0], 'path' => '/api/v1' . ($e[1] === '/' ? '' : $e[1]), 'scope' => $e[2], 'summary' => $e[3], 'fields' => $e[5],
            ], self::endpoints()),
        ];
    }

    /** A compact OpenAPI 3.0 document built from the same list. */
    public static function openapi(): array
    {
        $paths = [];
        foreach (self::endpoints() as [$method, $path, $scope, $summary, , $fields]) {
            preg_match_all('/\{(\w+)\}/', $path, $m);
            $operation = [
                'summary'     => $summary,
                'description' => $fields ? "Fields: {$fields}." : null,
                'x-scope'     => $scope,
                'parameters'  => array_map(fn($p) => ['name' => $p, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']], $m[1]),
                'security'    => $scope === 'public' ? [] : [['bearer' => []]],
                'responses'   => ['200' => ['description' => 'The standard envelope: { status, message, data, warnings, next, meta }']],
            ];
            if (in_array($method, ['POST', 'PATCH', 'PUT'], true)) {
                $operation['requestBody'] = ['content' => ['application/json' => ['schema' => ['type' => 'object']]]];
            }
            // An empty `security` list is meaningful (no auth), so only drop what is truly absent
            $operation = array_filter($operation, fn($v, $k) => $k === 'security' || ($v !== null && $v !== []), ARRAY_FILTER_USE_BOTH);
            $paths[$path][strtolower($method)] = $operation;
        }

        return [
            'openapi'    => '3.0.3',
            'info'       => ['title' => 'PastorEyes API', 'version' => 'v1', 'description' => 'Personal-token API for agents: retrieve and add pastoral-care data, with narrative responses and person disambiguation.'],
            'servers'    => [['url' => url('/api/v1')]],
            'paths'      => $paths,
            'components' => ['securitySchemes' => ['bearer' => ['type' => 'http', 'scheme' => 'bearer']]],
        ];
    }
}
