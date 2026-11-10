<?php

namespace Tests\Feature;

use App\Models\User;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleOAuthTest extends TestCase
{
    public function test_verified_google_user_can_sign_in_and_is_created(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The pdo_sqlite extension is required for OAuth persistence tests.');
        }

        $this->artisan('migrate:fresh');
        $this->mockGoogleUser([
            'email' => 'Tester@Example.com',
            'email_verified' => true,
            'name' => 'Test User',
        ]);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('dashboard-analytics'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'tester@example.com',
            'name' => 'Test User',
        ]);
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $this->mockGoogleUser([
            'email' => 'tester@example.com',
            'email_verified' => false,
            'name' => 'Test User',
        ]);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('auth_error');
        $this->assertGuest();
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get(route('dashboard-analytics'))->assertRedirect(route('login'));
    }

    public function test_google_redirect_reports_missing_credentials(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
        ]);

        $this->get(route('auth.google.redirect'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('auth_error');
    }

    public function test_google_consent_cancellation_returns_to_login(): void
    {
        $this->withSession(['state' => 'expected-state'])
            ->get(route('auth.google.callback', [
                'error' => 'access_denied',
                'state' => 'expected-state',
            ]))
            ->assertRedirect(route('login'))
            ->assertSessionHas('auth_error');
    }

    public function test_google_token_exchange_error_shows_a_useful_message(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andThrow(new RequestException(
            'OAuth token exchange failed.',
            new Request('POST', 'https://oauth2.googleapis.com/token'),
            new Response(400, [], '{"error":"invalid_client"}')
        ));

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('auth_error', 'Google rejected the OAuth client credentials. Verify GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET.');
    }

    public function test_google_connection_failure_shows_a_transport_message(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andThrow(new RequestException(
            'Connection failed.',
            new Request('POST', 'https://oauth2.googleapis.com/token')
        ));

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('auth_error', 'The server could not connect securely to Google. Check its internet connection and SSL certificate configuration.');
    }

    public function test_logout_ends_the_authenticated_session(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The pdo_sqlite extension is required for logout persistence tests.');
        }

        $this->artisan('migrate:fresh');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    private function mockGoogleUser(array $profile): void
    {
        $googleUser = GoogleUser::fake($profile);
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($googleUser);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);
    }
}
