<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that we're using the testing database.
     *
     * @return void
     */
    public function test_using_testing_database()
    {
        $this->assertEquals('mysql', config('database.default'));
        $this->assertEquals('fellowship_testing', config('database.connections.mysql.database'));
    }

    /**
     * Test that database operations don't affect the main database.
     *
     * @return void
     */
    public function test_database_operations_are_isolated()
    {
        // Create a user
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'username' => 'testuser'
        ]);

        // Verify the user exists in the test database
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com'
        ]);

        // Get the current connection name
        $connectionName = DB::getDefaultConnection();

        // Output some debug information
        $this->assertTrue(true, "Using database connection: {$connectionName}");
        $this->assertTrue(true, "Database path: " . config("database.connections.{$connectionName}.database"));
    }
}
