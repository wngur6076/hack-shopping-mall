<?php

namespace Tests\Feature\Backstage;

use App\Models\Tag;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;

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
            $this->assertArrayHasKey($key, $product->fresh()->getAttributes());
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
            $this->assertArrayHasKey($key, $product->fresh()->getAttributes());
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
            $this->assertArrayHasKey($key, $product->fresh()->getAttributes());
            $this->assertSame($value, $product->getAttributes()[$key]);
        }
    }

    /** @test */
    function title_is_required()
    {
        $user = User::factory()->seller()->create();
        $product = Product::factory()->create([
            'user_id' => $user->id,
            'title' => 'Old Title',
        ]);

        $response = $this->actingAs($user, 'api')->json('PATCH',"api/backstage/products/{$product->id}", $this->validParams([
            'title' => '',
        ]));

        $this->assertValidationError($response, 'title');
        tap($product->fresh(), function ($product) {
            $this->assertEquals('Old Title', $product->title);
        });
    }

    /** @test */
    function body_is_required()
    {
        $user = User::factory()->seller()->create();
        $product = Product::factory()->create([
            'user_id' => $user->id,
            'body' => 'Old Body',
        ]);

        $response = $this->actingAs($user, 'api')->json('PATCH',"api/backstage/products/{$product->id}", $this->validParams([
            'body' => '',
        ]));

        $this->assertValidationError($response, 'body');
        tap($product->fresh(), function ($product) {
            $this->assertEquals('Old Body', $product->body);
        });
    }

    /** @test */
    function poster_video_is_optional()
    {
        $user = User::factory()->seller()->create();
        $product = Product::factory()->create([
            'user_id' => $user->id,
            'poster_video_path' => 'https://www.youtube.com/old',
        ]);

        $response = $this->actingAs($user, 'api')->json('PATCH',"api/backstage/products/{$product->id}", $this->validParams([
            'poster_video' => '',
        ]));

        tap($product->fresh(), function ($product) {
            $this->assertNull($product->poster_video_path);
        });
    }

    /** @test */
    function file_link_is_required()
    {
        $user = User::factory()->seller()->create();
        $product = Product::factory()->create([
            'user_id' => $user->id,
            'file_link' => 'https://drive.google.com/file/old',
        ]);

        $response = $this->actingAs($user, 'api')->json('PATCH',"api/backstage/products/{$product->id}", $this->validParams([
            'file_link' => '',
        ]));

        $this->assertValidationError($response, 'file_link');
        tap($product->fresh(), function ($product) {
            $this->assertEquals('https://drive.google.com/file/old', $product->file_link);
        });
    }

    /** @test */
    function tags_is_required()
    {
        $user = User::factory()->seller()->create();
        Tag::factory()->create(['name' => '오버워치', 'slug' => 'overwatch']);
        $product = Product::factory()->create([
            'user_id' => $user->id,
        ])->syncTags(['오버워치']);

        $response = $this->actingAs($user, 'api')->json('PATCH',"api/backstage/products/{$product->id}", $this->validParams([
            'tags' => '',
        ]));

        $this->assertValidationError($response, 'tags');
        tap($product->fresh(), function ($product) {
            $this->assertTrue($product->hasTagFor('오버워치'));
        });
    }

    /** @test */
    function codes_is_required()
    {
        $user = User::factory()->seller()->create();
        $product = Product::factory()->create([
            'user_id' => $user->id,
        ])->addCodes([
            ['period' => 1, 'serial_number' => 'Old test1', 'price' => 1000],
        ]);

        $response = $this->actingAs($user, 'api')->json('PATCH',"api/backstage/products/{$product->id}", $this->validParams([
            'codes' => '',
        ]));

        $this->assertValidationError($response, 'codes');
        tap($product->fresh(), function ($product) {
            $this->assertTrue($product->hasCodeFor('Old test1'));
        });
    }

    /** @test */
    function code_period_is_required()
    {
        $user = User::factory()->seller()->create();
        $product = Product::factory()->create([
            'user_id' => $user->id,
        ])->addCodes([
            ['period' => 1, 'serial_number' => 'Old test1', 'price' => 1000],
        ]);

        $response = $this->actingAs($user, 'api')->json('PATCH',"api/backstage/products/{$product->id}", $this->validParams([
            'codes' => [
                ['serial_number' => 'New test7', 'price' => 5000],
            ],
        ]));

        $this->assertValidationError($response, 'codes.0.period');
        tap($product->fresh(), function ($product) {
            $this->assertEquals(1, $product->codes()->first()->period);
        });
    }

    /** @test */
    function code_serial_number_is_required()
    {
        $user = User::factory()->seller()->create();
        $product = Product::factory()->create([
            'user_id' => $user->id,
        ])->addCodes([
            ['period' => 1, 'serial_number' => 'Old test1', 'price' => 1000],
        ]);

        $response = $this->actingAs($user, 'api')->json('PATCH',"api/backstage/products/{$product->id}", $this->validParams([
            'codes' => [
                ['period' => 7, 'price' => 5000],
            ],
        ]));

        $this->assertValidationError($response, 'codes.0.serial_number');
        tap($product->fresh(), function ($product) {
            $this->assertEquals('Old test1', $product->codes()->first()->serial_number);
        });
    }

    /** @test */
    function code_price_is_required()
    {
        $user = User::factory()->seller()->create();
        $product = Product::factory()->create([
            'user_id' => $user->id,
        ])->addCodes([
            ['period' => 1, 'serial_number' => 'Old test1', 'price' => 1000],
        ]);

        $response = $this->actingAs($user, 'api')->json('PATCH',"api/backstage/products/{$product->id}", $this->validParams([
            'codes' => [
                ['period' => 7, 'serial_number' => 'New test7'],
            ],
        ]));

        $this->assertValidationError($response, 'codes.0.price');
        tap($product->fresh(), function ($product) {
            $this->assertEquals(1000, $product->codes()->first()->price);
        });
    }

    /** @test */
    function code_price_must_be_numeric()
    {
        $user = User::factory()->seller()->create();
        $product = Product::factory()->create([
            'user_id' => $user->id,
        ])->addCodes([
            ['period' => 1, 'serial_number' => 'Old test1', 'price' => 1000],
        ]);

        $response = $this->actingAs($user, 'api')->json('PATCH',"api/backstage/products/{$product->id}", $this->validParams([
            'codes' => [
                ['period' => 7, 'serial_number' => 'New test7', 'price' => 'not a price'],
            ],
        ]));

        $this->assertValidationError($response, 'codes.0.price');
        tap($product->fresh(), function ($product) {
            $this->assertEquals(1000, $product->codes()->first()->price);
        });
    }

    /** @test */
    function poster_image_is_uploaded_if_included()
    {
        Storage::fake('public');
        $user = User::factory()->seller()->create();
        $product = Product::factory()->create($this->oldAttributes([
            'user_id' => $user->id,
        ]));
        $file = File::image('product-poster.png', 325, 200);

        $response = $this->actingAs($user, 'api')->json('PATCH',"api/backstage/products/{$product->id}", $this->validParams([
            'poster_image' => $file,
        ]));

        tap($product->fresh(), function ($product) use ($file) {
            $this->assertNotNull($product->poster_image_path);
            Storage::disk('public')->assertExists($product->poster_image_path);
            $this->assertFileEquals(
                $file->getPathname(),
                Storage::disk('public')->path($product->poster_image_path)
            );
        });
    }

    /** @test */
    function poster_image_is_updated_the_existing_image_is_deleted()
    {
        Storage::fake('public');
        $user = User::factory()->seller()->create();
        $product = Product::factory()->create($this->oldAttributes([
            'user_id' => $user->id,
            'poster_image_path' => File::image('old-product-poster.png', 325, 200)->store('posters', 'public'),
        ]));
        $oldPosterImagePath = $product->poster_image_path;

        $file = File::image('new-product-poster.png', 325, 200);
        $response = $this->actingAs($user, 'api')->json('PATCH',"api/backstage/products/{$product->id}", $this->validParams([
            'poster_image' => $file,
        ]));

        tap($product->fresh(), function ($product) use ($file, $oldPosterImagePath) {
            Storage::disk('public')->assertMissing($oldPosterImagePath);

            Storage::disk('public')->assertExists($product->poster_image_path);
            $this->assertFileEquals(
                $file->getPathname(),
                Storage::disk('public')->path($product->poster_image_path)
            );
        });
    }

    /** @test */
    function poster_image_must_be_an_image()
    {
        Storage::fake('public');
        $user = User::factory()->seller()->create();
        $product = Product::factory()->create($this->oldAttributes([
            'user_id' => $user->id,
            'poster_image_path' => File::image('old-product-poster.png', 325, 200)->store('posters', 'public'),
        ]));
        $file = File::create('not-a-poster.pdf');

        $response = $this->actingAs($user, 'api')->json('PATCH',"api/backstage/products/{$product->id}", $this->validParams([
            'poster_image' => $file,
        ]));

        $this->assertValidationError($response, 'poster_image');
        tap($product->fresh(), function ($product) use ($file) {
            $this->assertFileNotEquals(
                $file->getPathname(),
                Storage::disk('public')->path($product->poster_image_path)
            );
        });
    }

    /** @test */
    function poster_image_is_optional()
    {
        Storage::fake('public');
        $user = User::factory()->seller()->create();
        $product = Product::factory()->create($this->oldAttributes([
            'user_id' => $user->id,
            'poster_image_path' => File::image('old-product-poster.png', 325, 200)->store('posters', 'public'),
        ]));
        $oldPosterImagePath = $product->poster_image_path;

        $response = $this->actingAs($user, 'api')->json('PATCH',"api/backstage/products/{$product->id}", $this->validParams([
            'poster_image' => 'Not File',
        ]));

        Storage::disk('public')->assertExists($oldPosterImagePath);
    }
}

