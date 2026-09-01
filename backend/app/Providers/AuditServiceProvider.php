<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\Terminal;
use App\Models\VerifyEvent;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditLogger::class);
    }

    public function boot(AuditLogger $auditLogger): void
    {
        Event::listen(Login::class, fn(Login $event) => $auditLogger->logAuthEvent('login', $event->user, $event->guard));
        Event::listen(Logout::class, fn(Logout $event) => $event->user === null
            ? null
            : $auditLogger->logAuthEvent('logout', $event->user, $event->guard));

        foreach (['created', 'updated', 'deleted'] as $event) {
            Event::listen("eloquent.{$event}: *", function (string $eventName, array $models) use ($auditLogger, $event): void {
                if (! isset($models[0])) {
                    return;
                }

                $model = $models[0];

                if ($model instanceof Terminal || $model instanceof VerifyEvent) {
                    return;
                }

                if ($model instanceof Order && $event === 'updated') {
                    $changed = array_keys($model->getChanges());

                    if ($changed === [] || array_diff($changed, ['transaction_status', 'transaction_error']) === []) {
                        return;
                    }
                }

                $auditLogger->logModelEvent($event, $model);
            });
        }
    }
}
