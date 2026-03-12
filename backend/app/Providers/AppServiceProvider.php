<?php

namespace App\Providers;

use App\Modules\Identity\Application\Contracts\EdsSignatureVerifier;
use App\Modules\Identity\Infrastructure\Security\KalkanExtensionEdsSignatureVerifier;
use App\Modules\Identity\Infrastructure\Security\KalkanHttpEdsSignatureVerifier;
use App\Modules\Identity\Models\ApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EdsSignatureVerifier::class, function () {
            return match ($this->resolveEdsVerifierDriver()) {
                'kalkan-extension' => new KalkanExtensionEdsSignatureVerifier(),
                'http' => new KalkanHttpEdsSignatureVerifier(),
                default => throw new \RuntimeException('Unsupported EDS verifier driver.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::viaRequest('api-token', function (Request $request) {
            $plainTextToken = $request->bearerToken();

            if (! $plainTextToken) {
                return null;
            }

            $token = ApiToken::query()
                ->where('token', hash('sha256', $plainTextToken))
                ->where(function ($query): void {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->first();

            if (! $token) {
                return null;
            }

            $token->forceFill(['last_used_at' => now()])->save();

            return $token->user;
        });
    }

    private function resolveEdsVerifierDriver(): string
    {
        $configured = (string) config('services.eds_auth.verifier_driver', 'auto');

        if ($configured !== 'auto') {
            return $configured;
        }

        return PHP_OS_FAMILY === 'Linux' ? 'kalkan-extension' : 'http';
    }
}
