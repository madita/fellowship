<?php

namespace Tests\Feature;

use App\Models\Event\Event;
use App\Models\Event\EventGuest;
use App\Models\Event\EventProfile;
use App\Models\Event\EventType;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\User;
use App\Support\TaxonomyHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Taxonomy answers in an event profile are stored as terms. Term titles are
 * translated (term_translations), so they must never be queried on `terms`.
 */
class EventProfileTaxonomyAnswerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $profile = EventProfile::create([
            'name'    => 'Potluck',
            'options' => json_encode([
                'form' => [
                    ['name' => 'food', 'type' => 'taxonomy', 'label' => 'What do you bring?', 'options' => 'food'],
                ],
            ]),
        ]);

        $type = EventType::create([
            'event_profile_id' => $profile->id,
            'name'             => 'Potluck',
            'color'            => '#123456',
            'options'          => json_encode([
                'answers'     => [
                    ['key' => 'going', 'value' => 'Yes'],
                    ['key' => 'notgoing', 'value' => 'No'],
                ],
                'max'         => ['going' => '10'],
                'guest'       => ['rsp'],
                'permissions' => ['edit', 'view'],
                'profile'     => ['going'],
            ]),
        ]);

        $this->event = Event::create([
            'title'         => 'Summer party',
            'slug'          => 'summer-party',
            'event_type_id' => $type->id,
            'startDate'     => now()->addWeek()->format('Y-m-d'),
        ]);
    }

    private function link(Term $term, string $taxonomy): void
    {
        $model           = new Taxonomy;
        $model->taxonomy = $taxonomy;
        $model->term_id  = $term->id;
        $model->save();
    }

    private function answer(array $food)
    {
        return $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/events/{$this->event->id}/answer", [
                'answer' => 'going',
                'data'   => json_encode(['food' => $food]),
            ]);
    }

    public function test_taxonomy_answers_resolve_translated_term_titles(): void
    {
        $salad = Term::firstOrCreateByTitle('Kartoffelsalat');
        $this->link($salad, 'food');

        $this->answer(['Kartoffelsalat', 'Nudelsalat'])
            ->assertOk()
            ->assertJson(['message' => 'joining_going']);

        $profile = json_decode(EventGuest::where('user_id', $this->user->id)->first()->profile, true);
        $this->assertSame(['Kartoffelsalat', 'Nudelsalat'], array_column($profile['food'], 'title'));
        $this->assertSame($salad->id, $profile['food'][0]['id']);

        // The existing term was reused, the new dish became a term in the field's taxonomy
        $this->assertSame(1, Term::whereTranslation('title', 'Kartoffelsalat')->count());
        $noodles = Term::whereTranslation('title', 'Nudelsalat')->first();
        $this->assertNotNull($noodles);
        $this->assertTrue(Taxonomy::where('taxonomy', 'food')->where('term_id', $noodles->id)->exists());
    }

    public function test_an_existing_term_from_another_taxonomy_is_linked_to_the_answer_taxonomy(): void
    {
        $salad = Term::firstOrCreateByTitle('Kartoffelsalat');
        $this->link($salad, 'recipes');

        $this->answer(['Kartoffelsalat'])->assertOk();

        $this->assertSame(1, Term::whereTranslation('title', 'Kartoffelsalat')->count());
        $this->assertTrue(Taxonomy::where('taxonomy', 'food')->where('term_id', $salad->id)->exists());
    }

    public function test_taxonomy_helper_creates_each_translated_term_once(): void
    {
        TaxonomyHelper::createTaxables('Grillgemüse', 'food');
        TaxonomyHelper::createTaxables('Grillgemüse', 'food');

        $this->assertSame(1, Term::whereTranslation('title', 'Grillgemüse')->count());
        $term = Term::whereTranslation('title', 'Grillgemüse')->first();
        $this->assertSame(1, Taxonomy::where('taxonomy', 'food')->where('term_id', $term->id)->count());
    }
}
