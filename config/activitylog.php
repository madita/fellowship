<?php

use Spatie\Activitylog\Models\Activity;

return [

    /*
     * Writing can be switched off entirely, which is mostly useful when
     * importing or backfilling: see the migration jobs.
     */
    'enabled' => env('ACTIVITY_LOGGER_ENABLED', true),

    /*
     * How long an entry is kept. `activitylog:clean` runs daily from the
     * scheduler (app/Console/Kernel.php) — before that nothing pruned the
     * table at all.
     */
    'delete_records_older_than_days' => 365,

    /*
     * Entries are always written with one of the names in
     * App\Support\Activities, so this default should not be reached.
     */
    'default_log_name' => 'default',

    'default_auth_driver' => null,

    /*
     * A subject deleted since is not resurrected to describe itself. The
     * activity feed drops rows whose subject has gone, so an entry does
     * not outlive what it is about.
     */
    'subject_returns_soft_deleted_models' => false,

    'activity_model' => Activity::class,

    'table_name' => env('ACTIVITY_LOGGER_TABLE_NAME', 'activity_log'),

    'database_connection' => env('ACTIVITY_LOGGER_DB_CONNECTION'),
];
