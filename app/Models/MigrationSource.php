<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class MigrationSource extends Model
{
    protected $fillable = [
        'name',
        'driver',
        'host',
        'port',
        'database',
        'username',
        'password',
        'charset',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'password' => 'encrypted',
        'port' => 'integer',
    ];

    public const DRIVERS = ['mysql', 'mariadb', 'pgsql', 'sqlite', 'sqlsrv'];

    public function mappings(): HasMany
    {
        return $this->hasMany(MigrationMapping::class);
    }

    /**
     * Register this source as a runtime database connection and return its
     * connection name. Purges any previous instance so edited credentials
     * take effect immediately.
     */
    public function connectionName(): string
    {
        $name = "migration_source_{$this->id}";

        config(["database.connections.{$name}" => $this->connectionConfig()]);
        DB::purge($name);

        return $name;
    }

    /**
     * @return array<string,mixed>
     */
    public function connectionConfig(): array
    {
        if ($this->driver === 'sqlite') {
            return [
                'driver' => 'sqlite',
                'database' => $this->database,
                'prefix' => '',
                'foreign_key_constraints' => false,
            ];
        }

        return [
            'driver' => $this->driver,
            'host' => $this->host,
            'port' => $this->port ?: $this->defaultPort(),
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->password,
            'charset' => $this->charset ?: 'utf8mb4',
            'prefix' => '',
        ];
    }

    private function defaultPort(): int
    {
        return match ($this->driver) {
            'pgsql' => 5432,
            'sqlsrv' => 1433,
            default => 3306,
        };
    }
}
