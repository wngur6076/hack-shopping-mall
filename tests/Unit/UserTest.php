<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    function retrieving_an_user_by_email()
    {
        $user = User::factory()->create(['email' => 'john@example.com']);

        $foundUser = User::findByEmail('john@example.com');

        $this->assertEquals($user->id, $foundUser->id);
    }

    /** @test */
    function retrieving_a_nonexistent_user_by_email_throws_an_exception()
    {
        $this->expectNotToPerformAssertions();

        try {
            User::findByEmail('john@example.com');
        } catch (ModelNotFoundException $e) {
            return;
        }
        $this->fail('No matching user was found for the specified email, but an exception
            was not thrown.');
    }

    /** @test */
    function user_can_pay_the_billed_amount()
    {
        $user = User::factory()->make(['email' => 'john@example.com', 'money' => 2500]);

        $user->payment(2500);

        $this->assertEquals(0, $user->money);
    }
}
