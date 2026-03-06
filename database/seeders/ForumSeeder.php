<?php

namespace Database\Seeders;

use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use Illuminate\Database\Seeder;

class ForumSeeder extends Seeder
{
    /**
     * Seed default forum categories as taxonomy entries.
     */
    public function run(): void
    {
        $categories = [
            [
                'title' => 'General Discussion',
                'description' => 'Chat about anything and everything with the community.',
                'sort' => 1,
            ],
            [
                'title' => 'Announcements',
                'description' => 'Official news and updates from the team.',
                'sort' => 2,
                'is_locked' => true,
            ],
            [
                'title' => 'Help & Support',
                'description' => 'Need help? Ask your questions here.',
                'sort' => 3,
                'children' => [
                    [
                        'title' => 'Technical Issues',
                        'description' => 'Report bugs and technical problems.',
                        'sort' => 1,
                    ],
                    [
                        'title' => 'Getting Started',
                        'description' => 'New here? Start with these guides and tips.',
                        'sort' => 2,
                    ],
                ],
            ],
            [
                'title' => 'Feedback & Suggestions',
                'description' => 'Share your ideas and suggestions for improvements.',
                'sort' => 4,
            ],
            [
                'title' => 'Off-Topic',
                'description' => 'Anything that doesn\'t fit in the other categories.',
                'sort' => 5,
            ],
        ];

        foreach ($categories as $data) {
            $taxonomy = $this->createCategory($data);

            if (!empty($data['children'])) {
                foreach ($data['children'] as $childData) {
                    $this->createCategory($childData, $taxonomy->id);
                }
            }
        }
    }

    private function createCategory(array $data, ?int $parentId = null): Taxonomy
    {
        $term = Term::firstOrCreate(['title' => $data['title']]);

        $taxonomy = Taxonomy::create([
            'term_id'    => $term->id,
            'taxonomy'   => 'forum_cat',
            'parent_id'  => $parentId,
            'sort'       => $data['sort'],
            'visible'    => true,
            'searchable' => true,
            'properties' => [
                'is_locked'  => $data['is_locked'] ?? false,
                'is_private' => $data['is_private'] ?? false,
            ],
        ]);

        if (!empty($data['description'])) {
            $taxonomy->description = $data['description'];
            $taxonomy->save();
        }

        return $taxonomy;
    }
}
