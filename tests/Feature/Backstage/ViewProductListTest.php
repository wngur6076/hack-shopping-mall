<?php

namespace Tests\Feature\Backstage;

use App\Models\Tag;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ViewProductListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Tag::factory()->create(['name' => '서든어택', 'slug' => 'suddenattack']);
        Tag::factory()->create(['name' => '메이플스토리', 'slug' => 'maplestory']);
        Tag::factory()->create(['name' => '오버워치', 'slug' => 'overwatch']);
    }

    /** @test */
    function guests_cannot_view_a_product_list()
    {
        Product::factory(2)->create();

        $response = $this->json('GET', '/api/backstage/products');

        $response->assertStatus(401);
    }

    /** @test */
    function users_can_view_a_product_listing()
    {
        Product::factory()->create();
        Product::factory()->create()->syncTags(['서든어택']);
        Product::factory()->create()->addCodes([['period' => 1, 'serial_number' => 'test1', 'price' => 1000]]);

        $response = $this->actingAs(User::factory()->create(), 'api')->json('GET', '/api/backstage/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'excerpt', 'poster_image', 'poster_video',
                        'file_link', 'created_date', 'user', 'tags', 'codes']
                ],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => [
                    'current_page', 'last_page', 'from', 'to', 'path', 'per_page', 'total'
                ],
            ]);
    }

    /** @test */
    function users_can_view_a_list_of_products_by_tag()
    {
        $this->withoutExceptionHandling();
        Product::factory()->create(['title' => 'Product 1']);
        Product::factory()->create(['title' => 'Product 2'])->syncTags(['서든어택']);
        Product::factory()->create(['title' => 'Product 3'])->syncTags(['메이플스토리']);
        Product::factory()->create(['title' => 'Product 4'])->syncTags(['서든어택']);

        $response = $this->actingAs(User::factory()->create(), 'api')->json('GET', '/api/backstage/tags/suddenattack/products');

        $response->assertJsonFragment(['title' => 'Product 2']);
        $response->assertJsonFragment(['title' => 'Product 4']);
        $response->assertJsonMissing(['title' => 'Product 1']);
        $response->assertJsonMissing(['title' => 'Product 3']);
    }

    /** @test */
    function users_cannot_view_products_with_non_existent_tags()
    {
        Product::factory()->create(['title' => 'Product 1']);
        Product::factory()->create(['title' => 'Product 2'])->syncTags(['서든어택']);

        $response = $this->actingAs(User::factory()->create(), 'api')->json('GET', '/api/backstage/tags/cannot/products');

        $response->assertStatus(404);
    }

    /** @test */
    function pagination_for_products_works()
    {
        for ($i = 0; $i < 4; $i++) {
            Product::factory()->create(['title' => 'Product '.$i]);
        }

        for ($i = 4; $i < 8; $i++) {
            Product::factory()->create(['title' => 'Product '.$i]);
        }
        $response = $this->actingAs(User::factory()->create(), 'api')->json('GET', '/api/backstage/products');

        $response->assertJsonFragment(['title' => 'Product 0']);
        $response->assertJsonFragment(['title' => 'Product 3']);

        $response = $this->actingAs(User::factory()->create(), 'api')->json('GET', '/api/backstage/products?page=2');

        $response->assertJsonFragment(['title' => 'Product 4']);
        $response->assertJsonFragment(['title' => 'Product 7']);
    }
}
