<?php

namespace Tests\Unit;

use App\Models\Tag;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    function retrieving_a_tag_by_slug()
    {
        $tag = Tag::factory()->create(['name' => '서든어택', 'slug' => 'suddenattack']);

        $foundTag = Tag::findBySlug('suddenattack');

        $this->assertEquals($tag->id, $foundTag->id);
    }
}
