<?php

namespace Tests\Feature\Backstage;

use App\Models\Tag;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DeleteProductTest extends TestCase
{
    use RefreshDatabase;

    private function attributes($overrides = [])
    {
        return array_merge([
            'title' => 'Test Title',
            'body' => 'Test Body',
            'poster_video_path' => 'https://www.youtube.com/test',
            'file_link' => 'https://drive.google.com/file/test',
        ], $overrides);
    }

    /** @test */
    function buyers_cannot_delete_product()
    {
        $user = User::factory()->buyer()->create();
        $product = Product::factory()->create($this->attributes([
            'user_id' => $user->id,
        ]));

        $response = $this->actingAs($user, 'api')->json('DELETE',"api/backstage/products/{$product->id}");

        $response->assertStatus(403);
        $this->assertEquals(1, Product::count());
    }

    /** @test */
    function guests_cannot_delete_product()
    {
        $product = Product::factory()->create($this->attributes());

        $response = $this->json('DELETE',"api/backstage/products/{$product->id}");

        $response->assertStatus(401);
        $this->assertEquals(1, Product::count());
    }

    /** @test */
    function sellers_can_delete_their_own_products()
    {
        $this->withoutExceptionHandling();

        $user = User::factory()->seller()->create();
        Tag::factory()->create(['name' => '서든어택', 'slug' => 'suddenattack']);
        Tag::factory()->create(['name' => '오버워치', 'slug' => 'overwatch']);
        $product = Product::factory()->create($this->attributes([
            'user_id' => $user->id,
        ]));
        $product->syncTags(['서든어택', '오버워치']);
        $product->addCodes([
            ['period' => 1, 'serial_number' => 'Test test1', 'price' => 1000],
            ['period' => 7, 'serial_number' => 'Test test7', 'price' => 2000],
        ]);

        $response = $this->actingAs($user, 'api')->json('DELETE',"api/backstage/products/{$product->id}");

        $response->assertStatus(204)
            ->assertSee(null);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('product_tag', ['product_id' => $product->id]);
        $this->assertDatabaseMissing('codes', ['serial_number' => 'Test test1']);
        $this->assertDatabaseMissing('codes', ['serial_number' => 'Test test7']);
    }

    /** @test */
    function sellers_cannot_delete_other_products()
    {
        $user = User::factory()->seller()->create();
        $otherUser = User::factory()->seller()->create();
        $product = Product::factory()->create($this->attributes([
            'user_id' => $otherUser->id,
        ]));

        $response = $this->actingAs($user, 'api')->json('DELETE',"api/backstage/products/{$product->id}");

        $response->assertStatus(404);
        $this->assertEquals(1, Product::count());
    }

    /** @test */
    function product_is_deleted_the_poster_image_is_also_deleted()
    {
        $user = User::factory()->seller()->create();
        $product = Product::factory()->create($this->attributes([
            'user_id' => $user->id,
            'poster_image_path' => File::image('old-product-poster.png', 325, 200)->store('posters', 'public'),
        ]));
        $posterImagePath = $product->poster_image_path;

        $response = $this->actingAs($user, 'api')->json('DELETE',"api/backstage/products/{$product->id}");

        Storage::disk('public')->assertMissing($posterImagePath);
    }
}
