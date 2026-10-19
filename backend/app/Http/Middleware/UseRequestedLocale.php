<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers in the language the SPA asks for (Accept-Language), so validation and
 * error messages match the interface language.
 */
class UseRequestedLocale
{
    public function __construct(private readonly Application $app) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->app->setLocale($request->getPreferredLanguage(Locale::values()) ?? Locale::En->value);

        return $next($request);
    }
}
