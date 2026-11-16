<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppVersionTest extends TestCase
{
    /**
     * The point of reading package.json rather than hardcoding the version is
     * that there is one place to bump. This fails if that wiring comes loose.
     */
    public function test_the_configured_version_matches_package_json()
    {
        $manifest = json_decode((string) file_get_contents(base_path('package.json')), true);

        $this->assertIsArray($manifest);
        $this->assertArrayHasKey('version', $manifest);
        $this->assertSame($manifest['version'], config('app.version'));
    }

    /**
     * The meta tag is what makes the running version readable without parsing
     * JavaScript, so a deploy can be verified with a single plain request.
     */
    public function test_the_spa_view_exposes_the_version_as_a_meta_tag()
    {
        config(['app.version' => '9.9.9-test']);

        $this->get('/')
            ->assertStatus(200)
            ->assertSee('<meta name="app-version" content="9.9.9-test">', false);
    }

    /**
     * The frontend reads it off window.App.
     */
    public function test_the_spa_view_exposes_the_version_to_the_frontend()
    {
        config(['app.version' => '9.9.9-test']);

        $this->get('/')
            ->assertStatus(200)
            ->assertSee('"version":"9.9.9-test"', false);
    }

    /**
     * An unreadable or versionless package.json leaves the config null, and
     * the view should then say nothing rather than render an empty tag.
     */
    public function test_the_version_meta_tag_is_omitted_when_no_version_is_configured()
    {
        config(['app.version' => null]);

        $this->get('/')
            ->assertStatus(200)
            ->assertDontSee('name="app-version"', false);
    }
}
