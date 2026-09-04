<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Migrations\GenericImportJob;
use App\Jobs\Migrations\MigrateLinkGalleryJob;
use App\Jobs\Migrations\MigrateWikiLinkingJob;
use App\Jobs\Migrations\MigrateWikiTermsLinkingJob;
use App\Models\MigrationAttribution;
use App\Models\MigrationLegacyUser;
use App\Models\MigrationLog;
use App\Models\MigrationMapping;
use App\Models\MigrationSource;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketType;
use App\Models\User;
use App\Services\Migration\MigrationTargets;
use App\Services\Migration\RowMapper;
use App\Services\Migration\SourceQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MigrationController extends Controller
{
    /**
     * Post-import steps: source-independent jobs that run over already
     * imported data. Row imports themselves are driven by the stored
     * mappings (Mappings tab) — see MigrationTargets.
     */
    protected array $migrations = [
        'linkGallery' => [
            'name'        => 'Link Gallery to Events',
            'description' => 'Attach gallery collections to imported events via the album name in the event details',
            'group'       => 'post',
            'job'         => MigrateLinkGalleryJob::class,
        ],
        'wikiLinking' => [
            'name'        => 'Wiki Internal Links',
            'description' => 'Rewrite remaining [[...]] links in imported wiki pages to real page links',
            'group'       => 'post',
            'job'         => MigrateWikiLinkingJob::class,
        ],
        'wikiTermsLinking' => [
            'name'        => 'Wiki Terms Linking',
            'description' => 'Rewrite remaining [[...]] links in wiki term descriptions',
            'group'       => 'post',
            'job'         => MigrateWikiTermsLinkingJob::class,
        ],
    ];

    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
        $this->middleware(['admin']);
    }

    /**
     * Get list of available migrations.
     */
    public function index(): JsonResponse
    {
        $migrations = [];
        foreach ($this->migrations as $key => $migration) {
            $migrations[] = [
                'key'         => $key,
                'name'        => $migration['name'],
                'description' => $migration['description'],
                'group'       => $migration['group'],
            ];
        }

        // Get any active batches
        $activeBatches = MigrationLog::whereIn('status', ['pending', 'running'])
            ->select('batch_id')
            ->distinct()
            ->pluck('batch_id');

        return response()->json([
            'migrations' => $migrations,
            'groups'     => [
                ['key' => 'post', 'name' => 'Post-import steps'],
            ],
            'activeBatches' => $activeBatches,
        ]);
    }

    /**
     * Start migrations (dispatches jobs to queue).
     */
    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'migrations'   => 'required|array|min:1',
            'migrations.*' => 'string',
        ]);

        $requestedMigrations = $request->input('migrations');
        $batchId = Str::uuid()->toString();

        // Validate all requested migrations exist
        foreach ($requestedMigrations as $key) {
            if (!array_key_exists($key, $this->migrations)) {
                return response()->json([
                    'error' => __('messages.migrations.unknown', ['key' => $key]),
                ], 400);
            }
        }

        // Create log entries and dispatch jobs
        foreach ($requestedMigrations as $index => $key) {
            $migration = $this->migrations[$key];

            // Create log entry
            $log = MigrationLog::create([
                'batch_id'       => $batchId,
                'migration_key'  => $key,
                'migration_name' => $migration['name'],
                'status'         => 'pending',
                'logs'           => [['type' => 'info', 'message' => __('messages.migrations.queued'), 'timestamp' => now()->toIso8601String()]],
            ]);

            // Dispatch job with delay to ensure sequential processing
            $jobClass = $migration['job'];
            $job = new $jobClass($batchId, $log->id);

            // Add delay for sequential processing (each job starts 1 second after previous)
            dispatch($job)->delay(now()->addSeconds($index));
        }

        return response()->json([
            'message' => __('messages.migrations.queued_all'),
            'batchId' => $batchId,
            'count'   => count($requestedMigrations),
        ]);
    }

    /**
     * Get status of a batch.
     */
    public function status(string $batchId): JsonResponse
    {
        $logs = MigrationLog::where('batch_id', $batchId)
            ->orderBy('id')
            ->get();

        if ($logs->isEmpty()) {
            return response()->json(['error' => __('messages.migrations.batch_not_found')], 404);
        }

        $migrations = $logs->map(function ($log) {
            return [
                'key'         => $log->migration_key,
                'name'        => $log->migration_name,
                'status'      => $log->status,
                'total'       => $log->total_items,
                'processed'   => $log->processed_items,
                'errors'      => $log->error_count,
                'percentage'  => $log->getProgressPercentage(),
                'currentItem' => $log->current_item,
                'lastError'   => $log->last_error,
                'startedAt'   => $log->started_at?->toIso8601String(),
                'completedAt' => $log->completed_at?->toIso8601String(),
            ];
        });

        // Calculate overall status
        $pending = $logs->where('status', 'pending')->count();
        $running = $logs->where('status', 'running')->count();
        $completed = $logs->where('status', 'completed')->count();
        $failed = $logs->where('status', 'failed')->count();
        $total = $logs->count();

        $overallStatus = 'pending';
        if ($running > 0) {
            $overallStatus = 'running';
        } elseif ($completed + $failed === $total) {
            $overallStatus = $failed > 0 ? 'completed_with_errors' : 'completed';
        }

        return response()->json([
            'batchId' => $batchId,
            'status'  => $overallStatus,
            'summary' => [
                'pending'   => $pending,
                'running'   => $running,
                'completed' => $completed,
                'failed'    => $failed,
                'total'     => $total,
            ],
            'migrations' => $migrations,
        ]);
    }

    /**
     * Get logs for a specific migration in a batch.
     */
    public function logs(string $batchId, string $migrationKey): JsonResponse
    {
        $log = MigrationLog::where('batch_id', $batchId)
            ->where('migration_key', $migrationKey)
            ->first();

        if (!$log) {
            return response()->json(['error' => __('messages.migrations.log_not_found')], 404);
        }

        return response()->json([
            'migration'  => $migrationKey,
            'name'       => $log->migration_name,
            'status'     => $log->status,
            'logs'       => $log->logs ?? [],
            'total'      => $log->total_items,
            'processed'  => $log->processed_items,
            'errors'     => $log->error_count,
            'percentage' => $log->getProgressPercentage(),
        ]);
    }

    /**
     * Cancel a batch: pending migrations are skipped by their queued jobs,
     * and running ones notice the status change and stop (they poll it
     * between items via BaseMigrationJob::checkForCancellation()).
     */
    public function cancel(string $batchId): JsonResponse
    {
        $updated = MigrationLog::where('batch_id', $batchId)
            ->whereIn('status', ['pending', 'running'])
            ->update([
                'status'       => 'failed',
                'last_error'   => __('messages.migrations.cancelled'),
                'completed_at' => now(),
            ]);

        return response()->json([
            'message'   => __('messages.migrations.cancelled_count', ['count' => $updated]),
            'cancelled' => $updated,
        ]);
    }

    /**
     * Get recent batches.
     */
    public function history(): JsonResponse
    {
        $batches = MigrationLog::selectRaw('
                batch_id,
                MIN(created_at) as started_at,
                MAX(completed_at) as completed_at,
                COUNT(*) as total_migrations,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed
            ')
            ->groupBy('batch_id')
            ->orderByDesc('started_at')
            ->limit(20)
            ->get();

        return response()->json([
            'batches' => $batches->map(function ($batch) {
                return [
                    'batchId'         => $batch->batch_id,
                    'startedAt'       => $batch->started_at,
                    'completedAt'     => $batch->completed_at,
                    'totalMigrations' => $batch->total_migrations,
                    'completed'       => $batch->completed,
                    'failed'          => $batch->failed,
                    'status'          => $batch->failed > 0 ? 'completed_with_errors' :
                        ($batch->completed === $batch->total_migrations ? 'completed' : 'running'),
                ];
            }),
        ]);
    }

    /**
     * Clear old migration logs.
     */
    public function clearHistory(): JsonResponse
    {
        $deleted = MigrationLog::where('created_at', '<', now()->subDays(7))->delete();

        return response()->json([
            'message' => __('messages.migrations.deleted_count', ['count' => $deleted]),
            'deleted' => $deleted,
        ]);
    }

    // ------------------------------------------------------------------
    // Generic migration tool: sources, schema introspection, mappings
    // ------------------------------------------------------------------

    private function sourceRules(bool $creating = true): array
    {
        return [
            'name' => ($creating ? 'required' : 'sometimes').'|string|max:255',
            'driver' => [$creating ? 'required' : 'sometimes', Rule::in(MigrationSource::DRIVERS)],
            'host' => 'nullable|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'database' => ($creating ? 'required' : 'sometimes').'|string|max:1024',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:1024',
            'charset' => 'nullable|string|max:64',
        ];
    }

    public function sources(): JsonResponse
    {
        return response()->json(
            MigrationSource::withCount('mappings')->orderBy('name')->get()
        );
    }

    public function storeSource(Request $request): JsonResponse
    {
        $source = MigrationSource::create($request->validate($this->sourceRules()));

        return response()->json($source, 201);
    }

    public function updateSource(Request $request, MigrationSource $source): JsonResponse
    {
        $data = $request->validate($this->sourceRules(false));

        // An empty password field means "keep the stored one".
        if (($data['password'] ?? '') === '' || $data['password'] === null) {
            unset($data['password']);
        }

        $source->update($data);

        return response()->json($source);
    }

    public function deleteSource(MigrationSource $source): JsonResponse
    {
        $source->delete();

        return response()->json(['message' => 'Source deleted']);
    }

    public function testSource(MigrationSource $source): JsonResponse
    {
        try {
            $tables = $this->tableNames($source);

            return response()->json([
                'ok' => true,
                'tables' => count($tables),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function sourceTables(MigrationSource $source): JsonResponse
    {
        try {
            return response()->json(['tables' => $this->tableNames($source)]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function sourceColumns(MigrationSource $source, string $table): JsonResponse
    {
        try {
            // The table name ends up in identifier position — only accept
            // names that actually exist in the source schema.
            if (!in_array($table, $this->tableNames($source), true)) {
                return response()->json(['error' => 'Unknown table'], 404);
            }

            $connection = $source->connectionName();
            $columns = collect(Schema::connection($connection)->getColumns($table))
                ->map(fn ($column) => [
                    'name' => $column['name'],
                    'type' => $column['type_name'] ?? $column['type'] ?? null,
                ])
                ->values();

            $sample = (array) DB::connection($connection)->table($table)->first();

            return response()->json([
                'columns' => $columns,
                'sample' => $sample,
                'rowCount' => DB::connection($connection)->table($table)->count(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function targets(): JsonResponse
    {
        return response()->json([
            'targets' => collect(MigrationTargets::all())
                ->map(fn ($target, $key) => ['key' => $key] + $target)
                ->values(),
            'transforms' => RowMapper::TRANSFORMS,
        ]);
    }

    private function mappingRules(bool $creating = true): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'migration_source_id' => [$required, 'integer', Rule::exists('migration_sources', 'id')],
            'name' => "{$required}|string|max:255",
            'target' => [$required, Rule::in(array_keys(MigrationTargets::all()))],
            'source_table' => "{$required}|string|max:255",
            'field_map' => "{$required}|array",
            'field_map.*' => 'array',
            'field_map.*.source' => 'nullable|string|max:255',
            'field_map.*.transform' => ['nullable', Rule::in(RowMapper::TRANSFORMS)],
            'field_map.*.format' => 'nullable|string|max:64',
            'field_map.*.template' => 'nullable|string|max:1024',
            'options' => 'nullable|array',
            'options.locale' => 'nullable|string|max:10|regex:/^[a-z]{2}(-[A-Za-z]{2,4})?$/',
            'options.joins' => 'nullable|array',
            'options.joins.*' => 'array',
            'options.joins.*.table' => 'required|string|max:255|regex:/^[A-Za-z0-9_]+$/',
            'options.joins.*.type' => ['nullable', Rule::in(SourceQuery::JOIN_TYPES)],
            'options.joins.*.first' => ['required', 'string', 'max:255', 'regex:'.SourceQuery::IDENTIFIER_PATTERN],
            'options.joins.*.operator' => ['nullable', Rule::in(SourceQuery::OPERATORS)],
            'options.joins.*.second' => ['required', 'string', 'max:255', 'regex:'.SourceQuery::IDENTIFIER_PATTERN],
            'options.wheres' => 'nullable|array',
            'options.wheres.*' => 'array',
            'options.wheres.*.column' => ['required', 'string', 'max:255', 'regex:'.SourceQuery::IDENTIFIER_PATTERN],
            'options.wheres.*.operator' => ['nullable', Rule::in(SourceQuery::OPERATORS)],
            'options.wheres.*.value' => 'nullable|string|max:1024',
        ];
    }

    public function mappings(): JsonResponse
    {
        return response()->json(
            MigrationMapping::with('source:id,name,driver')->orderBy('name')->get()
        );
    }

    public function storeMapping(Request $request): JsonResponse
    {
        $mapping = MigrationMapping::create($request->validate($this->mappingRules()));

        return response()->json($mapping->load('source:id,name,driver'), 201);
    }

    public function updateMapping(Request $request, MigrationMapping $mapping): JsonResponse
    {
        $mapping->update($request->validate($this->mappingRules(false)));

        return response()->json($mapping->load('source:id,name,driver'));
    }

    public function deleteMapping(MigrationMapping $mapping): JsonResponse
    {
        $mapping->delete();

        return response()->json(['message' => 'Mapping deleted']);
    }

    /**
     * Export all mappings as portable JSON (sources referenced by name).
     */
    public function exportMappings(): JsonResponse
    {
        $mappings = MigrationMapping::with('source:id,name')->orderBy('name')->get();

        return response()->json([
            'mappings' => $mappings->map(fn ($mapping) => array_filter([
                'source' => $mapping->source?->name,
                'name' => $mapping->name,
                'target' => $mapping->target,
                'source_table' => $mapping->source_table,
                'field_map' => $mapping->field_map,
                'options' => $mapping->options,
            ], fn ($value) => $value !== null)),
        ]);
    }

    /**
     * Import mappings from the JSON produced by exportMappings (or written
     * by hand). Sources are referenced by name; a mapping whose name already
     * exists is updated, so imports are idempotent.
     */
    public function importMappings(Request $request): JsonResponse
    {
        $request->validate([
            'mappings' => 'required|array|min:1',
            'mappings.*' => 'array',
            'mappings.*.source' => 'nullable|string|max:255',
        ]);

        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($request->input('mappings') as $index => $item) {
            $label = is_array($item) ? ($item['name'] ?? "#{$index}") : "#{$index}";

            // Resolve the source by name unless an id is given directly.
            if (empty($item['migration_source_id']) && !empty($item['source'])) {
                $source = MigrationSource::where('name', $item['source'])->first();
                if (!$source) {
                    $errors[] = "{$label}: unknown source \"{$item['source']}\" — create it on the Sources tab first";
                    continue;
                }
                $item['migration_source_id'] = $source->id;
            }
            unset($item['source']);

            $validator = Validator::make($item, $this->mappingRules());
            if ($validator->fails()) {
                $errors[] = "{$label}: " . implode(' ', $validator->errors()->all());
                continue;
            }
            $data = $validator->validated();

            $existing = MigrationMapping::where('name', $data['name'])->first();
            if ($existing) {
                $existing->update($data);
                $updated++;
            } else {
                MigrationMapping::create($data);
                $created++;
            }
        }

        $failedCompletely = $errors && $created === 0 && $updated === 0;

        return response()->json([
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ], $failedCompletely ? 422 : 200);
    }

    /**
     * Dry run: map the first rows of the source table without writing
     * anything, and report validation problems per row.
     */
    public function previewMapping(MigrationMapping $mapping): JsonResponse
    {
        try {
            $available = $this->tableNames($mapping->source);
            foreach (SourceQuery::tables($mapping) as $table) {
                if (!in_array($table, $available, true)) {
                    return response()->json(['error' => "Source table \"{$table}\" no longer exists"], 422);
                }
            }

            $mapper = new RowMapper($mapping->field_map);
            $query = SourceQuery::build($mapping);

            $rows = (clone $query)->limit(10)->get();
            $preview = $rows->map(function ($row) use ($mapper, $mapping) {
                $mapped = $mapper->map((array) $row);

                return [
                    'mapped' => $mapped,
                    'errors' => MigrationTargets::validateRow($mapping->target, $mapped),
                ];
            });

            return response()->json([
                'total' => (clone $query)->count(),
                'rows' => $preview,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Queue an import run for a mapping; reuses the batch status/log
     * endpoints the dashboard already polls.
     */
    public function runMapping(MigrationMapping $mapping): JsonResponse
    {
        $batchId = Str::uuid()->toString();

        $log = MigrationLog::create([
            'batch_id' => $batchId,
            'migration_key' => GenericImportJob::migrationKeyFor($mapping->id),
            'migration_name' => $mapping->name,
            'status' => 'pending',
            'logs' => [['type' => 'info', 'message' => __('messages.migrations.queued'), 'timestamp' => now()->toIso8601String()]],
        ]);

        dispatch(new GenericImportJob($batchId, $log->id, $mapping->id));

        return response()->json([
            'message' => __('messages.migrations.queued_all'),
            'batchId' => $batchId,
            'count' => 1,
        ]);
    }

    // ------------------------------------------------------------------
    // Legacy users: imported records remember their old owner's username
    // (migration_attributions); once the person registers, an admin
    // assigns the legacy identity and the content moves to their account.
    // ------------------------------------------------------------------

    public function legacyUsers(): JsonResponse
    {
        // Identity is (legacy system, username) — "Vimes" in the wiki and
        // "Vimes" in the forum may be different people.
        $rows = MigrationAttribution::query()
            ->selectRaw('legacy_source, legacy_username, attributable_type, COUNT(*) as items, MAX(assigned_user_id) as assigned_user_id, MAX(assigned_at) as assigned_at')
            ->groupBy('legacy_source', 'legacy_username', 'attributable_type')
            ->get()
            ->groupBy(fn ($row) => $row->legacy_source . '|' . $row->legacy_username);

        $assignedUsers = User::whereIn(
            'id',
            $rows->flatten()->pluck('assigned_user_id')->filter()->unique()
        )->get(['id', 'username'])->keyBy('id');

        // Open claim tickets for legacy accounts.
        $claims = Ticket::whereHas('ticketType', fn ($q) => $q->where('slug', 'legacy-account-claim'))
            ->whereIn('status', ['open', 'in_progress', 'pending'])
            ->with('creator:id,username,email')
            ->get();

        // Directory entries imported via the "Legacy Users" target: full
        // roster with e-mails, keyed like the attribution groups.
        $directory = MigrationLegacyUser::with('assignedUser:id,username')->get()
            ->keyBy(fn ($entry) => $entry->legacy_source . '|' . $entry->username);

        // Registered users matching a directory e-mail → suggestions.
        $registeredByEmail = User::whereIn(
            'email',
            $directory->pluck('email')->filter()->unique()
        )->get(['id', 'username', 'email'])->keyBy(fn ($user) => mb_strtolower($user->email));

        $legacyUsers = $rows->map(function ($types) use ($assignedUsers, $claims, $directory, $registeredByEmail) {
            $legacySource = $types->first()->legacy_source;
            $legacyUsername = $types->first()->legacy_username;
            $assignedId = $types->pluck('assigned_user_id')->filter()->first();
            $entry = $directory->get($legacySource . '|' . $legacyUsername);
            $suggested = $entry?->email ? $registeredByEmail->get(mb_strtolower($entry->email)) : null;
            // A claim matches when its username matches and it either names
            // this source or none (claims cover all systems by default).
            $claim = $claims->first(function ($ticket) use ($legacySource, $legacyUsername) {
                $claimSource = $ticket->metadata['legacy_source'] ?? null;

                return mb_strtolower($ticket->metadata['legacy_username'] ?? '') === mb_strtolower($legacyUsername)
                    && ($claimSource === null || $claimSource === $legacySource);
            });

            return [
                'legacy_source' => $legacySource,
                'legacy_username' => $legacyUsername,
                'email' => $entry?->email,
                'items' => $types->sum('items'),
                'types' => $types->mapWithKeys(fn ($row) => [class_basename($row->attributable_type) => (int) $row->items]),
                'assigned_user' => $assignedId ? $assignedUsers->get($assignedId)?->only(['id', 'username']) : ($entry?->assignedUser?->only(['id', 'username'])),
                'assigned_at' => $types->pluck('assigned_at')->filter()->first(),
                'suggested_user' => $suggested?->only(['id', 'username']),
                'claim' => $claim ? [
                    'ticket_id' => $claim->id,
                    'user' => $claim->creator?->only(['id', 'username']),
                    // Strong signal: the claimant's registered e-mail matches
                    // the e-mail of the legacy account (from the directory).
                    'email_verified' => (bool) ($entry?->email && $claim->creator
                        && mb_strtolower($claim->creator->email) === mb_strtolower($entry->email)),
                ] : null,
            ];
        });

        // Directory entries without any imported content still belong on
        // the roster (0 items).
        $covered = $legacyUsers->map(fn ($row) => $row['legacy_source'] . '|' . $row['legacy_username']);
        // toBase(): Eloquent\Collection::except() filters by model primary
        // keys, not the (source|username) collection keys used here.
        $directoryOnly = $directory->toBase()->except($covered->all())->map(function ($entry) use ($registeredByEmail, $claims) {
            $suggested = $entry->email ? $registeredByEmail->get(mb_strtolower($entry->email)) : null;
            $claim = $claims->first(function ($ticket) use ($entry) {
                $claimSource = $ticket->metadata['legacy_source'] ?? null;

                return mb_strtolower($ticket->metadata['legacy_username'] ?? '') === mb_strtolower($entry->username)
                    && ($claimSource === null || $claimSource === $entry->legacy_source);
            });

            return [
                'legacy_source' => $entry->legacy_source,
                'legacy_username' => $entry->username,
                'email' => $entry->email,
                'items' => 0,
                'types' => (object) [],
                'assigned_user' => $entry->assignedUser?->only(['id', 'username']),
                'assigned_at' => $entry->assigned_user_id ? $entry->updated_at : null,
                'suggested_user' => $suggested?->only(['id', 'username']),
                'claim' => $claim ? [
                    'ticket_id' => $claim->id,
                    'user' => $claim->creator?->only(['id', 'username']),
                    'email_verified' => (bool) ($entry->email && $claim->creator
                        && mb_strtolower($claim->creator->email) === mb_strtolower($entry->email)),
                ] : null,
            ];
        })->values();

        $legacyUsers = $legacyUsers->concat($directoryOnly)->sortBy([
            fn ($a, $b) => strnatcasecmp($a['legacy_username'], $b['legacy_username'])
                ?: strnatcasecmp($a['legacy_source'], $b['legacy_source']),
        ])->values();

        return response()->json(['legacyUsers' => $legacyUsers]);
    }

    /**
     * Assign a legacy username to a registered user: every attributed
     * record's ownership moves to that user, and an open claim ticket for
     * it is resolved.
     */
    public function assignLegacyUser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'legacy_username' => 'required|string|max:255',
            'legacy_source' => 'required|string|max:255',
            'user' => 'required|string|max:255',
        ]);

        $user = User::where('username', $data['user'])
            ->orWhere('email', $data['user'])
            ->first();
        if (!$user) {
            return response()->json(['message' => __('messages.migrations.user_not_found', ['user' => $data['user']])], 422);
        }

        // Scoped to one legacy system: assigning "Vimes" from the wiki must
        // not touch a "Vimes" imported from another system.
        $attributions = MigrationAttribution::where('legacy_username', $data['legacy_username'])
            ->where('legacy_source', $data['legacy_source'])
            ->get();
        $directoryEntry = MigrationLegacyUser::where('legacy_source', $data['legacy_source'])
            ->where('username', $data['legacy_username'])
            ->first();
        if ($attributions->isEmpty() && !$directoryEntry) {
            return response()->json(['message' => __('messages.migrations.legacy_user_not_found', ['name' => $data['legacy_username']])], 404);
        }

        $reassigned = [];

        DB::transaction(function () use ($attributions, $directoryEntry, $user, $data, &$reassigned) {
            foreach ($attributions->groupBy('attributable_type') as $type => $group) {
                $ids = $group->pluck('attributable_id')->all();

                if ($type === \Spatie\MediaLibrary\MediaCollections\Models\Media::class) {
                    // Media has no user_id column — record the owner as a
                    // custom property instead.
                    foreach ($type::whereIn('id', $ids)->cursor() as $media) {
                        $media->setCustomProperty('user_id', $user->id);
                        $media->save();
                    }
                } else {
                    $model = new $type();
                    $type::whereIn($model->getKeyName(), $ids)->update(['user_id' => $user->id]);
                }

                $reassigned[class_basename($type)] = count($ids);
            }

            MigrationAttribution::where('legacy_username', $data['legacy_username'])
                ->where('legacy_source', $data['legacy_source'])
                ->update(['assigned_user_id' => $user->id, 'assigned_at' => now()]);

            $directoryEntry?->update(['assigned_user_id' => $user->id]);

            // Resolve a matching claim ticket once nothing remains
            // unassigned for the claimed username.
            $stillUnassigned = MigrationAttribution::where('legacy_username', $data['legacy_username'])
                ->whereNull('assigned_user_id')
                ->exists();
            if (!$stillUnassigned) {
                Ticket::whereHas('ticketType', fn ($q) => $q->where('slug', 'legacy-account-claim'))
                    ->whereIn('status', ['open', 'in_progress', 'pending'])
                    ->get()
                    ->filter(fn ($ticket) => mb_strtolower($ticket->metadata['legacy_username'] ?? '') === mb_strtolower($data['legacy_username']))
                    ->each(fn ($ticket) => $ticket->update(['status' => 'resolved', 'resolved_at' => now()]));
            }
        });

        return response()->json([
            'message' => __('messages.migrations.legacy_assigned', ['name' => $data['legacy_username'], 'user' => $user->username]),
            'reassigned' => $reassigned,
        ]);
    }

    /**
     * @return string[]
     */
    private function tableNames(MigrationSource $source): array
    {
        $connection = $source->connectionName();

        return collect(Schema::connection($connection)->getTables())
            ->filter(function ($table) use ($source) {
                // On MySQL/MariaDB getTables() lists every schema the DB user
                // can see — including this app's own tables. Only tables of
                // the configured source database are usable (they are queried
                // unqualified), so hide the rest.
                if (!in_array($source->driver, ['mysql', 'mariadb'], true)) {
                    return true;
                }

                $schema = is_array($table) ? ($table['schema'] ?? null) : ($table->schema ?? null);

                return $schema === null || $schema === $source->database;
            })
            ->map(fn ($table) => is_array($table) ? $table['name'] : $table->name)
            ->values()
            ->all();
    }
}
