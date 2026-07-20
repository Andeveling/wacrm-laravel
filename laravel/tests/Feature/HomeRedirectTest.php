<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class HomeRedirectTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_visiting_root_is_redirected_to_login()
    {
        $response = $this->get(route('home'));

        $response->assertRedirect(route('login', absolute: false));
    }

    public function test_authenticated_user_visiting_root_is_redirected_to_dashboard()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertRedirect(route('dashboard', absolute: false));
    }
}
