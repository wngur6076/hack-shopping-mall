<?php

namespace Tests\Unit;

use Carbon\Carbon;
use App\Models\Tag;
use Tests\TestCase;
use App\Models\Code;
use App\Models\Order;
use App\Models\Product;
use App\Exceptions\NotEnoughCodesException;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private function sevenData()
    {
        return [
            ['period' => 1, 'serial_number' => 'test1-1', 'price' => 1000],
            ['period' => 1, 'serial_number' => 'test1-2', 'price' => 1000],
            ['period' => 7, 'serial_number' => 'test7-1', 'price' => 2000],
            ['period' => 7, 'serial_number' => 'test7-2', 'price' => 2000],
            ['period' => 15, 'serial_number' => 'test15', 'price' => 3000],
            ['period' => 30, 'serial_number' => 'test30', 'price' => 4000],
            ['period' => 999, 'serial_number' => 'test999', 'price' => 5000],
        ];
    }

    /** @test */
    function can_get_poster_video_url()
    {
        $product = Product::factory()->make([
            'poster_video_path' => 'https://www.youtube.com/test'
        ]);

        $this->assertEquals('//www.youtube.com/embed/test', $product->poster_video_url);
    }

    /** @test */
    function can_get_poster_image_url()
    {
        $product = Product::factory()->make([
            'poster_image_path' => 'posters/test.png'
        ]);

        $this->assertEquals(config("app.url").'/storage/posters/test.png', $product->poster_image_url);
    }

    /** @test */
    function can_get_created_date()
    {
        $product = Product::factory()->make([
            'created_at' => Carbon::parse('-1 week')
        ]);
        $this->assertEquals('1 week ago', $product->created_date);
    }

    /** @test */
    function can_get_excerpt()
    {
        $markdown = new \League\CommonMark\CommonMarkConverter(['allow_unsafe_links' => false]);
        $product = Product::factory()->make([
            'body' => 'test'
        ]);
        $this->assertEquals(strip_tags($markdown->convertToHtml('test'), 5), $product->excerpt(5));

        $product = Product::factory()->make([
            'body' => 'test1'
        ]);
        $this->assertEquals('test1...', $product->excerpt(5));
    }

    /** @test */
    function can_add_codes()
    {
        $product = Product::factory()->create()->addCodes($this->sevenData());

        $this->assertEquals(7, $product->codesRemaining());
    }

    /** @test */
    function codes_remaining_does_not_include_codes_associated_with_an_order()
    {
        $product = Product::factory()->create();
        $product->codes()->saveMany(Code::factory(30)->period(1)->create(['order_id' => 1]));
        $product->codes()->saveMany(Code::factory(20)->period(7)->create(['order_id' => null]));


        $this->assertEquals(20, $product->codesRemaining());
    }

    /** @test */
    function trying_to_reserve_more_tickets_than_remain_throws_an_exception()
    {
        $product = Product::factory()->create()->addCodes($this->sevenData());

        try {
            $product->reserveCodes([
                ['period' => 1, 'quantity' => 1],
                ['period' => 15, 'quantity' => 99],
            ], 'jane@example.com');
        } catch (NotEnoughCodesException $e) {
            $this->assertFalse($product->hasOrderFor('john@example.com'));
            $this->assertEquals(7, $product->codesRemaining());
            return;
        }

        $this->fail('Order succeeded even though there were not enough codes remaining.');
    }

    /** @test */
    function can_reserve_available_codes()
    {
        $product = Product::factory()->create()->addCodes($this->sevenData());
        $this->assertEquals(7, $product->codesRemaining());

        $reservation = $product->reserveCodes([
            ['period' => 1, 'quantity' => 2],
        ], 'jane@example.com');

        $this->assertCount(1, $reservation->codes());
        $this->assertEquals('jane@example.com', $reservation->email());
        $this->assertEquals(5, $product->codesRemaining());
    }

    /** @test */
    function cannot_reserve_codes_that_have_already_been_purchased()
    {
        $product = Product::factory()->create()->addCodes($this->sevenData());
        $order = Order::factory()->create();
        $order->codes()->saveMany($product->findCodeFor(1)->take(2)->get());

        try {
            $product->reserveCodes([
                ['period' => 1, 'quantity' => 2],
            ], 'jane@example.com');
        } catch (NotEnoughCodesException $e) {
            $this->assertEquals(5, $product->codesRemaining());
            return;
        }

        $this->fail('Reserving codes succeeded even though the codes were already sold.');
    }

    /** @test */
    function cannot_reserve_codes_that_have_already_been_reserved()
    {
        $product = Product::factory()->create()->addCodes($this->sevenData());
        $product->reserveCodes([
            ['period' => 1, 'quantity' => 2],
        ], 'jane@example.com');

        try {
            $product->reserveCodes([
                ['period' => 1, 'quantity' => 2],
            ], 'jane@example.com');
        } catch (NotEnoughCodesException $e) {
            $this->assertEquals(5, $product->codesRemaining());
            return;
        }

        $this->fail('Reserving codes succeeded even though the codes were already reserved.');
    }

    /** @test */
    function can_sync_tags()
    {
        Tag::factory()->create(['name' => '서든어택', 'slug' => 'suddenattack']);
        Tag::factory()->create(['name' => '메이플스토리', 'slug' => 'maplestory']);
        Tag::factory()->create(['name' => '오버워치', 'slug' => 'overwatch']);

        $product = Product::factory()->create()->syncTags(['서든어택', '오버워치']);

        $this->assertCount(2, $product->tags);
        $this->assertTrue($product->hasTagFor('서든어택'));
        $this->assertTrue($product->hasTagFor('오버워치'));
        $this->assertFalse($product->hasTagFor('메이플스토리'));
    }

    /** @test */
    function can_update_codes()
    {
        $product = Product::factory()->create()->addCodes([
            ['period' => 1, 'serial_number' => 'Old test1', 'price' => 1000],
            ['period' => 7, 'serial_number' => 'Old test7', 'price' => 2000],
        ]);

        $product->updateCodes([
            ['id' => 1, 'period' => 7, 'serial_number' => 'New test7', 'price' => 5000],
            ['id' => 2, 'period' => 15, 'serial_number' => 'New test15', 'price' => 13000],
            ['period' => 30, 'serial_number' => 'New test30', 'price' => 25000],
        ]);

        $this->assertCount(3, $product->codes);
        $this->assertEquals(1, $product->codeFor('New test7')->first()->id);
        $this->assertEquals(2, $product->codeFor('New test15')->first()->id);
        $this->assertEquals(3, $product->codeFor('New test30')->first()->id);

        $product->updateCodes([
            ['period' => 1, 'serial_number' => 'New test1', 'price' => 1500],
        ]);
        $this->assertCount(1, $product->fresh()->codes);
        $this->assertEquals(4, $product->codeFor('New test1')->first()->id);
    }
}
