<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class ZohoAuthController extends Controller
{
    private const UNKNOWN_USER_MESSAGE = 'Votre compte n’existe pas dans cette application. Contactez un administrateur.';

    private const GENERIC_ERROR_MESSAGE = 'Connexion Zoho impossible. Veuillez réessayer ou contacter un administrateur.';

    /**
     * Redirect the user to Zoho's OAuth consent screen.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('zoho')->redirect();
    }

    /**
     * Handle Zoho's OAuth callback.
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $zohoUser = Socialite::driver('zoho')->user();
            $email = $zohoUser->getEmail();

            if ($email === null) {
                throw new \RuntimeException('Zoho did not return an email address.');
            }

            $normalizedEmail = $this->normalizeEmail($email);
            $user = $this->findUserByNormalizedEmail($normalizedEmail);

            if ($user === null) {
                return $this->denyUnknownUser($normalizedEmail);
            }

            Auth::login($user, remember: true);
            $request->session()->regenerate();

            // The picker forwards single-organization users straight to the
            // whiteboard, so every successful login is routed through it.
            return to_route('organizations.select');
        } catch (InvalidStateException $exception) {
            Log::warning('Zoho OAuth state validation failed.', [
                'step' => 'zoho_invalid_state',
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
                'request_host' => $request->getHost(),
                'redirect_uri' => config('services.zoho.redirect'),
                'zoho_region' => config('services.zoho.region'),
            ]);

            return to_route('login')->withErrors([
                'email' => 'Session de connexion expirée ou invalide. Veuillez relancer la connexion Zoho.',
            ]);
        } catch (Throwable $exception) {
            Log::warning('Zoho OAuth failed', [
                'step' => 'zoho_callback',
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
                'request_host' => $request->getHost(),
                'redirect_uri' => config('services.zoho.redirect'),
                'zoho_region' => config('services.zoho.region'),
            ]);

            return to_route('login')->withErrors([
                'email' => self::GENERIC_ERROR_MESSAGE,
            ]);
        }
    }

    private function denyUnknownUser(string $normalizedEmail): RedirectResponse
    {
        Log::info('Zoho login denied for unknown user.', [
            'step' => 'zoho_user_not_found',
            'email' => $normalizedEmail,
        ]);

        return to_route('login')->withErrors([
            'email' => self::UNKNOWN_USER_MESSAGE,
        ]);
    }

    private function findUserByNormalizedEmail(string $normalizedEmail): ?User
    {
        return User::query()
            ->whereRaw(
                "LOWER(TRIM(REPLACE(REPLACE(email, '\r', ''), '\n', ''))) = ?",
                [$normalizedEmail],
            )
            ->first();
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
