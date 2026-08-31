<?php

namespace Tests\Unit;

use App\Services\Migration\RowMapper;
use PHPUnit\Framework\TestCase;

class RowMapperTest extends TestCase
{
    public function test_maps_plain_columns(): void
    {
        $mapper = new RowMapper(['title' => ['source' => 'location']]);

        $this->assertSame(['title' => 'Town Hall'], $mapper->map(['location' => 'Town Hall']));
    }

    public function test_date_time_and_datetime_transforms_with_formats(): void
    {
        $mapper = new RowMapper([
            'startDate' => ['source' => 'starttag', 'transform' => 'date', 'format' => 'Ymd'],
            'startTime' => ['source' => 'startzeit', 'transform' => 'time', 'format' => 'Hi'],
            'created_at' => ['source' => 'entry', 'transform' => 'datetime', 'format' => 'Y-m-d H:i'],
        ]);

        $mapped = $mapper->map([
            'starttag' => '20240315',
            'startzeit' => '1830',
            'entry' => '2024-03-01 09:15',
        ]);

        $this->assertSame('2024-03-15', $mapped['startDate']);
        $this->assertSame('18:30:00', $mapped['startTime']);
        $this->assertSame('2024-03-01 09:15:00', $mapped['created_at']);
    }

    public function test_unparseable_dates_become_null_and_fall_back_to_default(): void
    {
        $mapper = new RowMapper([
            'startDate' => ['source' => 'starttag', 'transform' => 'date', 'format' => 'Ymd', 'default' => '2000-01-01'],
        ]);

        $this->assertSame(['startDate' => '2000-01-01'], $mapper->map(['starttag' => 'garbage']));
    }

    public function test_scalar_transforms(): void
    {
        $mapper = new RowMapper([
            'a' => ['source' => 'a', 'transform' => 'int'],
            'b' => ['source' => 'b', 'transform' => 'float'],
            'c' => ['source' => 'c', 'transform' => 'bool'],
            'd' => ['source' => 'd', 'transform' => 'html_decode'],
            'e' => ['source' => 'e', 'transform' => 'trim'],
            'f' => ['source' => 'f', 'transform' => 'json'],
        ]);

        $mapped = $mapper->map([
            'a' => '42',
            'b' => '3.5',
            'c' => '1',
            'd' => 'Fish &amp; Chips',
            'e' => '  padded  ',
            'f' => '{"x":1}',
        ]);

        $this->assertSame(42, $mapped['a']);
        $this->assertSame(3.5, $mapped['b']);
        $this->assertTrue($mapped['c']);
        $this->assertSame('Fish & Chips', $mapped['d']);
        $this->assertSame('padded', $mapped['e']);
        $this->assertSame(['x' => 1], $mapped['f']);
    }

    public function test_missing_column_and_static_defaults(): void
    {
        $mapper = new RowMapper([
            'user_id' => ['default' => 1],                       // no source at all
            'description' => ['source' => 'missing_column'],     // column absent
            'title' => ['source' => 'title', 'default' => 'Untitled'],
        ]);

        $mapped = $mapper->map(['title' => '']);

        $this->assertSame(1, $mapped['user_id']);
        $this->assertNull($mapped['description']);
        $this->assertSame('Untitled', $mapped['title']); // empty string falls back
    }
}
