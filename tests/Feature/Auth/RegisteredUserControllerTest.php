<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisteredUserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_registration_from_the_sign_in_page(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Create an account')
            ->assertSee('href="'.route('register').'"', false);

        $this->get(route('register'))
            ->assertOk()
            ->assertViewIs('auth.register');
    }

    public function test_guest_must_sign_in_after_creating_an_account(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Jordan Rivera',
            'email' => 'jordan@example.com',
            'password' => 'school-event-password',
            'password_confirmation' => 'school-event-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'Your account is ready. Sign in with your new credentials to continue.');
        $this->assertGuest();
        $this->assertDatabaseHas('users', [
            'name' => 'Jordan Rivera',
            'email' => 'jordan@example.com',
        ]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Your account is ready. Sign in with your new credentials to continue.');

        $this->post(route('login.store'), [
            'email' => 'jordan@example.com',
            'password' => 'school-event-password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }
}
