<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LocalizationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Accept-Language');
        
        if ($header) {
            // Split by comma and take the first language (e.g. "en, ar" -> "en")
            $locales = explode(',', $header);
            $locale = trim(strtolower($locales[0]));
            
            // Basic cleanup (e.g. "en-US" -> "en")
            if (str_contains($locale, '-')) {
                $locale = explode('-', $locale)[0];
            }

            // Only set if we have a corresponding folder
            if (is_dir(base_path("lang/{$locale}"))) {
                app()->setLocale($locale);
            } else {
                app()->setLocale(config('app.locale'));
            }
        } else {
            app()->setLocale(config('app.locale'));
        }

        return $next($request);
    }
}
