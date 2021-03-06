<?php

namespace Tests\Feature\Backstage;

use App\Models\Tag;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AddProductTest extends TestCase
{
    use RefreshDatabase;

    private function validParams($overrides = [])
    {
        return array_merge([
            'title' => 'Test',
            'body' => 'Test Body',
            'poster_video_path' => 'https://www.youtube.com/test',
            'file_link' => 'https://drive.google.com/file/test',
            'codes' => [
                ['period' => 1, 'serial_number' => 'test1', 'price' => 1000],
                ['period' => 7, 'serial_number' => 'test7', 'price' => 2000],
            ],
            'tags' => ['서든어택', '오버워치'],
        ], $overrides);
    }

    private function assertValidationError($response, $field)
    {
        $response->assertStatus(422)->assertJsonStructure(['errors' => [$field]]);
    }

    /** @test */
    function buyers_cannot_add_new_product()
    {
        $user = User::factory()->buyer()->create();

        $response = $this->actingAs($user, 'api')->json('POST','api/backstage/products');

        $response->assertStatus(403);
        $this->assertEquals(0, Product::count());
    }

    /** @test */
    function guests_cannot_add_new_product()
    {
        $response = $this->json('POST','api/backstage/products');

        $response->assertStatus(401);
        $this->assertEquals(0, Product::count());
    }

    /** @test */
    function adding_a_valid_product()
    {
        $this->withoutExceptionHandling();
        $user = User::factory()->seller()->create();
        Tag::factory()->create(['name' => '서든어택', 'slug' => 'suddenattack']);
        Tag::factory()->create(['name' => '메이플스토리', 'slug' => 'maplestory']);
        Tag::factory()->create(['name' => '오버워치', 'slug' => 'overwatch']);

        $response = $this->actingAs($user, 'api')->json('POST','api/backstage/products', $this->validParams());
        $response->assertStatus(201);

        tap(Product::first(), function ($product) use ($user) {
            $this->assertTrue($product->user->is($user));
            $this->assertEquals('Test', $product->title);
            $this->assertEquals('Test Body', $product->body);
            $this->assertEquals('https://www.youtube.com/test', $product->poster_video_path);
            $this->assertEquals('https://drive.google.com/file/test', $product->file_link);
            $this->assertTrue($product->hasCodeFor('test1'));
            $this->assertTrue($product->hasCodeFor('test7'));
            $this->assertTrue($product->hasTagFor('서든어택'));
            $this->assertTrue($product->hasTagFor('오버워치'));
            $this->assertFalse($product->hasTagFor('메이플스토리'));
        });
    }

    /** @test */
    function title_is_required()
    {
        $user = User::factory()->seller()->create();

        $response = $this->actingAs($user, 'api')->json('POST','api/backstage/products', $this->validParams([
            'title' => '',
        ]));

        $this->assertValidationError($response, 'title');
    }

    /** @test */
    function body_is_required()
    {
        $user = User::factory()->seller()->create();

        $response = $this->actingAs($user, 'api')->json('POST','api/backstage/products', $this->validParams([
            'body' => '',
        ]));

        $this->assertValidationError($response, 'body');
    }

    /** @test */
    function poster_video_path_is_optional()
    {
        $user = User::factory()->seller()->create();

        $response = $this->actingAs($user, 'api')->json('POST','api/backstage/products', $this->validParams([
            'poster_video_path' => '',
        ]));

        tap(Product::first(), function ($product) use ($user) {
            $this->assertTrue($product->user->is($user));

            $this->assertNull($product->poster_video_path);
        });
    }

    /** @test */
    function file_link_is_required()
    {
        $user = User::factory()->seller()->create();

        $response = $this->actingAs($user, 'api')->json('POST','api/backstage/products', $this->validParams([
            'file_link' => '',
        ]));

        $this->assertValidationError($response, 'file_link');
    }

    /** @test */
    function tags_is_required()
    {
        $user = User::factory()->seller()->create();

        $response = $this->actingAs($user, 'api')->json('POST','api/backstage/products', $this->validParams([
            'tags' => '',
        ]));

        $this->assertValidationError($response, 'tags');
    }

    /** @test */
    function codes_is_required()
    {
        $user = User::factory()->seller()->create();

        $response = $this->actingAs($user, 'api')->json('POST','api/backstage/products', $this->validParams([
            'codes' => '',
        ]));

        $this->assertValidationError($response, 'codes');
    }

    /** @test */
    function code_period_is_required()
    {
        $user = User::factory()->seller()->create();

        $response = $this->actingAs($user, 'api')->json('POST','api/backstage/products', $this->validParams([
            'codes' => [
                ['serial_number' => 'test1', 'price' => 1000],
            ],
        ]));

        $this->assertValidationError($response, 'codes.0.period');
    }

    /** @test */
    function code_serial_number_is_required()
    {
        $user = User::factory()->seller()->create();

        $response = $this->actingAs($user, 'api')->json('POST','api/backstage/products', $this->validParams([
            'codes' => [
                ['period' => 1, 'serial_number' => '', 'price' => 1000],
            ],
        ]));

        $this->assertValidationError($response, 'codes.0.serial_number');
    }

    /** @test */
    function code_price_is_required()
    {
        $user = User::factory()->seller()->create();

        $response = $this->actingAs($user, 'api')->json('POST','api/backstage/products', $this->validParams([
            'codes' => [
                ['period' => 1, 'serial_number' => 'test1'],
            ],
        ]));

        $this->assertValidationError($response, 'codes.0.price');
    }

    /** @test */
    function code_price_must_be_numeric()
    {
        $user = User::factory()->seller()->create();

        $response = $this->actingAs($user, 'api')->json('POST','api/backstage/products', $this->validParams([
            'codes' => [
                ['period' => 1, 'serial_number' => 'test1', 'price' => 'not a price'],
            ],
        ]));

        $this->assertValidationError($response, 'codes.0.price');
    }
}
