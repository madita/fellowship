<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Whether pages show the trail above their title, and where it starts.
 *
 * Both are read by Breadcrumbs.vue on every page, including pages a guest
 * can open, so they have to come down with the public settings — a key
 * left out of that list is simply never seen by the frontend, which then
 * falls back to its own defaults and the admin's choice does nothing.
 */
class BreadcrumbSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_told_whether_trails_are_shown(): void
    {
        Setting::set('breadcrumbs_enabled', false, 'boolean');
        Setting::set('breadcrumbs_root', 'none');

        $settings = $this->getJson('/api/settings/public')->assertOk()->json('settings');

        $this->assertFalse($settings['breadcrumbs_enabled']);
        $this->assertSame('none', $settings['breadcrumbs_root']);
    }

    /**
     * Stored as the string a form posts, read back as a boolean: the
     * frontend getter treats '0' as on if it arrives as a string.
     */
    public function test_the_switch_comes_back_as_a_boolean(): void
    {
        Setting::set('breadcrumbs_enabled', '1', 'boolean');

        $this->assertTrue(
            $this->getJson('/api/settings/public')->assertOk()->json('settings.breadcrumbs_enabled')
        );
    }

    public function test_an_install_that_never_set_them_is_sent_nothing(): void
    {
        $settings = $this->getJson('/api/settings/public')->assertOk()->json('settings');

        // Which is what the frontend defaults are for: trails on, starting
        // at the dashboard for a member and the landing page for a guest
        $this->assertArrayNotHasKey('breadcrumbs_enabled', $settings);
        $this->assertArrayNotHasKey('breadcrumbs_root', $settings);
    }
}
