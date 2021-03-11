<?php

namespace Tests\Feature\Backstage;

use App\Models\Tag;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EditProductTest extends TestCase
{
    use RefreshDatabase;

    private function oldAttributes($overrides = [])
    {
        return array_merge([
            'title' => 'Old Title',
            'body' => 'Old Body',
            'poster_video_path' => 'https://www.youtube.com/old',
            'file_link' => 'https://drive.google.com/file/old',
        ], $overrides);
    }


    private function validParams($overrides = [])
    {
        return array_merge([
            'title' => 'New Title',
            'body' => 'New Body',
            'poster_video' => 'https://www.youtube.com/New',
            'file_link' => 'https://drive.google.com/file/New',
            'codes' => [
                ['id' => 1, 'period' => 7, 'serial_number' => 'New test7', 'price' => 5000],
                ['period' => 15, 'serial_number' => 'New test15', 'price' => 13000],
            ],
            'tags' => ['메이플스토리'],
        ], $overrides);
    }

    private function assertValidationError($response, $field)
    {
        $response->assertStatus(422)->assertJsonStructure(['errors' => [$field]]);
    }

    /** @test */
    function buyers_cannot_update_product()
    {
        $user = User::factory()->buyer()->create();
        $product = Product::factory()->create($this->oldAttributes([
            'user_id' => $user->id,
        ]));

        $response = $this->actingAs($user, 'api')->json('PATCH',"api/backstage/products/{$product->id}", $this->validParams());

        $response->assertStatus(403);

        foreach ($this->oldAttributes(['user_id' => $user->id]) as $key => $value) {
            $this->assertArrayHasKey($key, $product->getAttributes());
            $this->assertSame($value, $product->getAttributes()[$key]);
        }
    }

    /** @test */
    function guests_cannot_edit_products()
    {
        $user = User::factory()->seller()->create();
        $product = Product::factory()->create($this->oldAttributes([
            'user_id' => $user->id,
        ]));

        $response = $this->json('PATCH',"api/backstage/products/{$product->id}", $this->validParams());

        $response->assertStatus(401);

        foreach ($this->oldAttributes(['user_id' => $user->id]) as $key => $value) {
            $this->assertArrayHasKey($key, $product->getAttributes());
            $this->assertSame($value, $product->getAttributes()[$key]);
        }
    }

    /** @test */
    function sellers_can_edit_their_own_products()
    {
        $this->withoutExceptionHandling();

        $user = User::factory()->seller()->create();
        Tag::factory()->create(['name' => '서든어택', 'slug' => 'suddenattack']);
        Tag::factory()->create(['name' => '메이플스토리', 'slug' => 'maplestory']);
        Tag::factory()->create(['name' => '오버워치', 'slug' => 'overwatch']);
        $product = Product::factory()->create([
            'user_id' => $user->id,
            'title' => 'Old Title',
            'body' => 'Old Body',
            'poster_video_path' => 'https://www.youtube.com/old',
            'file_link' => 'https://drive.google.com/file/old',
        ]);
        $product->syncTags(['서든어택', '오버워치']);
        $product->addCodes([
            ['period' => 1, 'serial_number' => 'Old test1', 'price' => 1000],
            ['period' => 7, 'serial_number' => 'Old test7', 'price' => 2000],
        ]);

        $response = $this->actingAs($user, 'api')->json('PATCH',"api/backstage/products/{$product->id}", [
            'title' => 'New Title',
            'body' => 'New Body',
            'poster_video' => 'https://www.youtube.com/New',
            'file_link' => 'https://drive.google.com/file/New',
            'codes' => [
                ['id' => 1, 'period' => 7, 'serial_number' => 'New test7', 'price' => 5000],
                ['period' => 15, 'serial_number' => 'New test15', 'price' => 13000],
            ],
            'tags' => ['메이플스토리'],
        ]);
        $response->assertStatus(200);

        $markdown = new \League\CommonMark\CommonMarkConverter(['allow_unsafe_links' => false]);
        $response->assertJson([
            'data' => [
                'title' => 'New Title',
                'excerpt' => strip_tags($markdown->convertToHtml('New Body'), 200),
                'poster_video' => '//www.youtube.com/embed/New',
                'file_link' => 'https://drive.google.com/file/New',
                'tags' => [
                    ['name' => '메이플스토리', 'slug' => 'maplestory'],
                ],
                'codes' => [
                    ['period' => 7, 'serial_number' => 'New test7', 'price' => 5000],
                    ['period' => 15, 'serial_number' => 'New test15', 'price' => 13000],
                ]
            ]
        ]);

        tap($product->fresh(), function ($product) {
            $this->assertEquals('New Title', $product->title);
            $this->assertEquals('New Body', $product->body);
            $this->assertEquals('https://www.youtube.com/New', $product->poster_video_path);
            $this->assertEquals('https://drive.google.com/file/New', $product->file_link);
            $this->assertTrue($product->hasCodeFor('New test7'));
            $this->assertTrue($product->hasCodeFor('New test15'));
            $this->assertTrue($product->hasTagFor('메이플스토리'));
            $this->assertFalse($product->hasTagFor('서든어택'));
            $this->assertFalse($product->hasTagFor('오버워치'));
        });
    }

    /** @test */
    function sellers_cannot_edit_other_products()
    {
        $user = User::factory()->seller()->create();
        $otherUser = User::factory()->seller()->create();
        $product = Product::factory()->create($this->oldAttributes([
            'user_id' => $otherUser->id,
        ]));

        $response = $this->actingAs($user, 'api')->json('PATCH',"api/backstage/products/{$product->id}", $this->validParams());

        $response->assertStatus(404);

        foreach ($this->oldAttributes(['user_id' => $otherUser->id]) as $key => $value) {
            $this->assertArrayHasKey($key, $product->getAttributes());
            $this->assertSame($value, $product->getAttributes()[$key]);
        }
    }
}
