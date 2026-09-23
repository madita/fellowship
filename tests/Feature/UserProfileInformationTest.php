<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The rest of what a member tells us about themselves: that it saves at
 * all, and that only what they choose to share leaves the site.
 */
class UserProfileInformationTest extends TestCase
{
    use RefreshDatabase;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = User::factory()->create(['username' => 'frodo']);
    }

    private function fill(array $overrides = []): array
    {
        $payload = array_merge([
            'bio'           => 'Gardener, ring-bearer.',
            'pronouns'      => 'he/him',
            'city'          => 'Hobbiton',
            'country'       => 'The Shire',
            'website'       => 'https://example.test',
            'birthday'      => '1990-09-22',
            'phone'         => '+49 123 456',
            'address_line1' => 'Bag End',
            'postcode'      => '12345',
            'state'         => 'Westfarthing',
        ], $overrides);

        $this->actingAs($this->member, 'sanctum')
            ->patchJson('/api/account/information', $payload)
            ->assertOk();

        return $payload;
    }

    public function test_a_member_can_save_their_details(): void
    {
        $this->fill();

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $this->member->id,
            'city'    => 'Hobbiton',
            'phone'   => '+49 123 456',
        ]);
    }

    public function test_saving_again_edits_rather_than_adds(): void
    {
        $this->fill();
        $this->fill(['city' => 'Bree']);

        $this->assertDatabaseCount('user_profiles', 1);
        $this->assertSame('Bree', $this->member->fresh()->profile->city);
    }

    public function test_a_member_reads_back_everything_including_what_they_keep_private(): void
    {
        $this->fill();

        $data = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/account/information')
            ->assertOk()
            ->json('data');

        $this->assertSame('Bag End', $data['address_line1']);
        $this->assertSame('+49 123 456', $data['phone']);
        // And which of the shareable fields are shown
        $this->assertTrue($data['visibility']['bio']);
        $this->assertFalse($data['visibility']['birthday']);
    }

    public function test_details_are_optional(): void
    {
        $this->actingAs($this->member, 'sanctum')
            ->patchJson('/api/account/information', [])
            ->assertOk();

        $this->assertDatabaseHas('user_profiles', ['user_id' => $this->member->id]);
    }

    public function test_a_website_has_to_look_like_one(): void
    {
        $this->actingAs($this->member, 'sanctum')
            ->patchJson('/api/account/information', ['website' => 'not a website'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['website']);
    }

    public function test_details_are_nobody_elses_business_to_edit(): void
    {
        $this->patchJson('/api/account/information', ['city' => 'Bree'])->assertStatus(401);
    }

    /**
     * The point of the split: the private half never leaves the site.
     */
    public function test_a_members_page_never_shows_their_address_or_phone(): void
    {
        $this->fill();

        $body = $this->getJson('/api/members/frodo')->assertOk();

        $content = $body->getContent();
        $this->assertStringNotContainsString('Bag End', $content);
        $this->assertStringNotContainsString('+49 123 456', $content);
        $this->assertStringNotContainsString('12345', $content);
        $this->assertStringNotContainsString('Westfarthing', $content);
    }

    public function test_a_members_page_shows_what_they_share(): void
    {
        $this->fill();

        $about = $this->getJson('/api/members/frodo')->assertOk()->json('data.about');

        $this->assertSame('Gardener, ring-bearer.', $about['bio']);
        $this->assertSame('Hobbiton', $about['city']);
        $this->assertSame('https://example.test', $about['website']);
    }

    public function test_a_birthday_stays_in_unless_the_member_says_otherwise(): void
    {
        $this->fill();

        $about = $this->getJson('/api/members/frodo')->assertOk()->json('data.about');
        $this->assertArrayNotHasKey('birthday', $about);

        $this->fill(['visibility' => ['birthday' => true]]);

        $about = $this->getJson('/api/members/frodo')->assertOk()->json('data.about');
        $this->assertSame('1990-09-22', $about['birthday']);
    }

    public function test_a_member_can_take_a_shared_field_back(): void
    {
        $this->fill(['visibility' => ['city' => false, 'country' => false]]);

        $about = $this->getJson('/api/members/frodo')->assertOk()->json('data.about');

        $this->assertArrayNotHasKey('city', $about);
        $this->assertArrayNotHasKey('country', $about);
        // What they still share is untouched
        $this->assertSame('Gardener, ring-bearer.', $about['bio']);
    }

    public function test_a_choice_cannot_be_made_for_a_field_that_is_never_shared(): void
    {
        $this->fill(['visibility' => ['phone' => true, 'address_line1' => true]]);

        $about = $this->getJson('/api/members/frodo')->assertOk()->json('data.about');

        $this->assertArrayNotHasKey('phone', $about);
        $this->assertArrayNotHasKey('address_line1', $about);

        // And the nonsense is not remembered either
        $visibility = $this->member->fresh()->profile->visibility;
        $this->assertArrayNotHasKey('phone', $visibility);
    }

    public function test_a_member_who_has_filled_in_nothing_still_has_a_page(): void
    {
        $this->getJson('/api/members/frodo')
            ->assertOk()
            ->assertJsonPath('data.about', []);
    }

    public function test_every_shareable_field_is_one_the_model_knows(): void
    {
        // The public shape is built from this list, so a field added to the
        // table without being listed stays private by default
        foreach (array_keys(UserProfile::SHAREABLE) as $field) {
            $this->assertNotContains($field, UserProfile::PRIVATE_FIELDS);
        }
    }
}
