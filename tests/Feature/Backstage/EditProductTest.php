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
        $user = User::factory()->seller()->create();
        Tag::factory()->create(['name' => '서든어택', 'slug' => 'suddenattack']);
        Tag::factory()->create(['name' => '메이플스토리', 'slug' => 'maplestory']);
        Tag::factory()->create(['name' => '오버워치', 'slug' => 'overwatch']);

        $product = Product::factory()->create([
            'user_id' => $user->id,
            'title' => 'Test',
            'body' => 'Test Body',
            'poster_video_path' => 'https://www.youtube.com/test',
            'file_link' => 'https://drive.google.com/file/test',
        ]);
        $product->addTags(['서든어택', '오버워치']);
        $product->addCodes([
            ['period' => 1, 'serial_number' => 'Old test1', 'price' => 1000],
            ['period' => 7, 'serial_number' => 'Old test7', 'price' => 2000],
        ]);

        dd($product);
    }
}
