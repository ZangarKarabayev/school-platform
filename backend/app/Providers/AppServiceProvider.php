<?php

namespace App\Providers;

use App\Contracts\Student\VoucherServiceContract;
use App\Modules\Identity\Application\Contracts\EdsSignatureVerifier;
use App\Modules\Identity\Infrastructure\Security\KalkanExtensionEdsSignatureVerifier;
use App\Modules\Identity\Infrastructure\Security\KalkanHttpEdsSignatureVerifier;
use App\Modules\Identity\Models\ApiToken;
use App\Services\Student\VoucherService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(VoucherServiceContract::class, VoucherService::class);

        $this->app->bind(EdsSignatureVerifier::class, function () {
            return match ($this->resolveEdsVerifierDriver()) {
                'kalkan-extension' => new KalkanExtensionEdsSignatureVerifier,
                'http' => new KalkanHttpEdsSignatureVerifier,
                default => throw new \RuntimeException('Unsupported EDS verifier driver.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('mobile-login', static function (Request $request): array {
            $phone = preg_replace('/\D+/', '', (string) $request->input('phone', ''));
            if (strlen($phone) === 11 && str_starts_with($phone, '8')) {
                $phone = '7'.substr($phone, 1);
            }

            return [
                Limit::perMinute(30)->by('mobile-ip:'.$request->ip()),
                Limit::perMinute(5)->by('mobile-phone:'.hash('sha256', $phone)),
            ];
        });

        URL::forceRootUrl(config('app.url'));

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

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

            if (! $token || $token->user?->status !== 'active') {
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
