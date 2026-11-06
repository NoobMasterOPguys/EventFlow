<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_read_the_privacy_policy_and_terms_of_service(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertViewIs('legal.privacy')
            ->assertSee('Privacy Policy')
            ->assertSee('Google Calendar')
            ->assertSee('account deletion');

        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertViewIs('legal.terms')
            ->assertSee('Terms of Service')
            ->assertSee('Acceptable use')
            ->assertSee('governing jurisdiction has not yet been selected');
    }

    public function test_prelaunch_pages_disclose_missing_legal_and_provider_details(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Pre-launch draft.')
            ->assertSee('the operator’s legal name has not yet been provided')
            ->assertSee('production providers and their processing locations have not yet been selected or configured');
    }

    public function test_login_and_registration_pages_link_to_both_legal_pages(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('href="'.route('legal.privacy').'"', false)
            ->assertSee('href="'.route('legal.terms').'"', false);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('href="'.route('legal.privacy').'"', false)
            ->assertSee('href="'.route('legal.terms').'"', false);
    }

    public function test_configured_legal_operator_and_contact_are_rendered(): void
    {
        config([
            'legal.operator_name' => 'EventFlow Community Services',
            'legal.contact_email' => 'privacy@example.com',
            'legal.governing_jurisdiction' => 'Example jurisdiction',
            'legal.hosting_providers' => 'Example host',
            'legal.email_provider' => 'Example mail provider',
            'legal.effective_date' => '2025-01-01',
        ]);

        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertDontSee('Pre-launch draft.')
            ->assertSee('EventFlow Community Services')
            ->assertSee('Example jurisdiction')
            ->assertSee('mailto:privacy@example.com', false)
            ->assertSee('Effective date: 2025-01-01');
    }
}
