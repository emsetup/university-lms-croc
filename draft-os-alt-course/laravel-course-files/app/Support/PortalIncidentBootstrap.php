<?php

namespace App\Support;

use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Регистрирует перехват исключений для детального журнала (без правки bootstrap на стенде).
 */
final class PortalIncidentBootstrap
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered || ! class_exists(ExceptionEvent::class)) {
            return;
        }
        self::$registered = true;

        Event::listen(ExceptionEvent::class, static function (ExceptionEvent $event): void {
            $request = $event->getRequest();
            $request->attributes->set('portal_incident_throwable', $event->getThrowable());
        });
    }
}
