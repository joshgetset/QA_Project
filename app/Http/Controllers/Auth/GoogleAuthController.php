<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret') || ! config('services.google.redirect')) {
            return redirect()->route('login')->with('auth_error', 'Google sign-in is not configured. Contact the system administrator.');
        }

        return Socialite::driver('google')
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            $state = $request->session()->pull('state');

            if (! is_string($state) || ! hash_equals($state, (string) $request->query('state'))) {
                return redirect()->route('login')->with('auth_error', 'Your sign-in session expired. Please try again.');
            }

            return redirect()->route('login')->with('auth_error', 'Google sign-in was cancelled. Please try again.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException $exception) {
            Log::warning('Google OAuth callback rejected because its state was invalid.');

            return redirect()->route('login')->with('auth_error', 'Your sign-in session expired. Please try again.');
        } catch (RequestException $exception) {
            $response = $exception->getResponse();
            $providerError = null;

            if ($response !== null) {
                $responseBody = json_decode((string) $response->getBody(), true);
                $rawProviderError = is_array($responseBody) && is_string($responseBody['error'] ?? null)
                    ? $responseBody['error']
                    : null;
                $providerError = is_string($rawProviderError) && preg_match('/\A[a-zA-Z0-9_.-]{1,64}\z/', $rawProviderError)
                    ? $rawProviderError
                    : null;
            }

            Log::error('Google OAuth token exchange failed.', [
                'http_status' => $response?->getStatusCode(),
                'provider_error' => $providerError,
                'transport_error' => $response === null
                    ? $this->safeTransportError($exception->getHandlerContext()['error'] ?? null)
                    : null,
            ]);

            $message = match ($providerError) {
                'invalid_client', 'unauthorized_client' => 'Google rejected the OAuth client credentials. Verify GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET.',
                'invalid_grant' => 'Google rejected the authorization response. Verify the callback URL in Google Cloud and try signing in again.',
                default => $response === null
                    ? 'The server could not connect securely to Google. Check its internet connection and SSL certificate configuration.'
                    : 'Google sign-in could not be completed. Please try again.',
            };

            return redirect()->route('login')->with('auth_error', $message);
        } catch (GuzzleException $exception) {
            Log::error('Google OAuth provider request failed.', ['exception_class' => $exception::class]);

            return redirect()->route('login')->with('auth_error', 'Google sign-in could not be completed. Please try again.');
        }

        $email = $googleUser->getEmail();
        $googleProfile = $googleUser->getRaw();

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ($googleProfile['email_verified'] ?? false) !== true) {
            return redirect()->route('login')->with('auth_error', 'Google did not provide a verified email address. Please use a verified Google account.');
        }

        $email = Str::lower($email);
        $name = $googleUser->getName();

        if (! is_string($name) || trim($name) === '') {
            $name = $email;
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'email_verified_at' => now(),
                'password' => Str::random(64),
            ]
        );

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard-analytics'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function safeTransportError(mixed $error): ?string
    {
        if (! is_string($error)) {
            return null;
        }

        return Str::limit(preg_replace('/[\r\n\t]+/', ' ', $error) ?? '', 200, '');
    }
}
