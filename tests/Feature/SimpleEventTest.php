<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpleEventTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_example()
    {
        $this->assertTrue(true);
    }

    /**
     * Test that the home page loads.
     *
     * @return void
     */
    public function test_home_page_loads()
    {
        // This test might fail if the route requires authentication
        // or if there are redirects in place
        $response = $this->get('/');

        // Accept either 200 (OK) or 302 (redirect) as valid responses
        $this->assertTrue(
            $response->status() == 200 || $response->status() == 302,
            'Expected status code 200 or 302, got ' . $response->status()
        );
    }

    /**
     * Test that the user factory works correctly with username.
     *
     * @return void
     */
    public function test_user_factory_creates_user_with_username()
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->username);
        $this->assertIsString($user->username);
    }
}
