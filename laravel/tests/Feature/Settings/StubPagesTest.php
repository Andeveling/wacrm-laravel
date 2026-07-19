<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StubPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_page_renders_with_eleven_panels(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.overview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/overview')
                ->has('panels', 11),
            );
    }

    public function test_overview_page_marks_baseline_panels_as_disponible(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.overview'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('panels.0.slug', 'profile')
                ->where('panels.0.status', 'Disponible')
                ->where('panels.1.slug', 'security')
                ->where('panels.1.status', 'Disponible')
                ->where('panels.2.slug', 'appearance')
                ->where('panels.2.status', 'Disponible')
                ->where('panels.3.slug', 'members')
                ->where('panels.3.status', 'Disponible'),
            );
    }

    public function test_overview_page_marks_api_keys_as_module_gated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.overview'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('panels.4.slug', 'api-keys')
                ->where('panels.4.status', 'Disponible con módulo'),
            );
    }

    public function test_whatsapp_stub_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.whatsapp'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/whatsapp'),
            );
    }

    public function test_templates_stub_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.templates'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/templates'),
            );
    }

    public function test_quick_replies_stub_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.quick-replies'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/quick-replies'),
            );
    }

    public function test_fields_stub_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.fields'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/fields'),
            );
    }

    public function test_deals_stub_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.deals'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/deals'),
            );
    }

    public function test_profile_still_renders_after_overview_takeover(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/profile'),
            );
    }

    public function test_overview_redirects_guests_to_login(): void
    {
        $this->get(route('settings.overview'))
            ->assertRedirect(route('login'));
    }
}
