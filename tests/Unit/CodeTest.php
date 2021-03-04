<?php

namespace Tests\Unit;

use App\Models\Code;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CodeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    function retrieving_a_code_by_period()
    {
        $code = Code::factory()->create(['period' => 7]);

        $foundCode = Code::period(7)->first();

        $this->assertEquals($code->id, $foundCode->id);
    }

    /** @test */
    function a_code_can_be_reserved()
    {
        $code = Code::factory()->create(['period' => 7]);
        $this->assertNull($code->reserved_at);

        $code->reserve();

        $this->assertNotNull($code->fresh()->reserved_at);
    }

    /** @test */
    public function a_code_can_be_released()
    {
        $code = Code::factory()->create(['period' => 7]);
        $this->assertNull($code->reserved_at);

        $code->release();

        $this->assertNull($code->fresh()->reserved_at);
    }
}
