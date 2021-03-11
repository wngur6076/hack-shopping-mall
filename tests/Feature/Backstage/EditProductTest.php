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

    /** @test */
    function sellers_can_edit_their_own_product()
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
                ['id' => 2, 'period' => 15, 'serial_number' => 'New test15', 'price' => 13000],
            ],
            'tags' => ['메이플스토리'],
        ]);
        $response->assertStatus(200);

        tap(Product::first(), function ($product) {
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
}
