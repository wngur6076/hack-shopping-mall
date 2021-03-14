<?php

namespace Tests\Feature\Backstage;

use App\Models\Tag;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use Illuminate\Http\Testing\File;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ShowProductTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_view_their_product()
    {
        $this->withoutExceptionHandling();
        $markdown = new \League\CommonMark\CommonMarkConverter(['allow_unsafe_links' => false]);
        $user = User::factory()->create();
        Tag::factory()->create(['name' => '서든어택', 'slug' => 'suddenattack']);
        Tag::factory()->create(['name' => '오버워치', 'slug' => 'overwatch']);
        $product = Product::factory()->create([
            'user_id' => $user->id,
            'title' => 'Test Title',
            'body' => 'Test Body',
            'poster_image_path' => File::image('old-product-poster.png', 325, 200)->store('posters', 'public'),
            'poster_video_path' => 'https://www.youtube.com/test',
            'file_link' => 'https://drive.google.com/file/test',
        ]);
        $posterImagePath = $product->poster_image_path;

        $product->syncTags(['서든어택', '오버워치']);
        $product->addCodes([
            ['period' => 1, 'serial_number' => 'test1', 'price' => 1000],
            ['period' => 7, 'serial_number' => 'test7', 'price' => 2000],
        ]);

        $response = $this->actingAs($user, 'api')->json('GET',"api/backstage/products/{$product->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'id' => 1,
                'title' => 'Test Title',
                'body_html' => clean($markdown->convertToHtml('<p>Test Body</p>')),
                'body' => 'Test Body',
                'poster_image' => config("app.url").'/storage/'.$posterImagePath,
                'poster_video' => '//www.youtube.com/embed/test',
                'file_link' => 'https://drive.google.com/file/test',
                'tags' => [
                    ['name' => '서든어택', 'slug' => 'suddenattack'],
                    ['name' => '오버워치', 'slug' => 'overwatch'],
                ],
                'codes' => [
                    ['id' => 1, 'period' => 1, 'serial_number' => 'test1', 'price' => 1000],
                    ['id' => 2, 'period' => 7, 'serial_number' => 'test7', 'price' => 2000],
                ]
            ]
        ]);
    }
}
