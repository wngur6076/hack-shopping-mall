<?php

namespace Tests\Feature\Backstage;

use App\Models\Product;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AddProductTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    function buyers_cannot_add_new_product()
    {
        $user = User::factory()->buyer()->create();

        $response = $this->actingAs($user, 'api')->json('POST','api/backstage/products');

        $response->assertStatus(403);
    }

    /** @test */
    function guests_cannot_add_new_product()
    {
        $response = $this->json('POST','api/backstage/products');

        $response->assertStatus(401);
    }

    /** @test */
    function adding_a_valid_product()
    {
        $this->withoutExceptionHandling();
        $user = User::factory()->seller()->create();

        $this->actingAs($user, 'api')->json('POST','api/backstage/products', [
            'title' => 'Test',
            'body' => 'Test Body',
            'poster_video_path' => 'https://www.youtube.com/test',
            'file_link' => 'https://drive.google.com/file/test',
            'codes' => [['period' => 1, 'serial_number' => 'test1', 'price' => 1000]],

        ])->assertStatus(201);

        tap(Product::first(), function ($product) use ($user) {
            $this->assertTrue($product->user->is($user));
            $this->assertEquals('Test', $product->title);
            $this->assertEquals('Test Body', $product->body);
            $this->assertEquals('https://www.youtube.com/test', $product->poster_video_path);
            $this->assertEquals('https://drive.google.com/file/test', $product->file_link);
            $this->assertEquals(1, $product->codes()->first()->period);
            $this->assertEquals('test1', $product->codes()->first()->serial_number);
            $this->assertEquals('1000', $product->codes()->first()->price);
        });
    }
}
