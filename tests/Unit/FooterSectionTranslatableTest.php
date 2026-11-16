<?php

namespace Tests\Unit;

use App\Models\FooterSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FooterSectionTranslatableTest extends TestCase
{
    use RefreshDatabase;

    public function test_footer_section_stores_title_in_translations(): void
    {
        $section = FooterSection::create([
            'title' => 'Main Footer',
            'layout' => '3-col',
            'enabled' => true,
            'order' => 1,
        ]);

        $this->assertNotNull($section->id);
        $this->assertEquals('Main Footer', $section->title);
        $this->assertDatabaseHas('section_translations', [
            'section_id' => $section->id,
            'title' => 'Main Footer',
        ]);
    }

    public function test_footer_section_shares_translations_with_section(): void
    {
        $section = FooterSection::create([
            'title' => 'Footer Test',
            'layout' => '2-col',
            'enabled' => true,
            'order' => 1,
        ]);

        // Verify the translation uses the section_translations table, not footer_section_translations
        $this->assertDatabaseHas('section_translations', [
            'section_id' => $section->id,
            'title' => 'Footer Test',
        ]);
    }

    public function test_footer_section_scoped_to_footer_location(): void
    {
        FooterSection::create([
            'title' => 'Footer Section',
            'layout' => '3-col',
            'enabled' => true,
            'order' => 1,
        ]);

        // FooterSection defaults location to 'footer'
        $this->assertDatabaseHas('sections', [
            'location' => 'footer',
        ]);
    }
}
