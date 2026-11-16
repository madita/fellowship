<?php

namespace App\Jobs\Migrations;

use Exception;

/**
 * Thrown from inside a running migration when the user cancelled its batch.
 * Row-level catch blocks must rethrow it so the job actually stops.
 */
class MigrationCancelledException extends Exception
{
    public function __construct()
    {
        parent::__construct('Migration cancelled by user');
    }
}
